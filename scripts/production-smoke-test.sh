#!/usr/bin/env sh
set -eu

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
BASE_URL="${BASE_URL:-http://localhost:${APP_PORT:-8000}}"

require_running() {
    name="$1"
    running="$(docker inspect -f '{{.State.Running}}' "$name" 2>/dev/null || true)"
    if [ "$running" != "true" ]; then
        echo "FAILED: container $name is not running"
        exit 1
    fi
    echo "OK: $name running"
}

echo "Checking production containers..."
for container in \
    webmonitor_nginx \
    webmonitor_app \
    webmonitor_mysql8 \
    webmonitor_queue_metrics \
    webmonitor_queue_checks \
    webmonitor_queue_alerts \
    webmonitor_queue_security \
    webmonitor_queue_reports \
    webmonitor_scheduler
do
    require_running "$container"
done

echo "Checking Laravel maintenance commands..."
docker exec webmonitor_app php artisan migrate:status
docker exec webmonitor_app php artisan route:cache
docker exec webmonitor_app php artisan config:cache
docker exec webmonitor_app php artisan view:cache

echo "Checking HTTP health..."
if command -v curl >/dev/null 2>&1; then
    curl -fsS "$BASE_URL/up" >/dev/null || curl -fsS "$BASE_URL/status" >/dev/null
else
    wget -qO- "$BASE_URL/up" >/dev/null || wget -qO- "$BASE_URL/status" >/dev/null
fi
echo "OK: HTTP health endpoint responded"

echo "Checking queue workers..."
docker exec webmonitor_queue_metrics sh -lc "ps aux | grep '[q]ueue:work database --queue=metrics' >/dev/null"
docker exec webmonitor_queue_checks sh -lc "ps aux | grep '[q]ueue:work database --queue=checks' >/dev/null"
docker exec webmonitor_queue_alerts sh -lc "ps aux | grep '[q]ueue:work database --queue=alerts' >/dev/null"
docker exec webmonitor_queue_security sh -lc "ps aux | grep '[q]ueue:work database --queue=security' >/dev/null"
docker exec webmonitor_queue_reports sh -lc "ps aux | grep '[q]ueue:work database --queue=reports' >/dev/null"
docker exec webmonitor_scheduler sh -lc "php artisan schedule:list >/dev/null"

echo "Checking retention dry run..."
docker exec webmonitor_app php artisan monitoring:prune --dry-run

echo "Production smoke test passed."
