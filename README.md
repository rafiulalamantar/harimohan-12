# Harimohan'12 Alumni Portal

Ex-student registration with admin approval, a public alumni directory, a member portal with notices, and event fee payment through **bKash Tokenized Checkout**. Laravel 13, PHP 8.4, Tailwind CSS v4, mobile-first.

Live: https://harimohan-12.vercel.app · New to the code? Read this file, then **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.

## Features

**Visitors** (welcome page)
- Hero banner, latest notices, and the alumni directory of approved members: photo, SSC batch, job, blood group, LinkedIn / Facebook
- Search by name, job or batch and filter by batch; phone numbers only for logged-in approved members ("Log in to contact")

**Members**
- Register with name, email, 11-digit mobile (01XXXXXXXXX), SSC batch, optional details and photo, plus any extra questions the admin adds
- Choose a photo and position it in a card-shaped frame (drag, zoom, pinch); iPhone HEIC photos are converted automatically
- Log in with email **or** mobile number; logins are remembered for 10 days; **Forgot password** by email
- Portal: account status, current event, notices (pinned first), payment history, My Profile
- Pay the event fee with bKash once approved (switched off until bKash goes live)

**Admins** (`/admin`, menu grouped into Members, Events and Communication)
- Dashboard: registrations, paid count, money collected, batch chart; a 🔔 bell for new registrations
- **Students**: search, filter by status, batch or any answer, approve / reject, answer columns and totals (e.g. T-shirt size XL: 12), CSV export, **Adjust card photo**
- **Student fields**: extra questions as short text, number, long text, date, dropdown, radio buttons, checkboxes or an image only (e.g. a size chart)
- **Announcements** with banner, pin, schedule and draft; **Email members** (one copy per member, Brevo daily limit shown); **Welcome page** hero editor
- **Events** and **Payments** (filters, TrxID search, CSV export)
- Super admins can make approved students admins

## Run it on your computer

You need PHP 8.4, Composer, Node.js 22 and MySQL 8 (or SQLite for a quick look).

```bash
git clone https://github.com/rafiulalamantar/harimohan-12.git && cd harimohan-12
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan storage:link        # serves uploaded photos and banners from storage/app/public
```

`.env.example` uses SQLite. For MySQL (closer to the live site), create a database and set:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=harimohan_local
DB_USERNAME=root
DB_PASSWORD=your-local-password
```

Create the tables and data, then start the app:

```bash
php artisan migrate --seed                          # tables, the admin, a demo member, a sample event and notice
php artisan db:seed --class=DemoAlumniSeeder        # optional: 120 realistic demo alumni
composer run dev                                    # app + Vite at http://localhost:8000
```

| Login (local only) | Email | Password |
|---|---|---|
| Super admin | `ADMIN_EMAIL` in `.env` (default `admin@school.test`) | `ADMIN_PASSWORD` (default `ChangeMe@123`) |
| Demo member | `student@school.test` | `Student@123` |
| Demo alumni (DemoAlumniSeeder) | `alumni001@example.com` … `alumni120@example.com` | `password` |

The demo seeders refuse to run in production. Locally, emails (password resets, member emails) are written to `storage/logs/laravel.log` instead of being sent.

## Everyday commands

```bash
composer run dev                       # run the app with live CSS/JS reloading
php artisan test --compact             # all tests (in-memory SQLite, no setup needed)
php artisan test --compact --filter=StudentFieldTest
vendor/bin/pint --dirty                # format the PHP files you changed
npm run build                          # production CSS/JS (needed if you are not running composer run dev)
php artisan route:list --except-vendor # every URL and its controller
```

If a page looks unstyled or you see "Unable to locate file in Vite manifest", run `npm run build` or `composer run dev`.

## How we work

1. Create a branch from `main` (`feat/...`, `fix/...`, `refactor/...`).
2. Make the change with a test, run `vendor/bin/pint --dirty` and `php artisan test --compact`.
3. Push the branch; GitHub Actions runs Pint, the tests and the asset build.
4. When CI is green, merge into `main` with `git merge --no-ff` and push. **Pushing `main` deploys the live site.** Run new migrations on production first.
5. For a release, bump `version` in `config/app.php` and push an annotated tag `vX.Y.Z` whose message holds the release notes. CI publishes the GitHub release and updates `CHANGELOG.md`.

Where code belongs, naming rules and the main flows (photos, payments, student fields) are explained in **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.

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
php artisan test --compact
```

