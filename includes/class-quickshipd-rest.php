<?php
/**
 * REST API endpoint for delivery date lookups.
 *
 * Provides a lightweight endpoint that the frontend JS can call to refresh
 * delivery dates after a variation is selected or a shipping method changes,
 * without a full page reload.
 *
 * Endpoint: GET /wp-json/quickshipd/v1/date
 *   ?product_id=123            — optional
 *   &variation_id=456          — optional
 *   &shipping_instance=flat_rate:3 — optional
 *
 * @package QuickShipD
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShipD_Rest
 *
 * @since 1.0.0
 */
class QuickShipD_Rest {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'quickshipd/v1';

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the /date route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/date',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_date_request' ),
				'permission_callback' => '__return_true', // Public endpoint; no PII exposed.
				'args'                => array(
					'product_id'         => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'variation_id'       => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'shipping_instance'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'context'            => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'product',
						'enum'              => array( 'product', 'shop', 'cart', 'checkout' ),
					),
				),
			)
		);
	}

	/**
	 * Handle GET /quickshipd/v1/date.
	 *
	 * @param  WP_REST_Request $request  REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_date_request( WP_REST_Request $request ) {
		if ( 'yes' !== get_option( 'quickshipd_enabled', 'yes' ) ) {
			return new WP_Error( 'quickshipd_disabled', __( 'QuickShipD is disabled.', 'quickshipd' ), array( 'status' => 503 ) );
		}

		$product_id        = $request->get_param( 'product_id' );
		$variation_id      = $request->get_param( 'variation_id' );
		$shipping_instance = $request->get_param( 'shipping_instance' );
		$context           = $request->get_param( 'context' );

		// Use variation for per-product overrides if provided.
		$effective_id = $variation_id ?: $product_id ?: null;

		// Check disabled flag on the parent product.
		$parent_id = $product_id ?: $variation_id;
		if ( $parent_id && 'yes' === get_post_meta( $parent_id, '_quickshipd_disabled', true ) ) {
			return rest_ensure_response( array( 'html' => '', 'show' => false ) );
		}

		// Shipping method overrides.
		$overrides = array();
		if ( '' !== $shipping_instance ) {
			$parts       = explode( ':', $shipping_instance );
			$method_slug = $parts[0];
			$instance_id = isset( $parts[1] ) ? absint( $parts[1] ) : 0;
			if ( $instance_id ) {
				$min = get_option( 'woocommerce_' . $method_slug . '_' . $instance_id . '_quickshipd_min_days', '' );
				$max = get_option( 'woocommerce_' . $method_slug . '_' . $instance_id . '_quickshipd_max_days', '' );
				if ( '' !== $min ) {
					$overrides['min_days'] = $min;
				}
				if ( '' !== $max ) {
					$overrides['max_days'] = $max;
				}
			}
		}

		$calc   = QuickShipD_Calculator::from_settings( $overrides, $effective_id );
		$result = $calc->calculate();
		$html   = QuickShipD_Display::build_html( $result, $effective_id, $context );

		$date_fmt = get_option( 'quickshipd_date_format', 'D, M j' );

		return rest_ensure_response(
			array(
				'html'               => $html,
				'show'               => $result['show'],
				'min_date'           => $result['min_date']->format( 'Y-m-d' ),
				'max_date'           => $result['max_date']->format( 'Y-m-d' ),
				'min_date_formatted' => QuickShipD_Calculator::format_date( $result['min_date'], $date_fmt ),
				'max_date_formatted' => QuickShipD_Calculator::format_date( $result['max_date'], $date_fmt ),
				'is_range'           => $result['is_range'],
				'countdown_seconds'  => $result['countdown_seconds'],
			)
		);
	}
}
