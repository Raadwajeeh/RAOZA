# RAOZA local demo review

This mode exists so the whole shop can be reviewed before real production content and credentials are entered.

## What is demo data

The seeded products, SVG product artwork, inventory, shipping prices, discount codes, policy copy and admin credentials are development examples. They are deliberately labelled and must be replaced before production.

## Demo credentials and codes

- Admin URL: `/admin/login`
- Admin email: `admin@raoza.test`
- Admin password: `RaozaDemo!2026`
- Discount: `WELCOME10` — 10% with €30 minimum
- Discount: `RAOZA5` — €5 with €50 minimum
- Payment: local simulator; no real money

## Start locally

Requirements: PHP 8.3+, Composer, Node/npm, PostgreSQL 15+.

1. Copy `.env.demo.example` to `.env`.
2. Create PostgreSQL database/user matching the `.env` values, or edit those values for your local PostgreSQL.
3. Run `composer install`.
4. Run `npm install`.
5. Run `php artisan key:generate`.
6. Run `php artisan migrate:fresh --seed`.
7. Run `php artisan storage:link`.
8. Terminal A: `php artisan serve`.
9. Terminal B: `php artisan queue:work`.
10. Terminal C: `npm run dev`.
11. Open `http://127.0.0.1:8000`.

For a clean reset at any time: `php artisan migrate:fresh --seed`.

## Review path

Home → Shop → Product → select size/color → Add to Bag → Bag → Checkout → choose delivery → optionally enter a demo discount → Create order → Pay securely → choose a demo payment outcome → inspect Order/Admin/Inventory/Fulfillment.

## Production boundary

Never set `RAOZA_DEMO_MODE=true` or `PAYMENT_PROVIDER=demo` in production. Replace demo product artwork, product data, inventory, shipping rates, VAT/tax configuration, discounts, legal pages, company identity, SMTP, analytics and payment credentials before launch.

### PostgreSQL with Docker (optional)

If PostgreSQL is not installed locally but Docker Desktop is available, run:

`docker compose -f docker-compose.local.yml up -d`

The included `.env.demo.example` already matches this local container (`raoza` / `raoza` / database `raoza`).
