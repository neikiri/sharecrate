# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-31

### Added

- Initial public release of ShareCrate.
- **Short links** built from the file name (`example.com/<alias>`), with a custom,
  rewritable alias and a configurable shape for new aliases (from the file name / name
  with a random suffix / fully random).
- **Access protection** per file: password (bcrypt/argon2 with brute-force throttling),
  expiry date, maximum download count, and the ability to disable a link without deleting
  the underlying file.
- **Secure file serving**: uploads are stored outside the webserver's reach and streamed
  through PHP with Range request support for resumable downloads.
- **Browser uploads** with drag & drop and per-file progress.
- **FTP workflow**: drop files into `storage/uploads/` and turn them into links from the
  *FTP import* screen, or automatically via cron (`bin/scan.php --import`). Incomplete
  uploads (`.filepart`, `.part`, `.crdownload`, `.tmp`, or recently modified files) are
  skipped.
- **Admin dashboard**: usage overview (downloads over 30 days, storage used, most
  downloaded files, recent activity), file management with search/filters/bulk actions and
  a per-file download history, CSV export of the download log.
- **Users and roles**: *administrator* and *uploader* roles, per-user storage quotas, and
  account activation.
- **Settings**: site name, timezone, alias shape, log retention period, and upload limits,
  all editable from the dashboard.
- **Geolocation** for the download log (country/city), resolved via infrastructure headers
  (Cloudflare, `mod_geoip`), a local cache, or `ip-api.com` as a fallback — configurable or
  fully disableable via `GEOIP_PROVIDER`.
- **Privacy controls** for logged IP addresses: full, truncated, or not stored at all,
  while unique-visitor counts keep working via a salted hash.
- **Localization**: Czech and English UI, with automatic language selection based on
  visitor country (Czechia/Slovakia get Czech) and a manual switch, remembered in a cookie
  or on the user's account.
- **Guided installer** (`/install`) that provisions the database schema, creates the first
  administrator account, and writes the `.env` file.
- **Security hardening**: CSRF tokens on every POST form, `HttpOnly`/`SameSite=Lax`/
  `Secure` session cookies with ID rotation on login, prepared statements throughout,
  path-traversal guards on all `storage/` access, forced `attachment` disposition for
  HTML/SVG/JS/PHP, and standard security headers (CSP, `X-Content-Type-Options`,
  `Referrer-Policy`, `X-Frame-Options`).
- **Build pipeline**: a Node.js/Vite build (`npm run build`) that compiles the Tailwind CSS
  4 + Alpine.js frontend and assembles a self-contained `dist/` folder ready to upload to
  any Apache host, plus `npm run serve` for a local PHP dev-server preview and
  `npm run lint:php` for a syntax check across the codebase.
- Project documentation: README, MIT `LICENSE`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`,
  and `SECURITY.md`.
