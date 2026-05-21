# WebMonitor Production Readiness Review

Review date: 2026-05-21  
Reviewer role: Laravel security engineer, Windows agent engineer, platform architect, DevOps reviewer

## Executive Summary

Production readiness score: **90/100**

The platform is functionally strong and the regression suite is healthy. This pass added the missing production-hardening layer requested for deployment: production Docker Compose, central audit events, SSRF policy, queued report exports, retention pruning, Telegram token masking, and updated production runbooks. The system is ready for a controlled production pilot behind HTTPS with per-server agent keys.

Safe to deploy now:

- Core Laravel monitoring dashboard behind authentication.
- Server metrics ingestion with existing `/api/metrics` payload compatibility.
- Per-server agent package/config generation for controlled operators.
- Application mapping, Client Architecture Wizard, IIS Log Monitoring v1, Network Monitoring v1.1, Database Monitor Guided Setup, and Maintenance Reports for a pilot environment.

Should wait before broad production:

- Public internet exposure without HTTPS, secure cookies, and hardened Docker secrets.
- Broad public exposure before all legacy agents are migrated away from the global fallback key.
- Broad multi-tenant rollout without final review of fine-grained permissions for every legacy dashboard action.

Top 10 blockers:

1. Rotate all remaining agents to per-server keys, then set `AGENT_GLOBAL_API_KEY_ENABLED=false`.
2. Enforce HTTPS, `SESSION_SECURE_COOKIE=true`, trusted proxy headers, and HSTS at the reverse proxy.
3. Validate production secrets outside source control and avoid default `.env` credentials.
4. Review legacy dashboard actions for finer per-module authorization beyond current broad module permissions.
5. Decide whether TLS verification bypasses in website/SEO checks should remain per-monitor exceptions.
6. Validate backup and restore on the actual production Docker volumes/database.
7. Add process supervision/alerting for all split queue workers and scheduler.
8. Confirm generated report file retention policy with the business owner before enabling scheduled pruning.
9. Document operational ownership for SSRF allowlist changes.
10. Add deployment smoke checks to CI/CD so cache/migration failures block releases.

Top 10 recommended fixes:

1. Add CI/CD smoke checks for `migrate --force`, `route:cache`, `config:cache`, `view:cache`, and `/up`.
2. Rotate all agents to per-server keys and set `AGENT_GLOBAL_API_KEY_ENABLED=false`.
3. Validate production backup and restore procedures with a real restore drill.
4. Add uptime/process monitoring for `queue-metrics`, `queue-checks`, `queue-alerts`, `queue-security`, `queue-reports`, and `scheduler`.
5. Review and tighten legacy broad permissions for monitor, SEO, asset, and admin settings actions.
6. Decide per-monitor TLS verification policy for website and security scans.
7. Add operational approval for any `SECURITY_ALLOWED_SCAN_DOMAINS` or `SECURITY_ALLOWED_SCAN_CIDRS` change.
8. Add external error monitoring for failed jobs and report export failures.
9. Review Docker image hardening and consider running the app container as a non-root user in the final image.
10. Add restore validation to the production runbook.

## Fixes Applied

