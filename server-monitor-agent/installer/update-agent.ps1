Param(
    [string]$InstallPath = "C:\Program Files\ServerMonitorAgent",
    [string]$NewExePath = "./dist/server-monitor-agent-new.exe",
    [string]$ExeName = "server-monitor-agent.exe",
    [string]$BackupDir = "./installer/logs/updates"
)

function Require-Admin {
    if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
        Write-Error "This script must be run as Administrator."; exit 1
    }
}

Require-Admin

if (-not (Test-Path $NewExePath)) {
    Write-Error "New executable not found at $NewExePath"; exit 1
}

$svc = 'ServerMonitorAgent'
Write-Output "Stopping service $svc"
Stop-Service -Name $svc -Force -ErrorAction SilentlyContinue

if (-not (Test-Path $InstallPath)) {
    Write-Error "Install path $InstallPath does not exist"; exit 1
}

if (-not (Test-Path $BackupDir)) { New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null }

$timestamp = (Get-Date).ToString('yyyyMMddHHmmss')
$backupExe = Join-Path $BackupDir ("$ExeName.$timestamp.bak")
$backupConfig = Join-Path $BackupDir ("config.json.$timestamp.bak")

Try {
    # Backup current exe and config
    $currentExe = Join-Path $InstallPath $ExeName
    if (Test-Path $currentExe) { Copy-Item -Path $currentExe -Destination $backupExe -Force }
    $currentConfig = Join-Path $InstallPath 'config.json'
    if (Test-Path $currentConfig) { Copy-Item -Path $currentConfig -Destination $backupConfig -Force }

    # Replace exe (preserve config)
    Copy-Item -Path $NewExePath -Destination $currentExe -Force

    # Start service
    Start-Service -Name $svc -ErrorAction Stop

    # Write update log
    $logEntry = "[$(Get-Date -Format o)] Updated exe to $NewExePath, backups: $backupExe, $backupConfig"
    $logFile = Join-Path $BackupDir 'update.log'
    Add-Content -Path $logFile -Value $logEntry

    Write-Output "Update complete. Service started. Logs: $logFile"
} Catch {
    Write-Error "Update failed: $_"
    # Attempt to restore backup
    if (Test-Path $backupExe) { Copy-Item -Path $backupExe -Destination $currentExe -Force }
    if (Test-Path $backupConfig) { Copy-Item -Path $backupConfig -Destination $currentConfig -Force }
    Start-Service -Name $svc -ErrorAction SilentlyContinue
    exit 1
}
