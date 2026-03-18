# MAGNUS Reservation System

MAGNUS Reservation System is a plain PHP reservation platform for a shared living room in an apartment complex. It includes a first-run installer, bilingual Dutch/English UI, resident messaging, an admin panel, and a conservative in-app updater for supported deployment types.

## What This Project Includes

- Plain PHP application with front controller and simple routing
- MariaDB/MySQL through PDO
- Web installer at `/install`
- Shared-room reservation rules and overlap protection
- Internal messaging with Mailjet notifications
- Cloudflare Turnstile integration
- Admin panel for users, reservations, settings, and updates
- GitHub-aware update system for supported mutable deployments

## Project Structure

- [public/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/public) public web root
- [src/Core/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/src/Core) bootstrap, router, auth, views, validation
- [src/Controllers/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/src/Controllers) HTTP controllers
- [src/Services/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/src/Services) business logic, installer, updater, maintenance, mail
- [src/Lang/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/src/Lang) EN/NL translations
- [database/schema.sql](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/database/schema.sql) baseline schema
- [database/migrations/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/database/migrations) ordered SQL migrations
- [Dockerfile](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/Dockerfile) Docker image
- [docker-compose.yml](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/docker-compose.yml) local Docker stack
- [deployment/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/deployment) Apache/Nginx examples
- [scripts/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/scripts) bootstrap and server helper scripts
- [VERSION](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/VERSION) current app version
- [CHANGELOG.md](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/CHANGELOG.md) release notes

## General Prerequisites

### Runtime Versions

- PHP `8.1+`
- MariaDB `10.5+` or MySQL `8+`
- Apache `2.4+` or Nginx `1.18+`

### Required PHP Extensions

- `pdo`
- `pdo_mysql`
- `json`
- `session`
- `openssl`

Recommended:

- `mbstring`
- `curl`
- `intl`
- `zip` if you want archive-based in-app updates

### Webserver Requirements

- document root must point to `public/`
- URL rewriting must be enabled
- PHP must be allowed to write:
  - `.env`
  - `storage/`
  - `storage/logs/`
  - `storage/backups/`
  - `storage/updates/`

### Composer

Composer is not required. The project currently has no Composer-managed dependencies.

### External Services

Optional but recommended in production:

- Mailjet for email delivery
- Cloudflare Turnstile for anti-bot protection

If those credentials are missing:

- mail is written to `storage/logs/app.log`
- Turnstile checks are skipped gracefully

### Typical Linux Permissions

```bash
sudo touch /var/www/living-room/.env
sudo chown www-data:www-data /var/www/living-room/.env
sudo chmod 664 /var/www/living-room/.env
sudo chown -R www-data:www-data /var/www/living-room/storage
sudo chmod -R 775 /var/www/living-room/storage
```

## Getting the Code

The normal installation flow starts by obtaining the code from the GitHub repository named `MAGNUS Reservation System`. If the GitHub slug must be URL-safe, use `magnus-reservation-system`.

### Option 1: Clone with Git

```bash
git clone <your-github-repository-url>/magnus-reservation-system.git
cd magnus-reservation-system
```

### Option 2: Download ZIP

1. Download the repository ZIP from GitHub.
2. Extract it on your machine or server.
3. Rename the extracted folder if needed.
4. Continue with the deployment instructions below.

## Standard Installation Flow

For all deployment methods, the intended flow is:

1. get the code from GitHub
2. deploy or start the required services
3. ensure the app can write `.env` and `storage/`
4. open the application URL
5. complete the installer wizard
6. let the installer create or connect to the database
7. create the initial administrator account
8. verify the app works
9. add production secrets and hardening settings

## First-Run Installer

On a fresh install the application checks:

- `.env`
- `storage/installed.lock`

If installation is incomplete, the app redirects to `/install`.

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

- tries to create the database if it does not exist
- continues with an existing database if creation rights are unavailable
- imports the baseline schema from [database/schema.sql](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/database/schema.sql)
- applies versioned migrations from [database/migrations/](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/database/migrations)
- creates the first admin account securely with `password_hash()`
- writes `.env`
- creates `storage/installed.lock`

