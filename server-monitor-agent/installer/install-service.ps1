Param(
    [string]$InstallPath = "C:\Program Files\ServerMonitorAgent",
    [string]$ExeName = "server-monitor-agent.exe"
)

function Require-Admin {
    if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
        Write-Error "This script must be run as Administrator."; exit 1
    }
}

Require-Admin

Write-Output "Installing ServerMonitorAgent to $InstallPath"

if (-not (Test-Path $InstallPath)) {
    New-Item -ItemType Directory -Path $InstallPath -Force | Out-Null
}

$srcExe = Join-Path -Path (Split-Path -Path $MyInvocation.MyCommand.Path -Parent) -ChildPath "..\dist\$ExeName"
if (-not (Test-Path $srcExe)) {
    Write-Error "Packaged executable not found at $srcExe. Copy dist\$ExeName into the installer folder."; exit 1
}

$dstExe = Join-Path $InstallPath $ExeName
Copy-Item -Path $srcExe -Destination $dstExe -Force

# Copy config template if no config exists
$template = Join-Path (Split-Path -Path $MyInvocation.MyCommand.Path -Parent) 'config.json.template'
$dstConfig = Join-Path $InstallPath 'config.json'
if (-not (Test-Path $dstConfig) -and (Test-Path $template)) {
    Copy-Item -Path $template -Destination $dstConfig -Force
    Write-Output "Wrote sample config to $dstConfig. Please edit before starting the service."
}

# Create logs directory
$logs = Join-Path $InstallPath 'logs'
if (-not (Test-Path $logs)) { New-Item -ItemType Directory -Path $logs -Force | Out-Null }

# Create Windows Service
$svcName = 'ServerMonitorAgent'
$exists = Get-Service -Name $svcName -ErrorAction SilentlyContinue
if ($exists) {
    Write-Output "Service $svcName already exists. Stopping and updating binary."
    Stop-Service -Name $svcName -Force -ErrorAction SilentlyContinue
} else {
    Write-Output "Creating service $svcName"
    sc.exe create $svcName binPath= "`"$dstExe`"" start= auto DisplayName= "Server Monitor Agent" | Out-Null
}

Start-Service -Name $svcName -ErrorAction SilentlyContinue
Write-Output "Service $svcName installed and started."
