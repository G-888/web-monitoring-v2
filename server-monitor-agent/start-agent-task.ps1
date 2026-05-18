param(
    [string]$TaskName = "ServerMonitorAgent"
)

$ErrorActionPreference = "Stop"

Start-ScheduledTask -TaskName $TaskName
Write-Host "Started scheduled task: $TaskName"
