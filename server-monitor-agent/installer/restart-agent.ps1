Param(
    [string]$ServiceName = 'ServerMonitorAgent'
)

function Require-Admin {
    if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
        Write-Error "This script must be run as Administrator."; exit 1
    }
}

Require-Admin

Write-Output "Restarting service $ServiceName"
Restart-Service -Name $ServiceName -Force -ErrorAction Stop
Write-Output "Service restarted"
