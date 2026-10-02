=== Workshop Bookings Lite for WooCommerce ===
Contributors: tonyblue5
Tags: bookings, workshops, calendar, woocommerce, classes
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell workshop seats, single sessions and monthly participation with shared capacity, a visual calendar and API-free calendar exports.

== Description ==

Workshop Bookings Lite adds workshop bookings to WooCommerce products. Customers choose a session or one or more months, select the number of participants and pay through WooCommerce.

* Visual availability calendar with recurring weekly schedules and blackout dates.
* Individual sessions and monthly participation with shared occurrence capacity.
* Multiple selected months in one purchase; quantity is the number of participants.
* Basic scheduled email reminders.
* Add to Google Calendar links and signed multi-event iCalendar downloads for Apple Calendar and Outlook.
* English and Greek interface, selected by the WordPress locale.
* WooCommerce order APIs for both legacy order storage and HPOS.

Monthly participation is a one-time purchase of selected months, not automatic recurring billing. Reminders require a functioning WordPress cron runner and email transport. Configure workshop products using the standard WooCommerce product form.

Lite is independently usable. It includes no external update checker, licensing service, analytics collection or premium feature unlocking code. Optional Pro functionality is distributed in a separate plugin and includes customer analytics, loyalty and referrals, attendance responses, cancellation broadcasts and message templates.

= Privacy and external services =

Booking information is stored with WooCommerce order items. The plugin reads billing email for transactional reminders and uses your site's configured email transport. Calendar links are generated locally. Google receives event details only when a customer opens an Add to Google Calendar link; using that link is optional. No Google API credentials or central booking service are required. Calendar download URLs are private signed links; share them only with intended participants.

= Existing bookings =

The existing _kwb_* product and order-item metadata is retained. Deactivate another Workshop Bookings edition before activating Lite. Deactivation and removal of plugin files do not delete bookings. Back up files and the database before changing editions.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the workshop-bookings-for-woocommerce folder to wp-content/plugins, or upload the installable ZIP through Plugins > Add New.
3. Deactivate any legacy or Pro edition, then activate Lite.
4. Open WooCommerce > Workshop Bookings to configure reminders and calendars.
5. Edit a WooCommerce product, enable Workshop Booking and enter schedules, capacity and prices.
6. Make a test booking and verify totals, calendar downloads and reminder delivery.

== Frequently Asked Questions ==

= Is WooCommerce required? =
Yes. This plugin uses WooCommerce products, orders, customer details and payment gateways.

= Can monthly and single bookings share the same places? =
Yes. A monthly booking reserves its selected scheduled occurrences, and single bookings use the same availability.

= Does it renew automatically? =
No. The customer purchases the selected months in one checkout.

= Do calendar exports need API keys? =
No. Google links and signed .ics downloads are generated locally.

= Can customers book as guests? =
Yes, when guest checkout is enabled in WooCommerce. Referral rewards belong to the separate Pro edition.

= How do I switch to Pro? =
Back up your site, deactivate Lite and activate Pro. Existing booking data stays in the database. Do not run both editions together.

= Does uninstalling erase bookings? =
No. Order history and settings are retained. Remove personal data through your site's established WooCommerce data-retention procedures.

= Are there any hosted dependencies? =
No external booking, licensing or update service is required. Email uses WordPress and the configured mail transport.

== Screenshots ==

1. Visual booking calendar with remaining places.
2. Product workshop schedules, prices and shared capacity.
3. Single and monthly participation in the cart.
4. Booking details with calendar export links.
5. Greek booking interface.

== Changelog ==

= 1.0.0 =
* First separately packaged Lite edition.
* Existing booking metadata and calendar URLs preserved.
* Core booking, multi-month purchases, capacity and basic reminders.
* No external updater or licensing mechanism.

== Upgrade Notice ==

= 1.0.0 =
Back up your site and deactivate the legacy or Pro edition before activating Lite. Existing bookings are retained.
