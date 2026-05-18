# Web Monitoring System - Feature Status

Date: May 18, 2026  
System: Laravel app in Docker + Windows Node.js server agent

## Feature Readiness Matrix

| # | Feature | Status | Implementation | Notes |
|---|---------|--------|----------------|-------|
| 1 | Server Inventory | Partial | `Server` model, migration, controller, `/servers` UI | CRUD exists; needs polishing, grouping/tags, and production install flow |
| 2 | Server Online/Offline Heartbeat | Ready | Agent posts metrics; `ProcessServerMetric` updates `last_heartbeat_at` | Validated with `local-test`; 15-second offline threshold works |
| 3 | Website URL Health Check | Ready | `CheckWebsiteJob` + dashboard UI | HTTP checks, response time, uptime tracking, SEO/content checks |
| 4 | CPU Usage | Ready | Agent collects CPU via `systeminformation`; API stores it | Validated end-to-end |
| 5 | RAM Usage | Ready | Agent collects RAM via `systeminformation`; API stores it | Validated end-to-end |
| 6 | Disk Usage | Ready | Agent uses `systeminformation` with `fs.statfs` fallback | Validated on Windows packaged `.exe` |
| 7 | Server Metric Threshold Alerts | Ready | Per-server CPU/RAM/disk/offline thresholds + cooldowns | Uses Laravel receipt time for heartbeat freshness |
| 8 | Server Metric History Charts | Ready | `/server-resources/history` + Chart.js panel | CPU/RAM/disk history with server and range selector |
| 9 | Windows Service Status & Control | Ready | Service status, detail tab, command queue, agent execution path | Control requires `module.service_control` and a privileged agent |
| 10 | Database Connection Test | Partial | `DatabaseMonitor` model, encrypted password cast, check job, UI, scheduler | MySQL/MariaDB validated locally; PostgreSQL path exists but depends on PDO driver availability |
| 11 | Email/Telegram Alert | Ready | Mail queues + Telegram job + Slack/Discord webhook support | Needs production credentials per install |
| 12 | SSL Certificate Expiry Check | Ready | `CheckWebsiteJob` SSL detection + reminder job | 60-day warning threshold |

## Validated Agent Loop

Local agent path:

```text
D:\server-monitor-agent
```

Validated server id:

```text
local-test
```

Validated flow:

1. `local-test` server inventory row created.
2. Rebuilt agent executable loaded `D:\server-monitor-agent\config.json`.
3. Agent posted metrics to `POST /api/metrics`.
4. API returned `202 {"status":"accepted"}`.
5. Queue processed `ProcessServerMetric`.
6. Metrics were stored in `server_metrics`.
7. `servers.last_heartbeat_at` was updated.
8. After the agent stopped, online detection returned offline after the 15-second threshold.

Latest validated sample:

```json
{
  "server_id": "local-test",
  "cpu": "16.58",
  "ram_used": "11.15",
  "ram_total": "15.80",
  "disk_used": "181.34",
  "disk_total": "200.08"
}
```

## Agent Status

The Windows agent is now rebuilt and usable:

```text
D:\server-monitor-agent\dist\server-monitor-agent.exe
```

Current agent improvements:

- Supports `SERVER_MONITOR_*` environment variables.
- Looks for config next to the executable for Windows service installs.
- Validates config values before starting.
- Uses HTTP request timeout.
- Uses `fs.statfs` fallback when `systeminformation.fsSize()` returns no disks.
- Reports real CPU, RAM, and disk metrics on the current Windows host.

## Threshold Alerts

Server threshold alerts are implemented and validated.

Per-server settings:

- alerts enabled/disabled
- CPU threshold percent
- RAM threshold percent
- disk threshold percent
- offline threshold in seconds
- alert cooldown in seconds

Alert paths:

- CPU/RAM/disk thresholds are evaluated when `ProcessServerMetric` stores a new metric.
- Offline heartbeat alerts are evaluated by scheduled `CheckServerHeartbeats` every minute.
- Alerts are sent to active Super Admin alert channels.
- Supported channel types: email, Slack, Discord, Telegram.
- Cooldown timestamps prevent repeated alerts for the same condition.

Validation:

- Forced low thresholds on `local-test`.
- Dispatched a sample metric.
- Confirmed `last_cpu_alert_at`, `last_ram_alert_at`, and `last_disk_alert_at` were written.
- Forced a stale heartbeat.
- Ran `CheckServerHeartbeats`.
- Confirmed `last_offline_alert_at` was written.
- Disabled alerts on `local-test` after validation because the test agent is not left running.

