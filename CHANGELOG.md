# Release Notes

## [Unreleased](https://github.com/rafiulalamantar/harimohan-12/compare/v1.3.2...main)

## [v1.3.2](https://github.com/rafiulalamantar/harimohan-12/compare/v1.3.1...v1.3.2) - 2026-10-22

### Fixes

- **Photo uploads no longer fail with "413 FUNCTION_PAYLOAD_TOO_LARGE".** Large photos (phone cameras are often 5–10 MB) are now resized in the browser to at most 1600px before upload, staying well under Vercel's 4.5 MB limit. Applies to registration, profile and admin image uploads.
- **Directory cards show the full job title.** Long "profession at company" lines were cut off after two lines; they now show in full.

### Changes

- **Roll number removed.** The field is gone from registration, the admin student page and the CSV export; a migration drops the users.roll_no column and all saved roll numbers.

## [v1.3.1](https://github.com/rafiulalamantar/harimohan-12/compare/v1.3.0...v1.3.1) - 2026-10-22

- Names may only contain letters (English, Bangla or any script), spaces, dots, hyphens and apostrophes
- Anything else (e.g. HTML or digits) gets "Name can only contain letters, spaces, dots, hyphens and apostrophes." on the register and profile forms
- All existing names already match

## [v1.3.0](https://github.com/rafiulalamantar/harimohan-12/compare/v1.2.2...v1.3.0) - 2026-10-22

- 🔔 Bell in every admin's header with the number of registrations waiting for approval, shared by all admins
- Clicking it lists the 8 newest registrations: **Approve** right there for pending ones, "Approved by *admin* · *time*" or "Rejected by *admin*" for the rest
- If another admin already approved a student, approving again changes nothing and says who did it
- "View all pending" opens the Students list filtered to pending
- Who handled each registration is now recorded (`reviewed_by`, `reviewed_at`)
- Works from 320 px phones to desktop; keyboard and screen-reader friendly

## [v1.2.2](https://github.com/rafiulalamantar/harimohan-12/compare/v1.2.1...v1.2.2) - 2026-10-22

- Admin-only accounts that open the student portal are sent to the admin panel with "The student portal is only for alumni accounts." Student admins keep their portal.
- Student dashboard greeting is now "Roll Call: *name* - Yes, Sir! 🔥"

## [v1.2.1](https://github.com/rafiulalamantar/harimohan-12/compare/v1.2.0...v1.2.1) - 2026-10-22

- After login the header shows 🛡️ **Super admin** or 🛡️ **Admin** (students who are admins too); plain students see no badge
- The ☰ menu starts with "Logged in as <name>" and the admin level
- Below 400 px the badge shows only 🛡️; screen readers still hear the level
- The full menu row appears from 1280 px, so the admin menu never wraps or overflows

## [v1.2.0](https://github.com/rafiulalamantar/harimohan-12/compare/v1.1.2...v1.2.0) - 2026-10-22

- Super admins (the existing admin accounts) get **Make admin** / **Remove admin** on an approved student's page
- A promoted student keeps their portal and directory listing, and switches between Portal and Admin panel from the menu
- An admin student must lose admin access before being rejected or set back to pending
- Payments close once the event day has passed, even if the payment deadline is later
- A student opening an admin page is sent to their portal with a message instead of a bare 403
- Students table shows an Admin badge; profile heading reads "My Profile"

## [v1.1.2](https://github.com/rafiulalamantar/harimohan-12/compare/v1.1.1...v1.1.2) - 2026-10-22

- Alumni directory photos are cropped from the top, so heads are no longer cut off on tall or square portraits

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
