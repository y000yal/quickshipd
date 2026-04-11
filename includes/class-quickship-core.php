<?php
/**
 * Plugin bootstrap — singleton, hook registration, dependency loading.
 *
 * @package QuickShip
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShip_Core
 *
 * The single entry point for the plugin. Loads all classes, registers
 * the shipping-method integration hooks, and wires up the other subsystems.
 *
 * @since 1.0.0
 */
final class QuickShip_Core {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Admin handler instance.
	 *
	 * @var QuickShip_Admin
	 */
	private QuickShip_Admin $admin;

	/**
	 * Display handler instance.
	 *
	 * @var QuickShip_Display
	 */
	private QuickShip_Display $display;

	/**
	 * Product meta handler instance.
	 *
	 * @var QuickShip_Product_Meta
	 */
	private QuickShip_Product_Meta $product_meta;

	/**
	 * REST handler instance.
	 *
	 * @var QuickShip_Rest
	 */
	private QuickShip_Rest $rest;

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

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Require all class files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		$includes = QUICKSHIP_PATH . 'includes/';

		require_once $includes . 'class-quickship-calculator.php';
		require_once $includes . 'class-quickship-display.php';
		require_once $includes . 'class-quickship-admin.php';
		require_once $includes . 'class-quickship-product-meta.php';
		require_once $includes . 'class-quickship-rest.php';
	}

	/**
	 * Instantiate subsystems and call their init() methods.
	 *
	 * @return void
	 */
	private function init_subsystems(): void {
		$this->display      = new QuickShip_Display();
		$this->product_meta = new QuickShip_Product_Meta();
		$this->rest         = new QuickShip_Rest();

		$this->display->init();
		$this->display->init_preview_ajax(); // always register, regardless of enabled state.
		$this->product_meta->init();
		$this->rest->init();

		if ( is_admin() ) {
			$this->admin = new QuickShip_Admin();
			$this->admin->init();
		}
	}

	/**
	 * Register the shipping method integration hooks.
	 *
	 * Adds "QuickShip min/max days" fields to every shipping method instance
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
	 * Add QuickShip min/max fields to a shipping method's instance form.
	 *
	 * @param  array $fields  Existing form fields.
	 * @return array
	 */
	public function add_shipping_method_fields( array $fields ): array {
		$fields['quickship_min_days'] = array(
			'title'             => __( 'QuickShip: Min delivery days', 'quickship-delivery-date' ),
			'type'              => 'number',
			'description'       => __( 'Override the global minimum. Leave blank to use global.', 'quickship-delivery-date' ),
			'desc_tip'          => true,
			'default'           => '',
			'custom_attributes' => array(
				'min'  => 0,
				'max'  => 365,
				'step' => 1,
			),
		);

		$fields['quickship_max_days'] = array(
			'title'             => __( 'QuickShip: Max delivery days', 'quickship-delivery-date' ),
			'type'              => 'number',
			'description'       => __( 'Override the global maximum. Leave blank to use global.', 'quickship-delivery-date' ),
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

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'quickship-delivery-date',
			false,
			dirname( QUICKSHIP_BASENAME ) . '/languages'
		);
	}
}
