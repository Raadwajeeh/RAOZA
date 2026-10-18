# RAOZA Local Storefront Recovery — Phase 11

Date: 16 October 2026  
Baseline: `203becbd2107d5fec2cbba503e10e7a95aab94e5`  
Scope: local Windows development and visual review only

## Outcome

The storefront is locally reviewable with the approved RAOZA identity, campaign art, and six-product photographed catalog. The local PostgreSQL database `raoza_local` was populated without resetting it. Product selection, stock, cart, checkout, demo payment, customer/account, tracking/returns, and admin journeys pass the isolated browser suite.

Use the guarded launcher in this report. Do not reuse the previously running raw `php -S ... public/index.php` command: it is the direct cause of the broken public images.

## Root causes

1. **The active PHP command routed static files through Laravel.** The server on port 8080 was running as `php -S 127.0.0.1:8080 -t public public/index.php`. Requests such as `/brand/raoza-wordmark-light.png` and `/campaign/home-hero-desktop.webp` returned Laravel HTML with status 200 and `text/html`, so the browser displayed broken images. The same files return the correct `image/png` or `image/webp` content type under `php artisan serve`.
2. **The images were not missing.** The repository contains 137 tracked brand, campaign, catalog, and demo media files. Filename case and all 30 active catalog WebP paths match on disk. The logo, hero, campaign imagery, and official product photography are versioned in `public/brand`, `public/campaign`, and `public/catalog`; they do not depend on Vite or the Laravel storage link.
3. **The database had only schema.** All 26 migrations had run, but products, product images, categories, collections, variants, inventories, shipping methods, discounts, content pages, and users had zero rows. Migrations intentionally do not create merchant/demo data. Test fixtures hid this difference because PHPUnit/E2E use freshly seeded isolated test databases.
4. **The existing local seed path had not been run.** `LocalQaSeeder` is already environment-gated and idempotent. It builds synthetic records, then `OfficialCatalogSeeder` maps the six products to the approved repository photography and publication rules. Active products require active status plus a current/past publication date; browse pages additionally require active variants, and checkout requires available inventory.
5. **Vite was not the image root cause.** Vite was valid on port 5175 and served React/CSS/HMR successfully. Direct public media must be served by Laravel/PHP. The production Vite manifest also verifies successfully.
6. **The prior local payment resolution was not safe for checkout.** The current unmodified `.env` resolved to the Mollie provider with demo mode disabled. No live request was made. The new Windows launcher applies process-only `demo` payment, log-only mail, disabled analytics, and localhost settings without displaying or changing `.env`.
7. **Laravel's reload-enabled serve subprocess fails on this Herd/PHP 8.4 Windows setup.** The failure was reproduced on otherwise free ports while no Windows TCP exclusion or listener applied. `php artisan serve --no-reload` binds successfully, and raw PHP also hosted the isolated E2E server on port 8000. The launcher pins `127.0.0.1:8080`, uses `--no-reload`, and fails clearly on real conflicts.

## Changes made

- `.env.example`: aligned sanitized local defaults with `127.0.0.1:8080`, demo payments, and demo mode. The actual `.env` was not read out, overwritten, or committed.
- `scripts/start-local.ps1`: added a Windows launcher with dependency, environment, and port guards; explicit Laravel/Vite origins; optional queue; and process-scoped safe service overrides.
- `composer.json`: added `dev:windows` and `dev:windows:queue` entry points.
- `README.md`: documented setup, seeding, startup, browser URL, and the invalid raw PHP router command.
- `tests/Feature/Storefront/StorefrontAssetReadinessTest.php`: verifies required public images, exact case-sensitive seeded paths, valid image bytes, dimensions, and the complete 30-image catalog.
- `RAOZA_LOCAL_STOREFRONT_RECOVERY_PHASE11_REPORT.md`: this handoff.

No branding or photography was invented or replaced. The existing `public/storage` Windows junction correctly targets `storage/app/public` for future admin uploads. Current reviewed media uses direct versioned public paths and does not need that junction.

## Safe local setup (PowerShell)

From `D:\My Projects\RAOZA\RAOZA`:

```powershell
composer install
npm ci

# Only for a new clone with no .env; never overwrite an existing .env.
Copy-Item .env.example .env
php artisan key:generate

php artisan migrate
php artisan db:seed --class=LocalQaSeeder --no-interaction
composer run dev:windows
```

Open **http://127.0.0.1:8080**.

The seed command is local/testing-only, repeatable, and non-destructive. It does not call `migrate:fresh`, `db:wipe`, or delete orders. On this database it produced:

- 6 approved active products;
- 30 reviewed product images;
- 48 variants with coherent options and inventory;
- 2 categories and 2 collections;
- synthetic shipping, discounts, content, and a `.test` admin account.

