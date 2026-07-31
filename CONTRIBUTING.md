# Contributing to ShareCrate

Thanks for taking the time to contribute. Bug reports, fixes, translations, and feature
proposals are all welcome.

## Before you start

For anything beyond a small fix (new features, behavior changes, database schema changes),
please open an issue first to discuss the approach. It saves everyone time if the design is
agreed on before code is written.

## Development setup

```bash
git clone https://github.com/neikiri/sharecrate.git
cd sharecrate
npm install
```

You'll also need PHP 8.1+ and a MySQL/MariaDB database to run the app locally. See the
"📋 Requirements" and "🚀 Getting started" sections in the [README](README.md) for the full
setup, including the `.env` file and the `/install` wizard.

Useful scripts while developing:

```bash
npm run dev          # vite build --watch, for CSS/JS changes
npm run build        # full build into dist/
npm run serve        # PHP dev server preview of dist/ (port 8080)
npm run lint:php     # php -l over every PHP file
```

## Making a change

1. **Fork the repository** and create a branch off `main`:
   ```bash
   git checkout -b fix/short-description
   ```
2. **Make your changes.** Match the existing style:
   - PHP: `declare(strict_types=1);`, typed properties/parameters, PSR-4-style namespaces
     under `App\`. Look at neighboring classes in `src/server/app/` for conventions.
   - Frontend: Tailwind CSS 4 utility classes and Alpine.js components in
     `src/frontend/app.css` / `app.js` — no other JS framework, please.
   - Keep PHP framework-free: this project intentionally has no external PHP dependencies.
3. **Run the checks** before opening a PR:
   ```bash
   npm run lint:php
   npm run build
   ```
   Both should complete without errors.
4. **Adding a UI-facing string?** Add the key to both `src/server/app/locales/en.php` and
   `src/server/app/locales/cs.php` (see the "Adding a language" section in the README if
   you're adding a whole new language).
5. **Commit and push**, then open a pull request against `main`. Describe what changed and
   why, and reference the related issue if there is one.

## Reporting bugs

Open an issue with:

- What you did, what you expected, and what happened instead
- PHP version, MySQL/MariaDB version, and Apache version if relevant
- Steps to reproduce, and any relevant log output (`APP_DEBUG=true` locally can help)

## Reporting security issues

Do **not** open a public issue for security vulnerabilities. Follow the process in
[SECURITY.md](SECURITY.md) instead.

## Code of Conduct

This project follows the [Code of Conduct](CODE_OF_CONDUCT.md). Please be respectful in
issues, pull requests, and discussions.
