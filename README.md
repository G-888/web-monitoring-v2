# Web Monitor

A self-hosted distributed server monitoring system with a central Laravel dashboard and lightweight remote agents.

## What This Project Includes

- Laravel backend dashboard
- Real-time metrics ingestion API
- Remote monitoring agent
- Database queue processing
- WebSocket broadcasting via Laravel Reverb
- Tailwind + Vite frontend with dark-mode analytics
- SSL multi-converter for PEM, DER, and PFX formats
- Server log scanner with ripgrep-powered log analysis

## Docker Deployment

Running with Docker is the easiest way to get started.

### 1. Get The Files

```bash
git clone https://github.com/suhailgoapps-stack/web-monitoring.git
cd web-monitoring
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` and set `APP_KEY`, `DB_PASSWORD`, `AGENT_API_KEY`, and any mail/AI credentials.

### 3. Launch With Docker Compose

```bash
docker compose up -d --build
```

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
- `/servers` - Server inventory, heartbeat status, thresholds, and Windows service monitoring.
- `/servers/windows-services` - Windows service details and control commands.
- `/database-monitors` - Database connection checks with response-time history and failure alerts.
- `/log-inspections` - AI-powered log inspection.
- `/ssl-conversion` - Multi-format SSL certificate converter.

## Server Agent Notes

Server metrics are accepted only for active inventory rows whose `server_id` matches the agent config. The API uses `AGENT_API_KEY` from `.env`; keep it synchronized with the agent `apiKey`.

Windows service Start/Stop/Restart is intentionally split from normal metrics access. Users need both `module.server_metrics` to view server inventory/resources and `module.service_control` to queue service control commands.

Database monitoring uses `module.database_monitoring`. Stored database passwords are encrypted with Laravel's application key, so keep `APP_KEY` stable after configuring monitors.

When rebuilding the Windows agent, stop the running executable before replacement:

```powershell
# Administrator PowerShell
Stop-Process -Name server-monitor-agent -Force
Copy-Item D:\server-monitor-agent\dist\server-monitor-agent-new.exe D:\server-monitor-agent\dist\server-monitor-agent.exe -Force
D:\server-monitor-agent\start-agent-task.ps1
```

## Useful Docker Commands

- Stop the app: `docker compose down`
- View logs: `docker compose logs -f app`
- Recompile assets: `docker compose exec app npm run build`
- Clear application cache: `docker compose exec app php artisan optimize:clear`
- Clear historical failed jobs: `docker compose exec app php artisan queue:flush`

## License

MIT
