# WebMonitor Codebase Review and Function Details

Generated: 2026-05-20
Scope: Laravel monitoring platform, Blade/Tailwind UI, queue jobs, migrations, tests, Docker runtime, and Windows Node.js agent.
Review mode: full code and system review with documentation update.

## 1. Executive Summary

WebMonitor is a Laravel 11 monitoring and security-observability platform with a Windows Node.js agent. The platform currently supports website monitoring, SSL checks, server inventory, server metrics, Windows service monitoring and control, database monitoring, network dependency monitoring, application mapping, IIS log summary monitoring, agent deployment packages, security scans, alerting, role-based access control, and maintenance reporting.

The core production ingestion path remains backward compatible:

```text
Windows Node.js Agent
  -> POST /api/metrics
  -> MetricsController validation and agent authentication
  -> ProcessServerMetric queue job
  -> MySQL/MariaDB application metadata and metric tables
  -> dashboard views, charts, alert checks, and server health summaries
```

Newer subsystems were added beside the existing metric path rather than replacing it:

```text
IIS log collector
  -> POST /api/iis-logs/summary
  -> IisLogSummaryController
  -> iis_log_summaries, iis_suspicious_events, iis_log_collector_statuses
  -> /iis-logs dashboard, server detail IIS panel, and IIS alert thresholds
```

```text
Maintenance reports
  -> /reports/maintenance
  -> MaintenanceReportController
  -> MaintenanceReportService aggregation
  -> maintenance_reports
  -> HTML preview, PDF export, Excel-compatible export, and report history
```

The current implementation is mostly additive and production-safe. Existing `/api/metrics`, website checks, SSL checks, database checks, server dashboards, agent startup behavior, and alert channels are preserved.

## 2. Repository Map

Important owned paths:

```text
app/
  Console/Commands          Artisan commands for scheduled monitoring tasks
  Events                    broadcast events
  Http/Controllers          web UI and API controllers
  Http/Middleware           admin and access middleware
  Http/Requests             request validation classes
  Jobs                      queue workers for checks, metrics, alerts, scans
  Mail                      website and SSL email mailables
  Models                    Eloquent domain models
  Policies                  monitor access policy
  Providers                 app and dynamic email config providers
  Services                  alerting, deployment, reporting, scanning, resource services

bootstrap/
  providers.php             application and package provider registration

config/
  agent.php                 agent deployment defaults, feature flags, service templates
  services.php              integrations, AI/log/webshell/external services
  queue.php                 queue connection behavior
  reverb.php                WebSocket server config

database/
  migrations                schema for users, monitors, servers, apps, agents, IIS, reports
  seeders                   roles, permissions, demo/admin/application data

resources/
  views                     Blade dashboard UI
  js                        Alpine, Echo/Reverb bootstrap
  css                       Tailwind app styles

routes/
  web.php                   authenticated UI routes and local API ingestion routes
  console.php               scheduled monitoring definitions
  auth.php                  Laravel Breeze auth routes
  channels.php              broadcast auth channels

server-monitor-agent/
  agent.js                  Node.js metrics, service, and IIS collector agent
  installer/*.ps1           development/service installer scripts
  dist/*.exe                packaged agent executable artifacts

tests/
  Feature                   coverage for monitoring, agents, apps, IIS, reports, auth, logs
```

## 3. Runtime Architecture

### Laravel Application

The Laravel app owns user authentication, dashboards, monitor configuration, API ingestion, queue dispatch, alert routing, report generation, permissions, and metadata persistence.

Key runtime properties:

- Laravel 11 on PHP 8.3.
- MySQL/MariaDB stores application metadata, monitor results, server metrics, IIS summaries, application mapping, audit logs, and reports.
- Queue workers handle metric processing, website checks, database checks, SSL checks, alert fan-out, scan jobs, and service commands.
- Blade/Tailwind provides the current dashboard UI.
- Spatie permissions controls module access.

### Windows Agent

The Node.js agent keeps the existing metric loop intact and adds optional IIS log collection:

