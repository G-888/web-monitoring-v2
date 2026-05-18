param(
    [string]$TaskName = "ServerMonitorAgent",
    [string]$AgentPath = "D:\server-monitor-agent\dist\server-monitor-agent.exe",
    [string]$WorkingDirectory = "D:\server-monitor-agent"
)

$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script from an elevated PowerShell window."
}

if (-not (Test-Path -LiteralPath $AgentPath)) {
    throw "Agent executable not found: $AgentPath"
}

if (-not (Test-Path -LiteralPath (Join-Path $WorkingDirectory "config.json"))) {
    throw "config.json not found in working directory: $WorkingDirectory"
}

$action = New-ScheduledTaskAction `
    -Execute $AgentPath `
    -WorkingDirectory $WorkingDirectory

$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal `
    -UserId "SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Days 365) `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Settings $settings `
    -Description "Runs the WebMonitor server agent with service-control privileges." `
    -Force | Out-Null

Start-ScheduledTask -TaskName $TaskName

Write-Host "Installed and started scheduled task: $TaskName"
Write-Host "Agent: $AgentPath"
Write-Host "Working directory: $WorkingDirectory"
