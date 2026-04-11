<?php
/**
 * Plugin Name:       QuickShip — Estimated Delivery Date for WooCommerce
 * Plugin URI:        https://quickship.dev
 * Description:       Estimated delivery dates for WooCommerce on product, cart, and checkout — shipping-aware ranges, per-product overrides, optional countdown.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins: woocommerce
 * Author:            QuickShip
 * Author URI:        https://quickship.dev
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       quickship-delivery-date
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   10.0
 *
 * @package QuickShip
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Constants.
// -------------------------------------------------------------------------

define( 'QUICKSHIP_VERSION', '1.0.0' );
define( 'QUICKSHIP_PATH', plugin_dir_path( __FILE__ ) );
define( 'QUICKSHIP_URL', plugin_dir_url( __FILE__ ) );
define( 'QUICKSHIP_BASENAME', plugin_basename( __FILE__ ) );

// -------------------------------------------------------------------------
// Declare HPOS compatibility.
// -------------------------------------------------------------------------

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

// -------------------------------------------------------------------------
// Activation / deactivation.
// -------------------------------------------------------------------------

register_activation_hook(
	__FILE__,
	static function (): void {
		// Set default options on first activation so the plugin works
		// immediately without needing to visit the settings page.
		$defaults = array(
			'quickship_enabled'          => 'yes',
			'quickship_min_days'         => 3,
			'quickship_max_days'         => 5,
			'quickship_cutoff_hour'      => 14,
			'quickship_cutoff_min'       => 0,
			'quickship_exclude_weekends' => 'yes',
			'quickship_excluded_days'    => array(),
			'quickship_holidays'         => '',
			'quickship_show_product'     => 'yes',
			'quickship_show_shop'        => 'no',
			'quickship_show_cart'        => 'yes',
			'quickship_show_checkout'    => 'yes',
			'quickship_show_countdown'   => 'yes',
			'quickship_text_single'      => 'Get it by {date}',
			'quickship_text_range'       => 'Get it {start} – {end}',
			'quickship_text_countdown'   => 'Order within {countdown} to get it by {date}',
			'quickship_date_format'      => 'D, M j',
			'quickship_icon'             => 'truck',
			'quickship_text_color'       => '#16a34a',
			'quickship_bg_color'         => '',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}
);

// -------------------------------------------------------------------------
// Boot the plugin after all plugins are loaded so WooCommerce is available.
// -------------------------------------------------------------------------

add_action(
	'plugins_loaded',
	static function (): void {
		// Bail early if WooCommerce isn't active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					?>
					<div class="notice notice-error">
						<p>
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: link to WooCommerce plugin page */
									__( '<strong>QuickShip</strong> requires WooCommerce to be installed and activated. <a href="%s">Install WooCommerce</a>.', 'quickship-delivery-date' ),
									esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) )
								)
							);
							?>
						</p>
					</div>
					<?php
				}
			);
			return;
		}

		// One-time repair: detect and restore any wiped options before classes read them.
		if ( ! get_option( 'quickship_db_repaired_v2' ) ) {
			quickship_repair_options();
			update_option( 'quickship_db_repaired_v2', '1' );
		}

		require_once QUICKSHIP_PATH . 'includes/class-quickship-core.php';
		QuickShip_Core::get_instance();
	},
	20  // Priority 20 — after WooCommerce's own plugins_loaded at 10.
);

/**
 * Restore any options that appear to have been wiped (set to 0, '', or 'no'
 * by the WP Settings API bug). Called once per site on the request following
 * a plugin update.
 *
 * Also called by the "Restore Defaults" admin AJAX action.
 *
 * @param bool $force  When true, unconditionally resets everything to defaults.
 * @return void
 */
function quickship_repair_options( bool $force = false ): void {
	$defaults = array(
		'quickship_enabled'          => 'yes',
		'quickship_min_days'         => 3,
		'quickship_max_days'         => 5,
		'quickship_cutoff_hour'      => 14,
		'quickship_cutoff_min'       => 0,
		'quickship_exclude_weekends' => 'yes',
		'quickship_excluded_days'    => array(),
		'quickship_holidays'         => '',
		'quickship_show_product'     => 'yes',
		'quickship_show_shop'        => 'no',
		'quickship_show_cart'        => 'yes',
		'quickship_show_checkout'    => 'yes',
		'quickship_show_countdown'   => 'yes',
		'quickship_text_single'      => 'Get it by {date}',
		'quickship_text_range'       => 'Get it {start} – {end}',
		'quickship_text_countdown'   => 'Order within {countdown} to get it by {date}',
		'quickship_date_format'      => 'D, M j',
		'quickship_icon'             => 'truck',
		'quickship_text_color'       => '#16a34a',
		'quickship_bg_color'         => '',
	);

	if ( $force ) {
		foreach ( $defaults as $key => $value ) {
			update_option( $key, $value );
		}
		return;
	}

	// Detect wiped display options (all three became 'no').
	$display_wiped = (
		'no' === get_option( 'quickship_show_product', 'yes' ) &&
		'no' === get_option( 'quickship_show_cart', 'yes' ) &&
		'no' === get_option( 'quickship_show_checkout', 'yes' )
	);
	if ( $display_wiped ) {
		update_option( 'quickship_enabled', 'yes' );
		update_option( 'quickship_show_product', 'yes' );
		update_option( 'quickship_show_cart', 'yes' );
		update_option( 'quickship_show_checkout', 'yes' );
		update_option( 'quickship_show_countdown', 'yes' );
	}

	// Detect wiped delivery options (both days became 0).
	if ( 0 === (int) get_option( 'quickship_min_days', 3 ) && 0 === (int) get_option( 'quickship_max_days', 5 ) ) {
		update_option( 'quickship_min_days', 3 );
		update_option( 'quickship_max_days', 5 );
		update_option( 'quickship_cutoff_hour', 14 );
		update_option( 'quickship_cutoff_min', 0 );
		update_option( 'quickship_exclude_weekends', 'yes' );
	}

	// Detect wiped style options (text_color or text_single became '').
	if ( '' === get_option( 'quickship_text_color', '' ) || '' === get_option( 'quickship_text_single', '' ) ) {
		update_option( 'quickship_text_color', '#16a34a' );
		update_option( 'quickship_text_single', 'Get it by {date}' );
		update_option( 'quickship_text_range', 'Get it {start} – {end}' );
		update_option( 'quickship_text_countdown', 'Order within {countdown} to get it by {date}' );
		update_option( 'quickship_date_format', 'D, M j' );
		update_option( 'quickship_icon', 'truck' );
	}
}
