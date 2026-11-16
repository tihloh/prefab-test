# Prefab Test

A minimal PHP website demonstrating the real [Prefab Core](https://github.com/tihloh/prefab-core) APIs.

## Requirements

- PHP 8.1 or newer
- Composer
- PHP extensions: PDO and pdo_sqlite

## Install

```bash
composer update
```

## Run

```bash
php -S localhost:8000 -t public
```

Open http://localhost:8000

For Apache, point the DocumentRoot at the `public/` directory. Composer's `vendor/` directory should remain outside the web root.

## What it demonstrates

- Composer-based installation of `tihloh/prefab-core`
- `val()` for trimming, text formatting, and numeric conversions
- `prefab_datetime()` for server-side date formatting
- `prefab_env()` for reading an optional `.env` configuration
- A basic POST form with server-side validation, escaped output, and CSRF protection
- SQLite persistence using Prefab Core's `DatabaseManager` (`statement()` and `select()`)
- User registration and authentication using `tihloh/prefab-users` and `tihloh/prefab-auth`
- Private per-user submissions with password hashing, sessions, CSRF protection and basic login throttling
- Automatically created `submissions` table and recent records display
- Bootstrap 5 responsive layout

To set the site title, copy `.env.example` to `.env` and edit `APP_NAME`.

**Authentication:** Open `/?page=register` to create an account, then log in at `/?page=login`. The site saves users and submissions in the same SQLite database, and submissions are only visible to their owner. Registration is open to anyone who can access the site; do not deploy publicly without considering whether public registration is appropriate. Account passwords require at least 12 characters. Login attempts are restricted per session, not globally per IP. Password-reset and email verification are not yet implemented.

**Dependency compatibility:** The currently published Prefab Auth and Users v0.6 packages require Prefab Core ^0.4 or ^0.5, so this demo uses Prefab Core ^0.5 instead of ^0.6. Run `composer update` after pulling these changes to resolve the new dependency set, or run `composer install` when a compatible lock file is later committed.

SQLite is used for local data storage. The application creates `storage/prefab.sqlite` and the `submissions` table automatically on first request. Ensure the PHP process can write to the `storage/` directory. The SQLite file is ignored by Git and kept outside the public web root. No MySQL server, external service, GitHub Actions, or build tools are required. The site works without a `.env` file.
