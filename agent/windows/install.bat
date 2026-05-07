@echo off
:: install.bat – Helper to manage the InventoryAgent Windows service
:: Must be run as Administrator.
::
:: Usage:
::   install.bat install    – copy exe to ProgramFiles and register service
::   install.bat start      – start the service
::   install.bat stop       – stop the service
::   install.bat restart    – restart the service
::   install.bat remove     – stop and remove the service
::   install.bat status     – show service status
::   install.bat debug      – run the agent in the foreground (for testing)

setlocal EnableDelayedExpansion

:: ── Configurable paths ──────────────────────────────────────────────────────
set "SVC_NAME=InventoryAgent"
set "SVC_DISPLAY=IT Inventory Agent"
set "INSTALL_DIR=%ProgramFiles%\InventoryAgent"
set "EXE_NAME=inventory_agent.exe"
set "DATA_DIR=%ProgramData%\InventoryAgent"
:: ────────────────────────────────────────────────────────────────────────────

:: Require Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] This script must be run as Administrator.
    echo         Right-click install.bat and choose "Run as administrator".
    pause
    exit /b 1
)

:: Locate the exe (built by PyInstaller or pre-built)
set "EXE_SRC=%~dp0dist\%EXE_NAME%"
if not exist "%EXE_SRC%" (
    :: Fall back to same directory as this script
    set "EXE_SRC=%~dp0%EXE_NAME%"
)

if /i "%~1"=="install"  goto :do_install
if /i "%~1"=="start"    goto :do_start
if /i "%~1"=="stop"     goto :do_stop
if /i "%~1"=="restart"  goto :do_restart
if /i "%~1"=="remove"   goto :do_remove
if /i "%~1"=="status"   goto :do_status
if /i "%~1"=="debug"    goto :do_debug

echo Usage: install.bat [install^|start^|stop^|restart^|remove^|status^|debug]
exit /b 0

:: ── INSTALL ─────────────────────────────────────────────────────────────────
:do_install
if not exist "%EXE_SRC%" (
    echo [ERROR] Executable not found: %EXE_SRC%
    echo         Build it first with: pyinstaller inventory_agent.spec
    pause
    exit /b 1
)

echo [*] Creating install directory: %INSTALL_DIR%
if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"

echo [*] Copying executable...
copy /y "%EXE_SRC%" "%INSTALL_DIR%\%EXE_NAME%" >nul

echo [*] Creating data directory: %DATA_DIR%
if not exist "%DATA_DIR%" mkdir "%DATA_DIR%"

:: Copy example config if no config.ini exists yet
if not exist "%DATA_DIR%\config.ini" (
    if exist "%~dp0config.ini.example" (
        copy /y "%~dp0config.ini.example" "%DATA_DIR%\config.ini" >nul
        echo [!] A default config.ini has been created at:
        echo     %DATA_DIR%\config.ini
        echo     Edit it to set your server URL before starting the service.
    )
)

echo [*] Registering Windows service (auto-start)...
"%INSTALL_DIR%\%EXE_NAME%" install
if %errorLevel% neq 0 (
    echo [ERROR] Service registration failed.
    pause
    exit /b 1
)

:: Ensure start type is AUTO_START (the Python code sets this, but confirm via sc)
sc config "%SVC_NAME%" start= auto >nul

echo.
echo [OK] Service installed and set to auto-start.
echo      Run:  install.bat start   – to start it immediately.
echo      Edit: %DATA_DIR%\config.ini  – to configure the server URL / token.
goto :eof

:: ── START ────────────────────────────────────────────────────────────────────
:do_start
echo [*] Starting %SVC_NAME%...
sc start "%SVC_NAME%"
if %errorLevel% neq 0 (
    echo [ERROR] Failed to start service. Check the event log or run in debug mode.
    exit /b 1
)
echo [OK] Service started.
goto :eof

:: ── STOP ─────────────────────────────────────────────────────────────────────
:do_stop
echo [*] Stopping %SVC_NAME%...
sc stop "%SVC_NAME%"
goto :eof

:: ── RESTART ──────────────────────────────────────────────────────────────────
:do_restart
call :do_stop
timeout /t 3 /nobreak >nul
call :do_start
goto :eof

:: ── REMOVE ───────────────────────────────────────────────────────────────────
:do_remove
echo [*] Stopping service (if running)...
sc stop "%SVC_NAME%" >nul 2>&1
timeout /t 2 /nobreak >nul

echo [*] Removing service...
"%INSTALL_DIR%\%EXE_NAME%" remove
sc delete "%SVC_NAME%" >nul 2>&1

echo [*] Removing installed files...
if exist "%INSTALL_DIR%" rd /s /q "%INSTALL_DIR%"

echo [OK] Service removed.
echo      Data and logs remain at: %DATA_DIR%
echo      Delete that folder manually if desired.
goto :eof

:: ── STATUS ────────────────────────────────────────────────────────────────────
:do_status
sc query "%SVC_NAME%"
goto :eof

:: ── DEBUG ─────────────────────────────────────────────────────────────────────
:do_debug
echo [*] Running agent in foreground (debug mode). Press Ctrl+C to stop.
if exist "%INSTALL_DIR%\%EXE_NAME%" (
    "%INSTALL_DIR%\%EXE_NAME%" debug
) else if exist "%EXE_SRC%" (
    "%EXE_SRC%" debug
) else (
    echo [ERROR] Executable not found. Build with: pyinstaller inventory_agent.spec
    exit /b 1
)
goto :eof
