# Web Monitoring System - Feature Status

Date: May 18, 2026  
System: Laravel app in Docker + Windows Node.js server agent

## Feature Readiness Matrix

| # | Feature | Status | Implementation | Notes |
|---|---------|--------|----------------|-------|
| 1 | Server Inventory | Ready | `Server` model, migration, controller, `/servers` UI | CRUD, grouping/tags, heartbeat summary, and agent install snippets are implemented |
| 2 | Server Online/Offline Heartbeat | Ready | Agent posts metrics; `ProcessServerMetric` updates `last_heartbeat_at` | Validated with `local-test`; 15-second offline threshold works |
| 3 | Website URL Health Check | Ready | `CheckWebsiteJob` + dashboard UI | HTTP checks, response time, uptime tracking, SEO/content checks |
| 4 | CPU Usage | Ready | Agent collects CPU via `systeminformation`; API stores it | Validated end-to-end |
| 5 | RAM Usage | Ready | Agent collects RAM via `systeminformation`; API stores it | Validated end-to-end |
| 6 | Disk Usage | Ready | Agent uses `systeminformation` with `fs.statfs` fallback | Validated on Windows packaged `.exe` |
| 7 | Server Metric Threshold Alerts | Ready | Per-server CPU/RAM/disk/offline thresholds + cooldowns | Uses Laravel receipt time for heartbeat freshness |
| 8 | Server Metric History Charts | Ready | `/server-resources/history` + Chart.js panel | CPU/RAM/disk history with server and range selector |
| 9 | Windows Service Status & Control | Ready | Service status, detail tab, command queue, agent execution path | Control requires `module.service_control` and a privileged agent |
| 10 | Database Connection Test | Ready | `DatabaseMonitor` model, encrypted password cast, check job, UI, scheduler | MySQL/MariaDB validated locally; Docker image installs `pdo_pgsql` for PostgreSQL support |
| 11 | Email/Telegram Alert | Ready | Mail queues + Telegram job + Slack/Discord webhook support | Needs production credentials per install |
| 12 | SSL Certificate Expiry Check | Ready | `CheckWebsiteJob` SSL detection + reminder job | SNI-aware certificate capture, issuer/expiry/error storage, per-monitor reminder threshold |
| 13 | SSL Monitor Dashboard | Ready | `/ssl-monitors`, HTTPS monitor reuse, daily SSL refresh schedule | Add SSL-only URLs, auto-list HTTPS monitors from Add Monitor, check all, and permission-protected removal |
| 14 | Webshell Detection | Ready | `WebshellScannerService`, `/seo-security/webshell-scan`, SEO Security UI, scheduled job | Local allowed-path scanner with manual/scheduled history for suspicious scripts, obfuscation, command execution, and droppers |
| 15 | Incident History | Ready | `/incidents` timeline assembled from existing monitoring records | Website down checks, SSL issues, webshell suspicious/failed scans, and database failures |

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

### Server Inventory

- Add monitored servers with group and tag metadata.
- Review grouped online/offline inventory summaries.
- Copy per-server production agent config snippets from the inventory or edit screen.
- Track heartbeat, metrics, thresholds, and Windows service status from the same table.

### Website URL Health Check

- Add monitors from the dashboard.
- Assign website monitors to groups and tags.
- Filter dashboard monitors by group.
- Runs HTTP/HTTPS checks.
- Tracks response status, response time, uptime, content changes, and SEO poisoning indicators.

### SSL Certificate Expiry Check

- Runs automatically for HTTPS monitors.
- Stores expiry and issuer metadata.
- Stores the latest SSL failure reason when certificate capture fails.
- Supports expiry reminders using each monitor's `ssl_alert_threshold_days`.
- SSL socket capture is SNI-aware for hosts that require server-name indication.
- Logs a warning when a certificate socket cannot be opened or parsed.

### SSL Monitor Dashboard

- Dedicated `/ssl-monitors` page lists all HTTPS monitors.
- HTTPS monitors created through Add Monitor appear automatically.
- SSL Monitor can add one or more HTTPS URLs directly from a textarea.
- URL input accepts one URL per line or comma-separated values.
- Non-HTTPS URLs are ignored by the SSL-only add flow.
- Duplicate normalized URLs are not recreated; existing monitors are refreshed instead.
- Status summary cards show tracked, valid, expiring, expired, and pending counts.
- Certificate table shows monitor name, URL, SSL status, failure reason, expiry timestamp, issuer, last check time, and actions.
- Alert threshold can be edited per SSL monitor from the certificate table.
- Status rules:
  - `Pending`: no certificate expiry stored yet.
  - `Expired`: certificate expiry date is in the past.
  - `Expiring`: certificate expires within 30 days.
  - `Valid`: certificate expires in more than 30 days.
