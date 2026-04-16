<?php
/**
 * Frontend rendering for all display contexts.
 *
 * @package QuickShipD
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShipD_Display
 *
 * Renders the estimated delivery date HTML for product pages, shop archives,
 * cart, and checkout. All output is escaped. Inline colours come from options,
 * never from user-supplied URL parameters.
 *
 * @since 1.0.0
 */
class QuickShipD_Display {

	/**
	 * Register all frontend hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( 'yes' !== get_option( 'quickshipd_enabled', 'yes' ) ) {
			return;
		}

		// Product page.
		if ( 'yes' === get_option( 'quickshipd_show_product', 'yes' ) ) {
			add_action( 'woocommerce_single_product_summary', array( $this, 'render_product' ), 25 );
		}

		// Shop / archive pages.
		if ( 'yes' === get_option( 'quickshipd_show_shop', 'no' ) ) {
			add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_shop' ), 15 );
		}

		// Cart item data.
		if ( 'yes' === get_option( 'quickshipd_show_cart', 'yes' ) ) {
			add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_item' ), 10, 2 );
		}

		// Checkout order review.
		if ( 'yes' === get_option( 'quickshipd_show_checkout', 'yes' ) ) {
			add_action( 'woocommerce_review_order_before_shipping', array( $this, 'render_checkout' ) );
		}

		// Assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX: variation change.
		add_action( 'wp_ajax_quickshipd_variation_date', array( $this, 'ajax_variation_date' ) );
		add_action( 'wp_ajax_nopriv_quickshipd_variation_date', array( $this, 'ajax_variation_date' ) );

	}

	/**
	 * Register preview AJAX — always available in admin regardless of enabled state.
	 *
	 * @return void
	 */
	public function init_preview_ajax(): void {
		add_action( 'wp_ajax_quickshipd_preview', array( $this, 'ajax_preview' ) );
	}

	// -----------------------------------------------------------------------
	// Product page.
	// -----------------------------------------------------------------------

