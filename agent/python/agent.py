#!/usr/bin/env python3
"""
IT Inventory Management – Python Agent
Collects system information, communicates with the server API,
polls for commands, executes them and reports results.
"""

import configparser
import json
import logging
import os
import platform
import re
import signal
import subprocess
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

try:
    import psutil
except ImportError:
    psutil = None  # Degrade gracefully

try:
    import requests
except ImportError:
    print("ERROR: 'requests' library not found. Run: pip install requests psutil", file=sys.stderr)
    sys.exit(1)

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

DEFAULT_CONFIG = {
    "server": {
        "url": "http://localhost:8000",
        "verify_ssl": "true",
    },
    "agent": {
        "token": "",
        "interval": "60",
        "log_file": "agent.log",
        "log_level": "INFO",
    },
    "computer": {
        "hostname": "",
    },
}

CONFIG_FILE = Path(__file__).parent / "config.ini"
_running = True  # graceful shutdown flag


# ---------------------------------------------------------------------------
# Logging setup
# ---------------------------------------------------------------------------

def setup_logging(log_file: str, log_level: str) -> logging.Logger:
    level = getattr(logging, log_level.upper(), logging.INFO)
    fmt   = "%(asctime)s [%(levelname)s] %(message)s"

    handlers = [logging.StreamHandler(sys.stdout)]
    if log_file:
        try:
            handlers.append(logging.FileHandler(log_file))
        except OSError as e:
            print(f"WARNING: Cannot open log file {log_file}: {e}", file=sys.stderr)

    logging.basicConfig(level=level, format=fmt, handlers=handlers)
    return logging.getLogger("agent")


# ---------------------------------------------------------------------------
# Config helpers
# ---------------------------------------------------------------------------

def load_config() -> configparser.ConfigParser:
    cfg = configparser.ConfigParser()
    # Load defaults
    for section, values in DEFAULT_CONFIG.items():
        cfg[section] = values

    if CONFIG_FILE.exists():
        cfg.read(CONFIG_FILE)
    else:
        # Write example config
        example = CONFIG_FILE.parent / "config.ini.example"
        if not example.exists():
            with open(example, "w") as fh:
                cfg.write(fh)
        print(f"No config.ini found. Copy config.ini.example to config.ini and configure it.")
    return cfg


def save_token(cfg: configparser.ConfigParser, token: str) -> None:
    cfg["agent"]["token"] = token
    with open(CONFIG_FILE, "w") as fh:
        cfg.write(fh)


# ---------------------------------------------------------------------------
# System information collection
# ---------------------------------------------------------------------------

def get_hostname(cfg: configparser.ConfigParser) -> str:
    override = cfg.get("computer", "hostname", fallback="")
    if override:
        return override
    return platform.node()


def get_ip_address() -> str:
    """Best-effort primary IP (not loopback)."""
    if psutil:
        for iface, addrs in psutil.net_if_addrs().items():
            for addr in addrs:
                if addr.family.name in ("AF_INET",) and not addr.address.startswith("127."):
                    return addr.address
    try:
        import socket
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            s.connect(("8.8.8.8", 80))
            return s.getsockname()[0]
    except Exception:
        return ""


def get_mac_address() -> str:
    """MAC of the first non-loopback interface."""
    if psutil:
        for iface, addrs in psutil.net_if_addrs().items():
            if iface.lower().startswith("lo"):
                continue
            for addr in addrs:
                if addr.family.name in ("AF_LINK", "AF_PACKET"):
                    mac = addr.address
                    if mac and mac != "00:00:00:00:00:00":
                        return mac
    try:
        import uuid
        mac_int = uuid.getnode()
        mac_hex = f"{mac_int:012x}"
        return ":".join(mac_hex[i:i+2] for i in range(0, 12, 2))
    except Exception:
        return ""


def get_os_info() -> dict:
    system = platform.system()
    return {
        "os_name":    system,
        "os_version": platform.version(),
        "os_arch":    platform.machine(),
    }


def get_cpu_info() -> str:
    cpu = platform.processor()
    if not cpu and psutil:
        cpu = f"{psutil.cpu_count()} cores"
    return cpu or "Unknown"


def get_ram_gb() -> float:
    if psutil:
        return round(psutil.virtual_memory().total / (1024 ** 3), 2)
    return 0.0


def get_storage_gb() -> float:
    if psutil:
        try:
            usage = psutil.disk_usage("/")
            return round(usage.total / (1024 ** 3), 2)
        except Exception:
            pass
    return 0.0


