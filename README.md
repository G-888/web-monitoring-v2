# Web Monitor

A self-hosted distributed server monitoring system with a central Laravel dashboard and lightweight remote agents.

## What This Project Includes

- Laravel backend dashboard
- Real-time metrics ingestion API
- Remote monitoring agent
- Database queue processing
- WebSocket broadcasting via Laravel Reverb
- Tailwind + Vite frontend with dark-mode analytics
- SSL expiry monitor dashboard with daily certificate refresh
- SSL multi-converter for PEM, DER, and PFX formats
- Server log scanner with ripgrep-powered log analysis
- Webshell detection for allowed local web roots

## Docker Deployment

Running with Docker is the easiest way to get started.

### 1. Get The Files

```bash
git clone https://github.com/suhailgoapps-stack/web-monitoring.git
cd web-monitoring
```

### 2. Configure Environment

```bash
cp .env.local.example .env
```

Edit `.env` and set `APP_KEY`, database credentials, mail credentials, and alert channel credentials.

Environment file strategy:

- Use `.env.local.example` for host/local development. It defaults to SQLite and documents `DB_HOST=127.0.0.1` for local MySQL.
- Use `.env.production.example` for Docker production. It uses `DB_HOST=mysql`, matching the production Compose service name.
- Keep host-local `.env` free of Docker-only duplicate DB settings such as `DB_HOST=db`; production Artisan commands should run inside Docker.

For production:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set `APP_URL` to the HTTPS URL users and agents will use.
- Set `SESSION_SECURE_COOKIE=true` when serving over HTTPS.
- Use strong, unique database credentials instead of the compose defaults.
- Prefer generated per-server agent keys. Keep `AGENT_API_KEY` only for legacy agents.
- After all legacy agents are rotated, set `AGENT_GLOBAL_API_KEY_ENABLED=false`.
- Keep `AGENT_IIS_LOGS_ENABLED=false` and `AGENT_NETWORK_CHECKS_ENABLED=false` unless a generated server profile enables them.
- Keep outbound scan protection enabled with `SECURITY_BLOCK_PRIVATE_SCAN_IPS=true`; only allow trusted internal targets through `SECURITY_ALLOWED_SCAN_DOMAINS` or `SECURITY_ALLOWED_SCAN_CIDRS`.
- Keep `APP_KEY` stable. Encrypted database monitor passwords and Telegram tokens depend on it.
- Terminate TLS at a reverse proxy, forward `X-Forwarded-Proto=https`, and enable HSTS at the reverse proxy.

### 3. Launch With Docker Compose

Development:

```bash
docker compose up -d --build
```

Production:

```bash
cp .env.production.example .env
docker compose -f docker-compose.prod.yml up -d --build
```

The production compose file does not bind-mount source code, uses named volumes, avoids world-writable permissions, and separates queue workers by workload. It also sets the Compose project name to `webmonitor-prod` and uses explicit `webmonitor_prod_*` volume names so a pilot deployment does not collide with the existing local/dev stack.

Production container names:

- `webmonitor_nginx`
- `webmonitor_app`
- `webmonitor_mysql8`
- `webmonitor_queue_metrics`
- `webmonitor_queue_checks`
- `webmonitor_queue_alerts`
- `webmonitor_queue_security`
- `webmonitor_queue_reports`
- `webmonitor_scheduler`

### 4. Initialize The App

```bash
docker compose exec app bash docker-init.sh
```

## Local Development

Install backend and frontend dependencies:

```bash
composer install
npm install
```

Copy the example environment file and generate an app key:

```bash
copy .env.example .env
php artisan key:generate
```

Run migrations and build assets:

```bash
php artisan migrate
npm run build
```

Start the services in separate terminals:

```bash
php artisan serve
php artisan reverb:start
php artisan queue:work
```

Then open `http://127.0.0.1:8000`.

## Dashboard Features

- `/dashboard` - Main user dashboard with SSL expiry tracking.
- `/server-resources` - Real-time server resource monitoring.
- `/servers` - Server inventory with groups/tags, heartbeat status, thresholds, agent setup snippets, and Windows service monitoring.
- `/servers/windows-services` - Windows service details and control commands.
- `/database-monitors` - Database connection checks with response-time history and failure alerts.
- `/log-inspections` - AI-powered log inspection.
- `/ssl-monitors` - Dedicated SSL expiry dashboard for HTTPS monitors.
- `/seo-security` - SEO poisoning, file integrity, and webshell detection.
- `/ssl-conversion` - Multi-format SSL certificate converter.

## Server Agent Notes

Server metrics are accepted only for active inventory rows whose `server_id` matches the agent config. New generated configs use per-server API keys and store only a SHA-256 hash in the database. `AGENT_API_KEY` is a legacy fallback only; disable it with `AGENT_GLOBAL_API_KEY_ENABLED=false` after existing agents are rotated.

Windows service Start/Stop/Restart is intentionally split from normal metrics access. Users need both `module.server_metrics` to view server inventory/resources and `module.service_control` to queue service control commands.

