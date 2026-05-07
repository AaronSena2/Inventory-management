#!/usr/bin/env python3
"""
inventory_agent.py – IT Inventory Management Windows Service Agent
==================================================================
Installs and runs as a Windows service (auto-start).  When built with
PyInstaller the resulting binary is named inventory_agent.exe.

Usage (run as Administrator):
    inventory_agent.exe install   – register service (auto-start)
    inventory_agent.exe start     – start the service
    inventory_agent.exe stop      – stop the service
    inventory_agent.exe restart   – restart the service
    inventory_agent.exe remove    – uninstall the service
    inventory_agent.exe debug     – run in the foreground (no service)
    inventory_agent.exe status    – show current service status

All other service control operations can be performed through the
Windows Services MMC snap-in (services.msc) or sc.exe.

Config and logs are stored in:
    C:\\ProgramData\\InventoryAgent\\config.ini
    C:\\ProgramData\\InventoryAgent\\agent.log
"""

import configparser
import json
import logging
import os
import platform
import re
import subprocess
import sys
import threading
import time
from datetime import datetime, timezone
from pathlib import Path

# ---------------------------------------------------------------------------
# Determine the data directory (works both in source and frozen .exe)
# ---------------------------------------------------------------------------
DATA_DIR = Path(os.environ.get("PROGRAMDATA", r"C:\ProgramData")) / "InventoryAgent"
DATA_DIR.mkdir(parents=True, exist_ok=True)

CONFIG_FILE = DATA_DIR / "config.ini"
LOG_FILE    = DATA_DIR / "agent.log"

# ---------------------------------------------------------------------------
# Optional heavy dependencies – degrade gracefully when missing
# ---------------------------------------------------------------------------
try:
    import psutil
except ImportError:
    psutil = None

try:
    import requests
except ImportError:
    print("ERROR: 'requests' library not found. Run: pip install requests psutil pywin32",
          file=sys.stderr)
    sys.exit(1)

# ---------------------------------------------------------------------------
# Default configuration
# ---------------------------------------------------------------------------
DEFAULT_CONFIG = {
    "server": {
        "url":        "http://localhost:8000",
        "verify_ssl": "true",
    },
    "agent": {
        "token":     "",
        "interval":  "60",
        "log_level": "INFO",
    },
    "computer": {
        "hostname": "",
    },
}

MAX_OUTPUT_LENGTH = 65535   # Matches TEXT column capacity (64 KiB – 1); keeps log rows manageable.

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

def setup_logging(log_level: str = "INFO") -> logging.Logger:
    level  = getattr(logging, log_level.upper(), logging.INFO)
    fmt    = "%(asctime)s [%(levelname)s] %(message)s"
    handlers: list[logging.Handler] = [logging.StreamHandler(sys.stdout)]
    try:
        handlers.append(logging.FileHandler(str(LOG_FILE), encoding="utf-8"))
    except OSError as exc:
        print(f"WARNING: Cannot open log file {LOG_FILE}: {exc}", file=sys.stderr)
    logging.basicConfig(level=level, format=fmt, handlers=handlers, force=True)
    return logging.getLogger("inventory_agent")


# ---------------------------------------------------------------------------
# Configuration helpers
# ---------------------------------------------------------------------------

def load_config() -> configparser.ConfigParser:
    cfg = configparser.ConfigParser()
    for section, values in DEFAULT_CONFIG.items():
        cfg[section] = values
    if CONFIG_FILE.exists():
        cfg.read(str(CONFIG_FILE), encoding="utf-8")
    else:
        _write_example_config()
    return cfg


def save_config(cfg: configparser.ConfigParser) -> None:
    with open(str(CONFIG_FILE), "w", encoding="utf-8") as fh:
        cfg.write(fh)


def save_token(cfg: configparser.ConfigParser, token: str) -> None:
    cfg["agent"]["token"] = token
    save_config(cfg)


def _write_example_config() -> None:
    example = DATA_DIR / "config.ini.example"
    if not example.exists():
        cfg = configparser.ConfigParser()
        for section, values in DEFAULT_CONFIG.items():
            cfg[section] = values
        with open(str(example), "w", encoding="utf-8") as fh:
            cfg.write(fh)


