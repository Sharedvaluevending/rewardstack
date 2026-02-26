## Security hardening notes

### Immediate wins already applied
- Debug logger endpoint `/api/debug/wordsearch` is now **disabled by default**.
  - Enable explicitly only when needed: `WORDSEARCH_DEBUG_LOGGER_ENABLED=true`

### Recommended production settings
- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_DRIVER=redis`
- `CACHE_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- Set strong secrets for DB/Redis/API keys

### Webhooks
- Stripe webhook signature verification is supported when `STRIPE_WEBHOOK_SECRET` is set.

### Backups
- `scripts/backup-database.sh` no longer contains hardcoded credentials.

