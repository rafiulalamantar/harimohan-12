# School Alumni Reunion Portal (Laravel 13)

Ex-batch student registration, student portal with announcements, and reunion fee payment through **bKash Tokenized Checkout**.

## Features

**Student**
- Register with name, email, mobile, SSC batch, roll, profession, address
- Login with email **or** mobile number
- Portal: account status, current reunion info, notice board (pinned first)
- Pay reunion fee with bKash (only after admin approval) — one payment per reunion
- Payment history with bKash TrxID; profile edit

**Admin** (`/admin`)
- Dashboard: total / pending registrations, paid count, total collected, batch-wise chart
- Students: search, filter by batch/status, approve / reject, CSV export (Bangla-safe UTF-8)
- Announcements: create, edit, pin, schedule for later, draft
- Reunion events: title, date, venue, fee, deadline, active toggle (fee locked once payments exist)
- Payments: filter by status/event, search by TrxID/invoice/name/phone, CSV export

## Setup

Requirements: PHP 8.3+, Composer, MySQL 8 (or SQLite for a quick try).

```bash
composer install
cp .env.example .env
php artisan key:generate
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
php artisan migrate --seed     # creates tables, the admin user, a sample reunion and notice
php artisan serve              # http://localhost:8000
```

Admin login: the `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` (default `admin@school.test` / `ChangeMe@123` — change it).

UI uses the Tailwind CDN, so no `npm` build is needed.

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

`tests/Feature/ReunionFlowTest.php` covers registration, phone login, approval gate, successful payment (and that a callback refresh doesn't re-execute), amount mismatch rejection, execute→query fallback, cancel, admin access control, and scheduled announcements. bKash is mocked with `Http::fake`.

## Deploy checklist

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS on
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
app/Http/Controllers/Admin/*            admin panel
app/Models/{User,Announcement,ReunionEvent,Payment}.php
database/migrations/                    users (+alumni fields), announcements, reunion_events, payments
resources/views/                        Blade + Tailwind
```