## Metric History Charts

Server metric history charts are implemented on `/server-resources`.

Current behavior:

- `GET /server-resources/history` returns CPU, RAM percent, and disk percent series.
- The history panel includes a server selector.
- The range selector supports 1 hour, 6 hours, 24 hours, and 7 days.
- Charts use the latest 300 samples in the selected window.
- CPU, RAM, and disk charts render as separate Chart.js line charts.

## Windows Service Status

Windows service monitoring and command control are implemented.

Current behavior:

- Agent config supports `windowsServices`, for example `["Spooler", "WinDefend"]`.
- Environment override supports `SERVER_MONITOR_WINDOWS_SERVICES=Spooler,WinDefend`.
- Agent config supports `autoDiscoverWindowsServices` to discover likely app/database/web services without manual registration.
- Discovery patterns include ColdFusion, MySQL/MariaDB, SQL Server, PostgreSQL, Redis, Apache, nginx, Tomcat, IIS/W3SVC, Node, PM2, queue, and worker keywords.
- On Windows, the agent runs PowerShell `Get-Service` for configured services.
- Service states are sent in the existing `/api/metrics` payload.
- Laravel stores latest service state in `windows_services`.
- Laravel stores each sample in `windows_service_checks`.
- `/servers` shows how many configured Windows services are running.
- `/servers/windows-services` lists service details and provides Start/Stop/Restart controls.
- UI commands are queued in `windows_service_commands`.
- Agents claim queued commands on heartbeat, execute PowerShell service actions, and report command results on the next heartbeat.
- Non-running monitored service states can trigger Super Admin alert channels with cooldowns.
- Removing a service from monitoring soft-disables it so agent auto-discovery does not immediately re-add it.

Validation:

- Rebuilt `D:\server-monitor-agent\dist\server-monitor-agent.exe`.
- Ran the agent with `Spooler` and `WinDefend`.
- Confirmed the agent sent `services: 2`.
- Confirmed Laravel stored both services as `Running`.
- Enabled auto-discovery and confirmed the agent reported 6 services, including `MySQL80`, `IISADMIN`, `W3SVC`, and `SQLBackupAndFTP Client Service`.
- Queued a `start` command for `Spooler`.
- Confirmed the agent picked up the command and reported the result.
- Local validation returned `failed` because the agent was run from the shell without enough Windows privileges to control the service. Production service control requires running the agent as Administrator or under a service account with service-control rights.

Dependency status:

- `npm audit --omit=dev` currently reports 0 vulnerabilities after updating `systeminformation` to `^5.31.6` in `D:\server-monitor-agent`.

## Ready To Use Now

### Website URL Health Check

- Add monitors from the dashboard.
- Runs HTTP/HTTPS checks.
- Tracks response status, response time, uptime, content changes, and SEO poisoning indicators.

### SSL Certificate Expiry Check

- Runs automatically for HTTPS monitors.
- Stores expiry and issuer metadata.
- Supports expiry reminders.

### Email, Telegram, Slack, and Discord Alerts

- Alert channel plumbing exists.
- Production installs need `.env` credentials configured.

### Server Metrics

- Server inventory exists.
- Agent can send CPU/RAM/disk metrics.
- API validates registered active server IDs before queueing metrics.
- Queue stores metrics and updates heartbeat using Laravel server receipt time.

## Database Connection Monitoring

Initial database monitoring is implemented.

Current behavior:

- Stores database monitor inventory in `database_monitors`.
- Stores encrypted passwords with Laravel encrypted casts.
- Queues manual tests from `/database-monitors`.
- Schedules active database monitors every five minutes.
- Stores response-time history in `database_checks`.
- Sends failure alerts to active Super Admin alert channels with cooldowns.
- Validated locally against Docker MariaDB as `Local MariaDB`.

Known residual risk:

- PostgreSQL configuration is exposed in the UI and job path, but it requires the PHP `pdo_pgsql` extension in the runtime image.

## Production Notes

- Keep `AGENT_API_KEY` and agent `apiKey` synchronized.
- Use HTTPS for production agent traffic.
- Run the queue worker continuously in production.
- Rebuild the `.exe` after agent source changes:

```bash
npm run build:exe
```

- Current Node install:

```text
Node.js v24.15.0
npm 11.12.1
```
