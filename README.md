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

Native Google Calendar attendee RSVP requires a connected organizer Google Calendar account; the plugin's signed email RSVP works independently and is the fallback for Apple Calendar and non-Google users.


## Google Calendar OAuth

The plugin includes a WordPress-admin connection wizard for Google Calendar:

1. Enable Google Calendar API in a Google Cloud project.
2. Create an OAuth 2.0 Web application and copy the exact redirect URI displayed by the plugin.
3. Save the Client ID and Client Secret in Workshop Bookings settings.
4. Connect the organizer Google account through Google's OAuth consent screen.
5. Select a writable calendar and run the built-in connection test.

OAuth access/refresh tokens and the Client Secret are encrypted at rest with AES-256-GCM using a key derived from WordPress salts. They are never committed to the repository.

For completed/processing bookings, the plugin can create one Google event per occurrence, invite the billing email as an attendee, send Google invitation updates, and poll attendee RSVP responses back into booking capacity. A declined occurrence can release its seat while preserving the rest of a monthly booking.

The plugin also keeps its own signed email RSVP reminder because Google Calendar reminder settings are private to each attendee and cannot be forced by the organizer.
