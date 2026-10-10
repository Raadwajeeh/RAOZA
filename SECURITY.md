# Security

## Reporting

Report suspected vulnerabilities privately to the repository owner through the repository's private security-reporting channel. Do not include credentials, customer data or a working exploit in a public issue. Include the affected commit, route/component, impact and minimal reproduction steps.

## Trust boundaries and invariants

- The browser is untrusted. Prices, discounts, tax, shipping totals, inventory and state transitions are calculated and authorized on the server.
- Money uses integer minor units. Stock reservation/commit/release and payment/refund transitions are transactional and idempotent.
- Mollie webhooks and customer redirects are signals, not payment proof. Provider fetch results are authoritative; redirect returns never mark orders paid.
- Admin routes require authenticated staff roles and backend permissions. Sensitive cancellation, refund, return, shipment and fulfillment actions create attributed audit entries.
- Guest checkout is intentional. Order access is bound to the checkout session; there is no public customer registration surface.
- Ordinary PHPUnit is limited to SQLite `:memory:` by forced environment values and a runtime guard. PostgreSQL integration validation must use the isolated explicit CI job.

## Secrets and deployment

Never commit `.env`, provider keys, mail credentials, database dumps or runtime logs. Production secrets belong in the host secret store. `TRUSTED_PROXIES` must contain only actual proxy IPs/CIDRs; wildcard trust is prohibited. The edge must terminate HTTPS, overwrite forwarded headers, redirect HTTP to HTTPS and set secure cookies. HSTS is intentionally an edge decision after the final host and TLS behavior are verified.

The Content Security Policy is currently a conservative application header but should be revalidated against the final payment/analytics integrations before launch. The legacy `is_admin` field remains only for controlled backward compatibility; new authorization uses `role` and permission checks and the legacy field should be removed in a reviewed future migration.
