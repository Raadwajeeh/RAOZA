# RAOZA v2.1 — Localhost-ready merge

This package was produced by using the user's known-working localhost project as the compatibility baseline
and overlaying the latest v2.1 reviewed source.

Preserved compatibility points:
- Laravel bootstrap/public front controller structure from the working project
- Existing Composer/npm manifests and lock files (they were identical between both inputs)
- Current routes/config/source baseline, with v2.1 source changes applied
- No local secrets or machine-specific runtime state are included

Intentionally excluded:
- .env
- .git
- vendor
- node_modules
- public/build and public/hot
- IDE metadata
- storage/runtime caches, sessions, compiled views and logs
- bootstrap generated cache

When applying locally, keep the existing .env, .git, vendor and node_modules.
