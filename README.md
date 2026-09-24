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

- `kangiroo-workshop-bookings.zip`
- `kangiroo-workshop-bookings.zip.sha256`

## License

GPL-2.0-or-later.
