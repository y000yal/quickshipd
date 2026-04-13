<?php
/**
 * Per-product delivery date override fields in the WooCommerce product editor.
 *
 * @package QuickShipD
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShipD_Product_Meta
 *
 * Adds QuickShipD fields to the Shipping tab of the WooCommerce product
 * editor and saves them as postmeta.
 *
 * @since 1.0.0
 */
class QuickShipD_Product_Meta {

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
	 * Render the QuickShipD section inside the product Shipping tab.
	 *
	 * @return void
	 */
	public function render_fields(): void {
		global $post;

		echo '<div class="options_group quickshipd-product-options">';
		echo '<p class="form-field"><strong>' . esc_html__( 'QuickShipD — Delivery Estimate', 'quickshipd' ) . '</strong></p>';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_quickshipd_disabled',
				'label'       => esc_html__( 'Disable delivery estimate', 'quickshipd' ),
				'description' => esc_html__( 'Hide the delivery estimate for this product.', 'quickshipd' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_quickshipd_min_days',
				'label'             => esc_html__( 'Min delivery days', 'quickshipd' ),
				'description'       => esc_html__( 'Leave blank to use the global default.', 'quickshipd' ),
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
				'id'                => '_quickshipd_max_days',
				'label'             => esc_html__( 'Max delivery days', 'quickshipd' ),
				'description'       => esc_html__( 'Leave blank to use the global default.', 'quickshipd' ),
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
	 * Save the per-product QuickShipD fields on product save.
	 *
	 * @param  int $post_id  WooCommerce product post ID.
	 * @return void
	 */
	public function save_fields( int $post_id ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above (WooCommerce product save).
		// Disabled checkbox.
		$disabled = isset( $_POST['_quickshipd_disabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_quickshipd_disabled', $disabled );

		// Min days — allow empty string to mean "use global".
		$min_raw = isset( $_POST['_quickshipd_min_days'] )
			? sanitize_text_field( wp_unslash( $_POST['_quickshipd_min_days'] ) )
			: '';
		if ( '' === $min_raw ) {
			update_post_meta( $post_id, '_quickshipd_min_days', '' );
		} else {
			update_post_meta( $post_id, '_quickshipd_min_days', (string) absint( $min_raw ) );
		}

		// Max days.
		$max_raw = isset( $_POST['_quickshipd_max_days'] )
			? sanitize_text_field( wp_unslash( $_POST['_quickshipd_max_days'] ) )
			: '';
		if ( '' === $max_raw ) {
			update_post_meta( $post_id, '_quickshipd_max_days', '' );
		} else {
			update_post_meta( $post_id, '_quickshipd_max_days', (string) absint( $max_raw ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
