# MAGNUS Reservation System

MAGNUS Reservation System is a plain PHP and MariaDB application for managing reservations for a shared living room in an apartment complex. It includes resident signup with mailbox activation, a dark-mode reservation calendar, internal messaging, privacy controls, staff roles and permissions, branding management, and a first-run web installer.

## Overview

This project is intentionally framework-free and Docker Compose only. The supported runtime model is:

1. clone the repository
2. start the Docker Compose stack
3. open the web installer
4. create the first administrator
5. manage the application from the browser

Legacy deployment models such as shared hosting, standalone VPS installs, manual Apache/Nginx setups, and mutable in-app self-updates are no longer supported.

## Features

- Resident signup with mailbox-delivered activation codes
- Privacy-safe reservation calendar with overlap prevention and booking limits
- Public availability calendar without personal details
- Dark mode UI by default
- Internal messaging with in-app notifications and optional Mailjet email delivery
- Account, privacy, and profile-picture management
- Role and permission management for residents, managers, and administrators
- Admin-managed site logo upload
- Audit logging for sensitive actions
- Dutch and English translations
- First-run installer designed for Docker Compose

## Feature Summary

- Logged-in users land directly on the reservation calendar.
- Guests can view availability but never see resident identities.
- Staff access is permission-driven rather than hardcoded by role name.
- Reservation changes made by staff create in-app notifications and can also send email when Mailjet is configured.
- The in-app updater screen is informational only and points administrators to the supported Docker update workflow.

## Requirements / Prerequisites

- Docker Engine 24+ or Docker Desktop with the Compose plugin
- Git
- A modern browser

You do not need PHP, Apache, or MariaDB installed on the host machine.

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/finnwastakenwastaken/magnus-reservation-system.git
cd magnus-reservation-system
```

### 2. Review Environment Defaults

The repository includes a safe [.env.example](.env.example). You usually do not need to create `.env` before the first run because the installer writes durable runtime configuration to `storage/config/app.env`.

If you want to prepare values in advance, copy the example file and adjust placeholders:

```bash
cp .env.example .env
```

Never commit `.env` or any real credentials.

### 3. Start the Docker Compose Stack

```bash
docker compose up -d --build
```

Open the application in your browser:

```text
http://localhost:8080
```

## Configuration

### Important Runtime Paths

- application runtime config: `storage/config/app.env`
- install lock: `storage/installed.lock`
- app storage volume: `app_storage`
- uploaded images volume: `app_uploads`
- database volume: `mariadb_data`

### Important Environment Variables

The most important values are:

- `APP_NAME`
- `APP_URL`
- `APP_ENV`
- `APP_DEBUG`
- `APP_LOCALE`
- `SESSION_SECURE`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_ROOT_PASSWORD`
- `MAILJET_ENABLED`
- `MAILJET_API_KEY`
- `MAILJET_API_SECRET`
- `MAIL_FROM_EMAIL`
- `MAIL_FROM_NAME`
- `TURNSTILE_SITE_KEY`
- `TURNSTILE_SECRET_KEY`

Use placeholder values such as:

- `your_mailjet_api_key`
- `your_mailjet_api_secret`
- `your_turnstile_site_key`
- `your_turnstile_secret_key`
- `your-domain.example`

## First-Run Installer

On a fresh install the app redirects automatically to `/install`.

Recommended Docker Compose defaults:

- database host: `db`
- database port: `3306`
- database name: `living_room`
- database username: `living_room`
- database password: `change_me_database_password`

The MariaDB container already creates the configured database and database user. The installer then:

- validates the connection
- applies the schema
- marks bundled migrations as installed
- creates the first administrator account
- writes runtime config to `storage/config/app.env`
- creates `storage/installed.lock`

After a successful install, `/install` is blocked until you intentionally reset the environment.

## Usage

### Home Screen Behavior

- guests see a public landing page and public availability calendar
- authenticated users land on the reservation calendar
- authenticated users are redirected away from login, signup, and activation pages

### Reservation Calendar

- the main resident experience is a dark week/day calendar
- drag across time slots to create a reservation
- existing reservations are shown as unavailable
- your own reservations can be cancelled from the calendar
- server-side rules remain authoritative

Current booking rules:

- bookings must be in the future
- bookings must stay within the configured booking hours
- bookings cannot overlap
- weekly limits use ISO week totals
- monthly limits use calendar month totals

### Notifications

Users always receive in-app notifications for important actions such as staff reservation changes. If Mailjet is configured, email delivery is attempted in addition to the in-app notification.

### Roles and Permissions

The application uses a single primary role per user, with permissions assigned to roles.

Default system roles:

- `user`
- `manager`
- `admin`

Administrators can:

- create roles
- edit role names and descriptions
- assign permissions to roles
- assign roles to users

The protected administrator path remains guarded so the last privileged administrator cannot be removed accidentally.

## Updating

Docker Compose is the only supported update path.

### Normal Update Flow

```bash
git pull
docker compose up -d --build
docker compose exec app php scripts/migrate.php
docker compose logs -f app
```

### Notes

- use `docker compose up -d --build` after application or Dockerfile changes
- `docker compose pull` is usually not relevant for the `app` service because it is built from the local repository
- do not use `docker compose down -v` during normal updates unless you intentionally want to erase the database and runtime volumes
- if you only need logs after an update:

```bash
docker compose logs -f app
docker compose logs -f db
```

The in-app updater page is intentionally disabled for this Docker-only deployment model.

## Privacy and Security Notes

- `.env` must never be committed
- `storage/config/app.env` contains runtime secrets and must remain out of version control
- the repository does not include real resident data, credentials, or private contact information
- guest-facing availability views never expose names, apartment numbers, emails, or profile pictures
- profile pictures and optional contact fields are resident-controlled and privacy-aware
- managers and administrators can access additional data only when granted the relevant permissions
- manager/admin message oversight should be disclosed clearly in real-world house rules and privacy policy usage
- use HTTPS and set `APP_DEBUG=false` outside local development

## Troubleshooting

### The Installer Does Not Load

```bash
docker compose ps
docker compose logs -f app
```

Check that the app is reachable on `http://localhost:8080`.

### Database Connection Errors

- confirm the database host is `db`
- inspect the DB container logs:

```bash
docker compose logs -f db
```

- if the MariaDB volume was created earlier with different credentials, the stored database user/password may not match your current environment values
- for a disposable local setup, a full reset is:

```bash
docker compose down -v
docker compose up -d --build
```

Only do this if you intentionally want to delete the database data.

### The App Cannot Write Runtime Config or Uploads

Restart the stack so Docker recreates the named volumes:

```bash
docker compose down
docker compose up -d --build
```

The application writes installer config to `storage/config/app.env`, not to a mutable host deployment path.

### Reservation Calendar Does Not Load Correctly

- check browser console errors
- confirm `/reservations/feed` or `/availability/feed` returns data
- inspect app logs:

```bash
docker compose logs -f app
```

### Mail Notifications Do Not Send

- keep `MAILJET_ENABLED=false` if mail is not configured
- verify the Mailjet API values in runtime config
- inspect `storage/logs/`

### Turnstile Validation Fails

- verify the site key and secret
- confirm your configured app URL matches the Turnstile domain settings

## Contributing

Contributions are welcome.

- open an issue for bug reports or feature proposals
- open a pull request for focused improvements
- keep Docker Compose as the only supported runtime model

See [CONTRIBUTING.md](CONTRIBUTING.md) for the short contribution guide.

## License

No license file is currently included. Until a license is added, treat the project as all rights reserved by default.
