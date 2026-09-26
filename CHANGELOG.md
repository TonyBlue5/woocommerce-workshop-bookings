# Changelog

## 0.6.4
- Added multi-month monthly bookings with a per-product maximum number of selectable months.
- Monthly pricing now multiplies by the number of selected months while quantity continues to represent participants.
- Monthly mode no longer allows individual date selection; clicking a session date shows guidance to use the month-selection button.
- Added the “Maximum number of booking months” product setting directly below the booking horizon.
- Reworked calendar cells into responsive square tiles with compact, readable time and remaining-place information on desktop and mobile.
- Fixed monthly-only workshop products so their scheduled dates, times and remaining places are still shown in the visual calendar.
- Renamed “Maximum children per booking” to the neutral “Maximum participations per booking”.
- Front-end quantity help now refers to participants rather than children.
- Added automatic English / Greek UI based on the WordPress locale.
- English is the default commercial UI; Greek is selected automatically for el_* WordPress locales.
- Internationalized product booking settings, visual calendar, cart/order labels, customer calendar instructions, RSVP reminders and RSVP response pages.
- Localized the JavaScript calendar and admin schedule editor.
- Added regression coverage for monthly-only calendar visibility and English default terminology.


## 0.6.2
- Fixed delayed update detection caused by a 12-hour custom GitHub release cache.
- GitHub release metadata now refreshes every 10 minutes when no newer version is cached.
- WordPress Dashboard > Updates > Check again now clears the plugin's custom update cache.
- Older cached release metadata without a fetch timestamp is automatically treated as stale.


## 0.6.1
- Refined storefront booking calendar typography and spacing with a Roboto-first font stack.
- Active workshop dates always show date, time and remaining capacity without relying on hover styles.
- Monthly selection button no longer includes the remaining-seat count.
- Monthly selection summary now explains the total number of participations per child.
- Improved customer calendar section in order details and emails with clear instructions and cleaner Google / Apple / Outlook buttons, without confusing “all dates” wording.
- Added per-workshop calendar description override with fallback to the WooCommerce product short description.
- Calendar exports now automatically enrich descriptions with booking type, date, time, order context and product link.
- Calendar location now falls back from workshop override to global plugin location and then to the WooCommerce store address.
- Google Calendar and .ics exports use the same enriched title, description and location data.


## 0.6.0
- Commercial API-free architecture: no Google Cloud project, OAuth client, Client ID, Client Secret or Calendar API is required.
- Direct “Add to Google Calendar” links are generated locally for each booked occurrence.
- Apple / Outlook / iCalendar downloads remain available through signed multi-event .ics files.
- Configurable email reminders and signed YES / NO RSVP links work entirely on the WordPress site.
- RSVP decline can immediately release only that occurrence's capacity for another booking.
- Re-confirming after a released seat now checks capacity first to prevent reclaiming a seat already taken by another customer.
- Reminder subject, reminder body and RSVP button labels are configurable with placeholders.
- Calendar title, description, location, Google-link visibility, .ics visibility and .ics reminder timing are configurable.
- Removed Google OAuth/API code and cleans up legacy development OAuth tokens/secrets during migration.
- Retains per-workshop overrides for reminder timing, RSVP, booking cutoff, maximum quantity and location.


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
