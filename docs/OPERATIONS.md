## Operations / scaling runbook

### Health checks
- `GET /health` (web)
- `GET /api/health` (api)

Optional deep checks (disabled by default):
- `HEALTHCHECK_DB=true` to run a `SELECT 1`
- `HEALTHCHECK_REDIS=true` to run a Redis `PING`

### Queues
- Preferred: `QUEUE_CONNECTION=redis`
- Run workers (Supervisor or container):
  - `php artisan queue:work redis --sleep=1 --tries=3 --max-time=3600`

Scaling guidance:
- Add more worker processes first (horizontal)
- Split queues by priority (high/default/low)

Queue priorities:
- high: webhooks/payments/critical
- default: normal
- low: analytics/reporting

Monitor:
- ./scripts/queue-status.sh

### Cache
- Preferred: `CACHE_DRIVER=redis`
- For cache-heavy pages use short TTL caching + invalidation.

### Sessions
- Preferred: `SESSION_DRIVER=redis` (required for multi-server)

### Deploy checklist (high-level)
- `php artisan migrate --force`
- `php artisan config:cache`
- `php artisan route:cache`
- Restart workers

### Logs
- Default logs: `storage/logs/laravel.log`
- Recommend `LOG_CHANNEL=daily` in production.

### Request IDs
- Every response includes `X-Request-Id` (generated if missing).
- This value is also added to the log context to correlate requests with errors.


### Release identification
- Set `APP_VERSION` to your git SHA or release id.
- `/health` will return it so you can verify what is deployed.
