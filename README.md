# Harimohan'12 Alumni Portal (Laravel 13)

Ex-student registration, admin approval, a public alumni directory, a student portal with notices, and event fee payment through **bKash Tokenized Checkout**. Built with Laravel 13 and Tailwind CSS v4, mobile-first.

## Features

**Public welcome page**
- Hero banner and upcoming event (editable by admin), latest notice
- Alumni directory of approved students, searchable and filterable by batch: name, photo, SSC batch, job, LinkedIn and Facebook links when filled in
- Phone numbers are shown to approved members only; guests see "Log in to contact"

**Student**
- Register with name, email, 11-digit mobile (01XXXXXXXXX), SSC batch, roll, profession, company, address, photo, LinkedIn / Facebook, plus any extra questions the admin adds
- Login with email **or** mobile number (`+880`, spaces and dashes are accepted)
- **Forgot password**: reset link by email
- Portal: account status, current event, notice board (pinned first)
- Pay event fee with bKash (only after admin approval), one payment per event
- Payment history with bKash TrxID (cards on phones); profile edit

**Admin** (`/admin`)
- Dashboard: total / pending registrations, paid count, total collected, batch-wise chart
- Students: search, filter by batch/status, approve / reject, CSV export (Bangla-safe UTF-8)
- Student fields: extra questions for students (text, number, long text, dropdown, optional reference image)
- Announcements: create, edit, pin, banner image, schedule for later, draft
- Events: title, date, venue, fee, deadline, active toggle
- Payments: filter by status/event, search by TrxID/invoice/name/phone, CSV export
- Welcome page: edit the hero heading, text and banner
- Every list works on phones (cards below the `md` breakpoint, tables above)

## Setup

Requirements: PHP 8.3+, Composer, Node.js (for the Tailwind/Vite build), MySQL 8 (or SQLite for a quick try).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan storage:link       # serves uploaded photos and banners
```

Edit `.env` for MySQL:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_reunion
DB_USERNAME=root
DB_PASSWORD=
```

Set the admin login and bKash credentials (see below), then:

```bash
php artisan migrate --seed     # creates tables, the admin user, a sample event and notice
composer run dev               # app + Vite at http://localhost:8000
```

Admin login: the `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` (default `admin@school.test` / `ChangeMe@123`; change it).

The UI is Tailwind CSS v4 built with Vite. Run `npm run build` before deploying (or after pulling CSS/view changes without `npm run dev` running).

## Email (password reset)

Locally `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log`, so reset links can be copied from there.

In production use any SMTP provider. With the **Brevo free plan** (300 emails/day):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<Brevo SMTP login>
MAIL_PASSWORD=<Brevo SMTP key>
MAIL_FROM_ADDRESS=<a sender verified in Brevo>
```

Gmail SMTP also works (`smtp.gmail.com`, port 587, an App Password). Reset links expire after 60 minutes.

## bKash integration

Uses **PGW Tokenized Checkout v1.2.0-beta** (`app/Services/BkashService.php`):

1. `grant token` (cached ~50 min)
2. `create` → student is redirected to `bkashURL`
3. bKash redirects back to `/payment/bkash/callback?paymentID=..&status=success|failure|cancel`
4. `execute`; if execute errors or times out, `payment/status` query is used to confirm
5. Payment is marked **completed** only if bKash says `Completed` **and** the amount and invoice match our record. The row is locked during this, so a page refresh can't double-execute.

`.env`:

```
BKASH_SANDBOX=true
BKASH_USERNAME=...
BKASH_PASSWORD=...
BKASH_APP_KEY=...
BKASH_APP_SECRET=...
```

- **Sandbox**: get sandbox credentials from bKash (merchant / PGW integration team). Sandbox test wallets & OTP/PIN are in their sandbox docs.
- **Live**: you need a bKash merchant account with Tokenized Checkout enabled. Set `BKASH_SANDBOX=false` and the live credentials. `APP_URL` must be your real **HTTPS** domain because the callback URL is built from it — bKash can't redirect to `localhost` in live.
- bKash usually asks for a **sandbox test video / integration validation** before giving live keys. Run one success, one cancel, one failure, and one insufficient-balance case in sandbox and record them.

## Tests

```bash
php artisan test
```

- `tests/Feature/ReunionFlowTest.php`: registration, 11-digit mobile, phone login, approval gate, welcome directory privacy, successful payment (and that a callback refresh doesn't re-execute), amount mismatch rejection, execute→query fallback, cancel, admin access control, scheduled announcements, HTTPS redirect. bKash is mocked with `Http::fake`.
- `tests/Feature/PasswordResetTest.php`: reset link email, same reply for unknown emails, valid / invalid reset links.
- `tests/Feature/StudentFieldTest.php`: admin-defined student fields and clearing answers that no longer fit.

## Deploy checklist

- HTTPS on: in cPanel run **AutoSSL** (or install Let's Encrypt, both free). bKash live callbacks need HTTPS
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`. In production the app redirects `http://` to `https://`, builds every link with `https://`, and only answers to `APP_URL`'s host
- SMTP settings filled in (see **Email** above)
- `npm run build`, `php artisan storage:link`
- `php artisan config:cache route:cache view:cache`
- Point web root to `public/`; make `storage/` and `bootstrap/cache/` writable
- Change the admin password after first login (or set a strong `ADMIN_PASSWORD` before seeding)
- Daily DB backup (payments table is your money record)

## Structure

```
app/Services/BkashService.php           bKash API client
app/Http/Controllers/PaymentController  pay + callback
app/Http/Controllers/PortalController   student portal
app/Http/Controllers/AuthController     register / login
app/Http/Controllers/PasswordResetController  forgot / reset password
app/Http/Controllers/HomeController     public welcome page + alumni directory
app/Http/Controllers/Admin/*            admin panel
app/Http/Middleware/                    admin gate, rejected-user logout, HTTPS redirect, security headers
app/Models/{User,Announcement,ReunionEvent,Payment,StudentField,StudentFieldValue,Setting}.php
database/migrations/                    users (+alumni fields), announcements, reunion_events, payments, student fields, settings
resources/views/                        Blade + Tailwind v4 (resources/css/app.css)
```

## License

MIT. See [LICENSE](LICENSE).
