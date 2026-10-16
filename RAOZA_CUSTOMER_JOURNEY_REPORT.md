# RAOZA customer journey report — Phase 10

Date: 2026-10-16. All payment, mail, shipment and order operations used isolated demo/test implementations.

## Journey map

“Steps” counts meaningful decisions or page/state transitions, not individual fields.

| Journey | Meaningful steps | Evidence and friction | Phase 10 outcome |
|---|---:|---|---|
| Landing → category → product → variant → cart → checkout → payment result → confirmation | 8 | End-to-end browser journey verifies catalog entry, explicit option choices, cart quantity, saved-address checkout, terms gate, demo payment and confirmation. The checkout’s next stages were not visible before submission. | Added a three-stage checkout progress indicator; preserved the four-state order progress on confirmation. |
| Search → filters → sorting → product | 4 | Search, filter chips/sidebar/dialog, active count, clear action and sorting are available. Filtered results rendered without overflow at 375/768/1440. | Improved small breadcrumb/tool readability; retained URL-driven filter state and responsive dialog/sidebar behavior. |
| Guest checkout → secure order access → tracking | 6 | Guest completes checkout, requests/receives a test access URL, invalid tokens return 404, valid token reveals only its order. Tracking appears after manual fulfillment. | No ownership/security changes needed; flow retained and regression-tested. |
| Registration → verification → login → saved addresses → checkout | 6 | Registration is gated by verification; seeded verified customer logs in, sees saved address and checks out. | Kept verification and terms gates. Checkout orientation and general form legibility improved. |
| Account → orders → order detail → returns | 4 | Account lists the customer’s order; another customer receives 404; delivered order permits a return request. | Responsive account/order/returns screenshots added. Existing ownership and eligibility controls retained. |
| Failed payment → result → retry/recovery | 3 | Server tests cover failure/cancellation and idempotent retry behavior; confirmation UI exposes secure-payment continuation where appropriate. | No provider behavior changed. Progress/loading feedback made consistent; real-provider testing remains deferred. |
| Out-of-stock / invalid variants | 2 | Previously both actual OOS options and impossible combinations appeared disabled, hiding the reason. | Actual OOS options are selectable and announced; impossible combinations remain disabled with explanatory accessible text/title. |
| Mobile navigation → product discovery → shop/product | 3 | Full-screen menu was visible but did not explicitly manage focus. | Initial focus, keyboard boundary, Escape close, restoration and inert closed state implemented/tested. |
| Support/policy/size guide | 2 | Footer routes, policy pages and size guide are reachable; browser smoke tests assert trust-link destinations. | Breadcrumb/footer readability improved without changing approved policy content. |

## Prioritized friction register

| Priority | Impact | Effort | Finding | Status |
|---|---|---|---|---|
| P0 | High | Low | Keyboard focus could escape or become disoriented in mobile navigation. | Fixed. |
| P0 | High | Low | Cart could expose a keyboard-actionable checkout link while invalid. | Fixed. |
| P1 | Medium | Low | Checkout did not reveal the Details/Payment/Confirmation sequence. | Fixed. |
| P1 | Medium | Low | OOS and impossible variants were indistinguishable. | Fixed. |
| P1 | Medium | Low | Several small labels/breadcrumbs failed AA contrast. | Fixed and axe-checked. |
| P2 | Medium | Medium | Account tab strip requires horizontal scrolling at 375px. | Retained: functional and visible; monitor with usability testing. |
| P2 | Medium | High | Failed-payment copy cannot be validated against a live provider’s exact reasons/retry rules. | Unresolved by constraint; demo/server regression only. |
| P3 | Low | Medium | Very long checkout at 375px remains field-heavy. | Accepted: fields reflect delivery needs; progress and saved address reduce uncertainty. |

## Trust, validation and dead-end review

- Prices, VAT language, delivery choice, estimated total and terms acceptance remain visible before payment.
- No new delivery promise, return window, contact detail or legal claim was introduced.
- Empty cart offers a route back to products. Invalid cart checkout is not actionable.
- Product state communicates incomplete choice, unavailable combination, out of stock, low stock and add success.
- Login, registration, verification, guest access, account and admin access retain their existing redirects and authorization boundaries.
- Payment and fulfillment actions remain explicit demo/manual transitions, never production calls.

## Remaining validation

Physical-device, screen-reader, real email, real payment-provider, real carrier and production-performance validation were not performed. Legal, accountant and provider approvals remain owner decisions.
