# MAGNUS Reservation System

MAGNUS Reservation System is a plain PHP application for managing reservations for a shared living room in an apartment building. It includes resident signup and activation, privacy-aware reservations, internal messaging, bilingual Dutch/English support, role-based staff access, and a first-run web installer.

## Overview

The project is designed as a lightweight MVP with a clean, framework-free structure. It uses PDO for database access, MariaDB for storage, Bootstrap for the UI, and Docker Compose as the only supported deployment method.

Historical non-Docker deployment guides have been removed from the project and are no longer supported.

## Features

- Resident signup with mailbox-based activation codes
- Reservation calendar with overlap protection and configurable booking limits
- Public availability page with no resident-identifying details
- Internal messaging with optional Mailjet email notifications
- Privacy-aware account settings, data export, and self-service account deletion
- Role and permission management for residents, managers, and administrators
- Admin-managed branding logo and user profile pictures
- Bilingual Dutch/English interface
- First-run installer that writes runtime config and creates the first administrator

## Feature Summary

- Guests can check room availability without logging in.
- Residents can manage their profile, privacy settings, password, and verified email changes.
- Staff access is permission-driven, so managers can be granted operational access without receiving full system control.
- Reservation changes by staff are logged and can notify the affected resident.

## Requirements / Prerequisites

- Docker Engine 24+ or Docker Desktop with the Compose plugin
- Git for cloning and updating the repository
- A modern browser for the installer and admin interface

You do not need PHP, Apache, or MariaDB installed directly on the host machine. The Compose stack provides them.

## Installation

### 1. Get the Code

Clone the repository:

```bash
git clone https://github.com/finnwastakenwastaken/magnus-reservation-system.git
cd magnus-reservation-system
```

If you prefer, download the repository ZIP from GitHub and extract it locally before continuing.

### 2. Start Docker Compose

Build and start the application and database services:

```bash
docker compose up -d --build
```

Open the app in your browser:

```text
http://localhost:8080
```

### 3. Complete the First-Run Installer

On a fresh install, the app redirects automatically to `/install`.

Recommended Docker defaults:

- database host: `db`
- database port: `3306`
- database name: `living_room`
- database username: `living_room`
- database password: `change_me_database_password`

These values match the default `docker-compose.yml` configuration unless you changed them before starting the stack.

### 4. Finish Setup

In the installer:

- confirm the database connection details
- set the application URL
- create the first administrator account

After a successful install:

- config is written to `.env` when the project root is writable, otherwise to `storage/config/app.env`
- `storage/installed.lock` is created
- `/install` is blocked until you intentionally reset the install state

## Configuration

The repository includes a safe `.env.example` file. Copy it to `.env` only if you need to prepare values manually before or after running the installer.

```bash
cp .env.example .env
```

Important notes:

- never commit `.env`
- use placeholder values until you are ready to supply real credentials
- the installer normally writes the main app and database values for you

Important environment variables:

- App:
  - `APP_NAME`
  - `APP_ENV`
  - `APP_DEBUG`
  - `APP_URL`
  - `APP_LOCALE`
  - `SESSION_SECURE`
- Database:
  - `DB_HOST`
  - `DB_PORT`
  - `DB_DATABASE`
  - `DB_USERNAME`
  - `DB_PASSWORD`
  - `DB_ROOT_PASSWORD`
- Mailjet:
  - `MAILJET_ENABLED`
  - `MAILJET_API_KEY`
  - `MAILJET_API_SECRET`
  - `MAIL_FROM_EMAIL`
  - `MAIL_FROM_NAME`
- Cloudflare Turnstile:
  - `TURNSTILE_SITE_KEY`
  - `TURNSTILE_SECRET_KEY`

The in-app updater remains in the codebase for legacy mutable installs, but it is not a supported operational path for this Docker Compose deployment model. Leave `UPDATE_ENABLED=false` unless you are deliberately testing unsupported behavior.

## First-Run Installer

The installer is intended to work naturally inside Docker Compose:

- the app container connects to the MariaDB service named `db`
- the MariaDB container already creates the configured database and database user
- the installer applies the schema, marks shipped migrations as installed, and creates the first administrator account

If installation fails midway:

1. inspect the app logs
2. fix the underlying issue
3. retry `/install`

