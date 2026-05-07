# Windows Agent – `inventory_agent.exe`

This directory contains the Windows-specific agent that installs as a
**Windows service** and starts automatically with the computer.

---

## Prerequisites

| Requirement | Version |
|------------|---------|
| Python | 3.10 or newer (64-bit) |
| pip packages | see `requirements.txt` |
| OS | Windows 10 / Windows 11 / Windows Server 2019+ |

> **Admin rights** are required for all installation steps.

---

## 1 – Install Python dependencies

Open an **Administrator** command prompt in this directory and run:

```bat
pip install -r requirements.txt
```

---

## 2 – Configure the agent

Copy the example config and edit it:

```bat
copy config.ini.example config.ini
notepad config.ini
```

Set at minimum:

```ini
[server]
url = http://your-server-address:8000   ; ← change this
```

Leave `token` blank on first run – the agent will register and write it automatically.

---

## 3 – Build `inventory_agent.exe`

```bat
pyinstaller inventory_agent.spec
```

The compiled binary will be placed at `dist\inventory_agent.exe`.

> **Tip:** Re-run this command whenever you update the Python source.

---

## 4 – Install the Windows service

Run **as Administrator**:

```bat
install.bat install
```

This will:
1. Copy `inventory_agent.exe` to `%ProgramFiles%\InventoryAgent\`
2. Create the data directory `%ProgramData%\InventoryAgent\`
3. Copy `config.ini.example` → `%ProgramData%\InventoryAgent\config.ini` (if not already present)
4. Register the **InventoryAgent** Windows service with **Automatic** start type

Edit the config before starting:

```bat
notepad "%ProgramData%\InventoryAgent\config.ini"
```

---

## 5 – Start the service

```bat
install.bat start
```

The service is now running and will restart automatically after every reboot.

---

## Service management commands

| Command | Effect |
|---------|--------|
| `install.bat install` | Install & register service (auto-start) |
| `install.bat start` | Start the service |
| `install.bat stop` | Stop the service |
| `install.bat restart` | Restart the service |
| `install.bat status` | Show current service status |
| `install.bat remove` | Stop and uninstall the service |
| `install.bat debug` | Run the agent in the **foreground** (for testing) |

You can also manage the service through **Windows Services** (`services.msc`) or PowerShell:

```powershell
# Start
Start-Service InventoryAgent

# Stop
Stop-Service InventoryAgent

# View status
Get-Service InventoryAgent
```

---

## Files and directories

| Path | Description |
|------|-------------|
| `%ProgramFiles%\InventoryAgent\inventory_agent.exe` | Installed executable |
| `%ProgramData%\InventoryAgent\config.ini` | Runtime configuration |
| `%ProgramData%\InventoryAgent\agent.log` | Agent log file |

---

## Debug / first-run test

Before installing as a service you can test the agent interactively:

```bat
dist\inventory_agent.exe debug
```

This runs the full heartbeat loop in the current console window and prints all
log messages.  Press **Ctrl+C** to stop.

---

## Uninstall

```bat
install.bat remove
```

Data and logs in `%ProgramData%\InventoryAgent\` are **not** deleted automatically –
remove that folder manually if desired.

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Service fails to start | Run `install.bat debug` and check the console output |
| Token rejected (401) | Delete `token =` line from `config.ini` to force re-registration |
| `pywin32` import error | Run `python -m pywin32_postinstall -install` as Administrator |
| Cannot reach server | Check firewall rules and the `url` setting in `config.ini` |
| Log file not created | Ensure the service account has write access to `%ProgramData%\InventoryAgent\` |