After successful installation, `/install` is blocked until manually reset by a developer.

## Environment Variables

The installer writes `.env` automatically. Important settings include:

- `APP_INSTALLED`
- `APP_VERSION`
- `APP_URL`
- `APP_DEBUG`
- `APP_TIMEZONE`
- `SESSION_SECURE`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `ADMIN_EMAIL`
- `MAILJET_ENABLED`
- `MAILJET_API_KEY`
- `MAILJET_API_SECRET`
- `MAIL_FROM_EMAIL`
- `MAIL_FROM_NAME`
- `TURNSTILE_SITE_KEY`
- `TURNSTILE_SECRET_KEY`
- `UPDATE_ENABLED`
- `UPDATE_REPOSITORY_URL`
- `UPDATE_REPOSITORY_BRANCH`
- `UPDATE_STRATEGY`
- `UPDATE_GIT_BIN`
- `UPDATE_CHECK_AUTOMATIC`
- `UPDATE_BACKUP_PATH`
- `UPDATE_TEMP_PATH`

See [.env.example](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/.env.example).

## Docker Installation

This project includes a real Docker setup using:

- [Dockerfile](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/Dockerfile)
- [docker-compose.yml](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/docker-compose.yml)
- [docker/apache-vhost.conf](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/docker/apache-vhost.conf)

### Prerequisites

- Docker Engine
- Docker Compose plugin

### 1. Get the Code

```bash
git clone <your-github-repository-url>/magnus-reservation-system.git
cd magnus-reservation-system
```

### 2. Understand the Included Docker Stack

- `app` container:
  - PHP 8.3 + Apache
  - `mod_rewrite` enabled
  - PDO MySQL enabled
  - document root configured to `public/`
- `db` container:
  - MariaDB 11.4
  - persistent data volume `mariadb_data`

### 3. Start the Stack

```bash
docker compose up -d --build
```

### 4. Stop the Stack

```bash
docker compose down
```

### 5. Stop and Remove Persistent Database Data

This deletes the MariaDB volume:

```bash
docker compose down -v
```

### 6. View Logs

```bash
docker compose logs -f
docker compose logs -f app
docker compose logs -f db
```

### 7. Rebuild After Changes

```bash
docker compose up -d --build
```

### 8. Default Docker Database Values

From [docker-compose.yml](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/docker-compose.yml):

- host: `db`
- port: `3306`
- database: `living_room`
- username: `living_room`
- password: `living_room_password`

### 9. Access the Installer

Open:

```text
http://localhost:8080
```

The app redirects to `/install`.

### 10. Complete Installation

Use the Docker database values above and set:

- app URL: `http://localhost:8080`

Then create the initial admin account in the installer.

### 11. Persistence Notes

- MariaDB data is stored in the `mariadb_data` Docker volume
- application state is written into the mounted project directory:
  - `.env`
  - `storage/installed.lock`
  - `storage/logs/`
  - `storage/backups/`
  - `storage/updates/`

### 12. Post-Install Verification

- `/install` should no longer be reachable
- `/login` should load
- admin login should work
- reservation creation should work

### 13. Docker Update Guidance

Do not use the in-app updater inside Docker containers. Docker deployments should be updated by redeploying the image from GitHub:

```bash
git pull
docker compose up -d --build
```

The admin updater UI will warn about this automatically.

## Regular Webserver Installation

Use this for shared hosting, LAMP, or LEMP deployments.

### 1. Get the Code

Either clone:

```bash
git clone <your-github-repository-url>/magnus-reservation-system.git /var/www/living-room
```

Or upload the extracted GitHub ZIP to:

```text
/var/www/living-room
```

### 2. Set the Document Root

Set the web root to:

```text
/var/www/living-room/public
```

Do not point the webserver to the project root.

### 3. Set Permissions

The PHP/webserver user must be able to write:

- `/var/www/living-room/.env`
- `/var/www/living-room/storage/`

### 4. Configure Apache or Nginx

Apache:

- use [deployment/apache-vhost.conf](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/deployment/apache-vhost.conf)
- enable `mod_rewrite`

