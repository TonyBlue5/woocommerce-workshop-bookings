# Workshop Bookings for WooCommerce — Lite and Pro

Powered by e-iT – Information Technology & e-commerce.

## Deliverables

Run `node tools/build.cjs` to generate two installable plugin ZIPs and a CodeCanyon outer package in `build/`. No npm dependencies are required. ZIP contents come from explicit manifests; Lite physically excludes commercial modules and the external updater.

- Lite: `workshop-bookings-for-woocommerce` — calendar, recurring schedules, single/monthly/multi-month participation, shared capacity, basic reminders, calendar exports, English/Greek.
- Pro: `workshop-bookings-pro-for-woocommerce` — all core functions plus analytics, customer reports, loyalty, guest and registered referrals, cancellation broadcasts, advanced RSVP, My Account workshops, native reusable message templates and signed updates.

The root plugin file is the development/legacy entry point for Pro. Production packages use their own slug and entry filename. Only one edition should be active. Deactivate the existing edition before switching.

## Compatibility and data

Existing `_kwb_*` product/order metadata, option keys, original referral rows and coupon data are unchanged. A guest-referral table is additive; no destructive migration or uninstall is included. Both HPOS and legacy order storage use WooCommerce APIs. Retained attendance metadata remains authoritative when switching to Lite.

## Referrals

Signed first-touch cookies last up to 30 days. At checkout the plugin stores referrer, policy and normalized billing email on the order, so asynchronous payment completion works without cookies or account creation. Only completed paid eligible booking lines qualify. Self-referral, repeated account/email, expired/tampered cookies are rejected. The original account-referral table and additive guest table share one database identity lock. Registered-account fallback preserves old orders. Version 1.0.1 reconciles previously attributed completed orders in background batches. Orders without stored attribution cannot be retroactively assigned from an email alone. Partial qualifying-item refunds and full refunds revoke unused reward eligibility; already redeemed discounts are not reversed. Policies are fixed at registration or checkout. Keep WordPress salts stable.

## Reports and messages

Revenue uses line totals after discounts/item refunds, before tax/shipping, with currencies separate. Counts are booking lines. Dates use order creation in the store timezone. Allocate partial refunds to lines for per-workshop attribution. Lapsed customers group by normalized billing email; purchase does not imply marketing consent.

The Message designer stores up to 50 reusable rich-text templates. Campaigns use a preview/confirm flow, sanitized HTML, per-recipient delivery and retry history. Action Scheduler falls back to WP-Cron. A stuck sending state requires reconciliation against mail logs. Customer account data is scoped by account ID, never disclosed using billing email alone.

## Tests

Product family acceptance builds and installs the actual ZIPs, checks PHP syntax and tests both editions with HPOS/legacy on PHP 7.4/8.4. Existing compatibility/security workflows cover more PHP versions. Family tests exercise guest attribution, delayed completion, Store API attribution hooks, replay/deduplication, refunds, template sanitization and modal sections. Smoke tests are not proof of universal theme/gateway compatibility; complete the documented staging checklist before public launch.

## Signed releases

The manually dispatched release workflow keeps the existing RSA public key and GitHub signing secret. It builds the Pro asset name expected by the new updater and verifies the signature before publication. Do not dispatch it until a release is approved. The separate signed-candidate workflow verifies green compatibility, security and packaged acceptance workflows before signing both installable ZIPs. It uploads test artifacts without publishing a release or changing the stable updater channel. Paid checkout/license entitlement integration follows provider selection; signature verification alone is not purchase enforcement.

## Marketplace preparation

See `marketplace/documentation.html`, `marketplace/listing-copy.html`, `marketplace/assets-and-access.txt` and `editions/lite-readme.txt`. The exact approved logo file is not present; the documented palette and white cube lettering direction are preserved for final asset production.

GPL-2.0-or-later.

## Pending capacity

New checkout requests reserve their places while payment is pending. Cancel abandoned pending orders to release these holds; use WooCommerce cleanup where applicable. Checkout and attendance changes share a database lock, and late payments recheck capacity before confirming a booking. If a gateway has already collected money and the place was reallocated after cancellation, review the order note and refund or reschedule manually. Administrator-created orders and manual status overrides require the merchant to check capacity.

## Staff calendar (Pro)

WooCommerce → Workshop calendar shows shared availability and participants. Administrators and Shop Managers can create phone bookings for simple workshop products and release or restore individual occurrences. Phone orders start On hold; payment and refunds remain WooCommerce actions. Cancelling an order releases its reserved occurrences.
