# WebMonitor Codebase Review and Function Details

Generated: 2026-05-19  
Scope: Laravel monitoring platform, Blade/Tailwind UI, queue jobs, migrations, tests, Docker runtime, and Windows Node.js agent.  
Review mode: documentation-only; no runtime behavior was changed.

## 1. Executive Summary

WebMonitor is a Laravel 11 monitoring platform with a Windows/Node.js agent. The working production path is:

```text
Windows/Linux Agent
  -> POST /api/metrics
  -> MetricsController validation/auth/rate limit
  -> ProcessServerMetric queue job
  -> MySQL/MariaDB metadata + metric tables
  -> Reverb broadcast + dashboard polling
  -> Email/Telegram/Slack/Discord alert channels
```

The project now includes several major capability groups:

- Website uptime monitoring and SSL expiry monitoring.
- Server inventory, heartbeat, CPU/RAM/disk metrics, metric history, and resource dashboard.
- Windows service discovery, monitoring, alerting, and queued service control commands.
- Database monitor checks for MySQL/MariaDB and PostgreSQL.
- Log inspection uploads with heuristic severity counts and optional AI analysis.
- SEO poisoning detection, file integrity tracking, and webshell scanning.
- Application mapping with per-application roles and health calculation.
- Agent Operations dashboard with per-server deployment config/package generation.
- Role/permission gating through Spatie permissions.

The system is broadly additive and queue-oriented, which is good for production safety. The biggest immediate risks are around agent deployment package script mismatch, URL health mapping gaps in application mapping, a possible website check exception path, and some stale/dead helper code from earlier iterations.

## 2. Repository Map

Important owned paths:

```text
app/
  Console/Commands          Artisan commands for scheduled monitoring tasks
  Events                    Reverb broadcast events
  Http/Controllers          Web UI and API controllers
  Http/Middleware           Admin gate
  Http/Requests             Form request validation
  Jobs                      Queue workers for checks, metrics, alerts, scans
  Mail                      Website/SSL email mailables
  Models                    Eloquent domain models
  Policies                  Monitor access policy
  Providers                 App and dynamic email config providers
  Services                  Scanners, alerting, deployment, resource services

config/
  agent.php                 Agent deployment defaults, templates, feature flags
  services.php              Agent key, AI/log/webshell/external integrations
  queue.php                 Queue connection behavior
  reverb.php                WebSocket server config

database/
  migrations                Schema for users, monitors, servers, services, apps, audits
  seeders                   Roles, permissions, demo/application/admin data

resources/
  views                     Dark Blade dashboard UI
  js                        Alpine, Echo/Reverb bootstrap
  css                       Tailwind app styles

routes/
  web.php                   Authenticated UI routes and /api/metrics ingestion route
  console.php               Scheduler definitions
  auth.php                  Laravel Breeze auth routes
  channels.php              Broadcast auth channels

server-monitor-agent/
  agent.js                  Node.js metrics and Windows service agent
  installer/*.ps1           Windows service install/update/restart/uninstall scripts
  dist/*.exe                Packaged agent executable artifacts

tests/
  Feature                   Feature coverage for monitoring, agents, apps, auth, logs
  Unit                      Minimal unit example
```

Dependency/generated paths that should not be treated as source review targets:

- `vendor/`
- `node_modules/`
- `server-monitor-agent/node_modules/`
- `storage/framework/`
- `bootstrap/cache/`

## 3. Runtime Stack

Backend:

- PHP 8.2+
- Laravel 11
- Laravel Reverb
- Spatie Laravel Permission
- Pest/PHPUnit tests
- MySQL/MariaDB in Docker
- Database-backed Laravel queues

Frontend:

- Blade
- Tailwind CSS
- Alpine.js
- Vite
- Chart.js loaded from CDN in server resource view
- Mermaid loaded from CDN in layout

Agent:

- Node.js
- `systeminformation`
- `axios`
- PowerShell for Windows service discovery/control
- `@yao-pkg/pkg` for Windows executable packaging

Docker services:

- `app`: Apache/PHP Laravel app on port `8000`.
- `db`: MariaDB.
- `worker`: `php artisan queue:work --verbose --tries=3 --timeout=90`.
- `scheduler`: runs `schedule:run` every 60 seconds.
- `reverb`: WebSocket service on port `8080`.

## 4. Route Inventory

Total routes observed with `php artisan route:list`: 104.

Public/basic:

- `GET /`
- `GET /status`
- `GET /up`

Auth:

- Registration, login, logout, password reset, email verification, password confirmation.