```text
Agent Core
  - Existing metrics loop
  - Existing heartbeat metadata
  - Existing Windows service monitoring
  - Existing Windows service control polling
  - Optional IIS log collector, disabled by default
```

Old config remains valid:

```json
{
  "serverId": 1,
  "apiUrl": "https://example.com/api/metrics",
  "apiKey": "legacy-or-server-key"
}
```

IIS logging is optional:

```json
{
  "iisLogs": {
    "enabled": false,
    "paths": [],
    "scanIntervalSeconds": 60,
    "summaryOnly": true,
    "maxLinesPerRun": 5000,
    "sendRawSamples": false,
    "sampleLimit": 20,
    "allowlist": {
      "ips": [],
      "urlContains": [],
      "userAgents": []
    }
  }
}
```

If `iisLogs` is absent or disabled, the agent behaves as before.

## 4. Route and API Inventory

### Main UI Routes

Representative authenticated routes in `routes/web.php`:

| Area | Routes | Purpose |
| --- | --- | --- |
| Dashboard | `/dashboard`, root redirect | main monitoring overview |
| Monitors | `/monitors`, `/monitors/create`, `/monitors/{monitor}` | website monitor CRUD and details |
| Public status | `/status`, `/status/{slug}` | public uptime status views |
| SSL | `/ssl-monitor`, `/ssl-conversion` | certificate tracking and conversion utilities |
| SEO/security | `/seo-security`, security scan routes | SEO poisoning and security scans |
| Log inspection | `/logs`, upload/analyze routes | log upload analysis and optional AI summary |
| Applications | `/applications`, `/application-urls/{id}/link-monitor` | app mapping and URL monitor linking |
| Servers | `/servers`, `/servers/{server}` | inventory and server details |
| Agents | `/agents`, `/servers/{server}/agent-config`, `/servers/{server}/agent-package` | agent operations and deployment |
| Resources | `/server-resources` | node resource health dashboard |
| Database monitors | `/database-monitors` | database check configuration/results |
| Network monitors | `/network-monitors`, `/network-map` | TCP, DNS, ping-placeholder, topology, dependency mapping, and configured port baseline checks |
| IIS logs | `/iis-logs`, `/iis-logs/servers/{server}` | IIS summary and per-server drilldown |
| Reports | `/reports/maintenance`, `/reports/maintenance/history` | maintenance report generator/history |
| Admin | `/admin/*` | users, roles, permissions, settings |

### API Routes

Current ingestion endpoints:

| Endpoint | Controller | Compatibility Notes |
| --- | --- | --- |
| `POST /api/metrics` | `MetricsController@store` | Existing agent path. Must remain unchanged. |
| `GET /api/service-commands` | service command controller path | Existing agent command polling. |
| `POST /api/service-commands/{id}/result` | service command result path | Existing service control result reporting. |
| `POST /api/iis-logs/summary` | `Api\IisLogSummaryController@store` | New additive IIS summary ingestion. Returns 202. |
| `POST /api/network-checks/results` | `Api\NetworkCheckResultController@store` | New additive agent-side network check ingestion. Returns 202. |

The IIS endpoint reuses existing agent authentication behavior and supports the global `AGENT_API_KEY` compatibility path plus per-server keys where configured.

## 5. Domain Model Inventory

### Monitoring Core

| Model | Function |
| --- | --- |
| `Monitor` | Website monitor definition, URL, interval, alert recipients, maintenance window, ownership. |
| `CheckResult` | Website check result, response code, response time, status, body/SEO/security metadata. |
| `SslCertificate` or SSL result model | Certificate metadata and expiry tracking. |
| `Alert` / alert related records | Alert history, status, channel delivery metadata. |

### Server and Agent Operations

| Model | Function |
| --- | --- |
| `Server` | Inventory record, heartbeat, hostname, OS, runtime, metrics summary, agent key hash, IIS thresholds. |
| `ServerMetric` | CPU, memory, disk, process/resource metric samples. |
| `ServerService` | Discovered Windows services and service health. |
| `ServiceCommand` | Queued start/stop/restart service commands for agents. |
| `AgentAuditLog` | Agent config generation, package download, key rotation, and deployment audit trail. |