	/**
	 * Output the delivery estimate on a single product page.
	 *
	 * @return void
	 */
	public function render_product(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// Honour the per-product disable flag.
		if ( 'yes' === get_post_meta( $product->get_id(), '_quickshipd_disabled', true ) ) {
			return;
		}

		// Only show for in-stock products.
		if ( ! $product->is_in_stock() ) {
			return;
		}

		// For variable products show a placeholder that JS will populate when
		// a variation is selected.
		if ( $product->is_type( 'variable' ) ) {
			echo '<div class="quickshipd-delivery quickshipd-variable" data-nonce="' . esc_attr( wp_create_nonce( 'quickshipd_variation' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" style="display:none;"></div>';
			return;
		}

		$calc   = QuickShipD_Calculator::from_settings( array(), $product->get_id() );
		$result = $calc->calculate();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside build_html.
		echo self::build_html( $result, $product->get_id(), 'product' );
	}

	// -----------------------------------------------------------------------
	// Shop / archive.
	// -----------------------------------------------------------------------

	/**
	 * Output a compact delivery estimate on shop archive pages.
	 *
	 * @return void
	 */
	public function render_shop(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( 'yes' === get_post_meta( $product->get_id(), '_quickshipd_disabled', true ) ) {
			return;
		}

		if ( ! $product->is_in_stock() ) {
			return;
		}

		$calc   = QuickShipD_Calculator::from_settings( array(), $product->get_id() );
		$result = $calc->calculate();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::build_html( $result, $product->get_id(), 'shop' );
	}

	// -----------------------------------------------------------------------
	// Cart item data.
	// -----------------------------------------------------------------------

	/**
	 * Append delivery estimate as a cart item data row.
	 *
	 * @param  array $item_data  Existing item data.
	 * @param  array $cart_item  Cart item array.
	 * @return array
	 */
	public function render_cart_item( array $item_data, array $cart_item ): array {
		$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id']
			? $cart_item['variation_id']
			: $cart_item['product_id'];

		if ( 'yes' === get_post_meta( $cart_item['product_id'], '_quickshipd_disabled', true ) ) {
			return $item_data;
		}

		$overrides = $this->get_selected_shipping_method_overrides();
		$calc      = QuickShipD_Calculator::from_settings( $overrides, (int) $product_id );
		$result    = $calc->calculate();

		if ( ! $result['show'] ) {
			return $item_data;
		}

		$date_fmt_val = (string) get_option( 'quickshipd_date_format', 'D, M j' );
		$date_fmt     = '' !== $date_fmt_val ? $date_fmt_val : 'D, M j';
		$date_label   = $result['is_range']
			? QuickShipD_Calculator::format_date( $result['min_date'], $date_fmt ) . ' – ' . QuickShipD_Calculator::format_date( $result['max_date'], $date_fmt )
			: QuickShipD_Calculator::format_date( $result['max_date'], $date_fmt );

		$item_data[] = array(
			'name'    => esc_html__( 'Est. Delivery', 'quickshipd' ),
			'value'   => esc_html( $date_label ),
			'display' => esc_html( $date_label ),
		);

		return $item_data;
	}

	// -----------------------------------------------------------------------
	// Checkout.
	// -----------------------------------------------------------------------

	/**
	 * Output the delivery estimate in the order review table on checkout.
	 *
	 * @return void
	 */
	public function render_checkout(): void {
		if ( ! WC()->cart ) {
			return;
		}

		$overrides  = $this->get_selected_shipping_method_overrides();
		$max_result = null;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id']
				? $cart_item['variation_id']
				: $cart_item['product_id'];

			if ( 'yes' === get_post_meta( $cart_item['product_id'], '_quickshipd_disabled', true ) ) {
				continue;
			}

			$calc   = QuickShipD_Calculator::from_settings( $overrides, (int) $product_id );
			$result = $calc->calculate();

			// Keep the latest max_date (worst-case delivery for the full order).
			if ( null === $max_result || $result['max_date'] > $max_result['max_date'] ) {
				$max_result = $result;
			}
		}

		if ( null === $max_result ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<tr id="quickshipd-checkout-delivery"><td colspan="2">' . self::build_html( $max_result, null, 'checkout' ) . '</td></tr>';
	}

	// -----------------------------------------------------------------------
	// HTML builder — shared by all contexts.
	// -----------------------------------------------------------------------

	/**
	 * Build the delivery date HTML string.
	 *
	 * This is public so the admin preview AJAX handler can call it directly.
	 *
	 * @param  array    $result     Output from QuickShipD_Calculator::calculate().
	 * @param  int|null $product_id Product ID (for context; currently unused in HTML).
	 * @param  string   $context    One of 'product', 'shop', 'cart', 'checkout'.
	 * @param  array    $s          Optional settings overrides (used by live preview).
	 * @return string   Escaped HTML.
	 */
	public static function build_html( array $result, ?int $product_id, string $context, array $s = array() ): string {
		if ( empty( $result['show'] ) ) {
			return '';
		}

		$opt = static function ( string $key, string $default ) use ( $s ): string {
			if ( isset( $s[ $key ] ) && '' !== $s[ $key ] ) {
				return (string) $s[ $key ];
			}
			$val = (string) get_option( $key, $default );
			return '' !== $val ? $val : $default;
		};

		$primary_color   = $opt( 'quickshipd_text_color', '#16a34a' );
		$secondary_color = $opt( 'quickshipd_secondary_color', '#6b7280' );
		$bg_color        = $opt( 'quickshipd_bg_color', '#f0fdf4' );
		$border_radius   = max( 0, (int) $opt( 'quickshipd_border_radius', '8' ) );
		$padding         = max( 0, (int) $opt( 'quickshipd_padding', '10' ) );
		$date_fmt        = $opt( 'quickshipd_date_format', 'D, M j' );
		$icon_type       = $opt( 'quickshipd_icon', 'truck' );

		$min_date_fmt = QuickShipD_Calculator::format_date( $result['min_date'], $date_fmt );
		$max_date_fmt = QuickShipD_Calculator::format_date( $result['max_date'], $date_fmt );

		// Build date label from template.
		if ( $result['is_range'] ) {
			$tpl        = $opt( 'quickshipd_text_range', 'Get it {start} – {end}' );
			$date_label = str_replace(
				array( '{start}', '{end}' ),
				array( $min_date_fmt, $max_date_fmt ),
				$tpl
			);
		} else {
			$tpl        = $opt( 'quickshipd_text_single', 'Get it by {date}' );
			$date_label = str_replace( '{date}', $max_date_fmt, $tpl );
		}

		// Container inline style.
		$style_parts = array();
		if ( '' !== $bg_color ) {
			$style_parts[] = 'background-color:' . $bg_color;
			$style_parts[] = 'padding:' . $padding . 'px ' . ( $padding + 4 ) . 'px';
			$style_parts[] = 'border-radius:' . $border_radius . 'px';
		}
		$container_style = implode( ';', $style_parts );

		// Context CSS class.
		$context_class = 'quickshipd-context-' . sanitize_html_class( $context );

		// Icon SVG.
		$icon_svg = self::get_icon_svg( $icon_type );

		$html  = '<div class="quickshipd-delivery ' . esc_attr( $context_class ) . '"' . ( $container_style ? ' style="' . esc_attr( $container_style ) . '"' : '' ) . '>';
		$html .= '<div class="quickshipd-estimate" style="color:' . esc_attr( $primary_color ) . '">';
		$html .= $icon_svg;
		$html .= '<span class="quickshipd-date-text">' . esc_html( $date_label ) . '</span>';
		$html .= '</div>';

		// Countdown (product context only, and only if enabled and there are seconds left).
		if (
			'product' === $context &&
			'yes' === $opt( 'quickshipd_show_countdown', 'yes' ) &&
			$result['countdown_seconds'] > 0
		) {
			$countdown_fmt  = QuickShipD_Calculator::format_countdown( $result['countdown_seconds'] );
			$countdown_tpl  = $opt( 'quickshipd_text_countdown', 'Order within {countdown} to get it by {date}' );
			// Bold time uses primary color; surrounding text uses secondary color.
			$strong_html    = '<strong style="color:' . esc_attr( $primary_color ) . '">' . esc_html( $countdown_fmt ) . '</strong>';
			$countdown_text = str_replace(
				array( '{countdown}', '{date}' ),
				array( $strong_html, esc_html( $max_date_fmt ) ),
				esc_html( $countdown_tpl )
			);
			$html .= '<div class="quickshipd-countdown" style="color:' . esc_attr( $secondary_color ) . '" data-seconds="' . absint( $result['countdown_seconds'] ) . '">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$html .= $countdown_text;
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	// -----------------------------------------------------------------------
	// Assets.
	// -----------------------------------------------------------------------

	/**
	 * Enqueue frontend CSS and JS on relevant pages only.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$show_product  = 'yes' === get_option( 'quickshipd_show_product', 'yes' );
		$show_shop     = 'yes' === get_option( 'quickshipd_show_shop', 'no' );
		$show_cart     = 'yes' === get_option( 'quickshipd_show_cart', 'yes' );
		$show_checkout = 'yes' === get_option( 'quickshipd_show_checkout', 'yes' );

		$should_load = (
			( $show_product && is_product() ) ||
			( $show_shop && ( is_shop() || is_product_category() || is_product_tag() ) ) ||
			( $show_cart && is_cart() ) ||
			( $show_checkout && is_checkout() )
		);

		if ( ! $should_load ) {
			return;
		}

		wp_enqueue_style(
			'quickshipd-frontend',
			QUICKSHIPD_URL . 'assets/css/frontend.css',
			array(),
			QUICKSHIPD_VERSION
		);

		wp_enqueue_script(
			'quickshipd-frontend',
			QUICKSHIPD_URL . 'assets/js/frontend.js',
			array(),
			QUICKSHIPD_VERSION,
			true
		);

		wp_localize_script(
			'quickshipd-frontend',
			'quickshipdData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'quickshipd_variation' ),
			)
		);
	}

	// -----------------------------------------------------------------------
	// AJAX handlers.
	// -----------------------------------------------------------------------

	/**
	 * AJAX handler: return updated delivery HTML for a specific variation.
	 *
	 * @return void
	 */
	public function ajax_variation_date(): void {
		check_ajax_referer( 'quickshipd_variation', 'nonce' );

		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		if ( ! $variation_id ) {
			wp_send_json_error( array( 'message' => 'invalid_id' ) );
		}

		$variation = wc_get_product( $variation_id );
		if ( ! $variation || ! $variation->is_in_stock() ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		$parent_id = $variation->get_parent_id();
		if ( 'yes' === get_post_meta( $parent_id, '_quickshipd_disabled', true ) ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		$calc   = QuickShipD_Calculator::from_settings( array(), $variation_id );
		$result = $calc->calculate();

		wp_send_json_success( array( 'html' => self::build_html( $result, $variation_id, 'product' ) ) );
	}

	/**
	 * AJAX handler: return a preview for the admin settings page.
	 *
	 * @return void
	 */
	public function ajax_preview(): void {
		check_ajax_referer( 'quickshipd_preview', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		// ---- Delivery settings ----
		$min_days    = isset( $_POST['quickshipd_min_days'] ) ? absint( wp_unslash( $_POST['quickshipd_min_days'] ) ) : (int) get_option( 'quickshipd_min_days', 3 );
		$max_days    = isset( $_POST['quickshipd_max_days'] ) ? absint( wp_unslash( $_POST['quickshipd_max_days'] ) ) : (int) get_option( 'quickshipd_max_days', 5 );
		$cutoff_hour = isset( $_POST['quickshipd_cutoff_hour'] ) ? absint( wp_unslash( $_POST['quickshipd_cutoff_hour'] ) ) : (int) get_option( 'quickshipd_cutoff_hour', 14 );
		$cutoff_min  = isset( $_POST['quickshipd_cutoff_min'] ) ? absint( wp_unslash( $_POST['quickshipd_cutoff_min'] ) ) : (int) get_option( 'quickshipd_cutoff_min', 0 );

		$excl_weekends = isset( $_POST['quickshipd_exclude_weekends'] )
			? sanitize_text_field( wp_unslash( $_POST['quickshipd_exclude_weekends'] ) )
			: get_option( 'quickshipd_exclude_weekends', 'yes' );

		$excluded_days = 'yes' === $excl_weekends ? array( 0, 6 ) : array();

		$calc   = new QuickShipD_Calculator( $min_days, $max_days, $cutoff_hour, $cutoff_min, $excluded_days, array() );
		$result = $calc->calculate();

		// ---- Style / display settings (for live preview) ----
		$style_keys = array(
			'quickshipd_text_single',
			'quickshipd_text_range',
			'quickshipd_text_countdown',
			'quickshipd_date_format',
			'quickshipd_icon',
			'quickshipd_text_color',
			'quickshipd_bg_color',
			'quickshipd_show_countdown',
		);
		$settings = array();
		foreach ( $style_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$settings[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		wp_send_json_success( array( 'html' => self::build_html( $result, null, 'product', $settings ) ) );
	}

	// -----------------------------------------------------------------------
	// Helpers.
	// -----------------------------------------------------------------------

	/**
	 * Get the days overrides for the currently selected shipping method, if any.
	 *
	 * @return array Associative array with 'min_days' and/or 'max_days' keys.
	 */
	private function get_selected_shipping_method_overrides(): array {
		if ( ! WC()->cart || ! WC()->session ) {
			return array();
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		if ( empty( $chosen_methods ) ) {
			return array();
		}

		$method_id_full = reset( $chosen_methods ); // e.g. "flat_rate:3".
		if ( ! $method_id_full ) {
			return array();
		}

		// Parse instance ID.
		$parts       = explode( ':', $method_id_full );
		$instance_id = isset( $parts[1] ) ? absint( $parts[1] ) : 0;
		if ( ! $instance_id ) {
			return array();
		}

		// Use WooCommerce's own API to read instance settings (stored serialised, not as flat options).
		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );
		if ( ! $method ) {
			return array();
		}

		$min = $method->get_option( 'quickshipd_min_days', '' );
		$max = $method->get_option( 'quickshipd_max_days', '' );

		$overrides = array();
		if ( '' !== $min && is_numeric( $min ) ) {
			$overrides['min_days'] = (int) $min;
		}
		if ( '' !== $max && is_numeric( $max ) ) {
			$overrides['max_days'] = (int) $max;
		}
		return $overrides;
	}

	/**
	 * Return an inline SVG for the selected icon type.
	 *
	 * @param  string $type Icon key: 'truck', 'box', or 'none'.
	 * @return string  Safe SVG string.
	 */
	private static function get_icon_svg( string $type ): string {
		if ( 'none' === $type ) {
			return '';
		}

		if ( 'box' === $type ) {
			return '<svg class="quickshipd-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">'
				. '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="1.5" fill="none"/>'
				. '<polyline points="3.27 6.96 12 12.01 20.73 6.96" stroke="currentColor" stroke-width="1.5" fill="none"/>'
				. '<line x1="12" y1="22.08" x2="12" y2="12" stroke="currentColor" stroke-width="1.5"/>'
				. '</svg>';
		}

		// Default: truck.
		return '<svg class="quickshipd-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">'
			. '<path d="M1 3h15v13H1V3z" stroke="currentColor" stroke-width="1.5" fill="none"/>'
			. '<path d="M16 8h4l3 4v5h-7V8z" stroke="currentColor" stroke-width="1.5" fill="none"/>'
			. '<circle cx="5.5" cy="18.5" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/>'
			. '<circle cx="18.5" cy="18.5" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/>'
			. '</svg>';
	}
}
