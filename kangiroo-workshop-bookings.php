<?php
/**
 * Plugin Name: Kangiroo Workshop Bookings for WooCommerce
 * Description: Lightweight workshop booking slots for WooCommerce products with capacity control and Google Calendar / iCalendar links in customer emails.
 * Version: 0.2.0
 * Author: e-iT
 * Requires Plugins: woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 11.1
 * Text Domain: kangiroo-workshop-bookings
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declare WooCommerce HPOS compatibility as early as WooCommerce expects it.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

final class Kangiroo_Workshop_Bookings {

	const VERSION      = '0.2.0';
	const META_ENABLED = '_kwb_enabled';
	const META_SLOTS   = '_kwb_slots';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ) );
	}

	public static function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_required_notice' ) );
			return;
		}

		// Product admin.
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_settings' ) );

		// Front-end booking selection.
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_booking_fields' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 6 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart_capacity' ) );

		// Calendar links in customer emails and order details.
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_calendar_links' ), 20, 4 );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'order_calendar_links' ), 20 );

		// Signed .ics endpoint.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_download_ics' ) );
	}

	public static function woocommerce_required_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>Kangiroo Workshop Bookings:</strong> ';
		esc_html_e( 'Απαιτείται ενεργό WooCommerce.', 'kangiroo-workshop-bookings' );
		echo '</p></div>';
	}

	public static function add_product_tab( $tabs ) {
		$tabs['kwb_booking'] = array(
			'label'    => __( 'Workshop Booking', 'kangiroo-workshop-bookings' ),
			'target'   => 'kwb_booking_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 80,
		);

		return $tabs;
	}

	public static function render_product_panel() {
		global $post;

		if ( ! $post ) {
			return;
		}

		$enabled = get_post_meta( $post->ID, self::META_ENABLED, true );
		$slots   = get_post_meta( $post->ID, self::META_SLOTS, true );

		if ( ! is_string( $slots ) ) {
			$slots = '';
		}
		wp_nonce_field( 'kwb_save_product_' . $post->ID, 'kwb_nonce' );
		?>
		<div id="kwb_booking_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => self::META_ENABLED,
						'label'       => __( 'Ενεργοποίηση booking', 'kangiroo-workshop-bookings' ),
						'description' => __( 'Μετατρέπει το προϊόν σε εργαστήριο με επιλογή ημερομηνίας και ώρας.', 'kangiroo-workshop-bookings' ),
						'value'       => $enabled,
					)
				);
				?>
				<p class="form-field">
					<label for="<?php echo esc_attr( self::META_SLOTS ); ?>">
						<?php esc_html_e( 'Ημερομηνίες / ώρες', 'kangiroo-workshop-bookings' ); ?>
					</label>
					<textarea
						style="width:70%;min-height:180px"
						id="<?php echo esc_attr( self::META_SLOTS ); ?>"
						name="<?php echo esc_attr( self::META_SLOTS ); ?>"
						placeholder="2026-10-03|11:00|11:45|10&#10;2026-10-10|11:00|11:45|10"
					><?php echo esc_textarea( $slots ); ?></textarea>
					<span class="description" style="display:block;margin-left:150px;max-width:700px">
						<?php esc_html_e( 'Μία κράτηση ανά γραμμή: YYYY-MM-DD|HH:MM|HH:MM|ΧΩΡΗΤΙΚΟΤΗΤΑ. Παράδειγμα: 2026-10-03|11:00|11:45|10', 'kangiroo-workshop-bookings' ); ?>
					</span>
				</p>
			</div>
		</div>
		<?php
	}

	public static function save_product_settings( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST['kwb_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['kwb_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'kwb_save_product_' . $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no';
		update_post_meta( $post_id, self::META_ENABLED, $enabled );

		$raw = isset( $_POST[ self::META_SLOTS ] ) ? (string) wp_unslash( $_POST[ self::META_SLOTS ] ) : '';

		// Prevent oversized admin payloads from being stored or parsed.
		if ( strlen( $raw ) > 100000 ) {
			$raw = substr( $raw, 0, 100000 );
		}

		$lines = array_slice( preg_split( '/\r\n|\r|\n/', $raw ), 0, 500 );
		$clean = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line ) );

			if ( 4 !== count( $parts ) ) {
				continue;
			}

			list( $date, $start, $end, $capacity ) = $parts;

			if ( ! self::valid_date( $date ) || ! self::valid_time( $start ) || ! self::valid_time( $end ) ) {
				continue;
			}

			$timezone = wp_timezone();
			$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $start, $timezone );
			$end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $end, $timezone );

			if ( ! $start_dt || ! $end_dt || $end_dt <= $start_dt ) {
				continue;
			}

			$capacity = min( 10000, max( 1, absint( $capacity ) ) );
			$clean[]  = sprintf( '%s|%s|%s|%d', $date, $start, $end, $capacity );
		}

		update_post_meta( $post_id, self::META_SLOTS, implode( "\n", $clean ) );
	}

	private static function valid_date( $date ) {
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	private static function valid_time( $time ) {
		return (bool) preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time );
	}

	private static function is_booking_product( $product_id ) {
		return 'yes' === get_post_meta( $product_id, self::META_ENABLED, true );
	}

	private static function parse_slots( $product_id ) {
		$raw   = (string) get_post_meta( $product_id, self::META_SLOTS, true );
		$slots = array();

		if ( '' === trim( $raw ) ) {
			return $slots;
		}

		$timezone = wp_timezone();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', trim( $line ) ) );

			if ( 4 !== count( $parts ) ) {
				continue;
			}

			list( $date, $start, $end, $capacity ) = $parts;

			if ( ! self::valid_date( $date ) || ! self::valid_time( $start ) || ! self::valid_time( $end ) ) {
				continue;
			}

			$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $start, $timezone );
			$end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $end, $timezone );

			if ( ! $start_dt || ! $end_dt || $end_dt <= $start_dt ) {
				continue;
			}

			$slot_id = hash( 'sha256', $product_id . '|' . $date . '|' . $start . '|' . $end );

			$slots[ $slot_id ] = array(
				'id'       => $slot_id,
				'date'     => $date,
				'start'    => $start,
				'end'      => $end,
				'capacity' => min( 10000, max( 1, absint( $capacity ) ) ),
				'start_dt' => $start_dt,
				'end_dt'   => $end_dt,
			);
		}

		return $slots;
	}

	private static function format_slot_label( $slot ) {
		$date = wp_date( 'l j F Y', $slot['start_dt']->getTimestamp(), wp_timezone() );
		return sprintf( '%s, %s–%s', $date, $slot['start'], $slot['end'] );
	}

	public static function render_booking_fields() {
		global $product;

		if ( ! $product || ! self::is_booking_product( $product->get_id() ) ) {
			return;
		}

		$slots = self::parse_slots( $product->get_id() );
		$now   = new DateTimeImmutable( 'now', wp_timezone() );
		?>
		<div class="kwb-booking-fields" style="margin:1em 0">
			<label for="kwb_slot" style="display:block;font-weight:600;margin-bottom:.35em">
				<?php esc_html_e( 'Επιλέξτε ημερομηνία & ώρα', 'kangiroo-workshop-bookings' ); ?>
			</label>
			<select name="kwb_slot" id="kwb_slot" required style="width:100%;max-width:460px">
				<option value=""><?php esc_html_e( '— Επιλογή —', 'kangiroo-workshop-bookings' ); ?></option>
				<?php
				foreach ( $slots as $slot ) :
					if ( $slot['start_dt'] <= $now ) {
						continue;
					}

					$remaining = self::remaining_capacity( $product->get_id(), $slot );

					if ( $remaining < 1 ) {
						continue;
					}
					?>
					<option value="<?php echo esc_attr( $slot['id'] ); ?>">
						<?php
						echo esc_html(
							self::format_slot_label( $slot ) . ' — ' .
							sprintf(
								_n( '%d θέση διαθέσιμη', '%d θέσεις διαθέσιμες', $remaining, 'kangiroo-workshop-bookings' ),
								$remaining
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
			<small style="display:block;margin-top:.35em">
				<?php esc_html_e( 'Η ποσότητα του προϊόντος αντιστοιχεί στον αριθμό θέσεων/παιδιών.', 'kangiroo-workshop-bookings' ); ?>
			</small>
		</div>
		<?php
	}

	public static function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		$base_product_id = $variation_id ? wp_get_post_parent_id( $variation_id ) : $product_id;

		if ( ! self::is_booking_product( $base_product_id ) ) {
			return $passed;
		}

		$slot_id = isset( $_POST['kwb_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['kwb_slot'] ) ) : '';
		$slots   = self::parse_slots( $base_product_id );

		if ( ! $slot_id || ! isset( $slots[ $slot_id ] ) ) {
			wc_add_notice( __( 'Παρακαλώ επιλέξτε ημερομηνία και ώρα για το εργαστήριο.', 'kangiroo-workshop-bookings' ), 'error' );
			return false;
		}

		$slot = $slots[ $slot_id ];

		if ( $slot['start_dt'] <= new DateTimeImmutable( 'now', wp_timezone() ) ) {
			wc_add_notice( __( 'Η συγκεκριμένη ημερομηνία δεν είναι πλέον διαθέσιμη.', 'kangiroo-workshop-bookings' ), 'error' );
			return false;
		}

		$remaining = self::remaining_capacity( $base_product_id, $slot );

		if ( absint( $quantity ) > $remaining ) {
			wc_add_notice(
				sprintf(
					__( 'Υπάρχουν μόνο %d διαθέσιμες θέσεις για αυτή την ημερομηνία.', 'kangiroo-workshop-bookings' ),
					$remaining
				),
				'error'
			);
			return false;
		}

		return $passed;
	}

	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$base_product_id = $variation_id ? wp_get_post_parent_id( $variation_id ) : $product_id;

		if ( ! self::is_booking_product( $base_product_id ) ) {
			return $cart_item_data;
		}

		$slot_id = isset( $_POST['kwb_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['kwb_slot'] ) ) : '';
		$slots   = self::parse_slots( $base_product_id );

		if ( $slot_id && isset( $slots[ $slot_id ] ) ) {
			$slot = $slots[ $slot_id ];

			$cart_item_data['kwb_booking'] = array(
				'slot_id' => $slot_id,
				'date'    => $slot['date'],
				'start'   => $slot['start'],
				'end'     => $slot['end'],
			);

			// Keep different workshop dates as separate cart lines.
			$cart_item_data['kwb_unique'] = md5( $slot_id );
		}

		return $cart_item_data;
	}

	public static function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['kwb_booking'] ) ) {
			return $item_data;
		}

		$booking = $cart_item['kwb_booking'];

		$item_data[] = array(
			'key'   => __( 'Ημερομηνία', 'kangiroo-workshop-bookings' ),
			'value' => esc_html( wp_date( 'd/m/Y', strtotime( $booking['date'] ) ) ),
		);

		$item_data[] = array(
			'key'   => __( 'Ώρα', 'kangiroo-workshop-bookings' ),
			'value' => esc_html( $booking['start'] . '–' . $booking['end'] ),
		);

		return $item_data;
	}

	public static function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['kwb_booking'] ) ) {
			return;
		}

		$booking = $values['kwb_booking'];

		$item->add_meta_data( '_kwb_slot_id', $booking['slot_id'], true );
		$item->add_meta_data( '_kwb_date', $booking['date'], true );
		$item->add_meta_data( '_kwb_start', $booking['start'], true );
		$item->add_meta_data( '_kwb_end', $booking['end'], true );

		$item->add_meta_data(
			__( 'Ημερομηνία εργαστηρίου', 'kangiroo-workshop-bookings' ),
			wp_date( 'd/m/Y', strtotime( $booking['date'] ) ),
			true
		);

		$item->add_meta_data(
			__( 'Ώρα εργαστηρίου', 'kangiroo-workshop-bookings' ),
			$booking['start'] . '–' . $booking['end'],
			true
		);
	}

	public static function validate_cart_capacity() {
		if ( ! WC()->cart ) {
			return;
		}

		$requested = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['kwb_booking']['slot_id'] ) ) {
				continue;
			}

			$product_id = $cart_item['variation_id']
				? wp_get_post_parent_id( $cart_item['variation_id'] )
				: $cart_item['product_id'];

			$slot_id = $cart_item['kwb_booking']['slot_id'];

			if ( ! isset( $requested[ $product_id ] ) ) {
				$requested[ $product_id ] = array();
			}

			if ( ! isset( $requested[ $product_id ][ $slot_id ] ) ) {
				$requested[ $product_id ][ $slot_id ] = 0;
			}

			$requested[ $product_id ][ $slot_id ] += absint( $cart_item['quantity'] );
		}

		foreach ( $requested as $product_id => $by_slot ) {
			$slots = self::parse_slots( $product_id );

			foreach ( $by_slot as $slot_id => $qty ) {
				if ( ! isset( $slots[ $slot_id ] ) ) {
					wc_add_notice( __( 'Μία από τις επιλεγμένες ημερομηνίες εργαστηρίου δεν είναι πλέον διαθέσιμη.', 'kangiroo-workshop-bookings' ), 'error' );
					continue;
				}

				$remaining = self::remaining_capacity( $product_id, $slots[ $slot_id ] );

				if ( $qty > $remaining ) {
					wc_add_notice(
						sprintf(
							__( 'Για το «%1$s» απομένουν %2$d θέσεις στη συγκεκριμένη ημερομηνία.', 'kangiroo-workshop-bookings' ),
							get_the_title( $product_id ),
							$remaining
						),
						'error'
					);
				}
			}
		}
	}

	private static function remaining_capacity( $product_id, $slot ) {
		$booked = self::booked_quantity( $product_id, $slot['id'] );
		return max( 0, absint( $slot['capacity'] ) - $booked );
	}

	private static function booked_quantity( $product_id, $slot_id ) {
		$orders = wc_get_orders(
			array(
				'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		if ( empty( $orders ) ) {
			return 0;
		}

		$total = 0;

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$item_product_id = $item->get_variation_id()
					? wp_get_post_parent_id( $item->get_variation_id() )
					: $item->get_product_id();

				if ( (int) $item_product_id !== (int) $product_id ) {
					continue;
				}

				if ( (string) $item->get_meta( '_kwb_slot_id', true ) === (string) $slot_id ) {
					$total += absint( $item->get_quantity() );
				}
			}
		}

		return $total;
	}

	public static function email_calendar_links( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order ) {
			return;
		}

		if ( in_array( $order->get_status(), array( 'failed', 'cancelled', 'refunded' ), true ) ) {
			return;
		}

		self::render_calendar_links_for_order( $order, $plain_text );
	}

	public static function order_calendar_links( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::render_calendar_links_for_order( $order, false );
	}

	private static function render_calendar_links_for_order( $order, $plain_text = false ) {
		$bookings = self::get_order_bookings( $order );

		if ( empty( $bookings ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Προσθήκη κράτησης στο ημερολόγιο:', 'kangiroo-workshop-bookings' ) . "\n";

			foreach ( $bookings as $booking ) {
				echo esc_html( $booking['title'] ) . "\n";
				echo 'Google Calendar: ' . esc_url_raw( $booking['google_url'] ) . "\n";
				echo 'iCalendar / Apple Calendar: ' . esc_url_raw( $booking['ics_url'] ) . "\n\n";
			}

			return;
		}

		echo '<div style="margin:24px 0;padding:18px;border:1px solid #e5e5e5;border-radius:8px">';
		echo '<h3 style="margin-top:0">' . esc_html__( 'Προσθήκη στο ημερολόγιό σας', 'kangiroo-workshop-bookings' ) . '</h3>';

		foreach ( $bookings as $booking ) {
			echo '<p style="margin:0 0 8px"><strong>' . esc_html( $booking['title'] ) . '</strong><br>' . esc_html( $booking['label'] ) . '</p>';
			echo '<p style="margin:0 0 16px">';
			echo '<a href="' . esc_url( $booking['google_url'] ) . '" target="_blank" rel="noopener" style="display:inline-block;padding:9px 13px;margin:0 8px 6px 0;border:1px solid #ccc;border-radius:4px;text-decoration:none">📅 Google Calendar</a>';
			echo '<a href="' . esc_url( $booking['ics_url'] ) . '" style="display:inline-block;padding:9px 13px;margin:0 0 6px;border:1px solid #ccc;border-radius:4px;text-decoration:none">🍎 Apple / iCalendar</a>';
			echo '</p>';
		}

		echo '</div>';
	}

	private static function get_order_bookings( $order ) {
		$result = array();

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$date  = $item->get_meta( '_kwb_date', true );
			$start = $item->get_meta( '_kwb_start', true );
			$end   = $item->get_meta( '_kwb_end', true );

			if ( ! $date || ! $start || ! $end ) {
				continue;
			}

			$tz       = wp_timezone();
			$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $start, $tz );
			$end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $end, $tz );

			if ( ! $start_dt || ! $end_dt ) {
				continue;
			}

			$title       = $item->get_name();
			$description = sprintf(
				__( 'Κράτηση WooCommerce #%s', 'kangiroo-workshop-bookings' ),
				$order->get_order_number()
			);
			$location = get_bloginfo( 'name' );

			$google_url = add_query_arg(
				array(
					'action'   => 'TEMPLATE',
					'text'     => $title,
					'dates'    => self::google_dates( $start_dt, $end_dt ),
					'details'  => $description,
					'location' => $location,
				),
				'https://calendar.google.com/calendar/render'
			);

			$result[] = array(
				'title'      => $title,
				'label'      => wp_date( 'd/m/Y', $start_dt->getTimestamp(), $tz ) . ' ' . $start . '–' . $end,
				'google_url' => $google_url,
				'ics_url'    => self::ics_url( $order->get_id(), $item_id ),
			);
		}

		return $result;
	}

	private static function google_dates( DateTimeImmutable $start, DateTimeImmutable $end ) {
		$utc = new DateTimeZone( 'UTC' );

		return $start->setTimezone( $utc )->format( 'Ymd\THis\Z' ) . '/' .
			$end->setTimezone( $utc )->format( 'Ymd\THis\Z' );
	}

	private static function signature( $order_id, $item_id ) {
		$order = wc_get_order( absint( $order_id ) );
		$item  = $order ? $order->get_item( absint( $item_id ) ) : false;

		$context = absint( $order_id ) . '|' . absint( $item_id );

		if ( $order && $item ) {
			$context .= '|' . (string) $order->get_order_key();
			$context .= '|' . (string) $item->get_meta( '_kwb_date', true );
			$context .= '|' . (string) $item->get_meta( '_kwb_start', true );
			$context .= '|' . (string) $item->get_meta( '_kwb_end', true );
		}

		return hash_hmac( 'sha256', $context, wp_salt( 'auth' ) );
	}

	private static function ics_url( $order_id, $item_id ) {
		return add_query_arg(
			array(
				'kwb_ics'  => 1,
				'order_id' => absint( $order_id ),
				'item_id'  => absint( $item_id ),
				'sig'      => self::signature( $order_id, $item_id ),
			),
			home_url( '/' )
		);
	}

	public static function maybe_download_ics() {
		if ( empty( $_GET['kwb_ics'] ) ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$item_id  = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
		$sig      = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		if ( ! $order_id || ! $item_id || ! hash_equals( self::signature( $order_id, $item_id ), $sig ) ) {
			status_header( 403 );
			exit;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			status_header( 404 );
			exit;
		}

		$item = $order->get_item( $item_id );

		if ( ! $item ) {
			status_header( 404 );
			exit;
		}

		$date  = $item->get_meta( '_kwb_date', true );
		$start = $item->get_meta( '_kwb_start', true );
		$end   = $item->get_meta( '_kwb_end', true );

		if ( ! $date || ! $start || ! $end ) {
			status_header( 404 );
			exit;
		}

		$tz       = wp_timezone();
		$start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $start, $tz );
		$end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $end, $tz );

		if ( ! $start_dt || ! $end_dt ) {
			status_header( 404 );
			exit;
		}

		$host        = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$uid         = sprintf( 'kwb-%d-%d@%s', $order_id, $item_id, $host );
		$title       = $item->get_name();
		$description = sprintf(
			__( 'Κράτηση WooCommerce #%s', 'kangiroo-workshop-bookings' ),
			$order->get_order_number()
		);
		$location = get_bloginfo( 'name' );
		$utc      = new DateTimeZone( 'UTC' );

		$ics = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//e-iT//Kangiroo Workshop Bookings//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . self::ics_escape( $uid ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . $start_dt->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
			'DTEND:' . $end_dt->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
			'SUMMARY:' . self::ics_escape( $title ),
			'DESCRIPTION:' . self::ics_escape( $description ),
			'LOCATION:' . self::ics_escape( $location ),
			'END:VEVENT',
			'END:VCALENDAR',
		);

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: default-src \'none\'; sandbox' );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header(
			'Content-Disposition: attachment; filename="kangiroo-booking-' .
			$order_id . '-' . $item_id . '.ics"'
		);

		echo implode( "\r\n", $ics ) . "\r\n";
		exit;
	}

	private static function ics_escape( $value ) {
		return str_replace(
			array( '\\', ';', ',', "\r\n", "\r", "\n" ),
			array( '\\\\', '\;', '\,', '\n', '\n', '\n' ),
			(string) $value
		);
	}
}

Kangiroo_Workshop_Bookings::init();


/**
 * Fail-closed GitHub updater.
 *
 * Updates are only offered when a trusted public signing key is configured via:
 * define( 'KWB_RELEASE_PUBLIC_KEY', '-----BEGIN PUBLIC KEY-----...-----END PUBLIC KEY-----' );
 *
 * Every release must contain the exact ZIP, SHA-256 checksum and detached RSA signature.
 * The plugin refuses to install the update if any of those checks fail.
 */
