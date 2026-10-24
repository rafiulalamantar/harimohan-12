# How the Harimohan'12 Alumni Portal works

A guide for anyone starting on the code. Read the [README](../README.md) first to get the app running, then this file to find your way around.

## The big picture

A Laravel 13 app (PHP 8.4) with server-rendered Blade pages, Tailwind CSS v4 and a small amount of plain JavaScript. There is no front-end framework and no API: every page is a normal Laravel route that returns a view.

```
Browser ──► routes/web.php ──► middleware ──► controller ──► Form Request (validation)
                                                   │
                                                   ├──► models (Eloquent) ──► MySQL
                                                   ├──► services (bKash, photos, CSV, answers)
                                                   └──► Blade view ──► HTML + resources/js modules
```

Live it runs on Vercel (serverless PHP), with a TiDB MySQL database, uploads in Supabase Storage and email through Brevo. See the README's **Deploy to Vercel** section.

## Who uses it

| Person | What they can do | How the code knows |
|---|---|---|
| Visitor (not logged in) | See the welcome page, notices and the alumni directory (no phone numbers), register | no user |
| Member (`role = student`) | Their portal: event, payments, notices, My Profile | `User::isStudent()` |
| Approved member | Also sees phone numbers in the directory and can pay | `status = approved`, `User::isApproved()` |
| Admin | The admin panel | `role = admin`, or a student with `admin_level` set (`User::isAdmin()`) |
| Super admin | Also makes students admins | `admin_level = super` (`User::isSuperAdmin()`) |

A registration moves **pending → approved** or **pending → rejected** (`User::STATUSES`). A rejected member is logged out on their next request (`EnsureNotRejected` middleware).

## Folder map

| Folder | What lives there |
|---|---|
| `routes/web.php` | Every URL. Grouped by who may open it: guest, `portal` (members), `admin`. |
| `app/Http/Controllers` | Public and member pages: `HomeController` (welcome + directory), `AuthController`, `PasswordResetController`, `PortalController`, `PaymentController` (bKash). |
| `app/Http/Controllers/Admin` | One controller per admin page: students, student fields, announcements, memories, email members, events, payments, welcome page, dashboard. |
| `app/Http/Requests` | Validation for the bigger forms. `StudentProfileRequest` holds the rules registration and My Profile share. |
| `app/Http/Middleware` | `EnsureAdmin`, `EnsureStudent`, `EnsureNotRejected`, `RedirectToHttps`, `AddSecurityHeaders` (Content-Security-Policy). |
| `app/Models` | `User`, `Payment`, `ReunionEvent`, `Announcement`, `Memory` + `MemoryPhoto`, `StudentField` + `StudentFieldValue`, `MemberEmail`, `Setting`. |
| `app/Services` | Code that isn't a model or a controller: `BkashService` (payment API), `PhotoThumbnail` (card-size photo copies), `StudentAnswers` (Students-page filters and totals), `CsvWriter`. |
| `app/Console/Commands` | One-off maintenance commands (thumbnails backfill, replace a photo, refresh upload cache headers). |
| `resources/views` | Blade templates. `layouts/app.blade.php` is the shell (header, menus, flash messages). `components/` holds reusable pieces such as `x-field` and `x-avatar`. |
| `resources/js` | One file per browser feature (see **JavaScript** below). |
| `database/migrations`, `database/seeders`, `database/factories` | Schema, local data (`DatabaseSeeder`, `DemoAlumniSeeder`) and test data. |
| `tests/Feature` | PHPUnit feature tests, one file per area. |

## Conventions to follow

- **Validation:** a form with more than a few rules, or rules shared by two forms, gets a Form Request in `app/Http/Requests`. Tiny one-off checks (login, a status change) stay inline with `$request->validate()`.
- **Statuses and roles:** never type `'approved'` or `'completed'` by hand. Use the constants (`User::STATUS_APPROVED`, `Payment::STATUS_COMPLETED`, `User::STATUSES`) and the scopes (`User::students()`, `User::withPaidCount()`, `Payment::completed()`).
- **Controllers stay short:** pick the data, call a model or service, return a view or redirect. Every method declares its return type (`View`, `RedirectResponse`, `StreamedResponse`).
- **Queries in controllers, not in Blade.** Views only display what they are given.
- **Comments explain why**, not what: a PHPDoc block on the method, inline comments only for tricky logic.
- **Format and test before committing:** `vendor/bin/pint --dirty` and `php artisan test --compact`. CI runs both on every push.

