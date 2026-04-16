<?php
/**
 * Plugin Name:       QuickShipD — Estimated Delivery Date for WooCommerce
 * Plugin URI:        https://quickshipd.com
 * Description:       Estimated delivery dates for WooCommerce on product, cart, and checkout — shipping-aware ranges, per-product overrides, optional countdown.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins: woocommerce
 * Author:            y0000el
 * Author URI:        https://yoyallimbu.com.np
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       quickshipd
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   10.0
 *
 * @package QuickShipD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Constants.
// -------------------------------------------------------------------------

define( 'QUICKSHIPD_VERSION', '1.0.0' );
define( 'QUICKSHIPD_PATH', plugin_dir_path( __FILE__ ) );
define( 'QUICKSHIPD_URL', plugin_dir_url( __FILE__ ) );
define( 'QUICKSHIPD_BASENAME', plugin_basename( __FILE__ ) );

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
			'quickshipd_enabled'          => 'yes',
			'quickshipd_min_days'         => 3,
			'quickshipd_max_days'         => 5,
			'quickshipd_cutoff_hour'      => 14,
			'quickshipd_cutoff_min'       => 0,
			'quickshipd_exclude_weekends' => 'yes',
			'quickshipd_excluded_days'    => array(),
			'quickshipd_holidays'         => '',
			'quickshipd_show_product'     => 'yes',
			'quickshipd_show_shop'        => 'no',
			'quickshipd_show_cart'        => 'yes',
			'quickshipd_show_checkout'    => 'yes',
			'quickshipd_show_countdown'   => 'yes',
			'quickshipd_text_single'      => 'Get it by {date}',
			'quickshipd_text_range'       => 'Get it {start} – {end}',
			'quickshipd_text_countdown'   => 'Order within {countdown} to get it by {date}',
			'quickshipd_date_format'      => 'D, M j',
			'quickshipd_icon'             => 'truck',
			'quickshipd_text_color'       => '#16a34a',
			'quickshipd_secondary_color'  => '#6b7280',
			'quickshipd_bg_color'         => '#f0fdf4',
			'quickshipd_border_radius'    => '8',
			'quickshipd_padding'          => '10',
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
									__( '<strong>QuickShipD</strong> requires WooCommerce to be installed and activated. <a href="%s">Install WooCommerce</a>.', 'quickshipd' ),
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
		if ( ! get_option( 'quickshipd_db_repaired_v2' ) ) {
			quickshipd_repair_options();
			update_option( 'quickshipd_db_repaired_v2', '1' );
		}

		require_once QUICKSHIPD_PATH . 'includes/class-quickshipd-core.php';
		QuickShipD_Core::get_instance();
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
function quickshipd_repair_options( bool $force = false ): void {
	$defaults = array(
		'quickshipd_enabled'          => 'yes',
		'quickshipd_min_days'         => 3,
		'quickshipd_max_days'         => 5,
		'quickshipd_cutoff_hour'      => 14,
		'quickshipd_cutoff_min'       => 0,
		'quickshipd_exclude_weekends' => 'yes',
		'quickshipd_excluded_days'    => array(),
		'quickshipd_holidays'         => '',
		'quickshipd_show_product'     => 'yes',
		'quickshipd_show_shop'        => 'no',
		'quickshipd_show_cart'        => 'yes',
		'quickshipd_show_checkout'    => 'yes',
		'quickshipd_show_countdown'   => 'yes',
		'quickshipd_text_single'      => 'Get it by {date}',
		'quickshipd_text_range'       => 'Get it {start} – {end}',
		'quickshipd_text_countdown'   => 'Order within {countdown} to get it by {date}',
		'quickshipd_date_format'      => 'D, M j',
		'quickshipd_icon'             => 'truck',
		'quickshipd_text_color'       => '#16a34a',
		'quickshipd_secondary_color'  => '#6b7280',
		'quickshipd_bg_color'         => '#f0fdf4',
		'quickshipd_border_radius'    => '8',
	);

	if ( $force ) {
		foreach ( $defaults as $key => $value ) {
			update_option( $key, $value );
		}
		return;
	}

	// Detect wiped display options (all three became 'no').
	$display_wiped = (
		'no' === get_option( 'quickshipd_show_product', 'yes' ) &&
		'no' === get_option( 'quickshipd_show_cart', 'yes' ) &&
		'no' === get_option( 'quickshipd_show_checkout', 'yes' )
	);
	if ( $display_wiped ) {
		update_option( 'quickshipd_enabled', 'yes' );
		update_option( 'quickshipd_show_product', 'yes' );
		update_option( 'quickshipd_show_cart', 'yes' );
		update_option( 'quickshipd_show_checkout', 'yes' );
		update_option( 'quickshipd_show_countdown', 'yes' );
	}

	// Detect wiped delivery options (both days became 0).
	if ( 0 === (int) get_option( 'quickshipd_min_days', 3 ) && 0 === (int) get_option( 'quickshipd_max_days', 5 ) ) {
		update_option( 'quickshipd_min_days', 3 );
		update_option( 'quickshipd_max_days', 5 );
		update_option( 'quickshipd_cutoff_hour', 14 );
		update_option( 'quickshipd_cutoff_min', 0 );
		update_option( 'quickshipd_exclude_weekends', 'yes' );
	}

	// Detect wiped style options (text_color or text_single became '').
	if ( '' === get_option( 'quickshipd_text_color', '' ) || '' === get_option( 'quickshipd_text_single', '' ) ) {
		update_option( 'quickshipd_text_color', '#16a34a' );
		update_option( 'quickshipd_secondary_color', '#6b7280' );
		update_option( 'quickshipd_bg_color', '#f0fdf4' );
		update_option( 'quickshipd_border_radius', '8' );
		update_option( 'quickshipd_text_single', 'Get it by {date}' );
		update_option( 'quickshipd_text_range', 'Get it {start} – {end}' );
		update_option( 'quickshipd_text_countdown', 'Order within {countdown} to get it by {date}' );
		update_option( 'quickshipd_date_format', 'D, M j' );
		update_option( 'quickshipd_icon', 'truck' );
	}
	// Seed new options for existing installs.
	if ( false === get_option( 'quickshipd_secondary_color' ) ) {
		update_option( 'quickshipd_secondary_color', '#6b7280' );
	}
	if ( false === get_option( 'quickshipd_border_radius' ) ) {
		update_option( 'quickshipd_border_radius', '8' );
	}
	if ( '' === get_option( 'quickshipd_bg_color', 'x' ) ) {
		update_option( 'quickshipd_bg_color', '#f0fdf4' );
	}
}
