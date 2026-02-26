# Production Readiness Status (Snapshot)

Last updated: 2026-02-05  
Scope: Single Vultr server, live app at `https://revenueqr.com`  
Notes: This is a **point-in-time** checklist based on server-side verification and repo config.

---

## ✅ Confirmed OK (live)
- **Production env**: `APP_ENV=production`, `APP_DEBUG=false`, secure cookies enabled
- **Redis**: running and used for cache/queue/session
- **Queue workers**: high/default/low queues running
- **Scheduler**: running once (supervisor loop)
- **HTTPS + HSTS**: enabled on `revenueqr.com`
- **Health checks**: `/health` returns `ok` with DB + Redis checks passing
- **Local backups**: daily DB backups present in `storage/backups` with `.sha256`
- **Monitoring**: Sentry DSN is set
- **Email**: SendGrid API key is set
- **In-app scanning**: camera permission allowed via `Permissions-Policy`

---

## ⚠️ Not in place / not verified
- **CDN**: not enabled (DNS points directly to server; Apache headers)
- **Staging environment**: not found on this server (Docker configs exist only)
- **Rollback deploys**: not configured (no release `current` symlink)
- **Offsite backups**: not configured (local only)
- **DB performance monitoring**: not configured (no slow-query/APM verification)
- **CORS hardening**: still wide-open (`*`)

---

## Deferred (per owner request)
- Offsite backups (later)

---

## Next Steps (if/when desired)
- Add CDN (e.g., Cloudflare) for global caching of static assets
- Create a staging environment (separate host or Docker-based)
- Enable release-based deploys for instant rollback
- Add DB performance monitoring (slow query log / APM)
- Restrict CORS to approved origins if needed

---

## Verification Notes
- Backups confirmed via `/var/log/db-backup.log` and files in `storage/backups`
- Health verified via `https://revenueqr.com/health`
- HTTPS headers verified via `curl -I https://revenueqr.com`
