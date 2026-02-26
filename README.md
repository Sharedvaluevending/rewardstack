## RewardStack

Laravel + Inertia (Vue) platform.

### Docs
- `docs/TECHNICAL_README.md` (how to run, deploy, and operate)
- `docs/ENVIRONMENT.md` (env variables + Redis defaults)
- `docs/PRODUCTION_CHECKLIST.md` (production readiness checklist)
- `docs/SECURITY_HEADERS.md` (what headers we set and why)
- `docs/CI_CD.md` (CI, security scans, deploy secrets)

### Local quick start

```bash
cd /var/www/rewardstack
cp .env.example .env
php artisan key:generate
composer install
npm install
php artisan migrate
npm run build
php artisan serve
```

### Env sanity check

```bash
cd /var/www/rewardstack
./scripts/check-env.sh .env
```
