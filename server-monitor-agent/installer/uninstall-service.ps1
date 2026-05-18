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

$svcName = 'ServerMonitorAgent'
Write-Output "Stopping and removing service $svcName"
Stop-Service -Name $svcName -Force -ErrorAction SilentlyContinue
sc.exe delete $svcName | Out-Null

if (Test-Path $InstallPath) {
    Write-Output "Removing installation at $InstallPath"
    Remove-Item -Path $InstallPath -Recurse -Force
}

Write-Output "Uninstalled ServerMonitorAgent"
