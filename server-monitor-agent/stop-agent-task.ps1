param(
    [string]$TaskName = "ServerMonitorAgent"
)

$ErrorActionPreference = "Stop"

Stop-ScheduledTask -TaskName $TaskName
Write-Host "Stopped scheduled task: $TaskName"