The seeder prints the synthetic local admin credentials in the terminal. They are deliberately not copied into this report. Change them if the machine is shared.

For journeys that need queued work:

```powershell
composer run dev:windows:queue
```

The queue is optional for ordinary storefront review. The launcher refuses non-local Laravel environments and occupied ports. If it reports port 8080 or 5175 in use, return to the terminal that owns that process and press `Ctrl+C`; do not terminate an unidentified process. Then rerun the launcher.

Do **not** use:

```powershell
php -S 127.0.0.1:8080 -t public public/index.php
```

That exact router form breaks existing public media. If starting only Laravel manually, use:

```powershell
php artisan serve --host=127.0.0.1 --port=8080 --no-reload
```

## Local review checklist

1. Open the homepage and accept or reject optional cookies. Confirm the header wordmark, hero, editorial panels, campaigns, and product edit.
2. Open `/shop`; exercise category, collection, size, color, stock, search, price, and sort filters.
3. Open a product, switch gallery images, select size/color, review stock, and add it to the bag.
4. Change quantity, remove/re-add an item, and proceed to checkout.
5. Use only synthetic `.test` contact details. Confirm required-field and terms validation, then continue through the built-in demo payment screen.
6. Review the resulting confirmation and guest access. Registration/account verification, saved addresses, order history, tracking, and returns are covered by the isolated E2E journey.
7. Sign in to `/admin/login` with the synthetic credentials printed by the seeder to review catalog, inventory, customers, orders, fulfillment, content, discounts, and readiness. Do not issue official invoices or perform real refunds/shipping actions.

The launcher forces demo payments and log-only mail for its child processes. It does not use live Mollie keys, send email, contact a carrier, enable invoices, or change any approval gate.

## Visual and browser QA

Playwright CLI inspected the live PostgreSQL-backed storefront through a correctly routed Laravel server. The homepage, shop/product/cart/checkout UI, responsive images, and console/network activity were checked. All 41 captured homepage requests succeeded, including logo, desktop/mobile hero, campaign, product, responsive WebP, font, and Vite requests. Console result: zero errors and zero warnings. At 375 px, measured document width equalled viewport width (no horizontal overflow).

Screenshots:

- `output/playwright/phase11/home-mobile-375.png`
- `output/playwright/phase11/home-tablet-768.png`
- `output/playwright/phase11/home-desktop-1440.png`

The three screenshots were visually inspected for logo visibility, hero crop/contrast, header/navigation, typography, calls to action, and responsive composition. Automated axe checks additionally cover homepage, shop, product, cart, account login, and returns at mobile and desktop sizes, plus mobile-menu focus containment/restoration and checkout progress semantics.

## Verification results

| Check | Result |
|---|---|
| PostgreSQL migrations | 26/26 ran |
| Repeatable local seed | Pass; counts stable and reservations preserved |
| Targeted asset/catalog tests | 6 tests, 281 assertions passed |
| Full PHPUnit suite | 232 tests, 1,615 assertions passed |
| Chromium E2E + accessibility | 15/15 passed against isolated SQLite/demo services |
| TypeScript (`tsc --noEmit`) | Pass |
| Production Vite build | Pass; 830 modules transformed |
| Production build verifier | Pass; 58 manifest entries / 59 files |
| Composer validation | Pass |
| Composer security audit | No advisories |
| npm audit (high threshold) | 0 vulnerabilities |
| Browser console | 0 errors / 0 warnings |
| Static asset requests | Correct image MIME types and HTTP 200 |
| Windows launcher smoke | Pass on 8083/5176; Laravel, Vite, APP_URL, and static MIME types verified |
| Secret scan | Not run: `gitleaks` is not installed locally |

The E2E run used only the dedicated disposable `database/e2e.sqlite` with `APP_ENV=testing`, demo payments, array mail, and sync queue. It did not reset or connect to `raoza_local`.

## Remaining limitations and owner actions

- Stop the already-running incorrect port-8080 PHP process from its owning terminal before using `composer run dev:windows`. It was intentionally not terminated because process ownership was not established.
- The tracked `playwright.config.ts` was already deleted in the working tree before Phase 11. It was not restored. The browser suite was run with an equivalent temporary config that was removed after the run. Resolve that owner change before expecting plain `npm run test:e2e` to work again.
- Install `gitleaks` if a local secret scan is required; no secret-scan success is claimed here.
- The workspace contained multiple pre-existing deleted reports. They were preserved and are not part of Phase 11.
- This is local visual readiness, not production approval. Legal/accounting approvals, invoice issuance, live payment, real mail, carrier actions, and hosting remain disabled/out of scope.

No commit or push was made. The repository remains based on `203becbd2107d5fec2cbba503e10e7a95aab94e5`, with the unrelated working-tree deletions preserved.