Main monitoring:

- `GET /dashboard` -> `MonitorController@index`
- `GET /monitors/create` -> `MonitorController@create`
- `POST /monitors` -> `MonitorController@store`
- `GET /monitors/{monitor}/edit` -> `MonitorController@edit`
- `PATCH /monitors/{monitor}` -> `MonitorController@update`
- `DELETE /monitors/{monitor}` -> `MonitorController@destroy`
- `POST /monitors/{monitor}/toggle` -> `MonitorController@toggle`
- `POST /monitors/{monitor}/check` -> `MonitorController@check`

Server inventory/resources:

- `GET /servers` -> `ServerController@index`
- `GET /servers/create` -> `ServerController@create`
- `POST /servers` -> `ServerController@store`
- `GET /servers/{server}/edit` -> `ServerController@edit`
- `PATCH /servers/{server}` -> `ServerController@update`
- `DELETE /servers/{server}` -> `ServerController@destroy`
- `GET /server-resources` -> `ServerResourcesController@index`
- `GET /server-resources/snapshot` -> `ServerResourcesController@snapshot`
- `GET /server-resources/history` -> `ServerResourcesController@history`

Agent operations:

- `GET /agents` -> `AgentController@index`
- `GET /agents/{server}/config` -> `AgentController@downloadConfig`
- `GET /servers/{server}/agent-config` -> `AgentController@downloadConfig`
- `GET /servers/{server}/agent-package` -> `AgentController@downloadPackage`
- `POST /servers/{server}/agent-key/rotate` -> `AgentController@rotateKey`

Metrics API:

- `POST /api/metrics` -> `Api\MetricsController@store`

Windows services:

- `GET /servers/windows-services` -> `WindowsServiceController@index`
- `POST /servers/{server}/windows-services` -> `WindowsServiceController@store`
- `DELETE /windows-services/{windowsService}` -> `WindowsServiceController@destroy`
- `POST /windows-services/{windowsService}/commands` -> `WindowsServiceController@command`

Database monitors:

- CRUD plus `POST /database-monitors/{databaseMonitor}/test`.

Application mapping:

- `GET /applications` -> `ApplicationController@index`
- `GET /applications/create` -> `ApplicationController@create`
- `POST /applications` -> `ApplicationController@store`
- `GET /applications/{application}` -> `ApplicationController@show`
- `GET /applications/{application}/edit` -> `ApplicationController@edit`
- `PATCH /applications/{application}` -> `ApplicationController@update`

Security/analysis:

- `GET/POST /server-logs/scan`
- `GET/POST /log-inspections`
- `GET /log-inspections/{logInspection}`
- `POST /log-inspections/{logInspection}/ai-analyze`
- `GET /incidents`
- `GET/POST /ssl-conversion`
- SSL monitor CRUD/check routes
- `GET /seo-security`
- `POST /seo-security/scan`
- `POST /seo-security/webshell-scan`
- `GET/POST /assets`

Admin:

- Admin dashboard, user approval, permission editing, user delete/toggle admin.
- Email and Telegram settings/test/fetch/clear routes.

## 5. Main Functional Flows

### 5.1 Agent Metrics Ingestion

1. Agent loads config from `SERVER_MONITOR_CONFIG`, adjacent `config.json`, current directory, or bundled source directory.
2. Agent requires `serverId`, `apiUrl`, and `apiKey`.
3. Agent collects:
   - CPU current load
   - RAM used/total
   - main disk used/total
   - Windows service statuses if running on Windows
   - pending service command results
4. Agent posts payload to `/api/metrics` with `X-API-Key`.
5. `MetricsController@store`:
   - requires API key header
   - validates payload fields
   - rate-limits per reported `server_id`
   - finds existing server
   - authenticates per-server hash if present, else global `services.agent.key`
   - optionally auto-registers unknown server
   - syncs agent metadata onto `servers`
   - claims queued Windows service commands
   - returns monitored service list and commands
   - dispatches `ProcessServerMetric`
6. `ProcessServerMetric@handle`:
   - creates `server_metrics`
   - updates `servers.last_heartbeat_at` using server receipt time
   - evaluates CPU/RAM/disk thresholds
   - updates Windows service records and check history
   - processes command results
   - broadcasts `ServerMetricUpdated`

Compatibility notes:

- Existing payload fields remain unchanged.
- New metadata fields are nullable/optional.
- Global `AGENT_API_KEY` compatibility remains.
- Per-server API key is preferred when `agent_api_key_hash` exists.

### 5.2 Server Resources Dashboard