final class KWB_GitHub_Updater {

	const VERSION = '0.2.0';
	const API     = 'https://api.github.com/repos/TonyBlue5/woocommerce-workshop-bookings/releases/latest';
	const SLUG    = 'kangiroo-workshop-bookings';
	const PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA0r9E6m6osaldFxI1ALSi\nTaUaT5q0WtaA1tyjKwdNAyvkZKuZ2gppLEW8184AX+qg5BYp7YX/65nx1gYdHord\nXHv1YD+al4A6AcTZB6OV7omma/m0XauuyJWIW3jNyGyYkBDEdX3sfAlLG18VCBnv\n2FQQa4OcjXYSh03neJUHofXTL7N2G9YHqAr3BQdY3xTKAB5f/KdSGsAgNjJ+h9Sk\n7zB7E2LhcMBfKCnbkydbHmApoaiYq75OVizCFfbWkO3L0PuUpknLv8mo30Lw1y5a\noAUMbBjKmSri1L6Kwi7gq00ahJQ1cgLQhysXb5j9l7rgYu2jwnRneJ7K0qA3cgZc\ntLOQ8Bs2J/4pMuTpxIoqyHDS/GX/7+HPP86tNHCzEU2ckYSXkwzU7pyxgAFYO0Ul\nbQxBlBX74CqUWhDamRM+SbtPE3f0ZD+UJHwJhCbsSB9d4++QOL9ZVAcadpoCSGsO\nhRUTbOk+nue0VOihhqMw3u2vxn73qVhhesLz9BlqSheg/pWNk1wN6EBZWheC/xGR\n9J1r1aD5HgCIhR52RTbExZ4ANMZO2ZAFfGh8RynZiEx311mH2X0UPBxpdm8mqDsS\nC933cyvkeKiiqmnTMU9QF1HX07A+xyh3eGJiQNGmXL3GRrPS6O52tfla9wj0y77d\n6uBSKpmDU/gAfp1MiX3mz6cCAwEAAQ==\n-----END PUBLIC KEY-----";

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'updates' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verify_download' ), 10, 4 );
	}

	private static function public_key() {
		return self::PUBLIC_KEY;
	}

	private static function trusted_asset_url( $url, $filename ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		if ( empty( $parts['host'] ) || 'github.com' !== strtolower( $parts['host'] ) ) {
			return false;
		}

		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$prefix = '/TonyBlue5/woocommerce-workshop-bookings/releases/download/';

		if ( 0 !== strpos( $path, $prefix ) ) {
			return false;
		}

		return '/' . $filename === substr( $path, -strlen( '/' . $filename ) );
	}

	private static function release() {
		$cached = get_site_transient( 'kwb_github_release' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			self::API,
			array(
				'timeout'     => 12,
				'redirection' => 0,
				'headers'     => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Kangiroo-Workshop-Bookings/' . self::VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( 'kwb_github_release', array(), HOUR_IN_SECONDS );
			return array();
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $json ) ) {
			set_site_transient( 'kwb_github_release', array(), HOUR_IN_SECONDS );
			return array();
		}

		$version = ltrim( (string) ( $json['tag_name'] ?? '' ), 'vV' );

		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			set_site_transient( 'kwb_github_release', array(), HOUR_IN_SECONDS );
			return array();
		}

		$assets = array();

		foreach ( (array) ( $json['assets'] ?? array() ) as $asset ) {
			$name = isset( $asset['name'] ) ? sanitize_file_name( $asset['name'] ) : '';
			$url  = isset( $asset['browser_download_url'] ) ? esc_url_raw( $asset['browser_download_url'] ) : '';

			if ( $name && $url ) {
				$assets[ $name ] = $url;
			}
		}

		$zip_name = 'kangiroo-workshop-bookings.zip';
		$sha_name = 'kangiroo-workshop-bookings.zip.sha256';
		$sig_name = 'kangiroo-workshop-bookings.zip.sig';

		if (
			empty( $assets[ $zip_name ] ) ||
			empty( $assets[ $sha_name ] ) ||
			empty( $assets[ $sig_name ] ) ||
			! self::trusted_asset_url( $assets[ $zip_name ], $zip_name ) ||
			! self::trusted_asset_url( $assets[ $sha_name ], $sha_name ) ||
			! self::trusted_asset_url( $assets[ $sig_name ], $sig_name )
		) {
			set_site_transient( 'kwb_github_release', array(), HOUR_IN_SECONDS );
			return array();
		}

		$checksum_response = wp_remote_get(
			$assets[ $sha_name ],
			array(
				'timeout'     => 10,
				'redirection' => 5,
				'headers'     => array( 'User-Agent' => 'Kangiroo-Workshop-Bookings/' . self::VERSION ),
			)
		);

		if (
			is_wp_error( $checksum_response ) ||
			200 !== wp_remote_retrieve_response_code( $checksum_response ) ||
			! preg_match( '/\b([a-f0-9]{64})\b/i', wp_remote_retrieve_body( $checksum_response ), $match )
		) {
			set_site_transient( 'kwb_github_release', array(), HOUR_IN_SECONDS );
			return array();
		}

		$data = array(
			'version'   => $version,
			'package'   => $assets[ $zip_name ],
			'sha256'    => strtolower( $match[1] ),
			'signature' => $assets[ $sig_name ],
			'url'       => isset( $json['html_url'] ) ? esc_url_raw( $json['html_url'] ) : '',
			'body'      => isset( $json['body'] ) ? wp_kses_post( $json['body'] ) : '',
		);

		set_site_transient( 'kwb_github_release', $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	public static function updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::release();
		$plugin  = plugin_basename( __FILE__ );

		if ( $release && version_compare( self::VERSION, $release['version'], '<' ) ) {
			$transient->response[ $plugin ] = (object) array(
				'slug'         => self::SLUG,
				'plugin'       => $plugin,
				'new_version'  => $release['version'],
				'url'          => $release['url'],
				'package'      => $release['package'],
				'requires'     => '6.4',
				'requires_php' => '7.4',
			);
		} else {
			unset( $transient->response[ $plugin ] );
		}

		return $transient;
	}

	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::release();

		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Kangiroo Workshop Bookings for WooCommerce',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => 'e-iT',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'requires'      => '6.4',
			'requires_php'  => '7.4',
			'sections'      => array(
				'description' => 'Workshop booking layer for WooCommerce.',
				'changelog'   => $release['body'] ? $release['body'] : 'Security and compatibility maintenance release.',
			),
		);
	}

	public static function verify_download( $reply, $package, $upgrader, $hook_extra ) {
		$release = self::release();

		if ( ! $release || $package !== $release['package'] ) {
			return $reply;
		}

		$public_key = self::public_key();

		if ( ! $public_key || ! function_exists( 'openssl_verify' ) ) {
			return new WP_Error(
				'kwb_crypto_unavailable',
				'Kangiroo Workshop Bookings blocked the update because cryptographic signature verification is unavailable.'
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp = download_url( $package, 300 );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$actual = strtolower( hash_file( 'sha256', $tmp ) );

		if ( ! hash_equals( $release['sha256'], $actual ) ) {
			@unlink( $tmp );
			return new WP_Error(
				'kwb_bad_checksum',
				'Kangiroo Workshop Bookings blocked the update: SHA-256 verification failed.'
			);
		}

		$sig_response = wp_remote_get(
			$release['signature'],
			array(
				'timeout'     => 15,
				'redirection' => 5,
				'headers'     => array( 'User-Agent' => 'Kangiroo-Workshop-Bookings/' . self::VERSION ),
			)
		);

		if ( is_wp_error( $sig_response ) || 200 !== wp_remote_retrieve_response_code( $sig_response ) ) {
			@unlink( $tmp );
			return new WP_Error(
				'kwb_signature_missing',
				'Kangiroo Workshop Bookings blocked the update: the digital signature could not be downloaded.'
			);
		}

		$signature = wp_remote_retrieve_body( $sig_response );
		$contents  = file_get_contents( $tmp );

		$verified = is_string( $signature ) &&
			'' !== $signature &&
			false !== $contents &&
			1 === openssl_verify( $contents, $signature, $public_key, OPENSSL_ALGO_SHA256 );

		unset( $contents );

		if ( ! $verified ) {
			@unlink( $tmp );
			return new WP_Error(
				'kwb_bad_signature',
				'Kangiroo Workshop Bookings blocked the update: publisher signature verification failed.'
			);
		}

		return $tmp;
	}
}

KWB_GitHub_Updater::init();