- Replaced closure web routes with `StatusController` and `Route::redirect` so `php artisan route:cache` succeeds.
- Added configurable agent ingest payload limits and per-server rate limits for metrics, IIS summaries, and network results.
- Added safe failed-auth logging for agent API endpoints without logging API keys.
- Added `AGENT_GLOBAL_API_KEY_ENABLED` compatibility switch. Per-server keys remain preferred.
- Restricted server agent config/package/key rotation routes to `module.agent_deployment`.
- Restricted application bulk package download to `module.agent_deployment`.
- Hid deployment buttons from users without `module.agent_deployment`.
- Added operator audit fields to `windows_service_commands`: `requested_by`, `request_ip`.
- Added production indexes for check results, IIS suspicious events, application-server mappings, and maintenance report status history.
- Added job retry/timeout/backoff settings to core checks and metric processing.
- Removed raw SEO scan response headers/body from session debug output.
- Added validation for Asset Intelligence scan input.
- Added a 93-day cap for synchronous Maintenance Report generation.
- Updated `.env.example` with safer defaults and required production variables.
- Updated README with production deployment, agent key, queue/scheduler, backup, and feature guidance.
- Added `docker-compose.prod.yml` with no source bind mount, non-root database user, named volumes, split queue workers, scheduler, MySQL service, nginx reverse proxy, and healthchecks.
- Added central `audit_events` table, `AuditEvent` model, and `AuditLogger` service.
- Added audit logging for agent config/package/key actions, manual profile overrides, DB monitor configuration/enabling, network monitor create/update/delete, security scans, service control, and report generation/download.
- Added SSRF protection through `config/security.php` and `OutboundScanGuard`, blocking localhost, private, link-local, multicast, and reserved IPs unless explicitly allowlisted.
- Applied outbound scan policy to SEO manual scans, SEO scan-all, queued SEO scans, DNS intelligence scans, internal crawls, and Asset Intelligence scans.
- Moved Maintenance Report PDF/Excel file generation to `GenerateMaintenanceReportJob` on the `reports` queue; HTML preview remains synchronous.
- Added report queue status fields: `queued_at`, `started_at`, `completed_at`, `failed_at`, and `error_message`.
- Added `monitoring:prune` retention cleanup command with dry-run support.
- Masked saved Telegram bot tokens and made blank token submission preserve the existing token.
- Fixed queue assignment boot fatal by using Laravel's `onQueue()` instead of conflicting `$queue` properties.
- Cleaned environment-file strategy: host `.env` no longer contains duplicate Docker DB settings, `.env.production.example` uses `DB_HOST=mysql`, and `.env.local.example` documents local-only database defaults.
- Standardized production container naming to `webmonitor_app`, `webmonitor_mysql8`, `webmonitor_nginx`, `webmonitor_scheduler`, and queue-specific `webmonitor_queue_*` containers.
- Isolated production Compose from the dev stack with project name `webmonitor-prod` and explicit `webmonitor_prod_*` named volumes.
- Excluded host-generated Laravel cache files from Docker builds and cleared bootstrap caches during image build so production containers do not inherit a local SQLite config cache.
- Added `scripts/production-smoke-test.sh` for Docker deployment verification.
- Added backup/restore runbook and pilot deployment checklist to README.

## Detailed Findings Table