def get_software_list() -> list:
    """
    Collect installed software.
    Returns a list of dicts: {name, version, publisher, install_date}
    """
    system = platform.system()

    if system == "Windows":
        return _get_software_windows()
    elif system == "Linux":
        return _get_software_linux()
    elif system == "Darwin":
        return _get_software_macos()
    return []


def _get_software_windows() -> list:
    software = []
    try:
        result = subprocess.run(
            [
                "powershell", "-NoProfile", "-Command",
                "Get-ItemProperty HKLM:\\Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\* "
                "| Select-Object DisplayName,DisplayVersion,Publisher,InstallDate "
                "| ConvertTo-Json -Compress",
            ],
            capture_output=True, text=True, timeout=60,
        )
        items = json.loads(result.stdout or "[]")
        if isinstance(items, dict):
            items = [items]
        for item in items:
            name = (item.get("DisplayName") or "").strip()
            if not name:
                continue
            software.append({
                "name":         name,
                "version":      (item.get("DisplayVersion") or "").strip(),
                "publisher":    (item.get("Publisher") or "").strip(),
                "install_date": _parse_win_date(item.get("InstallDate") or ""),
            })
    except Exception:
        pass
    return software


def _parse_win_date(d: str) -> str:
    d = str(d).strip()
    if re.match(r"^\d{8}$", d):
        return f"{d[:4]}-{d[4:6]}-{d[6:]}"
    return ""


def _get_software_linux() -> list:
    software = []
    # Try dpkg
    try:
        result = subprocess.run(
            ["dpkg-query", "-W", "-f=${Package}\t${Version}\t${Maintainer}\n"],
            capture_output=True, text=True, timeout=30,
        )
        for line in result.stdout.splitlines():
            parts = line.split("\t")
            if len(parts) >= 1 and parts[0]:
                software.append({
                    "name":         parts[0],
                    "version":      parts[1] if len(parts) > 1 else "",
                    "publisher":    parts[2] if len(parts) > 2 else "",
                    "install_date": None,
                })
        if software:
            return software
    except FileNotFoundError:
        pass

    # Try rpm
    try:
        result = subprocess.run(
            ["rpm", "-qa", "--queryformat", "%{NAME}\t%{VERSION}\t%{VENDOR}\n"],
            capture_output=True, text=True, timeout=30,
        )
        for line in result.stdout.splitlines():
            parts = line.split("\t")
            if parts[0]:
                software.append({
                    "name":         parts[0],
                    "version":      parts[1] if len(parts) > 1 else "",
                    "publisher":    parts[2] if len(parts) > 2 else "",
                    "install_date": None,
                })
    except FileNotFoundError:
        pass

    return software


def _get_software_macos() -> list:
    software = []
    try:
        result = subprocess.run(
            ["system_profiler", "SPApplicationsDataType", "-json"],
            capture_output=True, text=True, timeout=60,
        )
        data = json.loads(result.stdout)
        apps = data.get("SPApplicationsDataType", [])
        for app in apps:
            name = app.get("_name", "")
            if name:
                software.append({
                    "name":         name,
                    "version":      app.get("version", ""),
                    "publisher":    app.get("obtained_from", ""),
                    "install_date": None,
                })
    except Exception:
        pass
    return software


def collect_system_info(cfg: configparser.ConfigParser) -> dict:
    os_info = get_os_info()
    return {
        "hostname":    get_hostname(cfg),
        "ip_address":  get_ip_address(),
        "mac_address": get_mac_address(),
        "os_name":     os_info["os_name"],
        "os_version":  os_info["os_version"],
        "os_arch":     os_info["os_arch"],
        "cpu_info":    get_cpu_info(),
        "ram_gb":      get_ram_gb(),
        "storage_gb":  get_storage_gb(),
    }


# ---------------------------------------------------------------------------
# API Communication
# ---------------------------------------------------------------------------

