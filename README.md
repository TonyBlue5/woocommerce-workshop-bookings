# Workshop Bookings for WooCommerce

A lightweight WooCommerce extension for selling workshop seats as normal WooCommerce products while keeping the existing checkout and payment gateways.

## Current capabilities

- Per-product workshop booking mode
- Multiple date/time slots
- Capacity per slot
- WooCommerce quantity = number of seats
- Capacity checks at add-to-cart and checkout
- Booking metadata stored on order items
- Google Calendar links in customer emails
- Apple Calendar / iCalendar `.ics` downloads
- HPOS-compatible WooCommerce order access
- Admin/Shop Manager workshop analytics, loyalty campaigns, inactive-customer exports and cancellation notices
- Configurable referral rewards and customer My Account bookings/coupons/referral links

## Commercial dashboard (0.7.0)

Open **WooCommerce → Workshop dashboard**. Only users with the Administrator or Shop Manager role **and** `manage_woocommerce` can use its screens and POST actions. The interface follows the current WordPress locale (English fallback, Greek for `el*`). All changes and CSV exports require a nonce.

### Analytics and customers

The selected period uses order creation dates in the store timezone. Monthly and single counts represent order lines, not individual seats or sessions. Revenue uses line totals after coupons, excludes tax/shipping, subtracts item refunds, and is zero for fully refunded orders. Processing, completed and refunded orders are included; pending, failed, cancelled and on-hold orders do not contribute. Different currencies remain separate. Refunds without line allocation only affect the report when the order is fully refunded; allocate partial refunds to lines for workshop attribution.

Inactive customers are grouped by normalized billing email and their latest paid workshop order. Choose the inactivity threshold, inspect the first 100 and export all matches. The CSV records marketing consent as **not recorded**; purchases do not automatically subscribe customers to newsletters. Reports read bounded order pages through WooCommerce CRUD, including historical bookings, and do not rewrite them. Reports aggregate synchronously; large stores should benchmark the full-history customer report before using it regularly.

### Loyalty and cancellation messages

Loyalty campaigns can target one previous customer's email or all customers meeting minimum paid booking-line and distinct-workshop counts. Review the message and coupon terms before confirming. Each recipient gets a separate email and an email-restricted, single-use coupon. All coupons apply to one participant in one booking line and cannot combine with other coupons.

Cancellation notices target a workshop product ID and a date, including every session that day. Confirming adds that date to the existing blackout list, prevents checkout of affected cart bookings and suppresses reminders/RSVP for cancelled sessions. Existing orders, payment records and stored occurrences remain intact. Refunds are handled separately through WooCommerce. Customers see the cancelled date in My Account. Events already saved in external calendars are not automatically removed.

Messages run through Action Scheduler (WP-Cron fallback), with 25 recipients per send batch, deduplication and delivery history. Configure a working mail transport and runner. “Sent” means the mail service accepted the message, not confirmed inbox delivery. Retry only failed messages. A request interrupted while sending stays unconfirmed to avoid automatic duplicate email; reconcile it with the mail provider's logs. Do not delete campaign options or delivery rows while a campaign is running.

### Referral terms

Enable referrals and configure the number of friends, discount percentage (100 = free), maximum months, purchase mode, optional workshop ID and coupon lifetime. The same mode/product restrictions apply to qualifying purchases and redemption. Each group of qualifying friends earns one coupon. For example: two new friends completing monthly purchases can earn 100% off up to one month for one participant. With a two-month booking, only one month's share of that line is discounted. Unused month allowances do not carry forward to another order.

An anonymous visitor opens a customer's unique link, then registers within 30 days using the signed first-touch cookie. Existing accounts and guest checkout do not qualify. A distinct registered friend and normalized billing email count at most once. The friend must complete a paid eligible booking; unpaid, failed and refunded qualifying items do not count. Self-referrals by account or email are rejected. This is not identity verification: stores should investigate abuse involving multiple identities.

Terms are snapshotted when the friend registers. Changing settings applies to future registrations. Eligibility is checked again when a reward is used, including partial item refunds. A refund cannot reverse an already redeemed discount; it prevents unearned future use. Unique referral rows and a database lock prevent repeated completion events from issuing duplicate rewards. Keep WordPress salts stable; coupon IDs and signed referral cookies depend on them.

### Customer dashboard and compatibility

Customers use **My Account → My workshops** for their own bookings, attendance links, order/calendar details, earned coupons and referral progress. Coupon activation applies the selected coupon to the current cart. Order and coupon lists are paginated. Account data is scoped to the logged-in user; billing-email matching alone never reveals another account's orders.

The update creates only two additive plugin tables (`kwb_referrals`, `kwb_deliveries`) and a My Account endpoint. It does not migrate or delete bookings. Orders use WooCommerce APIs for both legacy and HPOS storage. The signed RSA/SHA-256 updater and release keys are unchanged. Run the signed release workflow only after the PR and required CI checks pass.

