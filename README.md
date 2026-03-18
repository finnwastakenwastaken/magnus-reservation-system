# MAGNUS Reservation System

MAGNUS Reservation System is a plain PHP application for managing reservations for a shared living room in an apartment complex. It includes resident signup and activation, calendar-based reservations, internal messaging, bilingual Dutch/English support, an admin panel, a first-run installer, and a conservative in-app updater for supported deployments.

## Overview

This project is designed as a lightweight, framework-free MVP that is still structured for maintainability. It uses PDO for database access, MariaDB/MySQL for storage, Bootstrap for the interface, and a simple front-controller architecture.

Key goals:

- plain PHP without a full framework
- secure defaults for authentication, CSRF, and session handling
- privacy-aware resident experience
- straightforward installation on Docker, classic hosting, Coolify, or a VPS
- easy first-time setup through a web installer

## Features

- Resident signup with mailbox-based activation codes
- Secure login and session-based authentication
- Shared-room reservation calendar with overlap protection
- Configurable booking limits and opening hours
- Internal resident messaging with optional Mailjet notifications
- Bilingual interface in English and Dutch
- Admin panel for users, reservations, settings, and updates
- First-run installer that writes `.env` and creates the initial admin account
- In-app updater for supported mutable deployments
- Privacy-aware account management, including:
  - data overview and export
  - password change
  - verified email-change flow
  - self-service account deletion and anonymization
  - privacy visibility settings for optional profile fields

## Requirements / Prerequisites

### Runtime

- PHP `8.1+`
- MariaDB `10.5+` or MySQL `8+`
- Apache `2.4+` or Nginx `1.18+`

### Required PHP extensions

- `pdo`
- `pdo_mysql`
- `json`
- `session`
- `openssl`

Recommended:

- `mbstring`
- `curl`
- `intl`
- `zip` for archive-based in-app updates

### Web server requirements

- document root must point to `public/`
- URL rewriting must be enabled
- the PHP/web server user must be able to write:
  - `.env`
  - `storage/`
  - `storage/logs/`
  - `storage/backups/`
  - `storage/updates/`

### Dependencies

Composer is not required. The project currently has no Composer-managed runtime dependencies.

### Optional external services

- Mailjet for outbound email notifications
- Cloudflare Turnstile for anti-bot protection

If those credentials are not configured:

- email delivery falls back to file logging in `storage/logs/app.log`
- Turnstile checks are skipped gracefully in local development

## Installation

Start by obtaining the code:

```bash
git clone https://github.com/finnwastakenwastaken/magnus-reservation-system.git
cd magnus-reservation-system
```

You can also download the repository ZIP from GitHub and extract it manually.

### Docker

This repository includes:

- `Dockerfile`
- `docker-compose.yml`
- `docker/apache-vhost.conf`

1. Build and start the stack:

```bash
docker compose up -d --build
```

2. View logs if needed:

```bash
docker compose logs -f
docker compose logs -f app
docker compose logs -f db
```

3. Open the application:

```text
http://localhost:8080
```

4. Complete the installer at `/install`.

Default example Docker database values from `docker-compose.yml`:

- host: `db`
- port: `3306`
- database: `living_room`
- username: `living_room`
- password: `your_database_password`

Useful commands:

```bash
docker compose down
docker compose down -v
docker compose up -d --build
```

Persistence notes:

- database data is stored in the `mariadb_data` volume
- application runtime state is stored in the project directory, including `.env` and `storage/`

Do not use the in-app updater in Docker deployments. Rebuild and redeploy from Git instead.

### Shared hosting / classic Apache or Nginx hosting

1. Upload or clone the project to your server, for example:

```bash
git clone https://github.com/finnwastakenwastaken/magnus-reservation-system.git /var/www/living-room
```

2. Set the document root to:

```text
/var/www/living-room/public
```

3. Ensure `.env` and `storage/` are writable by the web server user.

Typical Linux permissions:

```bash
sudo touch /var/www/living-room/.env
sudo chown www-data:www-data /var/www/living-room/.env
sudo chmod 664 /var/www/living-room/.env
sudo chown -R www-data:www-data /var/www/living-room/storage
sudo chmod -R 775 /var/www/living-room/storage
```

4. Configure your web server.

Apache:

- use `deployment/apache-vhost.conf`
- enable `mod_rewrite`

```bash
sudo a2enmod rewrite
sudo cp /var/www/living-room/deployment/apache-vhost.conf /etc/apache2/sites-available/living-room.conf
sudo a2ensite living-room.conf
sudo systemctl reload apache2
```