class AgentClient:
    def __init__(self, base_url: str, token: str, verify_ssl: bool, logger: logging.Logger):
        self.base_url   = base_url.rstrip("/")
        self.token      = token
        self.verify_ssl = verify_ssl
        self.logger     = logger
        self.session    = requests.Session()
        self.session.headers.update({"Content-Type": "application/json"})

    def _auth_headers(self) -> dict:
        return {"X-Agent-Token": self.token}

    def register(self, system_info: dict) -> dict:
        resp = self.session.post(
            f"{self.base_url}/api/agent/register",
            json=system_info,
            verify=self.verify_ssl,
            timeout=30,
        )
        resp.raise_for_status()
        return resp.json()

    def heartbeat(self, system_info: dict, software: list) -> dict:
        payload = {**system_info, "software": software}
        resp = self.session.post(
            f"{self.base_url}/api/agent/heartbeat",
            json=payload,
            headers=self._auth_headers(),
            verify=self.verify_ssl,
            timeout=30,
        )
        resp.raise_for_status()
        return resp.json()

    def get_commands(self) -> list:
        resp = self.session.get(
            f"{self.base_url}/api/agent/commands",
            headers=self._auth_headers(),
            verify=self.verify_ssl,
            timeout=30,
        )
        resp.raise_for_status()
        return resp.json().get("commands", [])

    def post_result(self, command_id: int, exit_code: int, output: str, error_output: str) -> None:
        self.session.post(
            f"{self.base_url}/api/agent/command-result",
            json={
                "command_id":   command_id,
                "exit_code":    exit_code,
                "output":       output,
                "error_output": error_output,
                "executed_at":  datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S"),
            },
            headers=self._auth_headers(),
            verify=self.verify_ssl,
            timeout=30,
        )


# ---------------------------------------------------------------------------
# Command execution
# ---------------------------------------------------------------------------

def detect_package_manager() -> str:
    for pm in ("apt-get", "apt", "yum", "dnf", "zypper", "pacman"):
        try:
            subprocess.run([pm, "--version"], capture_output=True, timeout=5)
            return pm
        except (FileNotFoundError, subprocess.TimeoutExpired):
            pass
    if platform.system() == "Windows":
        return "winget"
    if platform.system() == "Darwin":
        return "brew"
    return ""


MAX_OUTPUT_LENGTH = 65535  # Max characters captured from command stdout/stderr


def run_shell(cmd: str, timeout: int = 300) -> tuple[int, str, str]:
    """Run a shell command, return (exit_code, stdout, stderr)."""
    try:
        result = subprocess.run(
            cmd,
            shell=True,
            capture_output=True,
            text=True,
            timeout=timeout,
        )
        return result.returncode, result.stdout[:MAX_OUTPUT_LENGTH], result.stderr[:MAX_OUTPUT_LENGTH]
    except subprocess.TimeoutExpired:
        return 1, "", f"Command timed out after {timeout}s"
    except Exception as e:
        return 1, "", str(e)


def execute_command(cmd: dict, logger: logging.Logger) -> tuple[int, str, str]:
    """Dispatch a command dict to the appropriate handler."""
    ctype   = cmd.get("command_type", "")
    payload = cmd.get("payload") or {}

    logger.info(f"Executing command type={ctype} id={cmd.get('id')}")

    system = platform.system()
    pm     = detect_package_manager()

    if ctype == "patch":
        if system == "Windows":
            return run_shell("winget upgrade --all --silent", timeout=600)
        elif pm in ("apt", "apt-get"):
            return run_shell("apt-get update && apt-get upgrade -y", timeout=600)
        elif pm in ("yum", "dnf"):
            return run_shell(f"{pm} update -y", timeout=600)
        else:
            return 1, "", f"Unsupported package manager: {pm}"

    elif ctype == "install":
        pkg = payload.get("package", "")
        if not pkg:
            return 1, "", "No package specified"
        if system == "Windows":
            return run_shell(f"winget install --silent {pkg}", timeout=300)
        elif pm in ("apt", "apt-get"):
            return run_shell(f"apt-get install -y {pkg}", timeout=300)
        elif pm in ("yum", "dnf"):
            return run_shell(f"{pm} install -y {pkg}", timeout=300)
        else:
            return 1, "", f"Unsupported package manager: {pm}"

    elif ctype == "uninstall":
        pkg = payload.get("package", "")
        if not pkg:
            return 1, "", "No package specified"
        if system == "Windows":
            return run_shell(f"winget uninstall --silent {pkg}", timeout=300)
        elif pm in ("apt", "apt-get"):
            return run_shell(f"apt-get remove -y {pkg}", timeout=300)
        elif pm in ("yum", "dnf"):
            return run_shell(f"{pm} remove -y {pkg}", timeout=300)
        else:
            return 1, "", f"Unsupported package manager: {pm}"

    elif ctype == "shell":
        raw_cmd = payload.get("command", "")
        if not raw_cmd:
            return 1, "", "No command specified"
        logger.warning(f"Executing shell command: {raw_cmd}")
        return run_shell(raw_cmd, timeout=payload.get("timeout", 300))

    elif ctype == "restart":
        delay = int(payload.get("delay", 0))
        if system == "Windows":
            return run_shell(f"shutdown /r /t {delay}")
        else:
            if delay > 0:
                return run_shell(f"sleep {delay} && reboot")
            return run_shell("reboot")

    elif ctype == "shutdown":
        delay = int(payload.get("delay", 0))
        if system == "Windows":
            return run_shell(f"shutdown /s /t {delay}")
        else:
            if delay > 0:
                return run_shell(f"sleep {delay} && shutdown -h now")
            return run_shell("shutdown -h now")

    else:
        return 1, "", f"Unknown command type: {ctype}"


