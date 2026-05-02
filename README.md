# Web Monitor

A self-hosted distributed server monitoring system with a central Laravel dashboard and lightweight remote agents.

## What this project includes

- Laravel backend dashboard
- Real-time metrics ingestion API
- Remote monitoring agent
- Redis queue processing
- WebSocket broadcasting via Laravel Reverb
- Tailwind + Vite frontend with dark-mode analytics
- **SSL Multi-Converter**: Convert between PEM, DER, and PFX formats.
- **Server Log Scanner**: Built-in ripgrep-powered log analysis.

---

## 🐳 Docker Deployment (Recommended)

Running with Docker is the easiest way to get started.

### 1. Get the files
Clone the repository to your server:
```bash
git clone https://github.com/suhailgoapps-stack/web-monitoring.git
cd web-monitoring
```

### 2. Configure Environment
```bash
cp .env.example .env
```
> [!IMPORTANT]
> Edit the `.env` file and set your `APP_KEY`, `DB_PASSWORD`, and any AI/Mail credentials.

### 3. Launch with Docker Compose
```bash
docker-compose up -d --build
```

### 4. Initialize the App
```bash
docker-compose exec app bash docker-init.sh
```

---

## 🛠️ Local Development Setup

### 1. Install backend dependencies
From the project root:
```bash
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
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 3. Build frontend assets
This project uses Vite for CSS and JavaScript. To build static assets once:
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

### 5. Start the Services
Open separate terminal windows and run:
- **Web Server**: `php artisan serve`
- **WebSocket**: `php artisan reverb:start`
- **Queue Worker**: `php artisan queue:work`

Then open: `http://127.0.0.1:8000`

---

## 📈 Dashboard Features

- `/dashboard` — Main user dashboard with SSL expiry tracking.
- `/server-resources` — Real-time server monitoring.
- `/log-inspections` — AI-powered log inspection.
- `/ssl-conversion` — Multi-format SSL certificate converter.

## 4. Useful Docker Commands

- **Stop the app**: `docker-compose down`
- **View logs**: `docker-compose logs -f app`
- **Recompile assets (CSS/JS)**: `docker-compose exec app npm run build`
- **Clear application cache**: `docker-compose exec app php artisan optimize:clear`

## License

MIT