Typical Apache commands:

```bash
sudo a2enmod rewrite
sudo cp /var/www/living-room/deployment/apache-vhost.conf /etc/apache2/sites-available/living-room.conf
sudo a2ensite living-room.conf
sudo systemctl reload apache2
```

Nginx:

- use [deployment/nginx-site.conf](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/deployment/nginx-site.conf)
- adjust `server_name`
- adjust the PHP-FPM socket if your PHP version differs

Typical Nginx commands:

```bash
sudo cp /var/www/living-room/deployment/nginx-site.conf /etc/nginx/sites-available/living-room
sudo ln -s /etc/nginx/sites-available/living-room /etc/nginx/sites-enabled/living-room
sudo nginx -t
sudo systemctl reload nginx
```

### 5. Open the Installer

Open your domain in a browser. The app redirects to `/install`.

### 6. Complete the Installer

Enter:

- database host
- database port
- database name
- database username
- database password
- public app URL
- initial admin account details

If the installer cannot create the database, create it manually in your hosting control panel and rerun the installer.

### 7. Post-Install Configuration

Edit `.env` if needed:

- `APP_DEBUG=false`
- `SESSION_SECURE=true` under HTTPS
- Mailjet credentials
- Turnstile credentials
- updater configuration if you want to enable in-app update checks

### 8. Post-Install Verification

- `/install` should be blocked
- `/login` should load
- admin login should work
- storage logs should be writable

### 9. Updating Later

For classic deployments, you can either:

- redeploy code manually from GitHub
- or use the in-app updater if the server is a writable mutable installation and the updater prerequisites are met

## Coolify Installation

Deploy this app in Coolify as a Dockerfile-based application.

### 1. Get the Code into GitHub

Coolify should point to your GitHub-hosted copy of the repository.

### 2. Create Resources in Coolify

- create a new application resource
- source: Git repository
- build type: `Dockerfile`
- exposed port: `80`
- optional separate MariaDB resource/service

### 3. Configure Storage

This project writes:

- `.env`
- `storage/installed.lock`
- `storage/logs/`
- `storage/backups/`
- `storage/updates/`

Your Coolify setup therefore needs writable persistent storage for the app directory or at minimum these paths.

### 4. Configure Database

Create a MariaDB service in Coolify and note:

- host
- port
- database
- username
- password

### 5. Deploy the Application

1. connect Coolify to your repository
2. use the included [Dockerfile](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/Dockerfile)
3. deploy
4. open the application URL

### 6. Complete the Installer

The app redirects to `/install`. Enter the MariaDB service details and create the first admin account.

### 7. Important Coolify Update Note

Do not use the in-app updater for Coolify deployments. Coolify should redeploy from GitHub instead. The updater UI will warn and block self-updates in this environment.

### 8. Verify the Installation

- `/install` should be blocked after success
- admin login should work
- HTTPS should be enabled through Coolify

## VPS Installation

This guide assumes a fresh Ubuntu server using:

- Nginx
- PHP-FPM
- MariaDB

### 1. Get the Server Ready

Connect to the VPS as a sudo-capable user.

### 2. Copy-Paste SSH Bootstrap Command

This installs the base stack required for this project:

- Nginx
- MariaDB
- PHP 8.3
- required PHP extensions
- Git
- UFW basics

Copy and paste this block on a fresh Ubuntu server:

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
echo "Base stack installed. Clone or upload the GitHub repository into /var/www/living-room next."
SH
```

The same logic is available in [scripts/vps-bootstrap.sh](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/scripts/vps-bootstrap.sh).

### 3. Clone the Repository onto the VPS

```bash
cd /var/www
sudo git clone <your-github-repository-url>/magnus-reservation-system.git living-room
sudo chown -R www-data:www-data /var/www/living-room
```

If you prefer not to use Git, upload the GitHub ZIP contents to `/var/www/living-room`.

### 4. Secure MariaDB

```bash
sudo mysql_secure_installation
```

Optional manual database/user setup:

```sql
CREATE DATABASE living_room CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'living_room'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON living_room.* TO 'living_room'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Configure Nginx