# ---------------------------------------------------------------------------
# Registration
# ---------------------------------------------------------------------------

def register(client: AgentClient, cfg: configparser.ConfigParser,
             system_info: dict, logger: logging.Logger) -> bool:
    """Register with the server and store the returned token."""
    logger.info("Registering with server...")
    try:
        result = client.register(system_info)
        token  = result.get("token", "")
        if not token:
            logger.error(f"Registration failed: {result}")
            return False

        client.token = token
        save_token(cfg, token)
        logger.info(f"Registered successfully. Computer ID: {result.get('computer_id')}")
        return True

    except requests.HTTPError as e:
        logger.error(f"Registration HTTP error: {e}")
    except requests.ConnectionError as e:
        logger.error(f"Registration connection error: {e}")
    except Exception as e:
        logger.exception(f"Registration unexpected error: {e}")
    return False


# ---------------------------------------------------------------------------
# Main agent loop
# ---------------------------------------------------------------------------

def agent_loop(cfg: configparser.ConfigParser, logger: logging.Logger) -> None:
    global _running

    base_url   = cfg.get("server", "url")
    verify_ssl = cfg.getboolean("server", "verify_ssl", fallback=True)
    interval   = cfg.getint("agent", "interval", fallback=60)
    token      = cfg.get("agent", "token", fallback="")

    client = AgentClient(base_url, token, verify_ssl, logger)

    # Register if no token
    if not token:
        system_info = collect_system_info(cfg)
        if not register(client, cfg, system_info, logger):
            logger.error("Cannot start without a valid token. Exiting.")
            return

    logger.info(f"Agent started. Server: {base_url}, interval: {interval}s")

    while _running:
        try:
            system_info = collect_system_info(cfg)
            software    = get_software_list()

            # Heartbeat
            logger.debug("Sending heartbeat...")
            client.heartbeat(system_info, software)
            logger.info("Heartbeat sent. RAM=%.1fGB Software=%d", system_info['ram_gb'], len(software))

            # Poll commands
            commands = client.get_commands()
            if commands:
                logger.info(f"Received {len(commands)} command(s)")

            for cmd in commands:
                cmd_id = cmd.get("id")
                try:
                    exit_code, stdout, stderr = execute_command(cmd, logger)
                    logger.info(f"Command {cmd_id} finished with exit_code={exit_code}")
                    client.post_result(cmd_id, exit_code, stdout, stderr)
                except Exception as e:
                    logger.exception(f"Error executing command {cmd_id}: {e}")
                    client.post_result(cmd_id, 1, "", str(e))

        except requests.HTTPError as e:
            if e.response is not None and e.response.status_code == 401:
                logger.error("Token rejected (401). Re-registering...")
                cfg["agent"]["token"] = ""
                system_info = collect_system_info(cfg)
                if not register(client, cfg, system_info, logger):
                    logger.error("Re-registration failed. Sleeping.")
            else:
                logger.error(f"HTTP error during loop: {e}")

        except requests.ConnectionError:
            logger.warning("Cannot reach server. Will retry next cycle.")

        except Exception as e:
            logger.exception(f"Unexpected error in agent loop: {e}")

        # Sleep in 1-second increments so SIGINT/SIGTERM is responsive
        for _ in range(interval):
            if not _running:
                break
            time.sleep(1)

    logger.info("Agent stopped.")


# ---------------------------------------------------------------------------
# Signal handlers
# ---------------------------------------------------------------------------

def _handle_signal(signum, frame):
    global _running
    logging.getLogger("agent").info(f"Signal {signum} received. Shutting down...")
    _running = False


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main():
    cfg = load_config()

    log_file  = cfg.get("agent", "log_file",  fallback="agent.log")
    log_level = cfg.get("agent", "log_level", fallback="INFO")
    logger    = setup_logging(log_file, log_level)

    signal.signal(signal.SIGINT,  _handle_signal)
    signal.signal(signal.SIGTERM, _handle_signal)

    agent_loop(cfg, logger)


if __name__ == "__main__":
    main()
