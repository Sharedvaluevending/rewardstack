## Production checklist

### App
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set (`php artisan key:generate`)
- [ ] HTTPS enabled

### Cache / Queues / Sessions (scaling requirements)
- [ ] `CACHE_DRIVER=redis`
- [ ] `QUEUE_CONNECTION=redis`
- [ ] `SESSION_DRIVER=redis`
- [ ] Redis secured (password, network rules)

### Database
- [ ] Backups configured and tested
- [ ] Migrations run with `--force`

### Workers / Scheduler
- [ ] Supervisor workers running (queue:work redis)
- [ ] Scheduler running (`php artisan schedule:run` via cron)

### Observability
- [ ] Error monitoring configured (Sentry if used)
- [ ] Logs rotating (recommend `LOG_CHANNEL=daily`)

