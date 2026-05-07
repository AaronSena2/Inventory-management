# -*- mode: python ; coding: utf-8 -*-
# inventory_agent.spec
# PyInstaller build specification for inventory_agent.exe
#
# Build command (run from the agent/windows/ directory):
#   pyinstaller inventory_agent.spec
#
# Output: dist/inventory_agent.exe  (single-file, no console window when
# running as a service; console visible in debug mode)
#
# Requirements before building:
#   pip install pyinstaller pywin32 requests psutil
#   python -m PyInstaller.utils.hooks

import sys
from pathlib import Path

# Locate pywin32 service bootstrap files so the frozen exe can be registered
# as a Windows service (pythonservice.exe equivalent).
try:
    import win32serviceutil as _wsvc
    _svc_dir = Path(_wsvc.__file__).parent
    _svc_boot = str(_svc_dir / "win32" / "PythonService.exe")
except Exception:
    _svc_boot = None

block_cipher = None

a = Analysis(
    ["inventory_agent.py"],
    pathex=["."],
    binaries=[],
    datas=[
        # Bundle the config example so it is available in the frozen exe
        ("config.ini.example", "."),
    ] if Path("config.ini.example").exists() else [],
    hiddenimports=[
        # pywin32 modules loaded dynamically
        "win32service",
        "win32serviceutil",
        "win32event",
        "servicemanager",
        "win32api",
        "win32con",
        "win32security",
        "pywintypes",
        # requests / urllib3 internals
        "requests",
        "urllib3",
        "charset_normalizer",
        "certifi",
        "idna",
        # psutil platform sub-module
        "psutil",
        "psutil._pswindows",
        # configparser / json shipped with stdlib
        "configparser",
        "json",
    ],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=["tkinter", "matplotlib", "numpy"],
    win_no_prefer_redirects=False,
    win_private_assemblies=False,
    cipher=block_cipher,
    noarchive=False,
)

pyz = PYZ(a.pure, a.zipped_data, cipher=block_cipher)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.zipfiles,
    a.datas,
    [],
    name="inventory_agent",
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,               # Compress with UPX if available
    upx_exclude=[],
    runtime_tmpdir=None,
    # console=True so that "debug" and "status" subcommands print to stdout;
    # the Windows SCM does not open a console window anyway.
    console=True,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    # Optional: set an icon (place inventory_agent.ico next to this spec file)
    icon="inventory_agent.ico" if Path("inventory_agent.ico").exists() else None,
    version_file=None,
)
