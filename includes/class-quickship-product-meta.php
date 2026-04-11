<?php
/**
 * Per-product delivery date override fields in the WooCommerce product editor.
 *
 * @package QuickShip
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShip_Product_Meta
 *
 * Adds QuickShip fields to the Shipping tab of the WooCommerce product
 * editor and saves them as postmeta.
 *
 * @since 1.0.0
 */
class QuickShip_Product_Meta {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'woocommerce_product_options_shipping', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
	}

	/**
	 * Render the QuickShip section inside the product Shipping tab.
	 *
	 * @return void
	 */
	public function render_fields(): void {
		global $post;

		echo '<div class="options_group quickship-product-options">';
		echo '<p class="form-field"><strong>' . esc_html__( 'QuickShip — Delivery Estimate', 'quickship-delivery-date' ) . '</strong></p>';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_quickship_disabled',
				'label'       => esc_html__( 'Disable delivery estimate', 'quickship-delivery-date' ),
				'description' => esc_html__( 'Hide the delivery estimate for this product.', 'quickship-delivery-date' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_quickship_min_days',
				'label'             => esc_html__( 'Min delivery days', 'quickship-delivery-date' ),
				'description'       => esc_html__( 'Leave blank to use the global default.', 'quickship-delivery-date' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '365',
					'step' => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_quickship_max_days',
				'label'             => esc_html__( 'Max delivery days', 'quickship-delivery-date' ),
				'description'       => esc_html__( 'Leave blank to use the global default.', 'quickship-delivery-date' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '365',
					'step' => '1',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * Save the per-product QuickShip fields on product save.
	 *
	 * @param  int $post_id  WooCommerce product post ID.
	 * @return void
	 */
	public function save_fields( int $post_id ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Disabled checkbox.
		$disabled = isset( $_POST['_quickship_disabled'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC verifies nonce.
		update_post_meta( $post_id, '_quickship_disabled', $disabled );

		// Min days — allow empty string to mean "use global".
		$min_raw = isset( $_POST['_quickship_min_days'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST['_quickship_min_days'] ) )
			: '';
		if ( '' === $min_raw ) {
			update_post_meta( $post_id, '_quickship_min_days', '' );
		} else {
			update_post_meta( $post_id, '_quickship_min_days', (string) absint( $min_raw ) );
		}

		// Max days.
		$max_raw = isset( $_POST['_quickship_max_days'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST['_quickship_max_days'] ) )
			: '';
		if ( '' === $max_raw ) {
			update_post_meta( $post_id, '_quickship_max_days', '' );
		} else {
			update_post_meta( $post_id, '_quickship_max_days', (string) absint( $max_raw ) );
		}
	}
}
