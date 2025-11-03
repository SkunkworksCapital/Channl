# Channl

Omnichannel micro-CRM to send and track SMS, WhatsApp and Email with simple lists and templates. Built with plain PHP (no framework), MySQL, and minimal JS.

## Features
- Auth: register/login, secure sessions, CSRF protection
- Two-factor auth (TOTP) optional per user
- Contacts and Lists
  - Import CSV contacts, manage lists, per-list default templates
  - Lists are channel-specific (SMS or Email)
- Templates
  - Separate pages: SMS Templates and Email Templates
  - Library of Twilio-friendly SMS templates; one-click import
  - Personalization placeholders: `{{name}}`, `{{first_name}}`, `{{email}}`, `{{phone}}`, `{{country}}`
- SMS (Twilio)
  - Single test send; template-based bulk send to a list
  - Autofill from list default or select another template
  - Recipients preview modal
  - Inbound webhook to capture replies; Inbox view
  - Manual sync from Twilio Messages API
- WhatsApp (Meta Cloud API)
  - Single send; webhook to capture inbound; Inbox view
- Email (SMTP or SendGrid)
  - Single test send; template-based bulk send to a list
  - Autofill subject/body; recipients preview modal
  - Supports SMTP (default) or SendGrid v3 API
  - Schedule sends for the future; honors quiet hours and daily caps
- Approvals (admin): review/approve or reject pending outbound before send
- Billing: simple balance/credits with per-channel rate estimates
- Exports (admin): CSV export for audits and messages
- Dashboard
  - Contacts, Lists, aggregate Messages
  - Per-channel counts: sent and failed for SMS/Email

## Tech Stack
- PHP 7.4+ (PDO MySQL, cURL, JSON)
- MySQL/MariaDB
- Plain views (no framework), lightweight routing (`core/router.php`)

## Repository Layout
- `core/` bootstrapping, config, router, helpers, http client
- `core/audit.php` centralized audit logging helpers
- `core/notify.php` lightweight in-app/email notifications
- `core/twofactor.php` TOTP helpers for optional 2FA
- `controllers/` feature controllers
- Admin/aux controllers: `ApprovalsController.php`, `BillingController.php`, `ExportsController.php`, `SettingsController.php`, `ApiController.php`
- `services/` provider clients (Twilio, SendGrid, WhatsApp Cloud)
- `views/` dark-themed pages
- `sql/schema.sql` reference schema (app also creates tables lazily)
- `content/sms_library/` curated SMS template library

## Scheduling, Quiet Hours, and Caps
- Schedule future sends on SMS and Email forms using "Send at (local)".
- Set your time zone, quiet hours, and optional daily caps in `Settings`.
- A worker processes due jobs and enforces quiet hours and caps. Run it via cron:
```bash
* * * * * php /path/to/Channl/bin/scheduler.php >> /var/log/channl_scheduler.log 2>&1
```
Notes
- Quiet hours defer sends to quiet end in your local time.
- Daily caps (0 = unlimited) postpone excess to the next day.

## Requirements
- PHP extensions: `pdo_mysql`, `curl`, `json`, `session`
- MySQL 5.7+ / MariaDB 10.3+
- Web server (Apache/Nginx) or PHP built-in server for local dev

## Quick Start (Local)
1. Clone and configure env
```bash
cp .env.example .env  # create and edit, or create .env from the sample below
```
2. Start MySQL and create a database/user, then update `.env`.
3. Run with PHP built-in server (for local only):
```bash
php -S localhost:8000 -t public
```
4. Open `http://localhost:8000` → Register an account → Start using.

## Environment Variables (.env)
The app loads `BASE_PATH/.env` on boot. These are the main variables (all optional unless noted):

Application
- `APP_ENV` dev|prod (default prod)
- `CSRF_KEY` random 32+ chars

Database
- `DB_HOST` (default 127.0.0.1)
- `DB_PORT` (default 3306)
- `DB_NAME` (required)
- `DB_USER` (required)
- `DB_PASS` (required)
- `DB_CHARSET` (default utf8mb4)

Email
- `EMAIL_MODE` smtp|sendgrid (default smtp)
- `EMAIL_HOST` (SMTP)
- `EMAIL_PORT` (SMTP, default 587)
- `EMAIL_USER` (SMTP)
- `EMAIL_PASS` (SMTP)
- `SENDGRID_API_KEY` (SendGrid)
- `EMAIL_FROM` sender address