### Application Mapping

| Model | Function |
| --- | --- |
| `Application` | Application name, environment, min required app/db servers, health calculation. |
| `ApplicationServer` | Pivot mapping servers to applications with roles. |
| `ApplicationUrl` | Application URL, optional linked monitor, normalized URL, current URL status. |

Supported application roles:

- `web`
- `application`
- `database`
- `worker`
- `scheduler`
- `file_storage`

The same server can provide multiple roles for the same application. This supports single-server deployments and clustered deployments.

### IIS Log Monitoring

| Model | Function |
| --- | --- |
| `IisLogSummary` | Per-server IIS request summary windows. |
| `IisSuspiciousEvent` | Limited suspicious IIS samples for investigation. |
| `IisLogCollectorStatus` | Latest collector health per server. |

### Reporting

| Model | Function |
| --- | --- |
| `MaintenanceReport` | Generated report metadata, filters, summary JSON, status, and generated file path. |

### Security and Analysis

| Model | Function |
| --- | --- |
| `LogInspection` | Uploaded log analysis results and heuristic counts. |
| `Incident` or incident records | Incident history and alert/security events where available. |
| Webshell/security scan records | File integrity, webshell findings, and security scan history. |

### Access Control

| Model | Function |
| --- | --- |
| `User` | Authenticated user and permissions. |
| Spatie `Role` / `Permission` | Role/module permission mapping. |

## 6. Feature Modules

### 6.1 Website Monitoring

Main files:

- `app/Jobs/CheckWebsiteJob.php`
- `app/Http/Controllers/MonitorController.php`
- `app/Console/Commands/RunMonitorChecks.php`
- monitor/check result migrations and tests

Functions:

- Scheduled website checks.
- Manual checks from UI.
- HTTP status, response time, downtime tracking.
- Alert recipient handling.
- Maintenance window support.
- SEO/security metadata capture where enabled.

Recent safety fix:

- `$response` is initialized before the HTTP try block.
- Fallback paths use `$response?->body() ?? ''`.
- Exception paths can record failure results without undefined variable errors.

### 6.2 SSL Monitoring

Functions:

- SSL certificate discovery.
- Expiry status calculation.
- SSL monitor dashboard.
- Dispatch of website checks when SSL-related actions need refresh.

### 6.3 Server Inventory and Metrics

Functions:

- Agent registration/update through `/api/metrics`.
- Heartbeat tracking.
- CPU, memory, disk, runtime, OS, hostname, capability and version metadata.
- Dashboard server health and resource summaries.

Compatibility:

- Existing metric payload fields are preserved.
- New metadata and IIS health fields are optional and additive.

### 6.4 Windows Services

Functions:

- Agent service discovery and status reporting.
- Configurable monitored services.
- Service stop/start/restart commands.
- Command result capture.
- Alerts for stopped monitored services.

Configured deployment templates include:

- Application server: `W3SVC`, `WAS`, `IISADMIN`, `ColdFusion 2023 Application Server`
- Database server: `MySQL80`
- App/database server: all application services plus `MySQL80`

### 6.5 Database Monitoring

Functions:

- MySQL/MariaDB and PostgreSQL connection checks.
- Success/failure status.
- Result history and dashboard display.
- Inclusion in maintenance report aggregation.

Current limitation:

- Database monitor records are not yet mapped directly to applications. Report database health is therefore a platform-wide section unless future schema links database monitors to applications or servers.

### 6.6 Application Mapping

Main files:

- `app/Http/Controllers/ApplicationController.php`
- `app/Http/Controllers/ApplicationUrlController.php`
- `app/Models/Application.php`
- `app/Models/ApplicationUrl.php`
- `resources/views/applications/*`
- `tests/Feature/ApplicationMappingTest.php`

Functions:

- Map each server to one or more applications.
- Assign one or more roles per server/application.
- Support single-server app/database deployments.
- Support app clusters with multiple app servers and DB servers.
- Configure minimum required app servers and database servers.
- Show application health dashboard.
- Show reverse mapping on server detail pages.
- Display mapped application names on Agent Operations.

URL mapping behavior:

- Application URLs are normalized on sync.
- Existing monitors with the same normalized URL are auto-linked.
- Application health uses the linked monitor latest result.
- If no monitor exists, URL status remains `unknown`.
- UI provides Create Monitor and Link Monitor paths for unmapped URLs.

Health calculation:

- Critical if linked website monitor is down.
- Critical if healthy app servers are below the configured minimum.
- Critical if healthy DB servers are below the configured minimum.
- Warning if a cluster node is down while minimum required capacity remains met.
- Healthy when required components are healthy.

### 6.7 Network Monitoring v1.1

Main files:

- `app/Http/Controllers/NetworkMonitorController.php`
- `app/Http/Controllers/Api/NetworkCheckResultController.php`
- `app/Http/Controllers/ServerPortBaselineController.php`
- `app/Jobs/CheckNetworkMonitor.php`
- `app/Jobs/CheckServerPortBaseline.php`
- `app/Console/Commands/RunNetworkChecks.php`
- `app/Services/NetworkCheckService.php`
- `app/Services/NetworkAlertService.php`
- `app/Models/NetworkMonitor.php`
- `app/Models/NetworkCheckResult.php`
- `app/Models/ServerPortBaseline.php`
- `resources/views/network-monitors/*`
- `tests/Feature/NetworkMonitoringTest.php`

Functions:

- Adds `network_monitors`, `network_check_results`, and `server_port_baselines`.
- Adds dependency metadata: `application_id`, `source_server_id`, `target_server_id`, and `dependency_type`.
- Supports monitor types `tcp_port`, `dns`, and `ping`; ping is safely marked unsupported when OS ping is not available.
- Supports source types `central` and `agent`.
- Supports dependency types `app_to_db`, `app_to_router`, `public_to_waf`, `server_to_svn`, `server_to_api`, `dns`, and `external_dependency`.
- Central checks run from Laravel through `app:run-network-checks`.
- Agent checks run from the Windows agent when `featureFlags.networkChecks` / `networkChecks.enabled` is enabled.
- Agent results post to `/api/network-checks/results` using existing agent authentication.
- Network monitoring is disabled by default.
- Port baseline checks only run against explicitly configured server/port records. No full-range scans are performed.
- `/network-map` shows application-level source-to-destination dependencies with port, protocol, expected state, and health badge.
- MySQL templates can create explicit baselines for MySQL Router ports `6446` and `6447` and MySQL DB port `3306`.
- DNS result history is stored through `network_check_results`; unexpected DNS drift is marked as `dns_drift`.
- Monitor-level maintenance windows suppress network alerts while still storing results.

Alerts:

- Expected open port closed.
- Expected closed port open.
- DNS result mismatch.
- DNS drift.
- Latency threshold exceeded.
- Cooldown is tracked per monitor or port baseline.

Reporting:

- Maintenance reports include a Network Connectivity Summary section.
- Failed dependencies, DNS mismatches/drift, port baseline violations, and affected applications appear in maintenance reports.
- Failed network checks contribute to incidents and recommendations.

### 6.8 Agent Deployment Generator

Main files:

- `app/Services/AgentDeploymentService.php`
- `app/Http/Controllers/AgentController.php`
- `resources/views/agents/_deployment-actions.blade.php`
- `config/agent.php`
- `server-monitor-agent/installer/*.ps1`
- `tests/Feature/AgentDeploymentTest.php`

Functions:

- Per-server agent API key generation.
- Stores only API key hash in the database.
- Shows/downloads plain key only during config/package generation.
- Key rotation action.
- Config generation at `GET /servers/{server}/agent-config`.
- Package generation at `GET /servers/{server}/agent-package`.
- Package ZIP name: `ServerMonitorAgent-{server_name}-v{agent_version}.zip`.
- Audit log entries for config generated, package downloaded, and key rotated.

Generated package contents:

- `server-monitor-agent.exe`
- `config.json`
- `install-service.ps1`
- `uninstall-service.ps1`
- `restart-agent.ps1`
- `update-agent.ps1`
- `README.txt`
- logs folder placeholder

