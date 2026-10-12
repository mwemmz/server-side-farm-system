# FFMS — Intelligent Farm Management System

A PHP 8 + PostgreSQL web application for running an entire agribusiness: crops,
livestock, inventory, procurement, sales, finance, labour, storage, equipment,
weather and more — with a "smart" layer (searchable pick-or-type dropdowns,
automatic schedules, cross-module auto-posting and "needs attention" alerts).

## Stack

- PHP 8.2 (Apache), PostgreSQL
- Vanilla PHP views + Tailwind (CDN) + a small combobox/panel JS layer
- Deployed on Render via Docker (`Dockerfile`); config comes from environment variables

## Configuration

Configuration is read by `getEnvVar()` in `config/env.php`, which checks **real
environment variables first** and only then falls back to a local `.env` file.
On Render, set these under **Environment** — do not commit a `.env` file.

| Variable | Purpose | Example |
| --- | --- | --- |
| `DB_HOST` | PostgreSQL host | `localhost` |
| `DB_PORT` | PostgreSQL port | `5432` |
| `DB_NAME` | Database name | `farm_system` |
| `DB_USER` | Database user | `postgres` |
| `DB_PASS` | Database password | *(secret)* |
| `DEFAULT_LAT` | Default map latitude | `-15.3875` |
| `DEFAULT_LON` | Default map longitude | `28.3228` |
| `SMTP_HOST` | SMTP server | `smtp.example.com` |
| `SMTP_PORT` | SMTP port | `587` |
| `SMTP_USER` | SMTP username | `user@example.com` |
| `SMTP_PASS` | SMTP password | *(secret)* |
| `SMTP_FROM` | From address | `user@example.com` |
| `SMTP_FROM_NAME` | From display name | `FFMS` |
| `JWT_SECRET` | HS256 signing key for `/api/v1` | *(long random secret)* |
| `CRON_SECRET` | Gate for the cron runner | *(long random secret)* |

Copy `.env.example` to `.env` for local development. `.env` is git-ignored and
must **never** be committed. See `docs/SMART_FORMS_AUDIT.md` for the smart-forms
rollout status.

> **Security:** if real credentials were ever committed, they remain in git
> history — rotate the database password and SMTP password, and set fresh
> `JWT_SECRET` / `CRON_SECRET` values.

## Local development

```bash
composer install
php -S localhost:8000 -t public        # or point Apache at public/
```

Migrations and seed data run automatically on boot (`db/migrate.php`).