```bash
sudo cp /var/www/living-room/deployment/nginx-site.conf /etc/nginx/sites-available/living-room
sudo nano /etc/nginx/sites-available/living-room
```

Set:

- `server_name`
- `root /var/www/living-room/public`
- the correct PHP-FPM socket if needed

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/living-room /etc/nginx/sites-enabled/living-room
sudo nginx -t
sudo systemctl reload nginx
```

### 6. Set Permissions

```bash
sudo touch /var/www/living-room/.env
sudo chown www-data:www-data /var/www/living-room/.env
sudo chmod 664 /var/www/living-room/.env
sudo chown -R www-data:www-data /var/www/living-room/storage
sudo chmod -R 775 /var/www/living-room/storage
```

### 7. Configure Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### 8. Run the Installer

Open your domain or server IP in a browser. The app redirects to `/install`.

Enter:

- DB host, usually `127.0.0.1`
- DB port, usually `3306`
- DB name
- DB user
- DB password
- public app URL
- initial admin details

### 9. Secure the Server After Install

- set `APP_DEBUG=false`
- set `SESSION_SECURE=true`
- configure Mailjet and Turnstile in `.env`
- enable HTTPS

Typical Let’s Encrypt step:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d example.com -d www.example.com
```

### 10. VPS Updates

For git-based VPS deployments you have two choices:

- normal deployment via `git pull`
- in-app updater from the admin panel

Manual git-based update:

```bash
cd /var/www/living-room
sudo git pull
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm
```

## In-App Update System

The admin panel includes an update page for supported environments.

### Where It Appears

- admin only
- available at `/admin/updates`

### What It Shows

- current installed version from [VERSION](/C:/Users/arrow/Documents/ICT Projects/PhPStorm/untitled1/VERSION)
- latest available version from GitHub
- detected update strategy
- release notes or changelog
- last update status

### Update Strategies

- `git`
  - used when the app is a writable git checkout and git is available
  - performs a fast-forward pull from the configured GitHub repository/branch
- `archive`
  - fallback for writable non-git installs if `ZipArchive` is available
  - downloads a GitHub archive and replaces versioned files while preserving runtime data
- `unsupported`
  - used for Docker, Coolify, and other environments where self-mutation is unsafe or unavailable

### Required Update Configuration

Set in `.env`:

```dotenv
UPDATE_ENABLED=true
UPDATE_REPOSITORY_URL=https://github.com/<owner>/magnus-reservation-system.git
UPDATE_REPOSITORY_BRANCH=main
UPDATE_STRATEGY=auto
UPDATE_GIT_BIN=git
UPDATE_CHECK_AUTOMATIC=false
UPDATE_BACKUP_PATH=storage/backups
UPDATE_TEMP_PATH=storage/updates
```

### Safety Behavior

- admin-only access
- CSRF protection on all update actions
- update lock prevents concurrent updates
- maintenance mode is enabled during update/rollback
- a code backup is created before applying the update
- `.env`, `storage/`, and runtime data are preserved
- update actions are written to `audit_log`

### Backups and Rollback

Before an update, the app creates a code backup under `storage/backups/`.

Rollback support:

- supported for code files
- does not perform automatic full database rollback
- if a migration fails, the error is shown clearly
- database rollback may still require manual intervention depending on the migration

### Supported Environments

Best supported:

- mutable git-based VPS deployments
- writable classic webserver deployments

Not supported for self-updating:

- Docker
- Coolify
- immutable container/platform deployments

In those environments, redeploy from GitHub instead of using the in-app updater.

## Post-Install Steps

For every deployment method:

### Access the Installer

- open the app URL
- the app redirects to `/install` on first run

### Create the Initial Admin

- enter the admin details in the installer
- the installer creates the first admin securely

### Verify the Application

- `/install` should be blocked
- `/login` should work
- admin login should work
- reservations should save
- messages should save
- if mail is disabled, `storage/logs/app.log` should be writable

### Secure the Installation