- `Check Now` queues an immediate SSL/website check for a single HTTPS monitor.
- `Check All` queues immediate checks for every visible HTTPS monitor.
- Active HTTPS monitors are queued for an SSL metadata refresh daily at 02:00.
- SSL reminder alerts use the per-monitor threshold instead of a hardcoded 60-day window.
- Remove action deletes the monitor URL from SSL Monitor.
- Remove permissions:
  - Super Admin can remove any SSL monitor URL.
  - Regular users can remove only their own SSL monitor URLs.
  - Unauthorized removal attempts are blocked by the existing monitor policy.
- Day counts are rounded to whole days for readable display.
- Pending rows show either the latest SSL failure reason or `Waiting for first scan`.
- Current validation coverage:
  - Lists HTTPS monitors created through the normal Add Monitor flow.
  - Adds multiple SSL URLs and avoids duplicates.
  - Queues single monitor checks.
  - Queues Check All for visible HTTPS monitors only.
  - Allows owner removal.
  - Blocks other users from removing someone else's URL.
  - Allows Super Admin removal.

### Webshell Detection

- Manual local file scans from `/seo-security`.
- Scheduled scans run daily at 03:00 for configured allowed paths.
- Manual and scheduled scans are stored in `webshell_scans`.
- Recent scan history is visible on the Webshell Detection tab.
- Restricts scans to configured allowed paths.
- Flags suspicious PHP/web script execution, command execution, obfuscation, encoded blobs, and dropper-style file writes.
- Reports file, line, severity, signature, excerpt, and reason for each finding.

### Incident History

- `/incidents` provides a unified recent incident timeline.
- Includes website downtime checks from `check_results`.
- Includes SSL expired, expiring, and pending-error states from monitors.
- Includes suspicious or failed webshell scans from `webshell_scans`.
- Includes failed database checks for users with database monitoring access.
- Regular users see only incidents tied to their website monitors; Super Admin can see all website/SSL monitor incidents.

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

PostgreSQL support:

- The Docker image installs `pdo_pgsql`.
- If a non-Docker PHP runtime is used, the database check job reports a clear missing-extension error when the needed PDO driver is unavailable.

## Production Notes

- Keep `AGENT_API_KEY` and agent `apiKey` synchronized.
- Use HTTPS for production agent traffic.
- Run the queue worker continuously in production.
- Configure `WEBSHELL_SCAN_ALLOWED_PATHS` to the web roots that should be scanned.
- Rebuild the `.exe` after agent source changes:

```bash
npm run build:exe
```

- Current Node install:

```text
Node.js v24.15.0
npm 11.12.1
```

## Proposed Implementation Plan

This plan is organized into four phases so you can track progress and prioritize work.

### Phase 1 — High Value, Low Effort

- [ ] Maintenance windows / scheduled silence
  - [ ] Add maintenance fields to `servers` and `monitors`.
  - [ ] Ignore alerts and incident creation during active maintenance.
  - [ ] Add UI controls on server and monitor edit pages.

- [ ] Network/service port checks
  - [ ] Extend monitor types to include `tcp` and `udp`.
  - [ ] Add connect checks and basic protocol validation in the check job.
  - [ ] Add UI selector and result display for port checks.

- [ ] Agent version reporting
  - [ ] Send `agent_version` with every metric payload.
  - [ ] Persist latest agent version on `servers`.
  - [ ] Display agent version and check-in status in inventory.


### Phase 2 — Agent and platform expansion

- [ ] Linux/macOS agent support
  - [ ] Reuse the current `agent.js` code with cross-platform abstractions.
  - [ ] Add Linux `systemd`/init and macOS `launchctl` service discovery.
  - [ ] Provide install scripts and config examples for Linux/macOS.

- [ ] Agent auto-update / release tracking
  - [ ] Add a backend endpoint for approved agent releases.
  - [ ] Allow the agent to report its current version and optionally pull updates.
  - [ ] Show upgrade status on the server inventory page.

- [ ] Adaptive anomaly detection
  - [ ] Build a baseline model from recent CPU/RAM/disk history.
  - [ ] Alert on abnormal spikes or pattern deviations.
  - [ ] Add an opt-in adaptive detection setting per server.

### Phase 3 — Security, logs, and audit

- [ ] Centralized log metadata and health
  - [ ] Extend the agent to optionally ship log metadata or error counts.
  - [ ] Display log health and suspicious log findings in the dashboard.
  - [ ] Keep log collection lightweight and optional.

- [ ] Audit trail and action history
  - [ ] Record key changes such as monitor/server edits, alert setup, and service commands.
  - [ ] Add an audit log page with filtering and export.

- [ ] Stronger agent authentication
  - [ ] Add request signing or JWT support for the agent API.
  - [ ] Support API key rotation and token revocation.
  - [ ] Harden rate limiting and error handling for agent endpoints.



## Priority Summary

- Priority 1: maintenance windows, agent version reporting, network checks
- Priority 2: Linux/macOS agent, auto-update support, adaptive anomaly detection
- Priority 3: centralized logs, audit trail, stronger agent auth
- Priority 4: more advanced security and operational polish

> This roadmap gives you a clear tracking plan for expanding coverage, reducing false alerts, and improving operational visibility.
