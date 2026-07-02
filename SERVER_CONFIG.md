# Server Configuration

Production server since 2026-07-02: shared Contabo VPS `207.180.244.55` (see DEPLOYMENT.md).

## nginx (replaces the old Apache setup)

- Site config: `/etc/nginx/sites-available/donation` (enabled via sites-enabled symlink)
- Shared app snippet: `/etc/nginx/snippets/donation-app.conf`
  - document root `/var/www/donation_backend/web`, `try_files` → `index.php` (pretty URLs)
  - php8.3-fpm via unix socket, 300s read timeout, 32m client_max_body_size
  - `/api/` serves legacy static JSONs from `/var/www/legacy-aws-api`
  - ACME webroot `/var/www/letsencrypt` for cert renewals
- Listeners: 8087 (HTTP, any host), 80 (named hosts), 443/8443 SSL
  - `207-180-244-55.sslip.io` — Let's Encrypt, auto-renewed by certbot (deploy hook reloads nginx)
  - `redjuniors.mooo.com` — cert copied from the old AWS box (works if DNS is repointed)
  - `donation.burma.it.com` — pre-configured for the Cloudflare tunnel option
- CORS is handled by the Yii2 app itself (Cors filter), not the web server.

## Permissions

- `runtime/` and `web/assets/` owned by `www-data`, group-writable
- `config/db.php` is git skip-worktree on the server (server-only DB credentials)

## Firewall

- ufw allows 8087/tcp ("Blood Donation API"); 80/443 were already open server-wide.

## History

Until 2026-07-02 the backend ran on AWS EC2 behind Apache
(DocumentRoot `/var/www/donation_backend/web`, mod_rewrite + AllowOverride,
runtime/assets 777). That instance is terminated and the AWS account closed.
