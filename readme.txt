=== Workshop Bookings for WooCommerce by e-iT ===
Contributors: TonyBlue5
Tags: woocommerce, bookings, workshops, calendar, icalendar
Requires at least: 6.4
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.6.4
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
* Product quantity acts as number of participants.
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
5. Use the structured weekday/time/capacity controls to define the weekly schedule.
6. Add blackout dates with the date picker when needed.
7. Configure reminders and calendar options under WooCommerce > Workshop Bookings.

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


= 0.6.0 =
* API-free commercial architecture: no Google Cloud, OAuth or Calendar API setup.
* Local Add to Google Calendar links for every booked occurrence.
* Signed Apple / Outlook / iCalendar (.ics) downloads.
* Configurable reminder email subject, body and lead time.
* Signed YES / NO RSVP handled directly by the WordPress plugin.
* Optional automatic seat release on NO for the specific occurrence only.
* Capacity check before a previously released seat can be reclaimed.
* Configurable calendar title, description, location and .ics alarm.
* Per-workshop reminder, RSVP, booking-cutoff, quantity and location overrides.


= 0.6.1 =
* Improved calendar readability and theme isolation.
* Active dates always display time and remaining seats.
* Cleaner monthly selection text and per-child participation summary.
* Improved calendar links block in customer emails and order details.
* Rich Google / Apple / Outlook calendar metadata with product-description fallback.
* Automatic WooCommerce store-address fallback for calendar location.


= 0.6.2 =
* Fixed delayed GitHub update detection.
* Update metadata refreshes promptly instead of being held for 12 hours.
* Dashboard > Updates > Check again clears the plugin update cache.


= 0.6.4 =
* Added selection of multiple monthly booking months with a configurable per-product maximum.
* Monthly price is multiplied by the selected month count.
* Monthly mode blocks individual date selection and guides the customer to select a month.
* Fixed calendar availability display for monthly-only workshops.
* Added responsive square calendar cells with compact time and availability information.
* Renamed child-specific quantity terminology to neutral participation terminology.
* Added automatic English / Greek UI based on the WordPress language.
* Localized booking forms, product settings, calendar UI, reminders, RSVP and customer order metadata.
