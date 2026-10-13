# Image storage

RAOZA stores image metadata and relative paths in MariaDB. Binary files remain on the server filesystem.

## Two media classes

| Media | Filesystem location | Database path | Lifecycle |
|---|---|---|---|
| Reviewed official/catalog campaign assets | active release `public/catalog`, `public/campaign`, `public/brand` | leading slash, e.g. `/catalog/...webp` | versioned and released with code |
| Admin product uploads | `shared/storage/app/public/products/<product-id>` | relative, e.g. `products/42/hash.webp` | persistent across releases and included in media backups |

`public_html/catalog` and the other static directories link to `raoza-app/public/...`. `public_html/storage` links to `shared/storage/app/public`. There is no copied duplicate catalog or upload directory.

Admin uploads accept only JPEG, PNG and WebP images, at most 5 MiB each and ten files per request. Laravel validates both image content and allowed MIME/extension, generates filenames, scopes writes to the public disk, and authorizes the route through authenticated admin permissions and CSRF middleware. SVG is deliberately excluded. Deletes apply only to relative upload paths; versioned leading-slash assets are never removed by the admin delete action.

Bootstrap manifests reject URLs, backslashes, NULs, query/fragment suffixes, traversal segments, unapproved public roots and missing files. Public application files outside the explicit media roots cannot be selected as catalog images.

## Operations

- Keep `storage/app/private` outside the web root. Laravel's `local` disk points there.
- Give the PHP user write access only to `shared/storage` and `bootstrap/cache`, not the whole release or `public_html`.
- Back up `shared/storage/app/public` separately from the database. A database-only restore will otherwise contain broken image paths.
- During the first conversion, inspect both `raoza-app/storage/app/public` and a real (non-symlink) `public_html/storage`. If both contain files, compare them and stop on conflicts; never let a recursive copy silently replace one upload with another.
- Test media restoration by restoring both database and media to an isolated environment and requesting several known `/storage/products/...` URLs.
- Before removing a release, retain any versioned official media still referenced by the active database or deploy a replacement path first.

Variant-specific image records are supported by the schema, but the current admin upload screen creates product-level images only. A variant-image assignment UI requires owner workflow approval; do not hand-edit `variant_id` in production.