## Key flows

### Registration and approval
`AuthController::register` (validated by `RegisterRequest`) creates a pending member and logs them in. Admins see new registrations in the 🔔 bell (`AppServiceProvider` shares them with the layout) and approve or reject them on **Members → Students** (`StudentController::updateStatus`, which records the reviewing admin).

### The alumni directory (welcome page)
`HomeController` lists approved members, 24 per page, with search and a batch filter. Phone numbers are only selected, shown and searchable when the viewer is an approved member. `resources/js/directory.js` swaps directory pages in place, and reveals a page's photos together once they have loaded.

### Memories (welcome page)
Admins share old photos, funny moments and stories on **Communication → Memories** (`Admin\MemoryController`, validated by `MemoryRequest`). A post (`Memory`) has a type (`Memory::KINDS`; "Remembering" honours a friend who passed away: one photo only (`Memory::maxPhotosFor`), grey badge, no zoom, and the card shows the whole tribute without linking to a page (`Memory::linksToPostPage`)), a title, an optional story and up to `Memory::MAX_PHOTOS` pictures (one for Remembering), one `MemoryPhoto` row each. A post needs a story or at least one photo. The welcome page shows the newest three (`x-memory-card`); everyone can open `/memories` and each post (`MemoryController`). A post's photos play as a slideshow (`x-memory-slides` + `resources/js/slideshow.js`): they change every few seconds, pause on mouse-over, keyboard focus, off-screen or with the Pause button, and never autoplay for visitors whose device asks for reduced motion; arrows, dots/thumbnails, swipe and ← → keys always work. The story keeps its line breaks with `whitespace-pre-line`, and Blade's `{{ }}` still escapes it. Pictures are shrunk in the browser like every image input (`photos/upload.js`); the six-photo limit keeps one save under Vercel's 4.5 MB request cap. Saving and deleting photos lives on the model, so the controller stays short: `Memory::addPhotos`, `Memory::removePhotos` (only that post's photos) and `Memory::deleteWithPhotos` also delete the files from storage. Slide timing is `Memory::slideInterval`.

