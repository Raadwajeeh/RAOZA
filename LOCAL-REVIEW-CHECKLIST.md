# RAOZA local review checklist

Use this after `php artisan migrate:fresh --seed`.

## Storefront
- Home shows seeded products and collections.
- Shop search returns demo products.
- T-Shirts and Hoodies category pages work.
- Product page changes variants by Size + Color.
- Sold-out demo variant cannot be added.
- Available variant can be added to Bag.
- Bag quantity update and removal work.

## Checkout
- Netherlands address fields validate.
- Both demo delivery methods are selectable.
- `WELCOME10` works above its minimum.
- `RAOZA5` works above its minimum.
- Invalid discount shows a validation error.
- Server creates order snapshots and reserves stock.
- VAT component is shown as included, not added twice.

## Payment simulator
- Success changes payment to paid, confirms order, commits reserved stock and starts Processing.
- Failure/cancellation releases reservation.
- Retry rechecks stock before a new attempt.

## Admin
- Login with the demo owner account.
- Dashboard loads.
- Products, categories, collections and inventory show demo data.
- Orders show the checkout order.
- Paid order appears in fulfillment workflow.
- Inventory adjustment creates an audit trail.
- Returns/refunds screens load for eligible test data.
- Discounts and content pages are visible.

## Content/privacy
- About, Shipping & Delivery, Returns & Refunds, Privacy and Terms pages render.
- Consent banner can choose analytics/marketing independently.
- Demo mode banner is visible.

## Before production
Every demo item above must be replaced/approved where it represents business data, legal text, prices, policies, credentials or artwork.