## Slot format (v0.1)

```text
2026-10-03|11:00|11:45|10
2026-10-10|11:00|11:45|10
```

Format: `YYYY-MM-DD|START|END|CAPACITY`

## Compatibility policy

The repository runs an automated smoke test on pushes, pull requests and every day against the latest WordPress and WooCommerce releases, across PHP 7.4, 8.1, 8.2, 8.3 and 8.4.

A passing workflow means the plugin can be installed and activated with the tested environment. Functional booking flows are expanded with dedicated automated tests as the plugin evolves.

## Roadmap

- Visual slot editor in WooCommerce admin
- Recurring workshop schedules
- Temporary seat holds during checkout
- Admin booking calendar
- Better concurrency protection
- GitHub release updater
- Automated functional tests for checkout and capacity

## Releases

Versions follow Semantic Versioning. Stable builds are tagged as `vX.Y.Z`. The release workflow produces:

- `woocommerce-workshop-bookings.zip`
- `woocommerce-workshop-bookings.zip.sha256`

## License

GPL-2.0-or-later.


## v0.3 recurring workshop model

A workshop product can enable either or both purchase modes:

- **Monthly participation**: one purchase reserves the selected number of seats in every scheduled occurrence of the chosen month.
- **Single participation**: one purchase reserves a seat only in the selected occurrence.

Both modes share the same occurrence capacity. If a Tuesday session has 10 seats and 7 monthly participants already include that Tuesday, only 3 single-session seats remain for that date.

Weekly schedules use ISO weekdays (1=Monday … 7=Sunday), with optional blackout dates for holidays or cancelled classes. Monthly iCalendar exports contain all booked occurrences.


## v0.4 calendar and attendance flow

The storefront uses a visual month calendar instead of date dropdowns. Days that do not belong to a workshop schedule are disabled, available workshop days show remaining seats, and single participation selects a specific occurrence.

The product editor uses structured schedule rows (weekday, start, end, capacity) and date-picker rows for cancellations/holidays.

Four hours before each paid occurrence, WordPress schedules an attendance reminder email with signed **YES / NO** links. A **NO** response releases that occurrence's seat without cancelling the rest of a monthly booking. Calendar exports also include a four-hour display reminder.

The plugin uses its own signed email RSVP flow, so attendance confirmation and seat release do not depend on Google Calendar APIs.


## API-free calendar architecture

The commercial plugin does not require a Google Cloud project, OAuth credentials, Google Calendar API access, or a central e-iT API service.

For each booked occurrence it can generate a normal **Add to Google Calendar** link locally. The customer chooses their own Google account and saves the event. For Apple Calendar, Outlook and other calendar applications, the plugin provides a signed multi-event **.ics** download.

Attendance confirmation is handled by the plugin itself: a configurable reminder email is scheduled before each occurrence and contains signed **YES / NO** links. When the customer declines, that specific occurrence can release its capacity for another booking without cancelling the rest of a monthly booking.

The reminder lead time, email subject/body, RSVP labels, cutoff, Google Calendar button, .ics button, .ics alarm, calendar title/description/location and booking defaults are configurable in WooCommerce > Workshop Bookings. Per-workshop overrides are also available for reminder timing, RSVP, cutoff, maximum quantity and location.


## v0.6.1 UI and calendar metadata

The visual availability calendar uses a plugin-owned Roboto-first font stack and explicitly overrides theme button colors so available dates remain readable without hover. Available cells display the workshop time and remaining capacity at all times.

Monthly booking selection uses a simpler month button and a confirmation message that states how many workshop participations are included **per child**.

Customer order details and emails now explain how to add bookings to Google Calendar or Apple / Outlook / iCalendar. Calendar events include workshop title, booking date/time, booking mode, order context, product link, and a richer description. A per-product calendar description can be entered in the Workshop Booking panel; when empty, the WooCommerce short description is used. Location falls back from product override to the global Workshop Bookings setting and finally to the WooCommerce store address.


## v0.6.4 multi-month bookings, bilingual UI and responsive calendar

Monthly participation can now span more than one selected month. Each product defines how many future months are visible and, separately, the maximum number of months a customer may include in a single booking. The monthly price is multiplied by the selected month count, while WooCommerce quantity remains the number of participants.

In monthly mode, session dates are informational only. Clicking an individual session displays guidance to select the month instead. Monthly-only products still populate the visual calendar with their real weekly occurrences, times and remaining capacity even when single-session purchases are disabled.

Calendar cells use responsive square tiles with compact time and remaining-place labels so the seven-column calendar remains readable on desktop and mobile.

The commercial UI is bilingual. English is the default, while WordPress installations using an `el_*` locale automatically receive the Greek interface. This covers the product booking panel, storefront calendar, cart/order booking metadata, customer calendar instructions, reminder/RSVP copy and the admin schedule editor.

Quantity terminology is neutral: the plugin uses “participants/participations” instead of assuming every booking is for children.
