# Civin production deployment

This guide targets Ubuntu 24.04 LTS, PHP 8.3, Nginx, MySQL, Redis, and
Supervisor. Civin's API and Filament panel are one Laravel application:

- `https://api.example.com/api/v1/...` serves the mobile API.
- `https://admin.example.com/admin` serves Filament.
- Both Nginx virtual hosts point to `/var/www/civin/backend/public`.
- Supervisor runs Redis queue workers.
- Cron invokes Laravel's scheduler every minute.

Replace all `example.com` values before enabling the configuration. PHP 8.2 is
also supported; set `PHP_VERSION=8.2` and update socket/service names if used.

## 1. DNS, server, and firewall

Provision an Ubuntu server with at least 2 vCPU and 2 GB RAM. Use a managed
database/Redis service for higher availability, backups, and easier scaling.

Create DNS `A`/`AAAA` records for `api.example.com` and `admin.example.com`
pointing to the server. Then allow only SSH, HTTP, and HTTPS:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Do not expose ports 3306 or 6379 publicly. Restrict SSH to keys and disable
password/root login after confirming key access.

## 2. Install server packages

Copy `deploy/scripts/bootstrap-ubuntu.sh` to the server and run:

```bash
sudo PHP_VERSION=8.3 NODE_MAJOR=22 bash bootstrap-ubuntu.sh
```

The script installs PHP-FPM and Laravel extensions, Composer, Nginx, MySQL,
Redis, Supervisor, Certbot, and Node.js 22. Node is needed because Vite 7 cannot
build on Ubuntu's older Node packages. A CI-built asset artifact can remove Node
from the production server.

For a first production hardening pass:

```bash
sudo mysql_secure_installation
sudo systemctl status php8.3-fpm nginx mysql redis-server supervisor
```

Tune `/etc/php/8.3/fpm/php.ini` for the workload, then restart PHP-FPM. Sensible
starting values are `memory_limit=256M`, `upload_max_filesize=20M`,
`post_max_size=20M`, `expose_php=Off`, and `opcache.enable=1`.

## 3. Create the database

Open MySQL as an administrator:

```bash
sudo mysql
```

Use a unique generated password instead of the placeholder:

```sql
CREATE DATABASE civin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'civin'@'localhost' IDENTIFIED BY 'CHANGE_TO_A_LONG_RANDOM_PASSWORD';
GRANT ALL PRIVILEGES ON civin.* TO 'civin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Enable automated database backups before launch. Keep backups encrypted,
off-server, and test a restore.

## 4. Create the application layout

Use a non-root deployment account with membership in the web-server group:

```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
sudo install -d -o deploy -g www-data -m 2775 /var/www/civin
sudo -u deploy git clone YOUR_PRIVATE_REPOSITORY_URL /var/www/civin
```

The resulting backend must be `/var/www/civin/backend`. Configure a read-only
deployment key for the private repository. Do not put credentials in the clone
URL or deployment script.

Set initial permissions:

```bash
sudo chown -R deploy:www-data /var/www/civin
sudo find /var/www/civin -type d -exec chmod 2755 {} \;
sudo chmod -R ug+rwX /var/www/civin/backend/storage \
    /var/www/civin/backend/bootstrap/cache
```

Recommended persistent/security-sensitive paths:

```text
/var/www/civin/backend/.env
/var/www/civin/backend/storage/
/etc/civin/firebase-service-account.json
/etc/nginx/sites-available/civin
/etc/supervisor/conf.d/civin-worker.conf
/etc/cron.d/civin-scheduler
```

Never commit `.env`, Firebase JSON, database dumps, or private keys.

## 5. Configure the production environment

Create the environment file from the supplied template:

```bash
cd /var/www/civin/backend
cp deploy/.env.production.example .env
chmod 640 .env
chown deploy:www-data .env
php artisan key:generate
```

Edit `.env` and replace every domain, password, and provider credential. Keep:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
SESSION_DOMAIN=admin.example.com
```

Configure a real SMTP/API mail provider; the development `log` mailer does not
send verification or reset emails. Keep `AGORA_APP_CERTIFICATE` and Firebase
service-account credentials server-side.

`BROADCAST_CONNECTION=log` does not provide real-time delivery. If live chat,
gifts, PK, or voice-room events require WebSockets, deploy Laravel Reverb or a
hosted Pusher-compatible service separately and update the broadcast variables.

For local Redis, bind only to loopback and keep protected mode enabled. For
managed MySQL/Redis, use private-network endpoints, TLS where supported, and
provider firewall rules.

## 6. First application installation

Run as the deployment user:

```bash
cd /var/www/civin/backend
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm install --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Seed only the required production reference data:

```bash
php artisan db:seed --class=CountrySeeder --force
php artisan db:seed --class=SettingSeeder --force
php artisan db:seed --class=AdminRoleSeeder --force
```

Do not run `migrate:fresh` in production. `DatabaseSeeder` also creates
`admin@civin.app` with the hard-coded password `password`. If it is used during
initial setup, change that password immediately and verify the super-admin role.
A safer follow-up is to modify the seeder to accept an injected, generated
password before production use.

## 7. Configure Nginx

Copy the supplied virtual hosts and replace the domains if not already changed:

```bash
sudo cp deploy/nginx/civin.conf /etc/nginx/sites-available/civin
sudo ln -s /etc/nginx/sites-available/civin /etc/nginx/sites-enabled/civin
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

