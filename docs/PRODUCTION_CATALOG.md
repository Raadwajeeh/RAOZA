# Production catalog initialization

## Confirmed repository state

`DatabaseSeeder` is local/testing-only but contains a demo owner, known demo password, invented stock, test discounts, demo shipping and placeholder policy pages. `OfficialCatalogSeeder` assumes those records already exist, changes legacy demo products, requires image files, and archives every other active product. `StorefrontContentSeeder` updates existing records and publishes content; it is not a clean catalog importer. None is approved for production.

Use `php artisan production:bootstrap` and a reviewed JSON manifest instead. The service creates categories, collections, products, options, variants, opening inventory movements, optional shipping methods and store information in one transaction. It keys records by stable slugs/SKUs, refuses demo mode, rejects secrets and unsafe/remote/missing image paths, detects cross-product SKU conflicts, and refuses to overwrite changed live stock. It does not archive or delete unrelated products.

## Approval gate

`resources/production/bootstrap-manifest.example.json` intentionally fails validation. The repository contains approved names, descriptions, taxonomy and official images, but it does not contain approved production values for:

- prices for all six products;
- complete size/color combinations and production SKUs;
- opening stock per SKU;
- care instructions;
- live shipping provider, price and service description;
- final business/store identity where optional store data is used.

These values must come from the owner. Do not derive production stock from local QA data or retain its demo prices.

## Workflow

1. Copy the example to a private path outside the release and source control.
2. Fill every required field from owner-approved source data. Prices are integer euro cents; stock is a non-negative unit count.
3. Use only public static paths such as `/catalog/...` or persistent upload paths such as `products/...`; all referenced files must already exist.
4. Validate without writes:

```bash
php artisan production:bootstrap /private/reviewed/raoza-catalog.json --dry-run
```

5. Take and verify a database backup. On a clean migrated staging database, apply explicitly:

```bash
php artisan production:bootstrap /private/reviewed/raoza-catalog.json --confirm
```

6. Re-run `--dry-run`, then `--confirm` once to prove idempotency. Confirm six approved products, expected variant count, exact prices, inventory and storefront visibility.

Reruns update reviewed descriptive/catalog fields but preserve an existing publication timestamp. Existing inventory must exactly equal the manifest opening stock or the command fails and instructs an audited inventory adjustment. Unrelated products and extra media/variants are left untouched by policy.

## Images

Official catalog image files are versioned under `public/catalog` and shipped in the release. The bootstrap validates their presence before starting its transaction. Admin-uploaded images belong in `shared/storage/app/public/products`; their database paths do not start with `/` and resolve through `/storage/...`.
