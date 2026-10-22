# Release Notes

## [Unreleased](https://github.com/rafiulalamantar/harimohan-12/compare/v1.1.1...main)

## [v1.1.1](https://github.com/rafiulalamantar/harimohan-12/compare/v1.1.0...v1.1.1) - 2026-10-22

- "bKash Payment Coming Soon" now shows in the homepage banner even when no event is active

## [v1.1.0](https://github.com/rafiulalamantar/harimohan-12/compare/v1.0.0...v1.1.0) - 2026-10-22

- Site name is now "Harimohan'12"
- Footer shows the app version, linking to its release
- "bKash Payment Coming Soon" shown on the event card and student dashboard while online payment is off
- Default homepage text and seeded notice no longer ask students to pay with bKash

## [v1.0.0](https://github.com/rafiulalamantar/harimohan-12/releases/tag/v1.0.0) - 2026-10-21

First release of the **Harimohan'12 alumni portal**, a Laravel 13 + Tailwind CSS v4 site for ex-students of Harimohan'12.

### Highlights

**Public welcome page**

- Hero banner, upcoming event and latest notice, all editable by the admin
- Alumni directory of approved students: name, photo, SSC batch, job, LinkedIn and Facebook links; searchable and filterable by batch
- Phone numbers are visible to approved members only

**Students**

- Registration with 11-digit mobile number (01XXXXXXXXX), photo, social links and admin-defined extra questions
- Login with email or mobile number (+880, spaces and dashes accepted)
- Forgot password by email
- Portal with account status, current event, notice board and payment history

**Admin**

- Dashboard, student approval / rejection, CSV exports
- Announcements (pin, banner, schedule), events, student fields, welcome page editor
- Every page works on phones

**Security**

- HTTPS redirect and https links in production
- Content-Security-Policy, HSTS, X-Frame-Options and other hardening headers
- Password reset links can't be spoofed through the Host header

### Not in this release

- **bKash payments are switched off.** Students see "Payment Process Under Construction" until `BKASH_ENABLED=true` is set, after bKash merchant credentials are in place.

### Deploying

See the **Deploy checklist** in the README: AutoSSL / Let's Encrypt on cPanel, `APP_ENV=production`, an `https://` `APP_URL`, SMTP (Brevo free plan works), `npm ci && npm run build`, `php artisan storage:link`, and a strong `ADMIN_PASSWORD`.

Licensed under MIT.
