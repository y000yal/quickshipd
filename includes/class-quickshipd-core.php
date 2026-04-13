<?php
/**
 * Plugin bootstrap — singleton, hook registration, dependency loading.
 *
 * @package QuickShipD
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShipD_Core
 *
 * The single entry point for the plugin. Loads all classes, registers
 * the shipping-method integration hooks, and wires up the other subsystems.
 *
 * @since 1.0.0
 */
final class QuickShipD_Core {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Admin handler instance.
	 *
	 * @var QuickShipD_Admin
	 */
	private QuickShipD_Admin $admin;

	/**
	 * Display handler instance.
	 *
	 * @var QuickShipD_Display
	 */
	private QuickShipD_Display $display;

	/**
	 * Product meta handler instance.
	 *
	 * @var QuickShipD_Product_Meta
	 */
	private QuickShipD_Product_Meta $product_meta;

	/**
	 * REST handler instance.
	 *
	 * @var QuickShipD_Rest
	 */
	private QuickShipD_Rest $rest;

	/**
	 * Private constructor — use ::get_instance().
	 */
	private function __construct() {}

	/**
	 * Return (and lazy-create) the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Load files and register all hooks.
	 *
	 * @return void
	 */
	private function setup(): void {
		$this->load_dependencies();
		$this->init_subsystems();
		$this->register_shipping_method_hooks();
	}

	/**
	 * Require all class files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		$includes = QUICKSHIPD_PATH . 'includes/';

		require_once $includes . 'class-quickshipd-calculator.php';
		require_once $includes . 'class-quickshipd-display.php';
		require_once $includes . 'class-quickshipd-admin.php';
		require_once $includes . 'class-quickshipd-product-meta.php';
		require_once $includes . 'class-quickshipd-rest.php';
	}

	/**
	 * Instantiate subsystems and call their init() methods.
	 *
	 * @return void
	 */
	private function init_subsystems(): void {
		$this->display      = new QuickShipD_Display();
		$this->product_meta = new QuickShipD_Product_Meta();
		$this->rest         = new QuickShipD_Rest();

		$this->display->init();
		$this->display->init_preview_ajax(); // always register, regardless of enabled state.
		$this->product_meta->init();
		$this->rest->init();

		if ( is_admin() ) {
			$this->admin = new QuickShipD_Admin();
			$this->admin->init();
		}
	}

	/**
	 * Register the shipping method integration hooks.
	 *
	 * Adds "QuickShipD min/max days" fields to every shipping method instance
	 * in WooCommerce Shipping Zones. Works for flat_rate, free_shipping,
	 * local_pickup, and any custom method because we hook into the generic
	 * woocommerce_shipping_instance_form_fields filter.
	 *
	 * @return void
	 */
	private function register_shipping_method_hooks(): void {
		// Generic filter applied to all method instances.
		add_filter( 'woocommerce_shipping_instance_form_fields_flat_rate', array( $this, 'add_shipping_method_fields' ) );
		add_filter( 'woocommerce_shipping_instance_form_fields_free_shipping', array( $this, 'add_shipping_method_fields' ) );
		add_filter( 'woocommerce_shipping_instance_form_fields_local_pickup', array( $this, 'add_shipping_method_fields' ) );

		// Catch-all for any registered shipping method ID.
		add_action( 'woocommerce_load_shipping_methods', array( $this, 'hook_all_shipping_methods' ) );
	}

	/**
	 * After WooCommerce loads its shipping methods, add our field filter to
	 * every registered method that we haven't already targeted.
	 *
	 * @return void
	 */
	public function hook_all_shipping_methods(): void {
		$already = array( 'flat_rate', 'free_shipping', 'local_pickup' );
		foreach ( WC()->shipping()->get_shipping_methods() as $method_id => $method ) {
			if ( ! in_array( $method_id, $already, true ) ) {
				add_filter(
					'woocommerce_shipping_instance_form_fields_' . $method_id,
					array( $this, 'add_shipping_method_fields' )
				);
			}
		}
	}

	/**
	 * Add QuickShipD min/max fields to a shipping method's instance form.
	 *
	 * @param  array $fields  Existing form fields.
	 * @return array
	 */
	public function add_shipping_method_fields( array $fields ): array {
		$fields['quickshipd_min_days'] = array(
			'title'             => __( 'QuickShipD: Min delivery days', 'quickshipd' ),
			'type'              => 'number',
			'description'       => __( 'Override the global minimum. Leave blank to use global.', 'quickshipd' ),
			'desc_tip'          => true,
			'default'           => '',
			'custom_attributes' => array(
				'min'  => 0,
				'max'  => 365,
				'step' => 1,
			),
		);

		$fields['quickshipd_max_days'] = array(
			'title'             => __( 'QuickShipD: Max delivery days', 'quickshipd' ),
			'type'              => 'number',
			'description'       => __( 'Override the global maximum. Leave blank to use global.', 'quickshipd' ),
			'desc_tip'          => true,
			'default'           => '',
			'custom_attributes' => array(
				'min'  => 0,
				'max'  => 365,
				'step' => 1,
			),
		);

		return $fields;
	}
}
