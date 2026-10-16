# RAOZA unused-files audit — Phase 10

Date: 2026-10-16

Method: tracked-file inventory plus repository-wide reference checks across TypeScript/React, Laravel routes/controllers, Inertia resolution, Blade, seeders, migrations, CSS, Vite, tests, Actions, mail/PDF and documentation.

## Inventory summary

- 590 tracked paths were inventoried at the baseline.
- `public` contained 131 tracked catalog/brand assets: 34 PNG and 97 WebP files.
- Package manifests, Composer scripts, Vite inputs and GitHub Actions were inspected.
- Untracked/generated build, Playwright and local database output remains governed by `.gitignore`; it was not treated as source cleanup.

## Deleted — PROVEN UNUSED

| Exact path/item | Evidence and references checked | Risk and post-removal evidence |
|---|---|---|
| `resources/js/Pages/Home.tsx` | Repository-wide search found no import, route render, test, dynamic Inertia name or resolver reference. `/` resolves `Storefront/Home`, not this two-line legacy re-export. | Low. TypeScript, Vite build, PHP and browser suites verify page resolution after deletion. Recoverable from Git history. |
| Playfair Display 600 normal font import in `resources/js/app.tsx` | CSS/component search found display usage at weight 400/italic; interface bold text uses Plus Jakarta Sans. Vite baseline/final manifests confirmed only the removed import caused the 600 files to emit. | Low. Visual matrix shows headings at all widths; build passes. Removes 51.59 kB raw emitted font assets. |

No dependency was removed. `@axe-core/playwright` was added as a development-only accessibility regression dependency and lockfile changes were reviewed.

## Required — retained

- All migrations, seeders, controllers, route definitions, Inertia pages, mail/PDF templates, tests and Actions workflows.
- All original catalog PNGs: `OfficialCatalogSeeder` contains explicit paths to the source images. Their optimized WebP companions do not make the database/source references disposable.
- Brand wordmarks and campaign/catalog media referenced by components, CSS, seeded content or tests.
- `sharp` (image optimization script) and `concurrently` (Composer development workflow).
- Customer/order/invoice/fulfillment/return/audit/notification/readiness code and historical data contracts.

## Unknown or likely unused — keep

- Any runtime upload/storage path and any asset whose use can depend on database or admin-managed content. No production database was inspected, so absence from static source is not deletion evidence.
- Historical reports, invoice/order documents, legal evidence and generated operational records. The Phase 10 rules explicitly protect them.
- Locally present duplicate-looking project folders outside Git tracking. They are outside the tracked cleanup boundary and were not deleted.
- The pre-existing working-tree deletions of historical reports and `playwright.config.ts`. They predated Phase 10, were not authored or staged by this work, and remain an owner worktree matter.

## CSS/dead-code assessment

Selector text searches alone are not adequate evidence because class names can be composed, rendered conditionally or sourced from content. The audit therefore did not mass-delete CSS. The verified Phase 10 fixes were appended to the existing system to minimize regression risk. A future CSS modularization should first add route-level coverage and runtime selector instrumentation.

## Safety conclusion

Only two low-risk, directly evidenced sources of unused output were removed. No recursive deletion, migration deletion, data deletion, upload cleanup or database mutation was performed. Git history provides recovery.
