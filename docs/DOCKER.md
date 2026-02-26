## Docker (local/staging)

This repo includes a Docker setup that runs:
- Laravel PHP-FPM (`app`)
- Nginx (`nginx`)
- MySQL 8 (`db`)
- Redis (`redis`)
- Queue worker (`worker`)
- Scheduler loop (`scheduler`)

### Start

```bash
cd /var/www/rewardstack
docker compose up -d --build
```

App will be available at:
- `http://localhost:8080`

### First-time setup

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### Logs

```bash
docker compose logs -f --tail=200
```

### Stop

```bash
docker compose down
```

### Notes
- This Docker setup is **safe to add** to the repo; it won’t affect production unless you deploy using it.
- For production you’d typically bake `.env` via secrets and set `APP_ENV=production`, `APP_DEBUG=false`.