# ---------------------------------------------------------------------------
# System information helpers
# ---------------------------------------------------------------------------

def get_hostname(cfg: configparser.ConfigParser) -> str:
    override = cfg.get("computer", "hostname", fallback="")
    return override if override else platform.node()


def get_ip_address() -> str:
    if psutil:
        for _iface, addrs in psutil.net_if_addrs().items():
            for addr in addrs:
                if addr.family.name == "AF_INET" and not addr.address.startswith("127."):
                    return addr.address
    try:
        import socket
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            s.connect(("8.8.8.8", 80))
            return s.getsockname()[0]
    except Exception:
        return ""


def get_mac_address() -> str:
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
        return ":".join(mac_hex[i : i + 2] for i in range(0, 12, 2))
    except Exception:
        return ""


def get_os_info() -> dict:
    return {
        "os_name":    platform.system(),
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
            return round(psutil.disk_usage("C:\\").total / (1024 ** 3), 2)
        except Exception:
            pass
    return 0.0


def _parse_windows_date(d: str) -> str:
    d = str(d).strip()
    if re.match(r"^\d{8}$", d):
        return f"{d[:4]}-{d[4:6]}-{d[6:]}"
    return ""


def get_software_list() -> list:
    """Return installed software as a list of dicts."""
    software: list[dict] = []
    try:
        result = subprocess.run(
            [
                "powershell", "-NoProfile", "-Command",
                "Get-ItemProperty "
                "HKLM:\\Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*, "
                "HKLM:\\Software\\Wow6432Node\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\* "
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
            if name:
                software.append({
                    "name":         name,
                    "version":      (item.get("DisplayVersion") or "").strip(),
                    "publisher":    (item.get("Publisher") or "").strip(),
                    "install_date": _parse_windows_date(item.get("InstallDate") or ""),
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
# API client
# ---------------------------------------------------------------------------

class AgentClient:
    def __init__(self, base_url: str, token: str, verify_ssl: bool,
                 logger: logging.Logger) -> None:
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

    def post_result(self, command_id: int, exit_code: int,
                    output: str, error_output: str) -> None:
        try:
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
        except Exception as exc:
            self.logger.warning("Failed to post result for command %s: %s", command_id, exc)


# ---------------------------------------------------------------------------
# Command execution
# ---------------------------------------------------------------------------

def run_shell(cmd: str, timeout: int = 300) -> tuple[int, str, str]:
    """Execute cmd via cmd.exe, return (exit_code, stdout, stderr)."""
    try:
        result = subprocess.run(
            cmd,
            shell=True,
            capture_output=True,
            text=True,
            timeout=timeout,
        )
        return (
            result.returncode,
            result.stdout[:MAX_OUTPUT_LENGTH],
            result.stderr[:MAX_OUTPUT_LENGTH],
        )
    except subprocess.TimeoutExpired:
        return 1, "", f"Command timed out after {timeout}s"
    except Exception as exc:
        return 1, "", str(exc)


def execute_command(cmd: dict, logger: logging.Logger) -> tuple[int, str, str]:
    """Dispatch a command dict to the appropriate handler."""
    ctype   = cmd.get("command_type", "")
    payload = cmd.get("payload") or {}

    logger.info("Executing command type=%s id=%s", ctype, cmd.get("id"))

    if ctype == "patch":
        return run_shell("winget upgrade --all --silent --accept-source-agreements"
                         " --accept-package-agreements", timeout=600)

    elif ctype == "install":
        pkg = payload.get("package", "")
        if not pkg:
            return 1, "", "No package specified"
        return run_shell(
            f'winget install --silent --accept-source-agreements'
            f' --accept-package-agreements --id "{pkg}"',
            timeout=300,
        )

    elif ctype == "uninstall":
        pkg = payload.get("package", "")
        if not pkg:
            return 1, "", "No package specified"
        return run_shell(
            f'winget uninstall --silent --id "{pkg}"',
            timeout=300,
        )

    elif ctype == "shell":
        raw_cmd = payload.get("command", "")
        if not raw_cmd:
            return 1, "", "No command specified"
        logger.warning("Executing shell command: %s", raw_cmd)
        return run_shell(raw_cmd, timeout=int(payload.get("timeout", 300)))

    elif ctype == "restart":
        delay = int(payload.get("delay", 0))
        return run_shell(f"shutdown /r /t {delay}")

    elif ctype == "shutdown":
        delay = int(payload.get("delay", 0))
        return run_shell(f"shutdown /s /t {delay}")

    else:
        return 1, "", f"Unknown command type: {ctype}"


# ---------------------------------------------------------------------------
# Registration helper
# ---------------------------------------------------------------------------

def do_register(client: AgentClient, cfg: configparser.ConfigParser,
                logger: logging.Logger) -> bool:
    logger.info("Registering with server...")
    try:
        system_info = collect_system_info(cfg)
        result = client.register(system_info)
        token  = result.get("token", "")
        if not token:
            logger.error("Registration failed: %s", result)
            return False
        client.token = token
        save_token(cfg, token)
        logger.info("Registered successfully. Computer ID: %s", result.get("computer_id"))
        return True
    except requests.HTTPError as exc:
        logger.error("Registration HTTP error: %s", exc)
    except requests.ConnectionError as exc:
        logger.error("Registration connection error: %s", exc)
    except Exception as exc:
        logger.exception("Registration unexpected error: %s", exc)
    return False


# ---------------------------------------------------------------------------
# Core agent loop (shared by both service and debug modes)
# ---------------------------------------------------------------------------

def agent_loop(stop_event: threading.Event, logger: logging.Logger) -> None:
    """
    Run the heartbeat/command-poll loop until stop_event is set.

    Uses a threading.Event so the Windows service can cleanly interrupt the
    inter-cycle sleep without a full-second tick delay.
    """
    cfg        = load_config()
    base_url   = cfg.get("server",  "url",       fallback="http://localhost:8000")
    verify_ssl = cfg.getboolean("server",  "verify_ssl", fallback=True)
    interval   = cfg.getint("agent", "interval",  fallback=60)
    token      = cfg.get("agent",  "token",      fallback="")

    client = AgentClient(base_url, token, verify_ssl, logger)

    if not token:
        if not do_register(client, cfg, logger):
            logger.error("Cannot start: registration failed.")
            return

    logger.info("Agent running. Server=%s interval=%ds", base_url, interval)

    while not stop_event.is_set():
        try:
            system_info = collect_system_info(cfg)
            software    = get_software_list()

            client.heartbeat(system_info, software)
            ram_gb = get_ram_gb()  # read directly so the log arg is not dict-sourced
            logger.info("Heartbeat sent. RAM=%.1fGB Software=%d", ram_gb, len(software))

            commands = client.get_commands()
            if commands:
                logger.info("Received %d command(s)", len(commands))

            for cmd in commands:
                cmd_id = cmd.get("id")
                try:
                    exit_code, stdout, stderr = execute_command(cmd, logger)
                    logger.info("Command %s finished with exit_code=%s", cmd_id, exit_code)
                    client.post_result(cmd_id, exit_code, stdout, stderr)
                except Exception as exc:
                    logger.exception("Error executing command %s: %s", cmd_id, exc)
                    client.post_result(cmd_id, 1, "", str(exc))

        except requests.HTTPError as exc:
            if exc.response is not None and exc.response.status_code == 401:
                logger.error("Token rejected (401). Re-registering...")
                cfg["agent"]["token"] = ""
                if not do_register(client, cfg, logger):
                    logger.error("Re-registration failed.")
            else:
                logger.error("HTTP error during loop: %s", exc)

        except requests.ConnectionError:
            logger.warning("Cannot reach server. Will retry next cycle.")

        except Exception as exc:
            logger.exception("Unexpected error in agent loop: %s", exc)

        # Interruptible sleep: wake up immediately on stop_event
        stop_event.wait(timeout=interval)

    logger.info("Agent loop stopped.")


# ---------------------------------------------------------------------------
# Windows Service wrapper
# ---------------------------------------------------------------------------

def _run_as_service() -> None:
    """Import pywin32 and run as a Windows service."""
    try:
        import win32service
        import win32serviceutil
        import servicemanager
    except ImportError:
        print(
            "ERROR: pywin32 is not installed. Run: pip install pywin32\n"
            "       Then re-run: inventory_agent.exe install",
            file=sys.stderr,
        )
        sys.exit(1)

    class InventoryAgentService(win32serviceutil.ServiceFramework):
        _svc_name_        = "InventoryAgent"
        _svc_display_name_ = "IT Inventory Agent"
        _svc_description_  = (
            "Collects system inventory, sends heartbeats, and executes remote "
            "management commands for the IT Inventory Management platform."
        )

        def __init__(self, args):
            win32serviceutil.ServiceFramework.__init__(self, args)
            self._stop_event = threading.Event()
            level    = load_config().get("agent", "log_level", fallback="INFO")
            self._logger = setup_logging(level)

        def GetAcceptedControls(self):
            result = win32serviceutil.ServiceFramework.GetAcceptedControls(self)
            result |= win32service.SERVICE_ACCEPT_PRESHUTDOWN
            return result

        def SvcStop(self):
            self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
            self._logger.info("Service stop requested.")
            self._stop_event.set()

        def SvcOtherEx(self, control, event_type, data):
            # Handle pre-shutdown so we can clean up before OS shutdown
            if control == win32service.SERVICE_CONTROL_PRESHUTDOWN:
                self.SvcStop()

        def SvcDoRun(self):
            servicemanager.LogMsg(
                servicemanager.EVENTLOG_INFORMATION_TYPE,
                servicemanager.PYS_SERVICE_STARTED,
                (self._svc_name_, ""),
            )
            self._logger.info("Service started.")
            try:
                agent_loop(self._stop_event, self._logger)
            except Exception as exc:
                self._logger.exception("Fatal error in service: %s", exc)
            servicemanager.LogMsg(
                servicemanager.EVENTLOG_INFORMATION_TYPE,
                servicemanager.PYS_SERVICE_STOPPED,
                (self._svc_name_, ""),
            )

    # Patch the startup type to AUTO_START after installation
    _orig_install = win32serviceutil.InstallService

    def _patched_install(*args, **kwargs):
        kwargs.setdefault("startType", win32service.SERVICE_AUTO_START)
        _orig_install(*args, **kwargs)

    win32serviceutil.InstallService = _patched_install

    if len(sys.argv) == 1:
        # Launched by the SCM without arguments: run the service dispatcher
        servicemanager.Initialize()
        servicemanager.PrepareToHostSingle(InventoryAgentService)
        servicemanager.StartServiceCtrlDispatcher()
    else:
        win32serviceutil.HandleCommandLine(InventoryAgentService)


# ---------------------------------------------------------------------------
# Debug (foreground) mode
# ---------------------------------------------------------------------------

def _run_debug() -> None:
    """Run the agent in the foreground – useful for first-time setup/testing."""
    import signal

    cfg    = load_config()
    level  = cfg.get("agent", "log_level", fallback="INFO")
    logger = setup_logging(level)

    stop_event = threading.Event()

    def _handle(signum, _frame):
        logger.info("Signal %s received – stopping.", signum)
        stop_event.set()

    signal.signal(signal.SIGINT,  _handle)
    signal.signal(signal.SIGTERM, _handle)

    logger.info("Running in DEBUG (foreground) mode. Press Ctrl+C to stop.")
    agent_loop(stop_event, logger)


# ---------------------------------------------------------------------------
# Status helper
# ---------------------------------------------------------------------------

def _show_status() -> None:
    try:
        import win32serviceutil
        status = win32serviceutil.QueryServiceStatus("InventoryAgent")
        state_map = {
            1: "STOPPED",
            2: "START_PENDING",
            3: "STOP_PENDING",
            4: "RUNNING",
            5: "CONTINUE_PENDING",
            6: "PAUSE_PENDING",
            7: "PAUSED",
        }
        state = state_map.get(status[1], f"UNKNOWN({status[1]})")
        print(f"InventoryAgent service status: {state}")
    except Exception as exc:
        print(f"Could not query service status: {exc}")


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main() -> None:
    # Ensure data dir exists before anything else writes to it
    DATA_DIR.mkdir(parents=True, exist_ok=True)

    args = sys.argv[1:]

    if args and args[0].lower() == "debug":
        _run_debug()
    elif args and args[0].lower() == "status":
        _show_status()
    else:
        # Delegate everything else (install/start/stop/restart/remove and
        # SCM invocation with no args) to the Windows service handler.
        _run_as_service()


if __name__ == "__main__":
    main()