1. `ServerResourcesController@index` passes an initial server snapshot from `ServerResourcesService`.
2. `resources/views/server-resources.blade.php` seeds Alpine with the initial snapshot.
3. Browser polls `/server-resources/snapshot` every five seconds.
4. History charts call `/server-resources/history?server_id=...&hours=...`.
5. Chart data is derived from `server_metrics` and returned as CPU/RAM/disk percentages.

### 5.3 Windows Service Monitoring and Control

1. Agent sends `services[]` with name/display/status/startup type.
2. `ProcessServerMetric` upserts `windows_services`.
3. First-seen services default to `is_monitored = true`.
4. Removed services are soft-disabled by setting `is_monitored = false`.
5. Users with `module.service_control` can queue start/stop/restart.
6. API response returns up to five queued commands.
7. Agent runs PowerShell service commands and reports results in the next metrics payload.

### 5.4 Website and SSL Monitoring

1. `MonitorController` manages website monitors.
2. `CheckWebsiteJob` performs HTTP GET with timeout and SSL verification disabled.
3. It records `check_results`, optional SEO results, SSL certificate metadata, and broadcasts `MonitorChecked`.
4. Down/recovered notifications are sent to monitor recipients and advanced channels.
5. SSL monitor UI scopes to HTTPS URLs and can queue immediate checks.
6. `SslRenewalReminderJob` sends daily reminders according to per-monitor thresholds.

### 5.5 Database Monitoring

1. `DatabaseMonitorController` stores encrypted database credentials via model accessors.
2. `CheckDatabaseConnection` uses PDO to run `select 1`.
3. Results are stored in `database_checks`.
4. Monitor summary fields are updated on `database_monitors`.
5. Failure alerts dispatch through the same email/Slack/Discord/Telegram pattern.

### 5.6 Application Mapping

Supported roles:

- `web`
- `application`
- `database`
- `worker`
- `scheduler`
- `file_storage`

Flow:

1. `ApplicationController` stores application metadata.
2. URLs are stored in `application_urls`.
3. Server-role mappings are stored in `application_servers`.
4. The same server can be attached multiple times with different roles.
5. Minimum rules are stored in `application_component_rules`.
6. `Application::healthSummary()` calculates:
   - Critical when any URL is down.
   - Critical when healthy app servers fall below `app_servers` minimum.
   - Critical when healthy database servers fall below `database_servers` minimum.
   - Critical when required non-app/db component is unhealthy.
   - Warning when a cluster node is down but minimums are still met.
   - Healthy when all required components are healthy.

### 5.7 Agent Deployment Generator

1. `AgentController@index` renders fleet status.
2. `downloadConfig` generates a fresh plain key, stores only SHA-256 hash, builds config, audits generation, returns JSON attachment.
3. `downloadPackage` generates a fresh key, builds config, creates ZIP, audits package download, returns ZIP.
4. `rotateKey` generates a new key hash and audits rotation.
5. `AgentDeploymentService` owns:
   - plain key generation
   - SHA-256 hash comparison
   - config generation
   - default Windows service templates
   - request option normalization
   - ZIP creation
   - audit logging

Security note:

- Plain keys are not stored.
- Generating config/package currently rotates the key because plaintext cannot be recovered later.

## 6. Function Inventory

### 6.1 Controllers

