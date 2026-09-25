# Changelog

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

All notable changes to Kangiroo Workshop Bookings are documented here.

## [0.1.0] - 2026-09-24

### Added
- Workshop booking mode for WooCommerce products.
- Multiple dated time slots with independent capacity.
- Seat quantity validation.
- Booking information on cart/order line items.
- Google Calendar link generation.
- Signed iCalendar download URLs for customer bookings.
- WooCommerce HPOS compatibility declaration.
