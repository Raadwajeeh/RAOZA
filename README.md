# RAOZA Store — 1.0.0-rc2 local demo

RAOZA is a custom Laravel + React/Inertia + PostgreSQL e-commerce application for a Netherlands-focused printed-apparel brand.

## Start here

For visual/local review, read **`LOCAL-DEMO.md`** first. The repository now includes seeded products, variants, inventory, collections, content pages, shipping examples, discount codes, a demo owner account and a local payment simulator. This makes the customer and admin flows reviewable before real production data is supplied.

### Local demo credentials

- `/admin/login`
- `admin@raoza.test`
- `RaozaDemo!2026`

These credentials are created only by the local/testing seeder. The seeder refuses to run outside local/testing.

## Stack

- PHP 8.3+
- Laravel 12
- React 19 + TypeScript
- Inertia 2
- Tailwind CSS 4
- PostgreSQL

## Local setup

1. `cp .env.demo.example .env` (Windows: copy the file manually)
2. Start PostgreSQL. Optional: `docker compose -f docker-compose.local.yml up -d`
3. `composer install`
4. `npm install`
5. `php artisan key:generate`
6. `php artisan migrate:fresh --seed`
7. `php artisan storage:link`
8. `php artisan serve`
9. In another terminal: `php artisan queue:work`
10. In another terminal: `npm run dev`
11. Open `http://127.0.0.1:8000`

See `LOCAL-REVIEW-CHECKLIST.md` for the exact review flow.

## Important boundaries

Demo products, SVG artwork, policies, shipping rates, VAT configuration, discounts and credentials are test data. Production must use approved business/legal data and real provider credentials. Demo payment is guarded so it cannot operate unless the application is local/testing **and** `RAOZA_DEMO_MODE=true`.

## Verification in this build environment

PHP source/tests/migrations/config were syntax-linted successfully. JSON was parsed successfully. A complete Composer/PHPUnit/Vite build could not be executed in the packaging environment because Composer/dependencies are unavailable and outbound package installation timed out. The local/CI release gate remains: install dependencies, commit generated lockfiles, run `composer test`, `npm run typecheck`, and `npm run build` before production.
