<?php
/**
 * WordPress Abilities API integration.
 *
 * Exposes delivery estimates and settings as abilities so AI agents and MCP
 * clients can discover and call them. Needs WordPress 6.9+; on older
 * versions the hooks never fire and nothing is registered.
 *
 * @package QuickShipD
 * @since   1.0.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShipD_Abilities
 *
 * @since 1.0.9
 */
class QuickShipD_Abilities {

	/**
	 * Ability category slug.
	 *
	 * @var string
	 */
	const CATEGORY = 'quickshipd';

	/**
	 * Register the QuickShipD ability category.
	 *
	 * @return void
	 */
	public function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'QuickShipD', 'quickshipd' ),
				'description' => __( 'Estimated delivery dates and delivery timing settings for WooCommerce.', 'quickshipd' ),
			)
		);
	}

	/**
	 * Register every ability.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		$days = array(
			'type'    => 'integer',
			'minimum' => 0,
			'maximum' => 365,
		);

		$nullable_days = array(
			'type'    => array( 'integer', 'null' ),
			'minimum' => 0,
			'maximum' => 365,
		);

		$settings_schema = array(
			'type'       => 'object',
			'properties' => array(
				'enabled'       => array( 'type' => 'boolean' ),
				'min_days'      => $days,
				'max_days'      => $days,
				'cutoff_hour'   => array(
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => 23,
				),
				'cutoff_min'    => array(
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => 59,
				),
				'excluded_days' => array(
					'type'        => 'array',
					'description' => __( 'Weekdays with no dispatch, 0 = Sunday to 6 = Saturday.', 'quickshipd' ),
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 0,
						'maximum' => 6,
					),
				),
			),
		);

		$product_schema = array(
			'type'       => 'object',
			'properties' => array(
				'min_days' => $nullable_days,
				'max_days' => $nullable_days,
				'disabled' => array( 'type' => 'boolean' ),
			),
		);

		$product_id = array(
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => __( 'Product or variation ID.', 'quickshipd' ),
		);

		wp_register_ability(
			'quickshipd/get-delivery-estimate',
			array(
				'label'               => __( 'Get delivery estimate', 'quickshipd' ),
				'description'         => __( 'Returns the estimated delivery date range for a product or variation, using global, per-product and shipping method timing.', 'quickshipd' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'product_id'           => $product_id,
						'shipping_instance_id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Optional shipping method instance ID, applies its min/max days override.', 'quickshipd' ),
						),
					),
					'required'             => array( 'product_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'show'               => array( 'type' => 'boolean' ),
						'min_date'           => array( 'type' => 'string' ),
						'max_date'           => array( 'type' => 'string' ),
						'min_date_formatted' => array( 'type' => 'string' ),
						'max_date_formatted' => array( 'type' => 'string' ),
						'is_range'           => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'get_delivery_estimate' ),
				'permission_callback' => '__return_true', // Same data as the public REST date endpoint.
				'meta'                => $this->meta( true ),
			)
		);

		wp_register_ability(
			'quickshipd/get-settings',
			array(
				'label'               => __( 'Get delivery settings', 'quickshipd' ),
				'description'         => __( 'Returns the store-wide QuickShipD timing settings.', 'quickshipd' ),
				'category'            => self::CATEGORY,
				'output_schema'       => $settings_schema,
				'execute_callback'    => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'can_manage_store' ),
				'meta'                => $this->meta( true ),
			)
		);

		wp_register_ability(
			'quickshipd/update-settings',
			array(
				'label'               => __( 'Update delivery settings', 'quickshipd' ),
				'description'         => __( 'Updates store-wide QuickShipD timing settings. Only the supplied fields change.', 'quickshipd' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array_merge( $settings_schema, array( 'additionalProperties' => false ) ),
				'output_schema'       => $settings_schema,
				'execute_callback'    => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'can_manage_store' ),
				'meta'                => $this->meta( false ),
			)
		);

		wp_register_ability(
			'quickshipd/get-product-settings',
			array(
				'label'               => __( 'Get product delivery settings', 'quickshipd' ),
				'description'         => __( 'Returns the QuickShipD overrides saved on a product or variation. Null or empty means the global value applies.', 'quickshipd' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'product_id' => $product_id ),
					'required'             => array( 'product_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $product_schema,
				'execute_callback'    => array( $this, 'get_product_settings' ),
				'permission_callback' => array( $this, 'can_edit_product' ),
				'meta'                => $this->meta( true ),
			)
		);

		wp_register_ability(
			'quickshipd/update-product-settings',
			array(
				'label'               => __( 'Update product delivery settings', 'quickshipd' ),
				'description'         => __( 'Sets QuickShipD overrides on a product or variation. Pass null for min_days or max_days to fall back to the global value.', 'quickshipd' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge( array( 'product_id' => $product_id ), $product_schema['properties'] ),
					'required'             => array( 'product_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $product_schema,
				'execute_callback'    => array( $this, 'update_product_settings' ),
				'permission_callback' => array( $this, 'can_edit_product' ),
				'meta'                => $this->meta( false ),
			)
		);
	}

	/**
	 * Shared ability meta.
	 *
	 * @param bool $read_only Whether the ability only reads data.
	 * @return array
	 */
	private function meta( bool $read_only ): array {
		return array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => $read_only,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
	}

	/**
	 * Permission: manage store settings.
	 *
	 * @return bool
	 */
	public function can_manage_store(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Permission: edit the given product.
	 *
	 * @param array|null $input Ability input.
	 * @return bool
	 */
	public function can_edit_product( $input = null ): bool {
		$id = absint( $input['product_id'] ?? 0 );
		return $id && current_user_can( 'edit_post', $id );
	}

	/**
	 * Calculate the delivery estimate.
	 *
	 * @param array|null $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_delivery_estimate( $input = null ) {
		$id = absint( $input['product_id'] ?? 0 );
		if ( ! $this->is_product_post( $id ) ) {
			return new WP_Error( 'quickshipd_invalid_product', __( 'Product not found.', 'quickshipd' ) );
		}

		$parent_id = (int) wp_get_post_parent_id( $id );
		$flag_id   = $parent_id ? $parent_id : $id;
		if ( 'yes' !== get_option( 'quickshipd_enabled', 'yes' ) || 'yes' === get_post_meta( $flag_id, '_quickshipd_disabled', true ) ) {
			return array( 'show' => false );
		}

		$overrides = array();
		$instance  = absint( $input['shipping_instance_id'] ?? 0 );
		if ( $instance ) {
			$overrides = QuickShipD_Display::shipping_method_overrides( WC_Shipping_Zones::get_shipping_method( $instance ) );
		}

		$result   = QuickShipD_Calculator::from_settings( $overrides, $id )->calculate();
		$date_fmt = get_option( 'quickshipd_date_format', 'D, M j' );

		return array(
			'show'               => (bool) $result['show'],
			'min_date'           => $result['min_date']->format( 'Y-m-d' ),
			'max_date'           => $result['max_date']->format( 'Y-m-d' ),
			'min_date_formatted' => QuickShipD_Calculator::format_date( $result['min_date'], $date_fmt ),
			'max_date_formatted' => QuickShipD_Calculator::format_date( $result['max_date'], $date_fmt ),
			'is_range'           => (bool) $result['is_range'],
		);
	}

	/**
	 * Read the global settings.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		return array(
			'enabled'       => 'yes' === get_option( 'quickshipd_enabled', 'yes' ),
			'min_days'      => (int) get_option( 'quickshipd_min_days', 3 ),
			'max_days'      => (int) get_option( 'quickshipd_max_days', 5 ),
			'cutoff_hour'   => (int) get_option( 'quickshipd_cutoff_hour', 14 ),
			'cutoff_min'    => (int) get_option( 'quickshipd_cutoff_min', 0 ),
			'excluded_days' => array_map( 'intval', (array) get_option( 'quickshipd_excluded_days', array() ) ),
		);
	}

	/**
	 * Update the global settings.
	 *
	 * @param array|null $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_settings( $input = null ) {
		$input   = (array) $input;
		$current = $this->get_settings();
		$merged  = array_merge( $current, array_intersect_key( $input, $current ) );

		if ( $merged['min_days'] > $merged['max_days'] ) {
			return new WP_Error( 'quickshipd_invalid_range', __( 'Minimum delivery days cannot exceed maximum.', 'quickshipd' ) );
		}

		if ( isset( $input['enabled'] ) ) {
			update_option( 'quickshipd_enabled', $input['enabled'] ? 'yes' : 'no' );
		}
		foreach ( array( 'min_days', 'max_days', 'cutoff_hour', 'cutoff_min' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				update_option( 'quickshipd_' . $key, (int) $merged[ $key ] );
			}
		}
		if ( isset( $input['excluded_days'] ) ) {
			update_option( 'quickshipd_excluded_days', array_values( array_unique( array_map( 'absint', $input['excluded_days'] ) ) ) );
		}

		return $this->get_settings();
	}

	/**
	 * Read per-product overrides.
	 *
	 * @param array|null $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_product_settings( $input = null ) {
		$id = absint( $input['product_id'] ?? 0 );
		if ( ! $this->is_product_post( $id ) ) {
			return new WP_Error( 'quickshipd_invalid_product', __( 'Product not found.', 'quickshipd' ) );
		}

		$min = get_post_meta( $id, '_quickshipd_min_days', true );
		$max = get_post_meta( $id, '_quickshipd_max_days', true );

		return array(
			'min_days' => is_numeric( $min ) ? (int) $min : null,
			'max_days' => is_numeric( $max ) ? (int) $max : null,
			'disabled' => 'yes' === get_post_meta( $id, '_quickshipd_disabled', true ),
		);
	}

	/**
	 * Update per-product overrides.
	 *
	 * @param array|null $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_product_settings( $input = null ) {
		$id = absint( $input['product_id'] ?? 0 );
		if ( ! $this->is_product_post( $id ) ) {
			return new WP_Error( 'quickshipd_invalid_product', __( 'Product not found.', 'quickshipd' ) );
		}

		foreach ( array( 'min_days', 'max_days' ) as $key ) {
			if ( array_key_exists( $key, (array) $input ) ) {
				// Empty string means "use global", same as the product editor.
				update_post_meta( $id, '_quickshipd_' . $key, null === $input[ $key ] ? '' : (string) absint( $input[ $key ] ) );
			}
		}
		if ( isset( $input['disabled'] ) ) {
			update_post_meta( $id, '_quickshipd_disabled', $input['disabled'] ? 'yes' : 'no' );
		}

		return $this->get_product_settings( array( 'product_id' => $id ) );
	}

	/**
	 * Whether the ID is a product or variation post.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	private function is_product_post( int $id ): bool {
		return $id && in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true );
	}
}
