## Release-based deploys (atomic + rollback)

This pattern keeps multiple versioned releases and swaps a `current` symlink.
It enables **instant rollback**.

### Layout
Example base path: `/var/www/rewardstack-app`

```
/var/www/rewardstack-app/
  current -> /var/www/rewardstack-app/releases/20251222_120000
  releases/
    20251222_120000/
    20251222_115500/
  shared/
    .env
    storage/
```

### One-time setup
- Create base folders: `releases/` and `shared/`
- Put your production `.env` in `shared/.env`
- Ensure `shared/storage` exists and is writable by the web user

### Deploy
Run on the server:

```bash
export RELEASE_BASE=/var/www/rewardstack-app
export REPO_DIR=/var/www/rewardstack   # your git repo checkout location (optional)
./scripts/release-deploy.sh
```

### Rollback
Instant rollback to the previous release:

```bash
export RELEASE_BASE=/var/www/rewardstack-app
./scripts/release-rollback.sh
```

### Notes
- This repo includes `scripts/deploy.sh` (in-place deploy) and the release scripts.
- Use release deploys for enterprise stability.