SMS (Twilio)
- `SMS_PROVIDER` twilio (default)
- `SMS_SID` Twilio Account SID
- `SMS_TOKEN` Twilio Auth Token
- `SMS_FROM` E.164 (e.g. +15551234567) OR Messaging Service SID `MGxxxxxxxx...`

WhatsApp (Meta Cloud)
- `WHATSAPP_ACCESS_TOKEN`
- `WHATSAPP_PHONE_NUMBER_ID`
- `WHATSAPP_VERIFY_TOKEN` webhook verification token

Optional pricing estimates
- `RATE_SMS`, `RATE_WHATSAPP`, `RATE_EMAIL`

## Routing Overview
- Home/dashboard: `/`
- Auth: `/login`, `/register`, `/logout`
- Settings: `/settings` (GET view, POST update)
- Contacts: `/contacts`, `/contacts/upload`, `/contacts/new`
- Lists: `/lists`, `/lists/{id}` (channel-specific), `POST /lists/{id}/members`, `GET /lists/{id}/members.json`
- Templates:
  - All: `/templates`
  - SMS only: `/templates/sms`
  - Email only: `/templates/email`
- SMS:
  - Send: `/sms/send` (single + list from template)
  - Inbox: `/sms/inbox`
  - Webhook: `POST /webhooks/twilio/sms`
  - Sync: `POST /sms/sync`
- Email:
  - Send: `/email/send` (single + list from template)
  - Inbox: `/email/inbox`
- WhatsApp:
  - Send: `/whatsapp/send`
  - Inbox: `/whatsapp/inbox`
  - Webhook: `GET/POST /webhooks/whatsapp`
- Billing: `/billing`, `/billing/buy` (GET form, POST purchase)
- Approvals (admin): `/approvals`, `POST /approvals/{id}/approve`, `POST /approvals/{id}/reject`
- Exports (admin): `/exports/audit.csv`, `/exports/messages.csv`
- API: `/api/balance`
- Health: `/health/db` (append `?verbose=1` for JSON)

## Templates and Personalization
Use placeholders in SMS and Email templates; they are replaced per recipient on bulk sends:
- `{{name}}` full name
- `{{first_name}}` first token of name
- `{{email}}`, `{{phone}}`, `{{country}}`

For SMS deliverability:
- Identify your brand early
- Use full domain links (no generic shorteners)
- Include `STOP` and optionally `HELP`

## Providers & Webhooks
### Twilio SMS
- Set `SMS_SID`, `SMS_TOKEN`, `SMS_FROM`
- Messaging webhook: `POST https://YOUR_DOMAIN/webhooks/twilio/sms`
- Messages API sync available via `/sms/sync`

### WhatsApp Cloud
- Set `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`
- Webhook URL: `https://YOUR_DOMAIN/webhooks/whatsapp`
- Verify token: set `WHATSAPP_VERIFY_TOKEN`; Meta will call with `hub.challenge`

### Email (SMTP or SendGrid)
- SMTP: set host/port/user/pass; `EMAIL_MODE=smtp`
- SendGrid: set `SENDGRID_API_KEY`; `EMAIL_MODE=sendgrid`

## Database
The app lazily creates tables when a feature is used, and you can also apply the reference DDL in `sql/schema.sql`.

Tables of note
- `contacts`, `contact_lists`, `contact_list_members`
- `message_templates`
- `messages` (stores outbound, inbound/replies, provider ids, price/currency when available)
- `message_events` (http logs to providers)

## Security Notes
- CSRF tokens for all POST forms
- Sessions with secure headers in `core/security.php`
- `.env` values override host env safely (quotes supported)
- `.gitignore` excludes `.env` and logs
- Optional TOTP-based two-factor authentication (`core/twofactor.php`)
- Audit logging for sensitive actions (`core/audit.php`); admin approvals for outbound

## Running Behind Apache/Nginx
- Point your webroot to `public/` and enable rewrite to `public/index.php`
- Example Apache snippet is included in `public/.htaccess`

## Development Tips
- Set `APP_ENV=dev` to enable verbose error display
- Hard refresh for asset/style changes (Cmd/Ctrl+Shift+R)
- Use `/health/db?verbose=1` to inspect DB connectivity

## Deploy
- Configure env vars in your host (don’t commit `.env`)
- Ensure PHP `pdo_mysql` and `curl` extensions are enabled
- Set Twilio/WhatsApp/SendGrid credentials
- Add HTTPS endpoints for webhooks

## License
MIT (see `LICENSE` if present). If this file is missing, treat as all-rights-reserved until a license is added.

---
Questions or ideas? Open an issue or PR.