# Prefab Test

A minimal PHP website demonstrating the real [Prefab Core](https://github.com/tihloh/prefab-core) APIs.

## Requirements

- PHP 8.1 or newer
- Composer

## Install

```bash
composer install
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
- Bootstrap 5 responsive layout

To set the site title, copy `.env.example` to `.env` and edit `APP_NAME`.

No database, external services, GitHub Actions, or build tools are required. The site works without a `.env` file.
