# Changelog

## 0.3.0

- Moved Mailjet and Cloudflare Turnstile administration into the admin panel with database-backed settings and masked secrets
- Added manual resident verification permission and staff actions for pending accounts
- Expanded audit logging for messaging, settings, verification, privacy changes, and oversight actions
- Reworked resident messaging into conversation threads with direct replies and admin broadcast messaging
- Replaced the broken CDN-dependent calendar with a bundled dark calendar UI that works without FullCalendar assets
- Clarified Docker Compose as the only supported deployment and update path

## 0.2.0

- Added resident privacy/account settings with data export, email-change verification, password change, and self-service account deletion
- Added privacy-safe resident directory and legal/privacy pages
- Added privacy-oriented schema fields for optional profile visibility, pending email changes, and anonymized account state
- Added retention cleanup for stale unactivated accounts, expired reset data, rate-limit records, and old update backups

## 0.1.0

- Initial public MVP release
- Installer wizard
- Reservations, messaging, admin panel
- GitHub-aware in-app updater foundation