Nginx:

- use `deployment/nginx-site.conf`
- adjust `server_name`
- adjust the PHP-FPM socket if needed

```bash
sudo cp /var/www/living-room/deployment/nginx-site.conf /etc/nginx/sites-available/living-room
sudo ln -s /etc/nginx/sites-available/living-room /etc/nginx/sites-enabled/living-room
sudo nginx -t
sudo systemctl reload nginx
```

5. Open the site and complete the installer.

### Coolify

Use Coolify as a Dockerfile-based deployment.

1. Create a new application in Coolify using the Git repository.
2. Use the included `Dockerfile`.
3. Expose port `80`.
4. Provision a MariaDB service in Coolify or connect to an external MariaDB/MySQL database.
5. Make sure persistent writable storage covers:
   - `.env`
   - `storage/installed.lock`
   - `storage/logs/`
   - `storage/backups/`
   - `storage/updates/`
6. Deploy the application and open `/install`.

Do not use the in-app updater on Coolify. Redeploy from GitHub through Coolify instead.

### VPS

This guide assumes a fresh Ubuntu server with Nginx, PHP-FPM, and MariaDB.

1. Prepare the server with the included helper script logic:

```bash
sudo bash <<'SH'
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y software-properties-common ca-certificates curl unzip git ufw
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y nginx mariadb-server php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl
systemctl enable nginx
systemctl enable php8.3-fpm
systemctl enable mariadb
systemctl start nginx
systemctl start php8.3-fpm
systemctl start mariadb
ufw allow OpenSSH || true
ufw allow 'Nginx Full' || true
mkdir -p /var/www/living-room
echo "Base stack installed. Clone or upload the repository into /var/www/living-room next."
SH
```

The same setup logic is also available in `scripts/vps-bootstrap.sh`.

2. Clone the project:

```bash
cd /var/www
sudo git clone https://github.com/finnwastakenwastaken/magnus-reservation-system.git living-room
sudo chown -R www-data:www-data /var/www/living-room
```

3. Secure MariaDB:

```bash
sudo mysql_secure_installation
```

4. Optionally create the database and user manually:

```sql
CREATE DATABASE living_room CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'living_room'@'localhost' IDENTIFIED BY 'your_strong_database_password';
GRANT ALL PRIVILEGES ON living_room.* TO 'living_room'@'localhost';
FLUSH PRIVILEGES;
```

5. Configure Nginx with `deployment/nginx-site.conf`, set the correct `server_name`, and enable the site.

6. Set file permissions for `.env` and `storage/`.

7. Enable the firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

8. Open the site and complete the installer.

9. Enable HTTPS, for example with Let's Encrypt:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d example.com -d www.example.com
```

For a git-based VPS deployment, the in-app updater can be appropriate if the checkout is writable and the environment is not containerized.

## Usage

### First-run installer

On a fresh install, the application checks whether it has already been installed by looking at:

- `.env`
- `storage/installed.lock`

If installation is incomplete, requests are redirected to `/install`.

The installer collects:

- database host
- database port
- database name
- database username
- database password
- application URL
- initial admin first name
- initial admin last name
- initial admin email
- initial admin password

The installer then:

- creates the database if permissions allow
- connects to an existing database if it already exists
- imports `database/schema.sql`
- records shipped migrations from `database/migrations/`
- creates the first admin account securely
- writes `.env`
- writes `storage/installed.lock`

After a successful install:

- `/install` is blocked
- the initial administrator can log in
- normal users can sign up and activate their account

### Basic usage notes

- residents sign up with their apartment number and wait for physical mailbox activation
- only activated users can log in
- reservations are limited by configurable booking hours and per-user weekly/monthly quotas
- resident-facing reservation listings use privacy-safe names
- admins manage users, reservations, settings, and updates

### Manual admin bootstrap

The installer normally creates the first admin account. If you need a recovery path:

```bash
php scripts/bootstrap_admin.php <first_name> <last_name> <email> <apartment_number> <password>
```

Use placeholder values that fit your environment. Do not commit real credentials or personal data.

## Configuration

Copy `.env.example` to `.env` if you need to prepare configuration manually before or after installation.

Example safe placeholders:

```dotenv
APP_URL=https://your-domain.example
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=living_room
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
ADMIN_EMAIL=admin@example.com

MAILJET_ENABLED=true
MAILJET_API_KEY=your_mailjet_api_key
MAILJET_API_SECRET=your_mailjet_api_secret
MAIL_FROM_EMAIL=no-reply@example.com
MAIL_FROM_NAME="MAGNUS Reservation System"

