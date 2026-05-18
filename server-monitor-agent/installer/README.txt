ServerMonitorAgent Installer v1.0.0

This folder contains scripts and templates to install the packaged agent as a Windows Service.

Installation path: C:\Program Files\ServerMonitorAgent\

Files included:
- server-monitor-agent.exe (packaged executable)  -- copy the one from ../dist
- config.json (place your config beside the exe)
- install-service.ps1
- uninstall-service.ps1
- restart-agent.ps1
- update-agent.ps1
- logs/ (directory for agent logs and update logs)

Usage:
1. Open PowerShell as Administrator.
2. Copy the packaged exe and the `config.json` (based on `config.json.template`) to the installer directory.
3. Run `.uild_and_install.ps1` or `.
un-install.ps1` to perform the install.

Notes:
- The service is created as `ServerMonitorAgent` and runs the packaged exe directly.
- Updating the agent should be done with `update-agent.ps1` which preserves `config.json`.
