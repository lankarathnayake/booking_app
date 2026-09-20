# Booking App

A self-hosted appointment booking system. An admin defines **services**, each with its own
**booking form fields**, **weekly schedule** and **blackout dates**, then issues **one-time links**
that customers use to book a slot. Bookings and confirmation emails are handled automatically.

Plain PHP + MySQL/MariaDB, no framework, no build step.

## Features

- **Services** - create, edit, deactivate, archive; per-service duration, lead time and booking window
- **Custom form fields per service** - text, textarea, number, date, time, date/time via dropdowns,
  email, phone, select, radio, checkbox, with per-field validation rules (length, value range,
  minimum/maximum age, custom regex) and custom error messages
- **Scheduling** - default weekly schedule per service, per-week overrides (edit, clear, or restore a
  specific week), and blackout dates
- **One-time links** - each tied to one service, single use, generated in the admin or via the API
- **Atomic slot booking** - a database constraint makes double-booking a slot impossible, even under
  simultaneous requests; cancelling a booking frees the slot
- **Customizable confirmation email** - one rich-text template (with placeholders and an optional logo)
  sent to both staff and customer
- **Admin dashboard** - bookings list with filters and CSV export, links, services, settings
- **API** - server-to-server endpoint to generate one-time links

## Requirements

- PHP with the `pdo_mysql` extension - developed and tested on PHP 8.2; no PHP 8-only syntax is used,
  so 7.3+ should work but is untested
- MySQL 5.7.6+ or MariaDB 10.2.1+ (the schema uses a stored generated column)
- Apache with `mod_rewrite`, `mod_headers` and `AllowOverride All` (the `.htaccess` blocks access to
  code/config directories - see [Deployment](#deployment))

## Installation

1. **Create the database** and load the schema:

   ```sql
   CREATE DATABASE booking_app CHARACTER SET utf8mb4;
   ```
   ```bash
   mysql -u <user> -p booking_app < db/schema.sql
   ```

2. **Configure.** Copy the example config and fill it in:

   ```bash
   cp config.local.example.php config.local.php
   ```

   `config.local.php` is git-ignored and holds your database credentials, public URL, timezone,
   SMTP settings and API key. **Never commit it.**

3. **Create the first admin user** (CLI only):

   ```bash
   php db/seed_admin.php "Your Name" "you@example.com" "a-strong-password"
   ```

4. Browse to your app URL and sign in at `/admin/signin.php`.

## How it works

1. In **Services**, create a service, then configure its **Fields**, **Schedule** (default weekly
   slots), **Weeks** (per-week overrides) and **Blackouts**.
2. In **One-Time Links**, generate links for that service and send them to customers (or generate
   them through the API).
3. The customer opens the link, picks an available date and time, fills in the service's form and
   submits. The link is consumed and confirmation emails go to staff and the customer.
4. Manage everything afterwards under **Bookings**.

Email content is edited under **Settings > Booking Confirmation Email**. Placeholders:
`{{service_name}}`, `{{appointment_date}}`, `{{appointment_time}}`, `{{booking_reference}}`,
`{{business_name}}`, `{{submitted_fields}}`.

## API

`POST /api/links/generate.php` returns freshly generated one-time links.

**Auth** - send your `API_KEY` (from `config.local.php`) as an `X-API-Key` header or an
`Authorization: Bearer <key>` header. If `API_KEY` is blank the API is disabled.

**Body** (JSON or form-encoded):

| Field          | Description                                   |
| -------------- | --------------------------------------------- |
| `service_id`   | ID of an active service (or use `service_slug`) |
| `service_slug` | Slug of an active service                     |
| `count`        | Number of links, 1-100 (default 1)            |
| `note`         | Optional label shown in the admin Links page  |

```bash
curl -X POST https://book.example.com/api/links/generate.php \
  -H "X-API-Key: YOUR_KEY" -H "Content-Type: application/json" \
  -d '{"service_slug":"consultation","count":2,"note":"order #1042"}'
```

```json
{
  "success": true,
  "service": { "id": 1, "name": "Consultation", "slug": "consultation" },
  "count": 2,
  "links": [
    { "code": "848c084ab976b99c", "url": "https://book.example.com/book/index.php?link=848c084ab976b99c" },
    { "code": "eaddf2823cadfd0c", "url": "https://book.example.com/book/index.php?link=eaddf2823cadfd0c" }
  ]
}
```

Errors return `{"success": false, "message": "..."}` with status 400 (bad input / inactive
service), 401 (bad or missing key), 404 (unknown service) or 405 (not a POST).

## Deployment

- **Serve over HTTPS.** Session cookies are marked `Secure` automatically when the request is HTTPS.
- **Keep `.htaccess` active.** It denies web access to `core/`, `common/`, `vendor/`, `db/` and all
  config files. Verify after deploying: `https://your-host/config.php` and `/core/Sql.php` must
  return 403. On nginx, port these rules to `location` blocks yourself.
- **Behind a reverse proxy?** Login throttling keys on `REMOTE_ADDR`, so configure the proxy to pass
  the real client IP (e.g. Apache `mod_remoteip`); otherwise all visitors share one counter.
- **Use a strong, unique `API_KEY`** and different credentials from your development setup.

## Security notes

Built in: prepared statements throughout, `password_hash()` for admin passwords, CSRF tokens on all
admin POSTs, `HttpOnly` + `SameSite=Lax` session cookies, database-backed login throttling
(5 failures per IP / 10 per account per 15 minutes), server-side re-validation of every form field,
timing-safe API key comparison, and email failures that can never fail a booking.

Not built in: rate limiting on the public booking endpoints (a link is single-use and unguessable, which
bounds abuse), two-factor auth, or per-user roles (all admins have full access).

## License

[MIT](LICENSE). Free to use, modify and redistribute, provided the copyright notice and license text are
kept in all copies - that is the attribution requirement.

Bundled third-party software: [PHPMailer](https://github.com/PHPMailer/PHPMailer) (LGPL 2.1, see
`vendor/PHPMailer/LICENSE`). Loaded at runtime from CDNs: Bootstrap, Bootstrap Icons and jQuery (MIT),
and TinyMCE (GPL-2.0-or-later).
