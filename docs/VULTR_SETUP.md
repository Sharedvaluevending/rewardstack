## Vultr deployment (recommended baseline)

This is a **staging-first** Vultr setup guide for RewardStack.

### Recommended architecture (simple and scalable)
- **VPS 1 (App)**: Nginx + PHP-FPM + Supervisor (queue workers) + cron (scheduler)
- **VPS 2 (DB)** (optional early): MySQL managed or separate VM
- **Redis**: on same VPS initially, move to managed/replicated later

### 0) Create a staging server first
- Clone production size (or smaller)
- Use a separate domain: `staging.yourdomain.com`
- Use a separate database and Stripe test keys

### 1) OS packages
Install the baseline services:
- Nginx
- PHP-FPM (8.1+)
- Composer 2
- Node 18+ (Node 20 OK)
- MySQL 8
- Redis
- Supervisor
- Certbot (Let’s Encrypt)

### 2) App layout (release-based deploy recommended)
Use the atomic release layout:

- Base: `/var/www/rewardstack-app`
- Symlink: `/var/www/rewardstack-app/current`
- Releases: `/var/www/rewardstack-app/releases/*`
- Shared: `/var/www/rewardstack-app/shared/.env` and `/var/www/rewardstack-app/shared/storage`

See: `docs/RELEASE_DEPLOY.md`

### 3) Environment hardening (production)
Minimum:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `CACHE_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `SESSION_DRIVER=redis`
- Set strong DB + Redis passwords
- Set Stripe/Printful/OpenAI keys

Optional (recommended):
- Set `STRIPE_WEBHOOK_TOKEN` and `PRINTFUL_WEBHOOK_TOKEN` and send `X-Webhook-Token` header

### 4) Nginx
Use the template:
- `ops/nginx/rewardstack.conf`

Copy to something like:
- `/etc/nginx/sites-available/rewardstack.conf` and symlink into `sites-enabled/`

### 5) PHP-FPM
Ensure PHP-FPM pool runs as your web user (commonly `www-data`).

### 6) Queue workers + scheduler
Use Supervisor templates:
- `ops/supervisor/rewardstack-worker.conf`
- `ops/supervisor/rewardstack-scheduler.conf`

### 7) Cron (if not using scheduler supervisor)
If you prefer cron:
- `ops/cron/rewardstack-cron`

### 8) Log rotation
Use:
- `ops/logrotate/rewardstack`

### 9) Backups
- Use `scripts/backup-database.sh` (no hardcoded creds)
- Add a cron entry
- Push backups off-server (S3/object storage) and test restores

### 10) Health checks
- Configure Vultr load balancer / uptime check to hit:
  - `/health`

