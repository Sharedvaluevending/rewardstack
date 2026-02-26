## Deployment

This project supports both traditional (server) deploys and Docker deploys.

### Golden rules
- Deploy to **staging** first.
- Run a **health check** after deploy (`/health`).
- Keep the previous release available for rollback.

---

## Option A: Traditional server deploy (recommended first)

### One-time server setup
- PHP 8.1+ (your Dockerfile uses 8.3), Composer 2, Node 18+ (Node 20 ok)
- Web server (Nginx) + PHP-FPM
- MySQL
- Redis (recommended for cache/session/queue)
- Supervisor (recommended for queue workers)

### Deploy script
We include a non-destructive deploy helper:

- `scripts/deploy.sh`

It does:
- `composer install` (prod flags)
- `npm ci && npm run build`
- `php artisan migrate --force`
- caches: `config:cache`, `route:cache`, `view:cache`
- restarts queue workers (Supervisor if present)
- optional service reloads
- health check to `/health`

Run it from your release directory:

```bash
cd /var/www/rewardstack
./scripts/deploy.sh
```

### Rollback
Keep the previous release directory. Roll back by:
- switching the symlink back
- reloading PHP-FPM
- restarting workers

---

## Option B: Docker deploy

Use:
- `docker-compose.prod.yml`

Bring up:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Then:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

---

## Health checks
- `GET /health`
- `GET /api/health`

Enable deep checks only when safe:
- `HEALTHCHECK_DB=true`
- `HEALTHCHECK_REDIS=true`


---

## Option A2: Release-based deploys (recommended for enterprise)

See: `docs/RELEASE_DEPLOY.md`