| ID | Severity | Area | File/path | Issue | Risk | Recommended fix | Status |
|---|---|---|---|---|---|---|---|
| PR-001 | High | Deployment | `routes/web.php` | Closure routes prevented reliable `route:cache`. | Production cache step could fail or be skipped. | Replace closures with controller/redirect. | Fixed |
| PR-002 | High | Agent API | `app/Http/Controllers/Api/*` | IIS/network ingest lacked rate limits and payload caps. | Queue/storage abuse or oversized request pressure. | Add payload limits and per-server rate limits. | Fixed |
| PR-003 | High | Agent API | `app/Http/Controllers/Api/*` | Global fallback API key always accepted when configured. | A leaked shared key can authenticate any registered server. | Add compatibility flag and rotate to per-server keys. | Fixed with default-compatible flag; disable before production |
| PR-004 | High | Authorization | `routes/web.php` | Agent config/package/key routes used server metrics permission. | Non-deployment operators could rotate keys and download secrets. | Require `module.agent_deployment`. | Fixed |
| PR-005 | Medium | Audit | `windows_service_commands` | Service-control command had no requester/IP. | Weak forensics for privileged service actions. | Store operator and request IP. | Fixed |
| PR-006 | Medium | Database | migrations | Missing composite indexes for common history lookups. | Slow dashboards/reports as data grows. | Add production indexes. | Fixed |
| PR-007 | Medium | Reporting | `MaintenanceReportController` | Interactive reports could cover unbounded date ranges. | Slow request/worker pressure. | Cap synchronous period and queue larger reports. | Fixed cap; queueing recommended |
| PR-008 | Medium | Security Scans | `SeoSecurityController` | Manual scan stored raw headers/body in session. | Sensitive response fragments could leak in UI/session. | Remove raw debug payload. | Fixed |
| PR-009 | Medium | Validation | `AssetIntelligenceController`, `SeoSecurityController`, scan jobs | Scan targets were not guarded against private/reserved address resolution. | SSRF and internal network probing risk. | Validate targets and add deny-by-default private/reserved IP policy with allowlists. | Fixed |
| PR-010 | High | Docker | `docker-compose.prod.yml` | Production deployment needed no bind mount, non-root DB user, named volumes, split services, healthchecks. | Weak production isolation and secrets posture. | Create production compose profile. | Fixed |
| PR-011 | High | Queues | `docker-compose.prod.yml`, jobs | One worker handled all workload classes. | Heavy scans/reports can delay metric ingestion. | Separate workers and queue names. | Fixed |
| PR-012 | High | Audit | multiple controllers/services | No central audit log for DB setup, reports, security scans, network edits. | Incomplete forensic trail. | Add generic `audit_events` and helper service. | Fixed |
| PR-013 | Medium | Secrets | `admin/telegram-settings` | Telegram token could be displayed to admins after save. | Secret exposure in browser/history/screenshare. | Mask saved value; require re-entry to replace. | Fixed |
| PR-014 | Medium | TLS | monitoring fetches | `withoutVerifying()` is used in monitoring/security scans. | Fetch behavior can ignore TLS authenticity. | Make bypass explicit per monitor/scan, default verify where possible. | Recommended before production |
| PR-015 | Medium | Reports | `MaintenanceReportController`, `GenerateMaintenanceReportJob` | PDF/Excel generated during request. | Timeout on large tenants. | Queue exports and download when complete. | Fixed |
| PR-016 | Medium | Retention | metrics/log/check tables | No retention cleanup visible. | Database growth and report slowdown. | Add scheduled retention command with dry-run. | Fixed |
| PR-017 | Low | Agent | `server-monitor-agent/agent.js` | Uses `console.log` for operational logging. | Acceptable for service logs, but no log levels/rotation. | Add structured logger and rotation later. | Optional |
| PR-018 | Medium | Agent Update | `server-monitor-agent/installer/update-agent.ps1` | Update path assumes `./dist/server-monitor-agent-new.exe`. | Generated package update flow needs operator clarity. | Document `NewExePath` or generate package-specific update script. | Recommended before production |
| PR-019 | Medium | Permissions | routes/controllers | Monitor, SSL, SEO, asset routes are broadly authenticated or broad-module only. | Users may access more than intended. | Add granular module/action permissions. | Recommended before production |
| PR-020 | Low | Docs | README | Production deployment guidance was incomplete. | Operator mistakes. | Add production guidance. | Fixed |

## Fix Plan

Must fix before production:

- Disable legacy global agent key after rotating all agents.
- Enforce HTTPS, secure cookies, trusted proxy, and reverse proxy HSTS.
- Validate production secrets and backup/restore with the actual deployment environment.

Should fix before pilot:

- Confirm process supervision for every split worker and scheduler.
- Confirm production SSRF allowlist ownership and change-control.
- Validate restore procedure from DB/storage backups.
- Review broad legacy module permissions for large teams.

Can improve later:

- Structured logging in Windows agent.
- Package-specific `update-agent.ps1` generation.
- More granular permissions for every create/edit/delete action.
- UI pagination/filtering across all large tables.
- Dedicated healthcheck endpoints for each Docker service.

## Verification Run

Commands run:

```bash
vendor\bin\pest
vendor\bin\pest tests\Feature\MaintenanceReportTest.php tests\Feature\AssetIntelligenceTest.php tests\Feature\WebshellScannerTest.php
php artisan route:list --except-vendor
php artisan route:cache
php artisan config:cache
php artisan view:cache
php artisan list monitoring --raw
node --check server-monitor-agent\agent.js
docker compose -f docker-compose.prod.yml config
docker exec webmonitor_app php artisan migrate --force
docker exec webmonitor_app php artisan monitoring:prune --dry-run
```