| Class | Function | Purpose |
| --- | --- | --- |
| `AdminController` | `index` | Admin dashboard stats, users, monitors, permissions |
| `AdminController` | `toggleMonitor`, `checkMonitor`, `assignMonitor`, `destroyMonitor` | Admin monitor operations |
| `AdminController` | `toggleUserAdmin`, `destroyUser`, `approveUser` | User administration |
| `AdminController` | `editPermissions`, `updatePermissions` | Spatie permission management |
| `AdminController` | `emailSettings`, `updateEmailSettings`, `testEmailSettings` | Email channel configuration |
| `AdminController` | `telegramSettings`, `updateTelegramSettings`, `fetchTelegramChatId`, `clearTelegramUpdates`, `testTelegramSettings` | Telegram configuration |
| `AgentController` | `index` | Agent fleet operations dashboard |
| `AgentController` | `downloadConfig` | Generate per-server config JSON and audit |
| `AgentController` | `downloadPackage` | Generate agent deployment ZIP and audit |
| `AgentController` | `rotateKey` | Rotate per-server agent key hash |
| `AgentController` | `resolveServer` | Resolve server route model/id/server_id fallback |
| `AgentController` | `preview` | Config preview helper, currently no registered route |
| `AlertChannelController` | `index`, `store`, `destroy` | User alert channel CRUD |
| `ApplicationController` | `index`, `create`, `store`, `edit`, `update`, `show` | Application mapping UI and persistence |
| `ApplicationController` | `validatedApplication`, `parseUrls`, `syncUrls`, `syncMappings`, `syncRules` | Application mapping internals |
| `AssetIntelligenceController` | `index`, `scan` | DNS/asset intelligence UI |
| `DatabaseMonitorController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `test` | Database monitor CRUD and test dispatch |
| `DatabaseMonitorController` | `validateInput` | Database monitor validation |
| `IncidentController` | `index` | Incident history aggregation page |
| `LogInspectionController` | `index`, `store`, `show`, `analyzeWithAi` | Log upload, preview, heuristic analysis, AI analysis |
| `LogInspectionController` | `analyzeFile`, `extractLevel`, `detectSourceType`, `appearsBinary`, `assertUploadIsSafe`, `readPreviewLines` | Log parsing and upload safety |
| `LogInspectionController` | `requestAiLogAnalysis`, `availableAiProviders`, `providerConfig`, `providerAttemptOrder`, `appearsBinaryBytes`, `extractJsonObject` | AI provider flow |
| `MonitorController` | `index`, `create`, `store`, `edit`, `update`, `destroy`, `toggle`, `check` | Website monitor dashboard and CRUD |
| `MonitorController` | `parseTags` | Comma-separated tag normalization |
| `ProfileController` | `edit`, `update`, `destroy` | Breeze profile management |
| `SeoSecurityController` | `index`, `scan`, `webshellScan` | SEO/security dashboard and scans |
| `ServerController` | `index`, `create`, `store`, `edit`, `update`, `destroy` | Server inventory CRUD |
| `ServerController` | `parseTags` | Server tag normalization |
| `ServerLogScannerController` | `index`, `scan` | Manual server log scanning |
| `ServerResourcesController` | `index`, `snapshot`, `history` | Resource dashboard and history APIs |
| `ServerResourcesController` | `percentage` | Metric percentage helper |
| `SslConversionController` | `index`, `convert` | Certificate conversion utility |
| `SslMonitorController` | `index`, `store`, `check`, `checkAll`, `destroy`, `updateThreshold` | SSL monitor page |
| `SslMonitorController` | `sslMonitorQuery`, `parseUrls`, `normalizeUrl`, `nameFromUrl`, `sslDaysLeft` | SSL monitor helpers |
| `WindowsServiceController` | `index`, `store`, `command`, `destroy` | Windows service monitoring and control |
| `Api\MetricsController` | `store` | Agent metrics ingestion |
| `Api\MetricsController` | `validateApiKey`, `registerServerFromAgent`, `syncAgentMetadata`, `claimCommands`, `monitoredServices` | API auth, auto-registration, command/service response |

### 6.2 Models

| Model | Important Functions |
| --- | --- |
| `AgentDeploymentAudit` | `server` |
| `AlertChannel` | `user` |
| `Application` | `servers`, `urls`, `componentRules`, `healthStatus`, `healthSummary`, `minRequired`, `serversForRoles`, `urlStatusSummary`, `urlStatus`, `isServerHealthy` |
| `ApplicationComponentRule` | `application` |
| `ApplicationServer` | Pivot model for repeated server-role mappings |
| `ApplicationUrl` | `application`, `monitor` |
| `CheckResult` | `monitor` |
| `DatabaseCheck` | `databaseMonitor` |
| `DatabaseMonitor` | `checks`, `latestCheck` plus encrypted password accessor/mutator |
| `EmailSetting` | encrypted password accessor/mutator, `getActive`, `toMailConfig` |
| `FileIntegrityHash` | `monitor` |
| `LogInspection` | `user` |
| `Monitor` | `user`, `latestResult`, `latestSeoResult`, `checkResults`, `seoResults`, `isUnderMaintenance`, `alertEmailRecipients`, `uptimePercentage` |
| `SeoDiscoveredPage` | `monitor` |
| `SeoResult` | `monitor` |
| `SeoScan` | `monitor` |
| `Server` | `latestMetric`, `isUnderMaintenance`, `metrics`, `windowsServices`, `windowsServiceCommands`, `applications`, `agentHeartbeatStatus`, `agentVersionState`, `agentStatusSummary` |
| `ServerMetric` | `scopeLatestForServer` |
| `TelegramSetting` | encrypted bot token accessor/mutator |
| `User` | `casts`, `monitors`, `logInspections`, `alertChannels` |
| `WebshellScan` | data model for scan history |
| `WindowsService` | relationships/casts for service state |
| `WindowsServiceCheck` | check history model |
| `WindowsServiceCommand` | queued command constants/state |

### 6.3 Jobs

| Job | Function | Purpose |
| --- | --- | --- |
| `CheckDatabaseConnection` | `handle` | Test DB connection and persist result |
| `CheckDatabaseConnection` | `dsn`, `ensurePdoDriverIsLoaded`, `probeQuery`, `canAlert`, `sendFailureAlert` | DB check helpers and alerting |
| `CheckServerHeartbeats` | `handle`, `isOffline`, `canAlert` | Offline heartbeat alert evaluation |
| `CheckWebsiteJob` | `handle` | Website uptime/SSL/SEO check and alert dispatch |
| `CheckWebsiteJob` | `detectSeoPoisoning`, `extractOutboundLinks`, `checkSsl`, `recordSslFailure`, `dispatchAdvancedAlerts` | Website check helpers |
| `FileIntegrityJob` | `handle` | File integrity monitoring |
| `InternalCrawlJob` | `handle` | Internal crawl for SEO/security |
| `ProcessServerMetric` | `handle` | Persist metrics, heartbeat, services, command results, broadcast |
| `ProcessServerMetric` | `evaluateThresholds`, `canAlert`, `processWindowsServices`, `shouldAlertOnService`, `processCommandResults`, `failed` | Metric processing helpers |
| `RunWebshellScanJob` | `handle`, `storeResult` | Scheduled/manual webshell scan persistence |
| `ScanDnsIntelligenceJob` | `handle` | DNS/asset intelligence scan |
| `SendTelegramNotification` | `handle` | Telegram queue notification |
| `SeoScanJob` | `handle` | SEO scan worker |
| `SslRenewalReminderJob` | `handle`, `sendSslReminder` | SSL expiry reminder worker |

### 6.4 Services

| Service | Main Functions | Purpose |
| --- | --- | --- |
| `AgentDeploymentService` | `generatePlainKey`, `keyMatches`, `buildConfig`, `preview`, `defaultWindowsServices`, `normalizeOptions`, `createPackage`, `packageFilename`, `audit`, `hashKey`, `readme` | Secure agent config/package generation |
| `CrawlerService` | crawl helpers | Internal crawl support |
| `DnsScannerService` | DNS scan helpers | Asset intelligence |
| `FileIntegrityService` | hash scan helpers | File integrity monitoring |
| `RipgrepScanner` | scan wrappers | Fast local log/content search |
| `SearchEngineMonitorService` | search monitoring helpers | SEO search visibility |
| `SearchEngineSeoScanner` | search provider scan helpers | Public search poisoning detection |
| `SeoScannerService` | scan helpers | SEO page poisoning detection |
| `ServerAlertService` | `sendThresholdAlert`, `sendOfflineAlert`, `sendWindowsServiceAlert`, `dispatch`, `formatMessage`, `formatPercent` | Server alert fanout |
| `ServerResourcesService` | `getSnapshot` plus older local host resource helpers | Server snapshot service for resource dashboard |
| `SslConverterService` | certificate conversion helpers | SSL conversion page |
| `TelegramService` | `isEnabled`, `getLastError`, `sendMessage`, `testConnection`, `fetchChatIdFromUpdates`, `clearFetchedUpdates` | Telegram integration |
| `WebshellScannerService` | `scan`, `resolveTargetPath`, `iterFiles`, `shouldScanFile` | Webshell detection under allowed roots |

### 6.5 Agent Functions

`server-monitor-agent/agent.js` contains one main class: `ServerMonitorAgent`.

| Function | Purpose |
| --- | --- |
| `constructor` | Load config and initialize runtime state |
| `loadConfig` | Merge file config and environment variables, validate required config |
| `resolveConfigPath` | Locate config file |
| `collectMetrics` | Collect CPU/RAM/disk/services and pending command results |
| `sendMetrics` | POST metrics with retries |
| `delay` | Promise sleep helper |
| `parseWindowsServices` | Parse env/file service lists |
| `parseBoolean` | Boolean config helper |
| `collectWindowsServices` | Dispatch named/autodiscovered Windows service collection |
| `collectNamedWindowsServices` | PowerShell lookup for explicit services |
| `collectDiscoveredWindowsServices` | PowerShell service inventory with keyword matching |
| `execFile` | Promise wrapper around `child_process.execFile` |
| `handleServerResponse` | Store monitored services and execute returned commands |
| `executeServiceCommand` | Run PowerShell start/stop/restart and report result |
| `clearCommandResults` | Clear results after successful send |
| `isSystemDisk` | Identify primary/system disk |
| `getDiskUsage` | Pick main disk or fallback to statfs |
| `getDiskUsageFromStatfs` | Cross-platform disk fallback |
| `run` | Main metric loop |
| `stop` | Graceful stop flag |

## 7. Database/Data Model Summary

Core Laravel/auth:

- `users`, `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`
- Spatie permission tables: `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`

Website monitoring:

- `monitors`: website URLs, owner, group/tags, interval, active flag, SEO fields, SSL fields, alert emails, maintenance windows.
- `check_results`: status code, response time, up/down, checked timestamp.
- `seo_results`: SEO suspicion result, patterns, search findings/queries.

Server monitoring:

- `servers`: inventory, server ID, name, type, host metadata, group/tags, geo, thresholds, heartbeat timestamps, maintenance, agent metadata, agent key hash, alert cooldown timestamps.
- `server_metrics`: raw CPU/RAM/disk samples by `server_id`.
- `windows_services`: service state per server.
- `windows_service_checks`: service history.
- `windows_service_commands`: queued service control commands and results.

Application mapping:

- `applications`: app name/code/environment/owner/status.
- `application_servers`: repeated server-role pivot with `role`, `is_primary`, `is_required`, notes.
- `application_urls`: app URLs or linked monitor IDs.
- `application_component_rules`: minimum required app/database server counts.

Database monitoring:

- `database_monitors`: DB endpoint credentials and latest status.
- `database_checks`: DB check history.

Security/analysis:

- `log_inspections`: uploaded log metadata, counts, highlights, AI result fields.
- `seo_scans`: manual/scheduled SEO scan history.
- `seo_discovered_pages`: crawl/discovery inventory.
- `file_integrity_hashes`: file hash baseline/history.
- `webshell_scans`: webshell scan history.

Alert/config/audit:

- `alert_channels`: user notification endpoints.
- `email_settings`: encrypted SMTP settings.
- `telegram_settings`: encrypted Telegram bot token.
- `agent_deployment_audits`: config/package/key rotation audit events.

## 8. Scheduler and Queue Design

Schedulers in `routes/console.php`:

- Website monitor command every minute.
- Website check and DNS intelligence dispatch every five minutes.
- Database monitor checks every five minutes.
- SEO checks hourly.
- Internal crawl daily.
- File integrity checks every ten minutes.
- Webshell scans daily at 03:00 for configured allowed roots.
- SSL renewal reminders daily.
- HTTPS SSL metadata refresh daily at 02:00.
- Server heartbeat/offline alerts every minute.

Queue behavior:

- Metrics ingestion is asynchronous through `ProcessServerMetric`.
- Website checks, DB checks, Telegram sends, SEO scans, file integrity, webshell scans, DNS scans, and reminders are queued.
- Docker has one generic worker for all queues. This is simple and works at small scale, but high-volume metrics/log/security jobs will eventually need separate queues/workers.

## 9. UI Review

Layout:

- Main app layout is a dark dashboard shell with sidebar navigation, permission-gated modules, profile/logout controls, theme toggle, and shared session success alert.
- Most pages use Tailwind utility classes and the same slate/orange dark design language.

Important pages:

- `dashboard.blade.php`: website monitor dashboard.
- `server-resources.blade.php`: server metric cards and Chart.js history charts.
- `servers/index/create/edit.blade.php`: server inventory and agent setup snippets.
- `servers/windows-services.blade.php`: monitored services and queued command controls.
- `agents/index.blade.php`: agent fleet status, app mapping names, deployment actions.
- `agents/_deployment-actions.blade.php`: config/package/download/copy/rotate UI and preview modal logic.
- `applications/index/create/edit/show.blade.php`: application map dashboard and detail.
- `database-monitors/*`: DB monitor CRUD and status.
- `log-inspections/*`: log upload/preview/AI analysis.
- `seo/index.blade.php`: SEO/security/webshell tabs.
- `ssl-monitors/index.blade.php`: SSL status dashboard.

UI consistency:

- The dark dashboard style is mostly consistent.
- Some pages use very rounded cards (`rounded-3xl`, `rounded-[2.5rem]`, `rounded-[3rem]`) while newer requirements prefer smaller card radius. If this UI standard matters, normalize gradually.
- Some SVG icons are inline rather than centralized/icon-library based. This is acceptable for current Blade, but a future UI refactor should standardize icons.

## 10. Security Review

Implemented strengths:

- Agent API requires `X-API-Key`.
- Per-server keys store only SHA-256 hash.
- Global key remains for old agents.
- Config/package/key events are audited.
- Server service control is permission-gated separately by `module.service_control`.
- Log uploads block executable/script extensions and suspicious executable content.
- Email/Telegram/DB passwords/tokens use encrypted accessors.
- App and server changes validate most user input.
- Monitor ownership is protected by `MonitorPolicy` for key actions.

Security gaps/risks:

- Agent per-server key hashing uses plain SHA-256, not a slow password hash or HMAC with server secret. For random 48-character keys this is acceptable, but HMAC with `APP_KEY` would reduce database-leak usefulness.
- `/api/metrics` has no request signing, timestamp freshness check, or replay protection.
- API rate limit is keyed by user-provided `server_id` before authentication.
- Agent command control trusts queued commands from server; this is expected, but target hosts should run with the minimum privilege that still allows service control.
- Website checks call `withoutVerifying()`, so uptime check succeeds against invalid TLS; SSL metadata is captured separately but HTTP fetch security is intentionally lax.
- External CDN scripts (`Chart.js`, Mermaid, fonts) may be undesirable in restricted or high-security deployments.

## 11. Findings and Risks

### High: Generated Agent Package Install Script Does Not Match ZIP Layout

`AgentDeploymentService::createPackage()` adds these files at ZIP root:

- `server-monitor-agent.exe`
- `config.json`
- `install-service.ps1`

But `server-monitor-agent/installer/install-service.ps1` looks for:

```powershell
..\dist\server-monitor-agent.exe
config.json.template
```

That means a downloaded ZIP can fail installation after extraction because the script expects a `dist` folder and config template layout, not the generated root-level files. It also does not copy the generated `config.json` into the install path.

Recommended fix:

- Update generated package script or include a package-specific `install-service.ps1`.
- The package script should copy `.\server-monitor-agent.exe` and `.\config.json` from the extracted folder.
- Keep repo development installer separate if needed.

### High: Website Check SEO Error Path Can Reference an Undefined Response

In `CheckWebsiteJob@handle`, `$response` is created inside the HTTP try block. If the request throws and `$monitor->seo_enabled` is true, this line can evaluate a response that does not exist:

```php
$html = $html ?: ($response->body() ?? '');
```

Recommended fix:

- Initialize `$response = null` before the try.
- Use `$response?->body() ?? ''`.

### Medium: Application URL Status Is Not Automatically Linked

`ApplicationController::syncUrls()` creates `application_urls` rows with URL string and `status = unknown`. `Application::urlStatus()` can use a linked monitor, but the controller does not auto-link to existing monitors or create monitors for URLs.

Impact:

- Application health URL status will remain `unknown` unless something else updates `application_urls.status` or sets `monitor_id`.

Recommended fix:

- On sync, find matching `Monitor::where('url', $url)` and set `monitor_id`.
- Optionally add a button to create/link a monitor from an application URL.

### Medium: Config/Package Download Rotates Key Every Time

`downloadConfig()` and `downloadPackage()` both call `generatePlainKey()`. This is secure because plaintext keys are never stored, but repeated downloads invalidate the previous per-server key.

Impact:

- An already-installed agent can stop authenticating after someone previews/downloads a new config/package and does not deploy it.

Recommended options:

- Keep current behavior but label buttons clearly: "Generate New Config/Key".
- Add a separate "download last generated config" only if encrypted plain-key escrow is intentionally introduced.
- Keep explicit rotate action for emergency invalidation.

### Medium: `AgentController::preview` Has No Route

There is a `preview()` method but no observed route. The UI preview is client-side/form-based, not this controller method.

Recommended fix:

- Remove the unused method or add a deliberate authenticated preview route.

### Medium: Route Model Binding Fallback May Not Work for Server IDs

`AgentController::resolveServer()` tries to resolve by numeric ID or `server_id`, but Laravel implicit route model binding for `Server $server` can 404 before the method runs when a non-primary-key server ID is used.

Recommended fix:

- Use `{server:server_id}` for routes that should accept server IDs, or keep URLs primary-key only.

### Low: `ServerResourcesService` Contains Unused Local Host Resource Helpers

`getCpuPercent`, `getRamInfo`, and `getStorageInfo` read the Laravel host's `/proc` state but are not used by `getSnapshot()`, which now returns agent-reported server metrics.

Recommended fix:

- Remove or move them to a separate host-health service if local app host monitoring is needed.

### Low: Single Generic Queue Worker Can Become a Bottleneck

The Docker worker processes all jobs in one queue. This is fine for current scale, but metrics, website checks, DB checks, AI analysis, scans, and future logs can compete.

Recommended fix:

- Introduce queue names such as `metrics`, `checks`, `alerts`, `security`, `ai`.
- Run separate workers with independent concurrency/timeouts.

## 12. Test Coverage Summary

Observed Pest feature coverage:

- Agent deployment:
  - config contains correct server ID
  - plain API key not stored
  - package contains required files
  - rotated key invalidates old key
- Application mapping:
  - single server with app and DB roles
  - cluster warning when one node down but minimum met
  - critical when URL down or minimum unmet
- Server metrics:
  - auto-registration
  - disabled auto-registration
  - registered active server ingestion
  - agent version storage
  - heartbeat uses server receipt time
  - soft-removed services stay unmonitored
  - service control permission requirement
- Server inventory:
  - group/tags
  - server type/agent settings
  - config route attachment
  - setup details visible
- Website monitor management:
  - create/update/filter/pause/resume/check/delete
- SSL monitor:
  - list/add/check/check-all/delete/update threshold and authorization rules
- Alert recipients:
  - configured emails preferred
  - owner fallback
  - website/SSL reminders do not use hardcoded recipients
- Log inspection:
  - upload/inspect
  - IIS-style file support
  - user isolation
  - max-size rejection
  - executable content rejection
  - AI analysis provider/fallback paths
- Webshell scanner:
  - suspicious PHP patterns
  - clean file reporting
  - path traversal/allowed-root protection
  - scheduled/manual scan history
- Incident history:
  - website/SSL/webshell incident display and user scoping
- Auth/profile/password tests from Breeze.

Recommended missing tests:

- Generated ZIP installer script actually works with generated ZIP layout.
- `/api/metrics` rejects old per-server key after a package download, not only after explicit rotate.
- Application URL auto-linking once implemented.
- Website check exception path with SEO enabled.
- Maintenance window suppresses website alerts if intended.
- Queue separation once introduced.

## 13. Backward Compatibility Notes

Kept compatible:

- Existing `/api/metrics` endpoint remains.
- Existing metric payload fields remain required and unchanged.
- Agent config still supports old shape:

```json
{
  "serverId": "...",
  "apiUrl": "...",
  "apiKey": "..."
}
```

- New metadata fields are optional.
- Global `AGENT_API_KEY` still works for existing agents.
- New per-server key only takes priority when a server has `agent_api_key_hash`.
- Server and monitor schema changes are additive/nullable/defaulted.

Potential compatibility footguns:

- Downloading a new config/package invalidates the previous per-server key.
- If an old agent is using the global key against a server that now has a per-server hash, the controller still falls back to global key if per-server match fails. This preserves compatibility but weakens per-server exclusivity until global key is disabled.

## 14. Production Readiness Recommendations

Near-term:

- Fix generated agent ZIP/script mismatch.
- Fix `CheckWebsiteJob` undefined `$response` path.
- Add route or remove `AgentController::preview`.
- Link application URLs to existing monitors.
- Add labels explaining that config/package download generates a new key.
- Add test for package install script layout.

Next:

- Move metrics jobs to a dedicated queue.
- Add queue names for checks, alerts, scans, AI.
- Replace CDN scripts with bundled local assets for offline/security deployments.
- Add replay protection/signing for agent payloads.
- Add agent-side disk spool for offline metrics if reliable metric capture is required.
- Add retention policy for high-volume tables (`server_metrics`, check histories, service checks).

Future SIEM/logging:

- Do not store raw logs in MySQL.
- Add log ingestion APIs in parallel to metrics.
- Use ClickHouse/OpenSearch for raw/normalized log storage.
- Keep metric queues isolated from log queues.
- Add feature flags to agent config for logging modules.

## 15. Verification Commands Used

Commands run during this review:

```powershell
rg --files
git status --short
Get-ChildItem -Force
rg --files app routes database resources tests config server-monitor-agent -g '!server-monitor-agent/node_modules/**' -g '!server-monitor-agent/dist/**'
rg -n "^(class|trait|interface|enum) |function |public function|protected function|private function|Route::|Broadcast::|Artisan::|Schedule::" app routes database config tests server-monitor-agent -g '!server-monitor-agent/node_modules/**' -g '!server-monitor-agent/dist/**'
php artisan route:list
rg -n "^test\(|it\(" tests
```

No tests were rerun as part of this documentation-only pass.