- set `APP_DEBUG=false`
- set `SESSION_SECURE=true` under HTTPS
- keep `.env` private
- configure Mailjet and Turnstile
- ensure `/install` remains blocked

### Updating Later

- back up the database
- deploy new code or use the supported in-app updater
- preserve `.env`
- preserve `storage/`
- reload/rebuild the runtime

## Mail Setup

Add to `.env`:

```dotenv
MAILJET_ENABLED=true
MAILJET_API_KEY=your_key
MAILJET_API_SECRET=your_secret
MAIL_FROM_EMAIL=no-reply@example.com
MAIL_FROM_NAME="Living Room App"
```

## Cloudflare Turnstile Setup

Add to `.env`:

```dotenv
TURNSTILE_SITE_KEY=your_site_key
TURNSTILE_SECRET_KEY=your_secret_key
```

Turnstile protects:

- signup
- login
- activation
- message compose

## Manual Admin Bootstrap

The installer normally creates the first admin. For recovery:

```bash
php scripts/bootstrap_admin.php <first_name> <last_name> <email> <apartment_number> <password>
```

## Resetting Installation in Development

To rerun the installer in development:

1. delete `.env` or set `APP_INSTALLED=false`
2. delete `storage/installed.lock`
3. optionally reset or drop the database
4. reload the app

## Recovering From a Failed Installation

If install fails halfway:

1. fix the real issue first
2. check `.env`
3. check `storage/installed.lock`
4. rerun `/install` if schema/admin were already created but config/lock were not
5. remove `.env`, remove `storage/installed.lock`, and reset the DB only if you want a full clean retry

## Troubleshooting

### Installer Not Loading

- confirm the document root points to `public/`
- confirm rewrite rules are enabled
- Apache: confirm `AllowOverride All` for `public/`
- Nginx: confirm `try_files $uri $uri/ /index.php?$query_string;`

### Database Connection Errors

- verify host, port, username, password
- verify MariaDB/MySQL is running
- Docker app container should use host `db`
- local/VPS installs often work better with `127.0.0.1` than `localhost`

### Database Creation Fails

- create the database manually
- rerun the installer with the existing database

### Permission Problems

- ensure `.env` is writable
- ensure `storage/` is writable
- fix Linux ownership to the webserver user such as `www-data`

### Missing PHP Extensions

- verify `pdo_mysql`
- verify `zip` if using archive-based updates
- restart PHP-FPM/Apache after installing extensions

### Mail Not Sending

- verify Mailjet credentials
- verify outbound HTTPS access
- check `storage/logs/app.log`

### Cloudflare Turnstile Not Working

- verify the site key and secret key
- verify your domain in Cloudflare Turnstile
- confirm the Turnstile JS loads in the browser

### Routing or Rewrite Problems

- Apache: enable `mod_rewrite`
- Nginx: ensure `try_files` is present
- ensure the document root is `public/`

### Docker Problems

- run `docker compose logs -f`
- verify port `8080` is free
- recreate the DB volume if initialization failed:

```bash
docker compose down -v
docker compose up -d --build
```

### In-App Updater Problems

- verify `UPDATE_REPOSITORY_URL`
- verify git exists if using git strategy
- verify `zip`/`ZipArchive` if using archive strategy
- verify the server can write `storage/backups/` and `storage/updates/`
- do not use the updater in Docker or Coolify

## Production Recommendations

- always use HTTPS
- set `APP_DEBUG=false`
- set `SESSION_SECURE=true`
- secure `.env` and all secrets
- keep `/install` inaccessible after setup
- schedule regular database backups
- keep OS, PHP, MariaDB, and webserver packages updated
- review logs regularly

## Notes

- default timezone is `Europe/Amsterdam`
- reservations are restricted to the same calendar day
- weekly limits use ISO weeks
- monthly limits use calendar months
- admin password reset currently uses a temporary password flow

## Known Limitations

- rollback restores application files only, not full database state
- archive-based self-updates require `ZipArchive` and writable code directories
- Docker and Coolify should redeploy from GitHub rather than self-update
- the reservation UI is a responsive list/table, not a drag-and-drop calendar