The configuration deliberately returns 404 for `/admin` on the API hostname and
for `/api` on the admin hostname. Static assets, Livewire, login, and health
routes continue through the shared Laravel front controller.

Confirm HTTP and DNS before requesting certificates:

```bash
curl -I http://api.example.com/up
curl -I http://admin.example.com/admin
```

## 8. Enable HTTPS with Let's Encrypt

Issue one certificate covering both hostnames and let Certbot update Nginx:

```bash
sudo certbot --nginx \
    -d api.example.com \
    -d admin.example.com \
    --redirect \
    --agree-tos \
    --no-eff-email \
    -m ops@example.com
```

Test automatic renewal:

```bash
sudo certbot renew --dry-run
systemctl status certbot.timer
```

After HTTPS is stable, optionally add this header to both TLS server blocks:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

Only enable HSTS when every affected subdomain is permanently HTTPS.

## 9. Configure Redis queues with Supervisor

Install and activate the supplied worker definition:

```bash
sudo cp deploy/supervisor/civin-worker.conf \
    /etc/supervisor/conf.d/civin-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status civin-worker:*
```

The default starts two workers on the `default` Redis queue. Adjust `numprocs`
after measuring CPU, memory, queue latency, and job throughput. A job must have a
timeout shorter than Supervisor's `stopwaitsecs`; jobs should be idempotent.

Useful operations:

```bash
sudo supervisorctl restart civin-worker:*
cd /var/www/civin/backend && php artisan queue:failed
cd /var/www/civin/backend && php artisan queue:retry all
```

## 10. Configure Laravel's scheduler

The project schedules daily/weekly ranking calculations and hourly VIP expiry.
Install the cron definition:

```bash
sudo cp deploy/cron/civin-scheduler /etc/cron.d/civin-scheduler
sudo chmod 0644 /etc/cron.d/civin-scheduler
sudo systemctl restart cron
cd /var/www/civin/backend && php artisan schedule:list
```

The application timezone is UTC; the daily ranking job runs at 00:05 UTC and
the weekly job at 00:15 UTC on Monday.

## 11. Configure the deployment command

Make the script executable:

```bash
chmod +x /var/www/civin/backend/deploy/scripts/deploy.sh
```

Permit the deployment account to reload only required services:

```bash
sudo visudo -f /etc/sudoers.d/civin-deploy
```

Add:

```text
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm, /usr/bin/systemctl reload nginx
```

Deploy:

```bash
sudo -iu deploy
cd /var/www/civin/backend
HEALTH_URL=https://api.example.com/up \
    ./deploy/scripts/deploy.sh
```

The script:

1. Enables Laravel maintenance mode.
2. Fast-forwards the configured Git branch.
3. Installs locked Composer dependencies without development packages.
4. Builds Vite assets.
5. clears stale caches, runs migrations, and rebuilds Laravel caches.
6. Restarts workers gracefully, reloads PHP-FPM/Nginx, and exits maintenance.
7. Requires the `/up` health check to succeed.

Set `BRANCH=production` or `PHP_FPM_SERVICE=php8.2-fpm` when applicable. The
script intentionally rejects a non-fast-forward pull and leaves local conflicts
for an operator to resolve.

This repository currently lacks `package-lock.json`, so frontend dependency
builds are not reproducible. Generate and commit the lockfile, then change
`npm install` to `npm ci` in the deployment script.

## 12. CI/CD invocation

A minimal CI job should connect over SSH with a restricted deployment key and
run only the server-side script:

```bash
ssh -o StrictHostKeyChecking=yes deploy@SERVER_IP \
  'cd /var/www/civin/backend && HEALTH_URL=https://api.example.com/up ./deploy/scripts/deploy.sh'
```

Store the SSH key and host fingerprint in the CI secret store. Keep `.env` and
provider credentials on the server or in a secrets manager, never in CI logs.
For zero-downtime and reliable rollback, evolve this basic deployment into
timestamped releases with shared `.env`/`storage` and an atomic `current`
symlink, or use Laravel Envoyer/Forge.

## 13. Production verification

Run these checks after the first deployment and after infrastructure changes:

```bash
curl --fail https://api.example.com/up
curl -I https://api.example.com/api/v1
curl -I https://admin.example.com/admin
cd /var/www/civin/backend && php artisan about --only=environment
cd /var/www/civin/backend && php artisan migrate:status
sudo supervisorctl status civin-worker:*
redis-cli ping
sudo nginx -t
```

Also verify admin login, API authentication, a queued email/notification, a
scheduled job, file URLs, SMTP delivery, Firebase operations, Agora token
creation, and mobile requests against the production API URL.

Monitor Laravel logs, Nginx 5xx responses, worker failures, disk space, TLS
renewal, database capacity, Redis memory, queue latency, and `/up`. Configure
alerts and an off-server log/metrics destination before launch.