TURNSTILE_SITE_KEY=your_turnstile_site_key
TURNSTILE_SECRET_KEY=your_turnstile_secret_key
```

Important variables:

- App:
  - `APP_NAME`
  - `APP_ENV`
  - `APP_DEBUG`
  - `APP_INSTALLED`
  - `APP_URL`
  - `APP_TIMEZONE`
  - `APP_LOCALE`
  - `SESSION_NAME`
  - `SESSION_SECURE`
  - `APP_VERSION`
- Database:
  - `DB_HOST`
  - `DB_PORT`
  - `DB_DATABASE`
  - `DB_USERNAME`
  - `DB_PASSWORD`
  - `DB_CHARSET`
- Mail:
  - `MAILJET_ENABLED`
  - `MAILJET_API_KEY`
  - `MAILJET_API_SECRET`
  - `MAIL_FROM_EMAIL`
  - `MAIL_FROM_NAME`
- Anti-bot:
  - `TURNSTILE_SITE_KEY`
  - `TURNSTILE_SECRET_KEY`
- Updater:
  - `UPDATE_ENABLED`
  - `UPDATE_REPOSITORY_URL`
  - `UPDATE_REPOSITORY_BRANCH`
  - `UPDATE_STRATEGY`
  - `UPDATE_GIT_BIN`
  - `UPDATE_CHECK_AUTOMATIC`
  - `UPDATE_BACKUP_PATH`
  - `UPDATE_TEMP_PATH`

## Updating the application

The project includes an admin-only in-app updater available at `/admin/updates`.

What it does:

- shows the installed version from `VERSION`
- checks GitHub for a newer version
- supports git-based updates when the deployment is a writable git checkout
- supports archive-based updates for some writable non-git deployments
- creates a backup before applying updates
- enables maintenance mode during update and rollback

When to use it:

- suitable for mutable git-based VPS deployments
- can be suitable for some classic writable web server deployments

When not to use it:

- do not use it in Docker deployments
- do not use it in Coolify deployments
- do not use it in immutable container or platform deployments

In unsupported environments, redeploy from Git instead.

## Security and privacy considerations

- Keep `.env` out of version control.
- The included `.env.example` file is safe to publish and should be copied and renamed for real deployments.
- This repository does not store personal contact information for residents or operators.
- Any required credentials must be supplied by the deploying user in their own environment.
- Never commit real API keys, database passwords, access tokens, or private email credentials.
- Use HTTPS in production.
- Set `APP_DEBUG=false` in production.
- Restrict access to writable directories and keep regular backups.

## Troubleshooting

### Installer not loading

- make sure the document root points to `public/`
- confirm rewrite rules are enabled
- Apache: verify `mod_rewrite` and suitable `AllowOverride` settings
- Nginx: verify `try_files $uri $uri/ /index.php?$query_string;`

### Database connection errors

- verify host, port, username, and password
- make sure MariaDB/MySQL is running
- in Docker, the app container should usually use `db` as the host
- on local Linux/VPS installs, `127.0.0.1` often works better than `localhost`

### Database creation fails during install

- create the database manually
- rerun the installer with the existing database

### Permission problems

- ensure `.env` is writable
- ensure `storage/` is writable
- verify ownership for the web server user such as `www-data`

### Missing PHP extensions

- verify `pdo_mysql`
- verify `zip` if you want archive-based updates
- restart PHP-FPM or Apache after installing extensions

### Mail not sending

- verify Mailjet credentials
- verify outbound HTTPS access
- check `storage/logs/app.log`

### Cloudflare Turnstile problems

- verify the site key and secret key
- verify the configured domain in Cloudflare Turnstile
- confirm the Turnstile JavaScript loads in the browser

### Routing problems

- verify Apache `mod_rewrite` or Nginx `try_files`
- verify the document root points to `public/`

### Docker issues

- use `docker compose logs -f`
- verify port `8080` is available
- rebuild the containers if needed:

```bash
docker compose down -v
docker compose up -d --build
```

### In-app updater issues

- verify `UPDATE_REPOSITORY_URL`
- verify git exists if using the git strategy
- verify `ZipArchive` if using the archive strategy
- verify the server can write to `storage/backups/` and `storage/updates/`
- do not use the updater in Docker or Coolify

## Contributing

Contributions are welcome. Please open an issue or submit a pull request if you find a bug, want to improve documentation, or want to add functionality.

See [CONTRIBUTING.md](CONTRIBUTING.md) for a short contribution guide.

## License

No license file is currently included in this repository. Until a license is added, treat the project as all rights reserved by default.