Deployment package installer behavior:

- Package-specific `install-service.ps1` copies `.\server-monitor-agent.exe` and `.\config.json` from the extracted package root into `C:\Program Files\ServerMonitorAgent`.
- It does not reference development paths such as `..\dist\server-monitor-agent.exe`.
- It does not reference `config.json.template`.

UI actions:

- Generate Config
- Generate Package
- Copy Install
- Copy Update
- Rotate Key

Operational note:

- Config/package generation rotates or exposes new per-server credentials. Admins must deploy the newly generated package/config before restarting the agent on that server.

### 6.9 IIS Log Monitoring v1

Main files:

- `server-monitor-agent/agent.js`
- `app/Http/Controllers/Api/IisLogSummaryController.php`
- `app/Http/Controllers/IisLogController.php`
- `app/Models/IisLogSummary.php`
- `app/Models/IisSuspiciousEvent.php`
- `app/Models/IisLogCollectorStatus.php`
- `resources/views/iis-logs/*`
- `tests/Feature/IisLogMonitoringTest.php`

Agent config:

- `iisLogs.enabled` defaults to `false`.
- Supported options:
  - `paths`
  - `scanIntervalSeconds`
  - `summaryOnly`
  - `maxLinesPerRun`
  - `sendRawSamples`
  - `sampleLimit`
  - `allowlist.ips`
  - `allowlist.urlContains`
  - `allowlist.userAgents`

Agent collector behavior:

- Parses IIS W3C log files under configured paths.
- Supports common fields:
  - `date`
  - `time`
  - `c-ip`
  - `cs-method`
  - `cs-uri-stem`
  - `cs-uri-query`
  - `sc-status`
  - `cs(User-Agent)`
- Reads only new lines using an offset state file.
- Handles log rotation and truncation safely.
- Counts:
  - `total_requests`
  - `status_2xx`
  - `status_3xx`
  - `status_4xx`
  - `status_5xx`
  - `http_404`
  - `http_500`
  - `suspicious_count`
- Captures:
  - `top_ips`
  - `top_urls`
  - limited `suspicious_samples`

Suspicious patterns include:

- `union select`
- `information_schema`
- `../`
- `..\`
- `%2e%2e`
- `cmd.exe`
- `powershell`
- `cfexecute`
- `cfide`
- `base64`
- `oastify.com`
- `burpcollaborator`
- `sqlmap`
- `nikto`
- `acunetix`
- `nessus`
- suspicious Googlebot user-agent

Allowlist behavior:

- IP, URL path contains, and user-agent allowlists are supported.
- Allowlisted suspicious-looking entries are not counted as suspicious.

Collector health:

- Tracks `last_scan_at`.
- Tracks `files_seen`.
- Tracks `files_read`.
- Tracks `lines_read`.
- Tracks `summaries_sent`.
- Tracks `last_error`.
- Tracks `state_file_path`.
- Collector errors do not crash the agent and do not stop metric submission.

Laravel storage:

- `iis_log_summaries` stores summary windows.
- `iis_suspicious_events` stores limited suspicious samples.
- `iis_log_collector_statuses` stores latest collector health per server.

IIS alert tuning:

Per-server threshold columns exist on `servers`:

- `iis_http_500_warning_threshold`
- `iis_http_500_critical_threshold`
- `iis_http_404_warning_threshold`
- `iis_http_404_critical_threshold`
- `iis_suspicious_warning_threshold`
- `iis_suspicious_critical_threshold`
- `iis_alert_cooldown_seconds`

The controller uses sensible defaults when these are not configured.

UI:

- `/iis-logs` shows per-server requests, 404 count, 500 count, suspicious count, last check, and collector health.
- Per-server IIS detail page shows trend chart, top IPs, top URLs, suspicious samples, and collector status.
- `/agents` shows whether IIS Logs capability is enabled.

Compatibility:

- `/api/metrics` is unchanged.
- IIS logging is disabled by default.
- Collector failures are reported through health status and `last_agent_error` style metadata, not by crashing the agent.

### 6.10 Maintenance Report Module v1

Main files:

- `app/Http/Controllers/MaintenanceReportController.php`
- `app/Services/MaintenanceReportService.php`
- `app/Models/MaintenanceReport.php`
- `resources/views/reports/maintenance/*`
- `database/migrations/2026_05_20_020000_create_maintenance_reports_table.php`
- `database/migrations/2026_05_20_020100_add_reports_permissions.php`
- `tests/Feature/MaintenanceReportTest.php`

Routes:

```text
GET  /reports/maintenance
GET  /reports/maintenance/history
POST /reports/maintenance
GET  /reports/maintenance/{maintenanceReport}/download
```

Permissions:

- `module.reports.view`
- `module.reports.generate`
- `module.reports.download`

The sidebar has a dedicated `Report` section, separate from Analysis.

Report filters:

- Report type: daily, weekly, monthly, custom.
- Date range.
- Application filter.
- Server group filter.
- Environment filter.
- Output: HTML preview, PDF, Excel-compatible export.

`maintenance_reports` table:

- `title`
- `report_type`
- `period_start`
- `period_end`
- `application_id`
- `generated_by`
- `status`
- `summary`
- `file_path`

Aggregated sections:

- Application health.
- Server uptime/heartbeat.
- CPU/RAM/disk average and max.
- Website uptime and downtime count.
- SSL expiry status.
- Database check success/failure.
- Windows service stopped events.
- IIS log summary: 404, 500, suspicious count, top IPs, top URLs.
- Incidents/alerts when available.
- Webshell/security scan summary when available.

Template sections:

- Cover page.
- Executive summary.
- Scope.
- Availability/SLA.
- Infrastructure health.
- Application health.
- Database health.
- IIS log summary.
- SSL certificate status.
- Incidents and alerts.
- Recommendations.
- Appendix.

Exports:

- HTML preview renders immediately.
- PDF export uses `barryvdh/laravel-dompdf`.
- Excel export uses SpreadsheetML `.xls` generated by the app. This avoids the current `ext-gd` requirement that blocked PhpSpreadsheet in the local PHP runtime.

Generated recommendations include:

- High disk usage.
- Expiring SSL.
- Website downtime.
- Database failures.
- Stopped services.
- IIS 500 spikes.
- Suspicious IIS activity.
- Offline agents.

Operational note:

- Running Docker containers must have Composer dependencies installed or be rebuilt after `composer.json` / `composer.lock` changes. A missing container vendor dependency caused the DomPDF facade error until dependencies were installed inside the app container.

### 6.11 Alerting

Existing alert channels:

- Email.
- Telegram.
- Slack.
- Discord.

Existing alert types include:

- Website up/down and threshold conditions.
- SSL expiry.
- Server metric thresholds.
- Windows service stopped events.
- Database check failures.
- IIS summary spikes.
- Security/webshell/SEO scan findings where implemented.

IIS alerts reuse existing alert delivery services and add cooldown behavior to avoid alert spam.

### 6.12 Security Scans and Asset Intelligence

Implemented areas include:

- Webshell scanning.
- File integrity/security scan pages.
- SEO poisoning checks.
- Log Scanner page.
- Asset intelligence with infrastructure/geolocation display.
- Vulnerability discovery UI sections.

Current observation:

- Geo/IP information for CDN-backed domains may correctly show the CDN edge/provider rather than the real origin. For example, Cloudflare-hosted targets commonly show Cloudflare ASN/location. This is expected unless origin discovery is implemented and authorized.

## 7. Permissions and Navigation

### Permission Groups

The admin permissions page groups permissions into user-facing cards:

- Monitors.
- Logs and Analysis.
- Reports.
- Modules and Features.
- Privileged Controls.
- General and System.

Report permissions are explicitly created before rendering the permissions UI so the Report section appears even on older installations after migration.

### Super Admin Gate

`AppServiceProvider` includes a `Gate::before` check so Super Admin users can pass module gates without needing every permission manually checked in the UI.

### Sidebar Sections

Current main sidebar structure:

- Monitoring.
- Analysis.
- Report.
- System.

The Maintenance Report link belongs under Report, not Analysis.

## 8. Database Schema Additions Reviewed

### Application Mapping

Application mapping is additive and uses dedicated app/application URL/pivot structures. It does not alter monitor semantics except optional linking from `application_urls.monitor_id`.

### Agent Deployment

Per-server API key support stores only a hash on the server record. Plain keys are only available at generation time.

### IIS Logs

Tables:

- `iis_log_summaries`
- `iis_suspicious_events`
- `iis_log_collector_statuses`

### Network Monitoring

Tables:

- `network_monitors`
- `network_check_results`
- `server_port_baselines`

These tables store compact check metadata and history only. They do not alter `/api/metrics`.

Indexes include server/date/status-oriented access patterns. These are suitable for the current summary-level v1 scope.

### Maintenance Reports

Table:

- `maintenance_reports`

The report stores summary JSON and optional generated file path. It does not modify source monitoring data.

## 9. Queue and Scheduler Behavior

Existing queue/scheduler behavior remains centered on:

- Website checks.
- SSL checks.
- Metric processing.
- Network checks.
- Alert dispatch.
- Database checks.
- Service commands.
- Security scans.

The IIS summary ingestion endpoint stores compact summaries synchronously. It does not push raw high-volume logs through the existing metric queue.

The maintenance report generator currently runs synchronously through the HTTP request. This is acceptable for v1 and small/medium datasets, but should become queued for larger production deployments.

## 10. Backward Compatibility Analysis

### Preserved

- Existing `/api/metrics` endpoint.
- Existing metric payload format.
- Existing global `AGENT_API_KEY` compatibility.
- Existing Windows Node.js agent config shape.
- Existing heartbeat and metric loops.
- Existing Windows service monitoring/control behavior.
- Existing website, SSL, database, and alert dashboard behavior.
- Existing monitor/check result tables and relationships.

### Additive Changes

- Optional `iisLogs` config section.
- Optional `networkChecks` config section.
- Optional IIS summary ingestion endpoint.
- Optional network check result ingestion endpoint.
- Optional per-server agent API keys.
- Optional application URL monitor linking.
- Optional report permissions and report pages.
- New tables for IIS and reports.
- Nullable threshold/config columns.

### Potential Compatibility Footguns

- Generated agent config/package can rotate the per-server agent key. Deploy the new key before restarting the service.
- Running Docker containers need updated Composer dependencies after package changes.
- Report PDF generation depends on DomPDF being installed and available in the runtime container.

## 11. Current Test Coverage

Feature coverage includes:

- Website monitor management.
- SSL monitoring.
- Monitor alert recipients.
- Server inventory and resource dashboards.
- Agent deployment package/config generation.
- Application mapping and URL linking.
- IIS log summary ingestion, health storage, thresholds, and allowlists.
- Maintenance report generation, HTML/PDF/Excel downloads, and permissions.

Representative tests:

- `tests/Feature/AgentDeploymentTest.php`
- `tests/Feature/ApplicationMappingTest.php`
- `tests/Feature/IisLogMonitoringTest.php`
- `tests/Feature/MaintenanceReportTest.php`
- `tests/Feature/MonitorManagementTest.php`
- `tests/Feature/SslMonitorTest.php`

## 12. Updated Risk Register

### High Priority

1. Report generation is synchronous.

   Large date ranges or many servers can make report generation slow in a browser request. Move report generation to a queued job for production-scale datasets.

2. Docker dependency drift can break package-backed features.

   DomPDF is present in `composer.json` and `composer.lock`, but running containers must run Composer install or be rebuilt. This should be part of deployment steps.

3. `/api/metrics` and `/api/iis-logs/summary` do not yet include request signing or replay protection.

   Existing API key authentication remains compatible, but production hardening should add signed timestamps/nonces as optional v2 auth.

### Medium Priority

4. Database monitor health is not application-scoped.

   Maintenance reports include database health, but without DB monitor to application/server mapping it cannot always attribute database health to a specific application.

5. Per-server key hashes are SHA-256 of high-entropy tokens.

   This is acceptable for generated random tokens, but a peppered HMAC or dedicated token hashing service would harden database-leak scenarios.

6. IIS v1 stores summaries in MySQL.

   This is acceptable for summary-only monitoring. Do not store raw logs in MySQL at scale.

7. Agent package generation rotates key during download.

   Secure by design, but operationally surprising. Keep confirm dialogs and audit logs visible.

8. Some frontend assets still rely on browser-side CDN resources.

   For locked-down/offline deployments, vendor these assets locally.

### Low Priority

9. `routes/web.php` is large.

   As modules grow, split routes by feature file to reduce merge friction.

10. Spreadsheet export is `.xls` SpreadsheetML rather than `.xlsx`.

   This is functional and dependency-light, but native `.xlsx` can be revisited if the runtime includes required PHP extensions.

11. CDN-backed asset intelligence may show CDN location.

   This is expected behavior and should be labeled clearly in the UI to avoid confusion.

## 13. Previously Flagged Issues Now Resolved

These were stale findings in the older review document and have been corrected:

- Agent deployment ZIP installer no longer references `..\dist` or `config.json.template`.
- Package-specific `install-service.ps1` copies `.\server-monitor-agent.exe` and `.\config.json`.
- `CheckWebsiteJob` no longer risks an undefined `$response` variable in exception fallback paths.
- Application URLs now normalize and auto-link matching existing monitors.
- Application health can use linked monitor latest results.
- Application URL UI offers Create Monitor / Link Monitor actions.
- Copy Install and Copy Update actions are wired to clipboard behavior with fallback text.
- Maintenance Report navigation is now under a dedicated Report sidebar section.
- Report permissions are created and grouped in the admin permission screen.
- DomPDF facade is registered and runtime Composer dependencies were installed in the Docker app container.

## 14. Recommended Next Steps

### Immediate

1. Queue maintenance report generation.
2. Add a report generation progress/status page.
3. Add DB monitor to server/application mapping.
4. Add signed request support for new agent endpoints while preserving existing API key behavior.
5. Add a deployment checklist that runs migrations, Composer install, optimize clear, and queue restart in Docker.

### Near Term

1. Add report scheduling and email delivery.
2. Add local vendoring for frontend CDN assets.
3. Add clearer CDN/origin labels to asset intelligence geo views.
4. Add audit events for report generation/download.
5. Add per-module health diagnostics page for agent collectors.

### Later

1. Move high-volume raw logs to a dedicated log store if raw logging is introduced.
2. Add SIEM-style correlation only after IIS v1 summary monitoring is stable.
3. Add optional signed payload v2 with nonce replay protection.
4. Add mTLS option for agent-to-server traffic.

## 15. Operational Runbook Notes

### After Pulling Code

Run:

```bash
composer install --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

For Docker:

```bash
docker exec web_monitor_app composer install --no-interaction --no-dev --optimize-autoloader
docker exec web_monitor_app php artisan migrate --force
docker exec web_monitor_app php artisan optimize:clear
docker exec web_monitor_app php artisan queue:restart
```

### Report Module Check

Verify:

```bash
php artisan route:list --path=reports/maintenance
php artisan tinker --execute="dump(class_exists(Barryvdh\\DomPDF\\Facade\\Pdf::class));"
```

Expected:

- Report routes exist.
- DomPDF facade class exists.
- Sidebar shows Report section for users with `module.reports.view` or Super Admin.

### IIS Module Check

Verify:

```bash
php artisan route:list --path=iis-logs
```

Expected:

- `/iis-logs` UI route exists.
- `/api/iis-logs/summary` endpoint exists.
- IIS logging remains disabled unless agent config enables it.

## 16. Production Safety Summary

The current codebase remains compatible with the validated production monitoring flows. New modules are additive and optional:

- IIS logging is disabled by default.
- Network monitoring is disabled by default.
- Report generation does not mutate monitoring source data.
- Per-server agent keys keep legacy global key compatibility.
- Application mapping links monitors without changing monitor checks.
- Existing metric ingestion remains the central stable path.

The main production hardening work left is operational: queue heavier report work, document Docker dependency updates, add stronger optional API request signing, and introduce explicit application/database mapping for better report attribution.