Feature tests live in `tests/Feature`, one file per area: `ReunionFlowTest` (registration, login, approval, directory privacy, bKash payments with `Http::fake`, admin menu, security headers), `StudentFieldTest` (extra fields, answers, Students-page filters and totals), `PhotoThumbnailTest` (card thumbnails and admin re-framing), `MemberEmailTest`, `PasswordResetTest`, `UploadCachingTest`, `ErrorPagesTest`, `DatabaseSeederTest`.

## Deploy to Vercel

Vercel has no PHP runtime of its own, so this uses the community [`vercel-php`](https://github.com/vercel-community/php) runtime (`vercel.json`, `api/index.php`). Vercel functions are serverless: the disk is wiped after each request, so the database and uploads must live elsewhere.

**1. S3 driver:** `league/flysystem-aws-s3-v3` (used for Supabase Storage) is already in `composer.json`; Vercel installs it with the other packages.

**2. Database (MySQL-compatible):** create a free [TiDB Cloud](https://tidbcloud.com) Starter cluster in the Singapore region. From its *Connect* dialog copy host, port (`4000`), user (`xxxx.root`) and password, and create a database (`CREATE DATABASE school_reunion;`). SSL is set in `vercel.json` (`MYSQL_ATTR_SSL_CA`).

**3. Uploads:** create a free [Supabase](https://supabase.com) project (no card needed). Under *Storage* create a **public** bucket (e.g. `uploads`), then under *Storage → S3* copy the endpoint and region and create an access key. Any S3-compatible store works; Cloudflare R2 also does, but needs a card on file.

**4. Import the GitHub repo in Vercel**, leave *Framework* as **Other**, and add these Environment Variables:

```
APP_NAME="Harimohan'12 Alumni"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                # php artisan key:generate --show
APP_URL=https://your-project.vercel.app

DB_CONNECTION=mysql
DB_HOST=gateway01.ap-southeast-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=school_reunion
DB_USERNAME=xxxx.root
DB_PASSWORD=...

AWS_ACCESS_KEY_ID=...             # Supabase Storage → S3 access key
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-southeast-2 # the project's region
AWS_BUCKET=uploads
AWS_ENDPOINT=https://<project-ref>.storage.supabase.co/storage/v1/s3
AWS_URL=https://<project-ref>.supabase.co/storage/v1/object/public/uploads
AWS_USE_PATH_STYLE_ENDPOINT=true

MAIL_* ...                        # see Email above
BKASH_* ...                       # see bKash above
```

`vercel.json` already sets the serverless-specific values: caches and compiled views in `/tmp`, logs to stderr, queue `sync`, trusted proxies, the S3 upload disk and the TLS CA for TiDB.

**5. Create tables once, from your computer**, with the production database values in a local `.env.production`-style shell:

```bash
DB_CONNECTION=mysql DB_HOST=... DB_PORT=4000 DB_DATABASE=school_reunion DB_USERNAME=... DB_PASSWORD=... \
MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem ADMIN_PASSWORD='a-strong-password' \
php artisan migrate --seed --force
```

(`/etc/ssl/cert.pem` is the macOS CA bundle.) Run `php artisan migrate --force` the same way after pulling new migrations.

**Limits to know:** Vercel rejects request bodies over 4.5 MB, so keep photo uploads small; preview deployments get their own URL that does not match `APP_URL`, so test on the production URL.

## License

MIT. See [LICENSE](LICENSE).
