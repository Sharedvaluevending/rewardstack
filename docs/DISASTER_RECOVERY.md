## Disaster Recovery (DR)

### Backup
Create a backup:

```bash
/var/www/rewardstack/scripts/backup-database.sh /var/www/rewardstack/.env
```

Verify a backup:

```bash
/var/www/rewardstack/scripts/verify-backup.sh /var/www/rewardstack/storage/backups/<file>.sql.gz
```

### Restore

```bash
/var/www/rewardstack/scripts/restore-database.sh /var/www/rewardstack/storage/backups/<file>.sql.gz /var/www/rewardstack/.env
```

### Recommended operational practices
- Store backups off-server (S3/object storage) in addition to local retention.
- Test restores on a staging DB regularly.
- Document who has access to secrets and where they are stored.