When things go wrong:
- **Storage down while saving:** the disk returns `false` instead of throwing, so `Memory::addPhotos` throws `PhotoNotSavedException`; the controller saves inside a DB transaction, so nothing is half-saved, and sends the admin back to the form with their text and the message. New photos are stored before ticked ones are removed, so a failed edit never loses existing photos.
- **A photo file missing or not loading:** images with `data-fallback` show a "Photo not available" tile (`photo-fallback.js`); the slideshow hides Full size for it.
- **Upload too big for Vercel (4.5 MB):** `form-guards.js` stops any upload form over 4 MB before sending (GIFs aren't shrunk) and explains what to do.
- **Double tap on Save:** `<form data-submit-once>` disables the button after the first submit.
- **Offline:** `network-status.js` holds every form and keeps what was typed; `public/sw.js` shows the offline page.

### Extra student fields
Admins define questions on **Members → Student fields** (`StudentField`): short text, number, long text, date, dropdown, radio buttons, checkboxes, or an image only (a picture with no answer, such as a size chart). Each member's answer is one `StudentFieldValue` row stored as text; checkbox choices are stored one per line. Answers are admin-only: they appear on the Students page (with filters and totals from `StudentAnswers`), the student's admin page and the CSV export. Changing a field clears saved answers that no longer fit, so those members are asked again.

### Photos
Vercel's PHP has no image library, so **the browser does the image work** before upload:

1. `photos/process.js` decodes the file (iPhone HEIC is converted with `heic-to`) and shrinks big photos, because Vercel rejects uploads over 4.5 MB.
2. `photos/cropper.js` opens **Position your photo**: a 4:3 frame (the directory card shape) where the member drags and zooms.
3. `photos/upload.js` puts the framed JPEG (up to 1200×900) and a 480 px card thumbnail into the form.
4. The server stores both (`PhotoThumbnail::forUpload`). Cards and avatars use the thumbnail.

Admins can re-frame an existing photo from a student's admin page (`photos/reframe.js` → `StudentController::updateCardPhoto`). Only a new thumbnail is saved, under a **new file name**, because uploads are cached by browsers for a year (`immutable`); the original photo is kept.

### Payments (bKash)
`PaymentController::pay` creates an *initiated* payment and sends the member to bKash. bKash returns to `/payment/bkash/callback`, which locks the payment row, executes (or queries) the payment, and marks it **completed** only when the amount and invoice match. Payments are switched off until `BKASH_ENABLED=true` ("bKash Payment Coming Soon").

### Email to members
**Communication → Email members** sends one copy per member, addressed to them (Bcc-only mail lands in Gmail spam), and stops before Brevo's free limit of 300 emails a day (`MemberEmail::remainingToday`).

## Security notes

- **Content-Security-Policy** (`AddSecurityHeaders`): scripts only from this site, no inline scripts, images only from this site, `data:`, `blob:` (the cropper preview) and the upload storage host. Locally it only *reports*; live it *blocks*. If something works locally but not live, check the browser console for CSP errors first.
- Destructive buttons ask first through `data-confirm` (`resources/js/confirm.js`), not inline `onsubmit`.
- Production redirects to HTTPS and only answers to `APP_URL`'s host.
- Never edit a password directly in the database: Laravel stores a bcrypt hash and errors on plain text. Use **Forgot password** or `User::update(['password' => ...])`, which hashes it.

## JavaScript

`resources/js/app.js` only imports the feature files, in the order their listeners attach:

| File | Feature |
|---|---|
| `confirm.js` | "Are you sure?" before destructive form submits |
| `photos/process.js` | Decode (HEIC), shrink, make the card thumbnail |
| `photos/cropper.js` | The Position your photo dialog |
| `photos/upload.js` | Photo inputs with `data-crop`: crop, preview, Adjust photo |
| `photos/reframe.js` | Admin **Adjust card photo** button |
| `dropdowns.js` | Header menus and the 🔔 bell: one open at a time, Escape closes |
| `directory.js` | In-place directory paging and the photo shimmer |
| `form-guards.js` | Before a form is sent: upload size limit, `data-submit-once` |
| `photo-fallback.js` | "Photo not available" tile for `<img data-fallback>` that fail to load |
| `memory-form.js` | Admin memory form: photo picker allows one or several photos to match the chosen type |
| `slideshow.js` | Memory photo slideshows: autoplay, arrows, dots, swipe, keyboard, pause |
| `network-status.js` | Offline / back-online bar; holds forms while offline |

## Tests

PHPUnit feature tests in `tests/Feature`, running on in-memory SQLite (`phpunit.xml`), so they need no database setup. External services are faked (`Http::fake` for bKash, `Mail::fake`, `Storage::fake`). Add or update a test whenever behaviour changes; a refactor must keep every test passing unchanged.

## Releasing

Work on a branch, push it, and wait for CI to pass. Then merge into `main` with `--no-ff` and push. **Pushing `main` deploys the live site** (Vercel). If the branch adds a migration, run it on the production database *before* pushing `main`. Each release bumps `version` in `config/app.php` (shown in the footer) and is tagged `vX.Y.Z` with release notes in the tag message; CI publishes the GitHub release and updates `CHANGELOG.md`.

## Glossary

| Word | Meaning |
|---|---|
| Alumni / member / student | A former student with an account (`role = student`). |
| Batch | SSC exam year (`ssc_batch`). |
| Student field | An extra question the admin adds (`StudentField`); its answers are `StudentFieldValue`s. |
| Card photo / thumbnail | The 480 px 4:3 copy shown on directory cards and avatars (`photo_thumb`). |
| Notice / announcement | A post on the welcome page and portal (`Announcement`). |
| Memory | A photo/story post in the welcome page's Memories section (`Memory`, pictures in `MemoryPhoto`). |
| Event | A paid event members can pay for (`ReunionEvent`). |