To rerun the installer in development, remove `.env` or `storage/config/app.env` if present, remove `storage/installed.lock`, and then restart the stack.

## Usage

### Common Docker Commands

Start or rebuild the stack:

```bash
docker compose up -d --build
```

Stop the stack:

```bash
docker compose down
```

View logs:

```bash
docker compose logs -f
docker compose logs -f app
docker compose logs -f db
```

Open a shell in the app container:

```bash
docker compose exec app sh
```

### Data Persistence

- MariaDB data is stored in the named volume `mariadb_data`
- application state such as generated config, logs, backups, and uploads is stored in the project working tree because the repository is mounted into the app container

Do not run `docker compose down -v` unless you intentionally want to delete the database volume.

### Admin Recovery

The installer normally creates the first administrator. If you need a manual recovery path, run the bootstrap script inside the app container:

```bash
docker compose exec app php scripts/bootstrap_admin.php <first_name> <last_name> <email> <apartment_number> <password>
```

Use placeholder values that fit your environment and never commit real credentials.

## Updating

Docker Compose is the only supported update path.

1. Pull the latest code:

```bash
git pull
```

2. Rebuild and restart the stack:

```bash
docker compose up -d --build
```

3. If the release includes database changes, run migrations inside the app container:

```bash
docker compose exec app php scripts/migrate.php
```

4. Check logs:

```bash
docker compose logs -f app
docker compose logs -f db
```

Notes:

- `docker compose pull` is usually not needed here because the `app` service is built from the local repository, not pulled as a prebuilt application image
- `docker compose up -d --build app` is enough when you only need to rebuild the app container
- keep the `mariadb_data` volume intact during updates unless you are intentionally resetting the database

If an update breaks the stack:

1. return to a known-good Git commit
2. rebuild with `docker compose up -d --build`
3. restore your database backup if the issue included destructive data changes

## Privacy and Security Notes

- Keep `.env` and `storage/config/app.env` out of version control.
- The included `.env.example` file is safe to publish and should be used only as a template.
- This repository does not include real resident data, contact data, API keys, or private credentials.
- Guest-facing reservation pages intentionally hide names, apartment numbers, email addresses, and profile pictures.
- Staff access to user data and message oversight should be disclosed to residents in your real deployment.
- Use HTTPS in production and set `APP_DEBUG=false`.

## Troubleshooting

### The Installer Does Not Load

- make sure the stack is running: `docker compose ps`
- check the app logs: `docker compose logs -f app`
- confirm `http://localhost:8080` matches your published port

### Database Connection Errors

- confirm the `db` service is healthy: `docker compose ps`
- verify the app and installer are using `db` as the database host
- inspect database logs: `docker compose logs -f db`
- if the error says the credentials are invalid on a stack you already started before, the `mariadb_data` volume probably still contains the original MariaDB user/password
- for a brand-new local install, resetting the DB volume with `docker compose down -v` and then `docker compose up -d --build` is the fastest recovery path
- do not remove the volume if you need to keep existing database data; use the original credentials instead

### The App Cannot Write `.env` or Uploads

- verify the project directory is writable on the host
- confirm `storage/` and `public/uploads/` exist
- the installer can fall back to `storage/config/app.env` if the project root is not writable
- rebuild the stack after permission fixes

### Image Uploads Fail

- only PNG, JPG/JPEG, and WEBP are accepted
- verify PHP upload-related limits if large files fail
- confirm `public/uploads/` is writable

### Mail Notifications Do Not Send

- verify the Mailjet values in `.env`
- check `storage/logs/app.log`
- confirm the container has outbound network access

### Cloudflare Turnstile Does Not Work

- verify the site key and secret in `.env`
- confirm the configured domain matches your actual app URL
- check browser console errors and app logs

### Docker Stack Problems After an Update

- inspect logs with `docker compose logs -f`
- rebuild from scratch with `docker compose up -d --build`
- only use `docker compose down -v` if you intentionally want a full reset

## Contributing

Contributions are welcome. Open an issue or submit a pull request if you want to improve the app, documentation, or Docker workflow.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the short contribution guide.

## License

No license file is currently included in this repository. Until a license is added, treat the project as all rights reserved by default.