Database monitoring uses `module.database_monitoring`. Stored database passwords are encrypted with Laravel's application key, so keep `APP_KEY` stable after configuring monitors. The Docker image includes MySQL/MariaDB and PostgreSQL PDO drivers.

Agent deployment package downloads and key rotation require `module.agent_deployment`. Generated packages contain `server-monitor-agent.exe`, `config.json`, service management scripts, and a README. Downloading a config or package rotates that server's per-server key, so deploy the new config before restarting the agent.

When rebuilding the Windows agent manually outside generated packages, stop the running executable before replacement:

```powershell
# Administrator PowerShell
Stop-Process -Name server-monitor-agent -Force
Copy-Item D:\server-monitor-agent\dist\server-monitor-agent-new.exe D:\server-monitor-agent\dist\server-monitor-agent.exe -Force
D:\server-monitor-agent\start-agent-task.ps1
```

## Production Operations

Run these inside the Docker app container after deployment:

```bash
docker exec webmonitor_app php artisan migrate --force
docker exec webmonitor_app php artisan db:seed --force
docker exec webmonitor_app php artisan config:cache
docker exec webmonitor_app php artisan route:cache
docker exec webmonitor_app php artisan view:cache
```

Required long-running services:

- `queue-metrics`: `php artisan queue:work database --queue=metrics --sleep=1 --tries=3 --timeout=60`
- `queue-checks`: `php artisan queue:work database --queue=checks --sleep=3 --tries=3 --timeout=120`
- `queue-alerts`: `php artisan queue:work database --queue=alerts --sleep=3 --tries=3 --timeout=90`
- `queue-security`: `php artisan queue:work database --queue=security --sleep=5 --tries=2 --timeout=300`
- `queue-reports`: `php artisan queue:work database --queue=reports --sleep=5 --tries=2 --timeout=300`
- `scheduler`: run `php artisan schedule:work` as a long-running process, or run `php artisan schedule:run` every minute from cron
- `reverb`: only required for realtime dashboard broadcasting

Run the production smoke test after each deployment:

```bash
sh scripts/production-smoke-test.sh
```

### Backup And Restore Runbook

Recommended backup scope:

- MySQL dump from `webmonitor_mysql8`
- `.env`
- Laravel storage volumes mounted in `webmonitor_app`
- generated agent package/audit records in the database

Create a database backup:

```bash
docker exec webmonitor_mysql8 sh -lc 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > webmonitor.sql
```

Create a storage backup:

```bash
docker run --rm --volumes-from webmonitor_app -v "$PWD:/backup" alpine \
  tar czf /backup/webmonitor-storage.tgz -C /var/www/html/storage app logs framework
```

Restore into a clean environment:

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker exec -i webmonitor_mysql8 sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < webmonitor.sql
docker run --rm --volumes-from webmonitor_app -v "$PWD:/backup" alpine \
  sh -lc "cd /var/www/html/storage && tar xzf /backup/webmonitor-storage.tgz"
docker exec webmonitor_app php artisan migrate --force
docker exec webmonitor_app php artisan optimize:clear
```

Validation after restore:

```bash
docker exec webmonitor_app php artisan migrate:status
docker exec webmonitor_app php artisan route:cache
docker exec webmonitor_app php artisan config:cache
docker exec webmonitor_app php artisan view:cache
sh scripts/production-smoke-test.sh
```

Retention pruning:

```bash
docker exec webmonitor_app php artisan monitoring:prune --dry-run
docker exec webmonitor_app php artisan monitoring:prune
```

### Pilot Deployment Checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- HTTPS is active at the reverse proxy.
- `SESSION_SECURE_COOKIE=true`
- Per-server agent keys are used for new packages/configs.
- `AGENT_GLOBAL_API_KEY_ENABLED=false` after legacy agents are migrated.
- `webmonitor_queue_metrics`, `webmonitor_queue_checks`, `webmonitor_queue_alerts`, `webmonitor_queue_security`, and `webmonitor_queue_reports` are running.
- `webmonitor_scheduler` is running.
- Backup and restore have been tested.
- `audit_events` receives entries for key rotation, package download, DB setup, report generation, service control, and security scans.

## Feature Guides

- Client onboarding starts at `/client-architecture/setup`.
- Architecture review is available from `/applications/{application}/architecture-review`.
- Database placeholders are completed through Database Monitor Guided Setup.
- IIS Log Monitoring is disabled by default and enabled by generated app-server profiles.
- Network Monitoring checks only configured monitors and expected port baselines; it does not perform port range scans.
- Maintenance Reports are available under the sidebar Reports section and are limited to 93 days for interactive generation.

## Useful Docker Commands

- Stop production: `docker compose -f docker-compose.prod.yml down`
- View app logs: `docker logs -f webmonitor_app`
- View worker logs: `docker logs -f webmonitor_queue_metrics`
- Clear application cache: `docker exec webmonitor_app php artisan optimize:clear`
- Clear historical failed jobs: `docker exec webmonitor_app php artisan queue:flush`

## License

MIT
