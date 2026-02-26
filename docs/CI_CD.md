## CI/CD

### CI
- Workflow: `.github/workflows/ci.yml`
- Runs on PRs and pushes to `main/master`
- Builds assets and runs `php artisan test`

### Security
- Workflow: `.github/workflows/security.yml` (CodeQL + dependency audits)
- Workflow: `.github/workflows/secrets.yml` (Gitleaks secrets scan)
- Dependabot: `.github/dependabot.yml`

### Deploy
Workflow: `.github/workflows/deploy.yml`

This workflow is **guarded**:
- It will only attempt a deploy if required GitHub Secrets exist.
- Use GitHub **Environments** to require manual approvals for `production`.

#### Required secrets (staging)
- `STAGING_HOST` (e.g. `staging.example.com`)
- `STAGING_PORT` (optional, default 22)
- `STAGING_USER` (e.g. `deploy`)
- `STAGING_SSH_KEY` (private key contents)
- `STAGING_PATH` (e.g. `/var/www/rewardstack`)
- `STAGING_URL` (e.g. `https://staging.example.com`)

#### Required secrets (production)
- `PROD_HOST`
- `PROD_PORT` (optional)
- `PROD_USER`
- `PROD_SSH_KEY`
- `PROD_PATH`
- `PROD_URL`

#### How it deploys
- SSH to the server
- `git fetch` and `git checkout $GITHUB_SHA`
- runs `./scripts/deploy.sh`
- hits `/health`

#### Rollback (recommended pattern)
- Keep releases in versioned directories and switch a symlink.
- This repo currently includes a deploy helper; symlink-based releases can be added next.


### Recommended next upgrade
- Switch deploys to release-based (`scripts/release-deploy.sh`) for instant rollback.


#### Release deploy secrets (optional)
If you choose deploy_mode=release, also set:
- `STAGING_RELEASE_BASE` (e.g. `/var/www/rewardstack-app`)
- `PROD_RELEASE_BASE` (e.g. `/var/www/rewardstack-app`)

Release base must contain `releases/` and `shared/` (see `docs/RELEASE_DEPLOY.md`).
