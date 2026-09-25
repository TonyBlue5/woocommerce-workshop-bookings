=== Workshop Bookings for WooCommerce by e-iT ===
Contributors: TonyBlue5
Tags: woocommerce, bookings, workshops, calendar, icalendar
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight workshop booking slots for WooCommerce products with capacity control and Google Calendar / iCalendar links.

== Description ==

Workshop Bookings for WooCommerce keeps WooCommerce as the checkout and payment layer and adds workshop booking functionality to products.

Current features:
* Monthly participation that reserves every scheduled session in a selected month.
* Single-session participation for one available occurrence.
* Separate monthly and single prices per WooCommerce product.
* Shared per-occurrence capacity between monthly and single bookings.
* Weekly recurring schedules and blackout dates.
* Enable booking per WooCommerce product.
* Multiple date/time slots per workshop.
* Capacity per slot.
* Product quantity acts as number of seats/children.
* Capacity validation before checkout.
* Booking data stored on WooCommerce order items.
* Google Calendar links in customer order emails.
* Apple Calendar / iCalendar (.ics) downloads.
* HPOS-aware order access using WooCommerce APIs.

== Installation ==

1. Upload and activate the plugin.
2. Open a WooCommerce product.
3. Go to Product data > Workshop Booking.
4. Enable booking.
5. Add one slot per line using: YYYY-MM-DD|HH:MM|HH:MM|CAPACITY

Example:
2026-10-03|11:00|11:45|10
2026-10-10|11:00|11:45|10

== Changelog ==

= 0.3.0 =
* Added monthly recurring workshop bookings.
* Added separate monthly and single-session pricing.
* Added shared capacity across recurring and individual bookings.
* Added weekly schedules and blackout dates.
* Added multi-event iCalendar export for monthly bookings.
* Added Google Calendar links for every booked occurrence.
* Added recurring booking security regression tests.

= 0.2.0 =
* Security hardening and signed GitHub updater.


= 0.4.0 =
* Visual availability calendar with disabled non-workshop days and remaining-seat badges.
* Structured weekly schedule and blackout date controls in the product editor.
* Four-hour attendance reminder emails with signed YES/NO response links.
* Declined individual occurrences release their capacity.
* Four-hour iCalendar alarms.