Results:

- Full test suite: **141 passed, 589 assertions**.
- Focused production/security suite: **25 passed, 97 assertions**.
- `node --check server-monitor-agent\agent.js`: passed.
- `php artisan route:cache`: passed after closure route fix.
- `php artisan config:cache`: passed.
- `php artisan view:cache`: passed.
- `php artisan list monitoring --raw`: shows `monitoring:prune`.
- `docker compose -f docker-compose.prod.yml config`: passed with project name `webmonitor-prod`, standardized `webmonitor_*` container names, `DB_HOST=mysql`, explicit `webmonitor_prod_*` named volumes, and no application source bind mount.
- `node --check server-monitor-agent/agent.js`: passed during final cleanup verification.
- Host `.env` DB strategy check: local `DB_CONNECTION=sqlite` remains, with no duplicate `DB_HOST=db` or Docker DB host override. Local Docker dev may keep `DB_PASSWORD=password`; production must use `.env.production.example` values.
- Production image build exposed and fixed stale host Laravel cache leakage into Docker images. After `optimize:clear`, `webmonitor_app` reports `database.default=mysql`.
- Docker `migrate --force` using `webmonitor_app`: passed against the isolated production MySQL 8 container.
- Docker `monitoring:prune --dry-run` using `webmonitor_app`: passed after migrations completed.
- Docker production app cache commands also passed inside `webmonitor_app`: `route:cache`, `config:cache`, and `view:cache`.
- Existing dev app container was restored after production verification; production and dev containers now run side-by-side with separate container and volume names.
- Host `.env` no longer contains duplicate Docker DB host settings; production Artisan commands should run inside `webmonitor_app`.

## Backward Compatibility

- `/api/metrics` payload fields remain unchanged.
- Existing agents using `AGENT_API_KEY` still work while `AGENT_GLOBAL_API_KEY_ENABLED=true`.
- Per-server key generation remains preferred for new configs/packages.
- IIS logging remains disabled by default unless a profile enables it.
- Network agent checks remain disabled by default and only include explicitly configured checks.
- Database passwords remain encrypted with Laravel encrypted casts and are not rendered back into forms.
- Agent package layout remains root-level `server-monitor-agent.exe` and `config.json`.

## Production Readiness Checklist

| Area | Status | Notes |
|---|---|---|
| Authentication and authorization | Recommended Before Production | Core auth exists; agent deployment now privileged; add finer permissions. |
| Agent API security | Recommended Before Production | Rate limits/caps added; disable global fallback after rotation. |
| Package generation | Ready | Structure and installer path tested. |
| Config generation | Ready | Per-server keys, valid JSON, profile resolver defaults. |
| Database migrations | Ready | Applied in Docker; added indexes and audit columns. |
| Queue workers | Ready for pilot | Production compose splits metrics/checks/alerts/security/reports workers. |
| Scheduler | Ready for pilot | Scheduled commands exist; document process supervision. |
| Reports | Ready for pilot | HTML preview is synchronous; PDF/Excel exports are queued. |
| IIS logging | Ready for pilot | Disabled by default; health/status and tests exist. |
| Network monitoring | Ready for pilot | Explicit checks only; no range scanning in network monitor. |
| DB monitoring | Ready for pilot | Password encrypted; guided setup tested. |
| Audit logging | Ready for pilot | Central `audit_events` added for sensitive actions; expand event catalog as workflows mature. |
| Backups | Ready for pilot | Backup/restore runbook documented; validate with a real restore drill before broad production. |
| Docker deployment | Ready for pilot | `docker-compose.prod.yml` added with nginx/app/mysql/split workers/scheduler and standard names. |
| Documentation | Ready for pilot | README includes production deployment, smoke test, backup/restore, and pilot checklist. |
| Tests | Ready | Full suite passing: 141 tests, 589 assertions. |

## Deployment Checklist

Pre-deploy:

- Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`.
- Generate and persist `APP_KEY`.
- Configure strong DB credentials and backups.
- Run `composer install --no-dev --optimize-autoloader`.
- Run `npm ci && npm run build`.
- Set mail and alert channel credentials.
- Set `AGENT_GLOBAL_API_KEY_ENABLED=false` only after all agents use per-server keys.
- Confirm `QUEUE_CONNECTION=database` or production queue backend.
- Confirm `SECURITY_BLOCK_PRIVATE_SCAN_IPS=true` and only approved scan allowlists are configured.

Deploy:

- `docker compose -f docker-compose.prod.yml up -d --build`
- `docker exec webmonitor_app php artisan migrate --force`
- `docker exec webmonitor_app php artisan db:seed --force`
- `docker exec webmonitor_app php artisan config:cache`
- `docker exec webmonitor_app php artisan route:cache`
- `docker exec webmonitor_app php artisan view:cache`
- Start web, worker, scheduler, and Reverb if realtime is required.

Post-deploy smoke:

- Login as Super Admin.
- Open dashboard, agents, server inventory, IIS logs, network monitors, reports.
- Generate one test agent config for a non-production server.
- Run one website check, one database test, one network central check.
- Confirm queue worker processes jobs.
- Confirm storage writes reports and logs.
- Generate one PDF/Excel report and confirm it moves from pending to completed.
- Run `sh scripts/production-smoke-test.sh`.
- Run `docker exec webmonitor_app php artisan monitoring:prune --dry-run`.

## Rollback Checklist

- Put app in maintenance mode if user-facing behavior is broken.
- Restore previous container/image or Git release.
- Restore database backup if migrations must be rolled back.
- Run `php artisan migrate:rollback --step=N` only if data impact is understood.
- Run `php artisan optimize:clear`.
- If agent keys were rotated, re-download configs/packages or restore `agent_api_key_hash` from backup.
- Confirm `/api/metrics` ingestion resumes.
- Confirm worker and scheduler are running.

## Final Deployment Cleanup Notes

- Production Compose now uses stable service/container names: `webmonitor_nginx`, `webmonitor_app`, `webmonitor_mysql8`, `webmonitor_scheduler`, `webmonitor_queue_metrics`, `webmonitor_queue_checks`, `webmonitor_queue_alerts`, `webmonitor_queue_security`, and `webmonitor_queue_reports`.
- Production Compose uses project name `webmonitor-prod` and explicit volume names: `webmonitor_prod_mysql_data`, `webmonitor_prod_storage_app`, `webmonitor_prod_storage_logs`, `webmonitor_prod_storage_framework`, and `webmonitor_prod_bootstrap_cache`.
- The production nginx config is baked into `webmonitor-nginx:prod` via `docker/nginx/Dockerfile`, avoiding a source-tree bind mount.
- `.dockerignore` and `Dockerfile` now prevent host `bootstrap/cache/*.php` files from being baked into Docker images.
- Production DB host strategy is explicit: `.env.production.example` uses `DB_HOST=mysql`, while `.env.local.example` uses local-friendly defaults and documents `DB_HOST=127.0.0.1` for host MySQL.
- The host `.env` has been cleaned of duplicate Docker DB host settings. Run production Artisan commands inside Docker with `docker exec webmonitor_app ...`.
- `scripts/production-smoke-test.sh` checks containers, cache commands, HTTP health, queue processes, scheduler availability, and `monitoring:prune --dry-run`.
- README now includes backup/restore commands for MySQL and Laravel storage volumes plus a pilot checklist covering production env flags, HTTPS cookies, per-server agent keys, queue/scheduler state, backup testing, and audit-log verification.

## Remaining Risk Notes

- The Docker compose file remains convenient for development/pilot but is not a hardened production artifact.
- Security scan features now block private/reserved targets by default, but allowlist ownership needs operational governance.
- Monitoring retention exists as a command; schedule it only after retention windows are approved.
- Report export queueing is implemented; monitor the `reports` queue and failed jobs in production.
