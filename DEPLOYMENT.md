# Deployment Instructions for Backend

## Production Server (since 2026-07-02)

The backend runs on the shared Contabo VPS — **no longer on AWS**.

- Server: `207.180.244.55` (host `vmi3014550`, shared with medico, hello7, property, etc.)
- SSH: `ssh root@207.180.244.55`
- App dir: `/var/www/donation_backend`
- Web server: nginx (`/etc/nginx/sites-available/donation` + `/etc/nginx/snippets/donation-app.conf`) + php8.3-fpm
- Database: PostgreSQL 16, db `donation`, role `donation_user` (password in `config/db.php` on the server; file is `skip-worktree` so git pull never touches it)
- Public URLs:
  - `https://207-180-244-55.sslip.io/` — primary API URL used by the Flutter app (Let's Encrypt cert, auto-renews via certbot)
  - `http://207.180.244.55:8087/` — direct HTTP (testing)
  - `https://redjuniors.mooo.com/` — legacy URL; works only if its DNS A record points to 207.180.244.55 (cert copied from AWS lives at `/etc/nginx/ssl-redjuniors/`)
  - `/api/*.json` — legacy static JSONs (dhamma/diwar apps) served from `/var/www/legacy-aws-api`

## Quick Deploy

```bash
ssh root@207.180.244.55
cd /var/www/donation_backend
git pull origin main
php yii migrate --interactive=0
php yii cache/flush-all        # if needed
chown -R www-data:www-data runtime web/assets
```

Run migrations before releasing a client that depends on new database fields.
For the Facebook-post time persistence feature, the backend migration must be
applied before the updated Flutter app is used.

nginx/php-fpm reload is normally NOT needed for PHP code changes.

## Testing the API

```bash
curl -X GET "https://207-180-244-55.sslip.io/report/dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

## Troubleshooting

1. Yii2 application logs:
```bash
tail -f /var/www/donation_backend/runtime/logs/app.log
```

2. nginx/php errors:
```bash
tail -f /var/log/nginx/error.log
journalctl -u php8.3-fpm -f
```

3. Database connection:
```bash
php yii migrate/new
```

## History

- Until 2026-07-02 the backend ran on AWS EC2 (54.206.49.166 / redjuniors.mooo.com,
  Apache + PostgreSQL 16). Migrated to Contabo to cut AWS costs; the final AWS DB
  dump is kept at `donation_dump_2026-07-02_final_aws.dump` (project root, local)
  and `/root/backups-donation/` on the server. The old database was renamed
  `donation_old_aws` on the Contabo Postgres as an extra safety copy.
