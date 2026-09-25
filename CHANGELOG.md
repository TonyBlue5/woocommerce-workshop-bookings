# Changelog

## 0.5.0
- Generic commercial branding: Workshop Bookings for WooCommerce by e-iT.
- Global settings for reminders, RSVP, booking defaults and Google Calendar behavior.
- Per-workshop overrides for reminder timing, RSVP, booking cutoff, maximum quantity and location.
- Google Calendar OAuth 2.0 connection wizard with exact redirect URI display.
- Encrypted at-rest storage for Google Client Secret and OAuth tokens using AES-256-GCM derived from WordPress salts.
- Calendar selection, write-access connection test and manual backfill/sync.
- Automatic Google event creation for paid bookings with attendees and invitation notifications.
- Configurable RSVP polling sync; Google declined responses can release occurrence capacity.
- Automatic cleanup of Google events on cancelled, refunded or failed orders.
- Diagnostics status and configurable sync interval.
- Security and compatibility regression coverage for OAuth state validation and encrypted credential storage.


## 0.4.0
- Replaced booking date dropdowns with a visual availability calendar.
- Disabled non-workshop days and displayed remaining seats on bookable dates.
- Added mode-aware labels: month selection for monthly participation and date selection for single participation.
- Replaced raw weekly schedule text with admin repeaters for weekday, start, end, and capacity.
- Replaced blackout text entry with date-picker rows.
- Added signed YES/NO attendance response links and four-hour reminder emails.
- Declined occurrences immediately release their capacity for single-session booking.
- Added four-hour VALARM reminders to iCalendar exports.
- Added RSVP security regression tests.


## 0.3.1
- Booking products are purchasable even when the standard WooCommerce base price is blank.
- Product pages display the configured monthly and/or single-session booking prices.


## 0.3.0
- Monthly recurring participation by selected calendar month.
- Single-session participation alongside monthly participation.
- Separate pricing for monthly and single bookings.
- Shared capacity accounting for both booking modes.
- Weekly recurring schedule configuration.
- Blackout dates for holidays/cancellations.
- Multi-event iCalendar export and per-occurrence Google Calendar links.
- Expanded security and WooCommerce compatibility tests.

All notable changes to Workshop Bookings for WooCommerce are documented here.

## [0.1.0] - 2026-09-24

### Added
- Workshop booking mode for WooCommerce products.
- Multiple dated time slots with independent capacity.
- Seat quantity validation.
- Booking information on cart/order line items.
- Google Calendar link generation.
- Signed iCalendar download URLs for customer bookings.
- WooCommerce HPOS compatibility declaration.
