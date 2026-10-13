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

## Automated regression

```bash
DB_HOST=localhost DB_PORT=5432 DB_NAME=farm_system DB_USER=postgres DB_PASS=postgres \
php -S localhost:8000 -t public &   # local server ONLY — never point BASE at the hosted DB
BASE=http://localhost:8000 bash tests/regression.sh
```

The suite logs in, then asserts CSRF/validation guards, stock-movement maths
(in/out, below-zero refusal, parallel races), reorder + procurement behaviour,
dropdown rendering, storage units + harvest-to-storage, vaccine/species rules,
equipment service reminders, a full page sweep for HTTP 200 with no PHP warning
output, and an optional PHP log scan (set `PHP_LOG=/path/to/server.log`).

## Manual test (6 flows)

1. **Stock in/out & reorder.** Inventory → add an item with a `min_level`;
   record in/out moves and confirm the Quantity badge, the "Balance after"
   column and the Low-stock badge all agree. Try an `out` above the balance —
   it must refuse with "Not enough stock". Create a reorder rule under the
   item's threshold and run the reorder check.
2. **Report a problem (no "Array").** Crop → Pests & Problems → pick an issue
   and a Type; the dropdowns must show real labels (no `>Array<`). Livestock →
   report a pest on an animal the same way; the saved record keeps the plain
   issue name. Mark one treated and optionally deduct a stock item.
3. **Harvest into storage.** Storage → add a facility (unit required, default
   kg — it can be edited). Harvest → "Store in" that facility: stock rises by
   the kg, and harvesting more than the capacity (or a deleted facility id)
   is refused without breaking the harvest record.
4. **Vaccination species rules.** Record a vaccination of Newcastle (NCD) on a
   cow — refused with "is for Poultry, not this Cattle". Goat + PPR vaccine is
   accepted. Batch-vaccinate a cow and a goat together — refused as
   mixed-species. Vaccinate poultry with Marek's: no Next Due date appears
   (single dose at day-old).
5. **Equipment service clock.** Add a Tractor with a purchase date ~10 months
   ago and no interval — it picks up 90 days and shows "Service overdue".
   Record a service: the badge clears to "Service OK" and the next date is set
   purchase-free. Spare-part stock and cost are optional.
6. **CSRF + no 500s.** POST any form without the token → 403. Browse every
   module landing and manage page → all 200, none show PHP warnings; the server
   PHP log stays empty.
