## Environment configuration

This app is a Laravel + Inertia (Vue) platform.

### Safe rule
- **Never commit** your real `.env` file.
- Use `.env.example` as the template.

### Enterprise-ready defaults (recommended)
These support horizontal scaling (multiple app servers) and non-blocking background work:

- **Cache**: `CACHE_DRIVER=redis`
- **Queues**: `QUEUE_CONNECTION=redis`
- **Sessions**: `SESSION_DRIVER=redis`

### Redis database separation
`config/database.php` already supports separate Redis databases:

- `REDIS_DB=0` (default connection; queues/general)
- `REDIS_CACHE_DB=1` (cache connection)

Related variables:
- `REDIS_CLIENT=phpredis`
- `REDIS_HOST`, `REDIS_PORT`
- `REDIS_PASSWORD` (**set a strong password in production**)
- `REDIS_QUEUE=default`

### Production minimums
Set these in production:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` (generated with `php artisan key:generate`)
- `LOG_LEVEL=error` (or `warning`)
- `SESSION_SECURE_COOKIE=true` (when HTTPS is enabled)

