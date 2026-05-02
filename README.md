# Web Monitor

A self-hosted distributed server monitoring system with a central Laravel dashboard and lightweight remote agents.

## What this project includes

- Laravel backend dashboard
- Real-time metrics ingestion API
- Remote monitoring agent
- Redis queue processing
- WebSocket broadcasting via Laravel Reverb
- Tailwind + Vite frontend with dark-mode analytics

## Local setup

### 1. Install backend dependencies

From the project root:

```bash
cd c:\Users\User\web-monitor
composer install
npm install
```

### 2. Configure environment

Copy the example environment file and generate an app key:

```bash
copy .env.example .env
php artisan key:generate
```

Update `.env` with your database and queue settings. At minimum, set:

```env
APP_NAME=Web Monitor
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your-reverb-app-id
REVERB_APP_KEY=your-reverb-app-key
REVERB_APP_SECRET=your-reverb-app-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 3. Build frontend assets

This project uses Vite for CSS and JavaScript.

To build static assets once:

```bash
npm run build
```

For development with live reload:

```bash
npm run dev
```

### 4. Run database migrations

```bash
php artisan migrate
```

### 5. Start the Laravel app

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Then open:

- `http://127.0.0.1:8000`

### 6. Optional services

If you need background queue processing:

```bash
php artisan queue:work
```

If you need Reverb WebSocket broadcasting:

```bash
php artisan reverb:start
```

## Troubleshooting UI issues

If the UI is broken, the most common fix is rebuilding Vite assets:

```bash
npm install
npm run build
```

If you are developing locally and want hot reload:

```bash
npm run dev
php artisan serve
```

## Agent setup

The remote agent lives outside this repo in the agent project.

1. Install dependencies:

```bash
cd ../server-monitor-agent
npm install
```

2. Configure `config.json` with your backend URL and API key.

3. Start the agent:

```bash
npm start
```

## API endpoint

### POST /api/metrics

Headers:

```http
X-API-Key: agent-key-123
Content-Type: application/json
```

Payload example:

```json
{
  "server_id": "server-123",
  "cpu": 45.2,
  "ram_used": 3.2,
  "ram_total": 8,
  "disk_used": 120,
  "disk_total": 256,
  "timestamp": "2026-05-01T10:00:00Z"
}
```

Response:

```json
{ "status": "accepted" }
```

## Dashboard access

- `/dashboard` — main user dashboard
- `/server-resources` — real-time server monitoring
- `/log-inspections` — upload logs and run inspection

## Quick start summary

1. `composer install`
2. `npm install`
3. `copy .env.example .env`
4. `php artisan key:generate`
5. `npm run build`
6. `php artisan migrate`
7. `php artisan serve --host=127.0.0.1 --port=8000`

## License

MIT
