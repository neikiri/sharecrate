# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.x.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability in ShareCrate, please report it responsibly.

**Do not open a public issue.**

Instead, send an email to **neikiri@neikiri.dev** with:

- A description of the vulnerability
- Steps to reproduce the issue
- Any potential impact (e.g. can it expose files, bypass a password, leak other users' data)

You can expect an initial response within **48 hours**.

## Scope

ShareCrate is a self-hosted PHP/MySQL application that stores and serves files behind
short links, optionally protected by a password, an expiry date, and a download limit.
Security-relevant areas include:

- Authentication and session handling (admin/uploader login, "remember me" tokens)
- Authorization (admin vs. uploader roles, per-file password checks)
- File handling: path traversal, upload validation, and how files are served (`storage/`
  is never served directly by Apache — everything goes through PHP)
- SQL injection (prepared statements are used throughout `App\Core\Database`)
- Cross-site request forgery (CSRF tokens on every POST form)
- Cross-site scripting (XSS) in any user-supplied content (file titles, descriptions,
  aliases, settings)
- Rate limiting / brute-force protection on login and file password attempts
- Handling and storage of visitor IP addresses (see the privacy modes in the README)

## Best Practices for Self-Hosters

- Always deploy behind HTTPS and enable the redirect-to-HTTPS block in `.htaccess`
- Keep `APP_DEBUG=false` in production so errors are never shown to visitors
- Keep PHP, MySQL/MariaDB, and Apache up to date
- Make sure `mod_rewrite` and `AllowOverride All` are active — without them, `.htaccess`
  has no effect and `storage/`/`app/` could become directly reachable
- Rotate `APP_KEY` only if you understand it invalidates existing signed tokens and IP
  hashes
- Keep ShareCrate itself updated to the latest release

## Acknowledgements

We appreciate the security research community and will credit reporters (with permission).
