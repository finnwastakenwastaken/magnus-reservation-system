# MAGNUS Reservation System

MAGNUS Reservation System is a Docker Compose-first PHP and MariaDB application for managing reservations for a shared living room in an apartment complex. It combines resident signup and mailbox activation, a dark reservation calendar, conversation-style messaging, privacy controls, and a staff permission system in a framework-free codebase.

## Overview

The project is intentionally simple to operate:

1. clone the repository
2. start the Docker Compose stack
3. open the web installer
4. create the first administrator
5. manage bookings, users, roles, branding, and integrations from the browser

Docker Compose is the only supported runtime and deployment model.

## Features

- Resident signup with mailbox-delivered activation codes
- Dark, interactive reservation calendar with privacy-safe booking labels
- Public availability view without resident identities
- Server-side overlap, quota, and opening-hours enforcement
- Conversation-style internal messaging with direct replies
- Admin broadcast messaging to all users or selected roles
- In-app notifications with optional Mailjet email delivery
- Account privacy controls, profile pictures, and self-service account deletion
- Role and permission management with manual user verification
- Admin-managed site logo upload
- Audit logging for resident, staff, and settings changes
- Dutch and English translations
- First-run installer built for Docker Compose

## Requirements

- Docker Engine 24+ or Docker Desktop with the Compose plugin
- Git
- A modern browser

You do not need PHP, Apache, Nginx, or MariaDB installed on the host.

## Installation

### Clone the repository

```bash
git clone https://github.com/finnwastakenwastaken/magnus-reservation-system.git
cd magnus-reservation-system
```

### Review the example environment file

The repository includes a safe [.env.example](.env.example). In most cases you can start the stack without creating `.env`, because the installer writes durable runtime configuration to `storage/config/app.env`.

If you want to override Docker defaults before the first run:

```bash
cp .env.example .env
```

Never commit `.env`, `storage/config/app.env`, or any real credentials.

### Start the stack

```bash
docker compose up -d --build
```

Open the application at:

```text
http://localhost:8080
```

## Configuration

### Runtime storage

- installer/runtime config: `storage/config/app.env`
- install lock: `storage/installed.lock`
- app storage volume: `app_storage`
- upload volume: `app_uploads`
- database volume: `mariadb_data`

### Environment variables

The main Docker/runtime variables are:

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

Mailjet and Cloudflare Turnstile can still be defined in environment variables as bootstrap or recovery fallbacks, but normal administration now happens in the admin panel after installation.

Use placeholder values such as:

- `your-domain.example`
- `your_mailjet_api_key`
- `your_mailjet_api_secret`
- `your_turnstile_site_key`
- `your_turnstile_secret_key`

## First-Run Installer

On a fresh stack the app redirects automatically to `/install`.

Recommended defaults inside Docker Compose:

- database host: `db`
- database port: `3306`
- database name: `living_room`
- database username: `living_room`
- database password: `change_me_database_password`

The MariaDB container already creates the configured database and application user. The installer then:

- verifies the database connection
- applies the schema and bundled migrations
- creates the first administrator account
- writes runtime configuration to `storage/config/app.env`
- creates `storage/installed.lock`

After installation, `/install` is blocked until you intentionally reset the environment in development.

## Usage

### Home and access flow

- guests see a public landing page and public availability calendar
- authenticated users land directly on the reservation calendar
- authenticated users are redirected away from login, signup, and activation pages

### Reservation calendar

- the main resident view is a dark day/week/list planner
- drag across the grid to select a time range and create a reservation
- reserved times are shown as blocked
- your own reservations can be cancelled directly from the calendar
- the public availability view shows only unavailable time ranges, never personal data

Server-side rules remain authoritative:

- reservations must be in the future
- reservations must stay within configured booking hours
- reservations cannot overlap
- weekly limits use ISO weeks
- monthly limits use calendar months

### Messaging and notifications

- resident messaging is grouped into conversation threads
- replies happen inside the same conversation screen
- admins can send broadcast messages to all users or selected roles
- users always receive in-app notifications
- if Mailjet is configured in the admin panel, email delivery is attempted in addition to in-app notifications

### Roles and permissions

The app uses one primary role per user, with permissions assigned to roles.

Default roles:

- `user`
- `manager`
- `admin`

Administrators can:

- create and edit roles
- assign permissions to roles
- assign roles to users
- manually verify pending users with the `users.verify` permission

The protected administrator path remains guarded so the last privileged administrator cannot be locked out accidentally.

### Integrations

Mailjet and Cloudflare Turnstile are configured from the admin panel:

- `Admin -> Integrations`

Database-backed settings are the active source of truth after installation. Environment variables remain fallback-only.

## Updating

Docker Compose is the only supported update path.

### Standard update flow

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
- inspect logs after an update with:

```bash
docker compose logs -f app
docker compose logs -f db
```

The in-app updater page is informational only and intentionally does not mutate the deployment.

## Privacy and Security Notes

- `.env` must never be committed
- `storage/config/app.env` contains runtime secrets and must remain out of version control
- the repository does not include real resident data, credentials, or private contact information
- guest-facing availability views never expose names, apartment numbers, emails, or profile pictures
- activation codes are not shown back to residents after signup; they are intended for physical mailbox delivery
- private-message oversight for staff requires an explicit reason and every access is logged
- use HTTPS and set `APP_DEBUG=false` outside local development

## Troubleshooting

### The installer does not load

```bash
docker compose ps
docker compose logs -f app
```

### Database connection errors

- confirm the host is `db`
- inspect the MariaDB logs:

```bash
docker compose logs -f db
```

- if the MariaDB volume was created earlier with different credentials, the stored user/password may no longer match your current environment values
- for a disposable local reset:

```bash
docker compose down -v
docker compose up -d --build
```

Only do this if you intentionally want to delete database data.

### Runtime config or uploads cannot be written

Restart the stack so Docker recreates the named app volumes:

```bash
docker compose down
docker compose up -d --build
```

### Calendar does not render correctly

- inspect the browser console
- check `/reservations/feed` or `/availability/feed`
- inspect the app logs:

```bash
docker compose logs -f app
```

### Mail notifications do not send

- verify Mailjet settings in `Admin -> Integrations`
- leave Mailjet disabled if you do not intend to send email
- inspect `storage/logs/`

### Turnstile validation fails

- verify Turnstile settings in `Admin -> Integrations`
- confirm the configured site matches your Turnstile domain settings

## Contributing

Contributions are welcome.

- open an issue for bugs or feature proposals
- keep Docker Compose as the only supported runtime model
- keep changes framework-free unless there is a strong operational reason otherwise

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

No license file is currently included. Until a license is added, treat the project as all rights reserved by default.
