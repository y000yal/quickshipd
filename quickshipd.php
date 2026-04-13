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
			'quickshipd_bg_color'         => '',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}
);

/**
 * One-time migration from the old slug (quickship_ / _quickship_) after rename to quickshipd.
 *
 * @return void
 */
function quickshipd_migrate_legacy_from_quickship(): void {
	if ( '1' === get_option( 'quickshipd_legacy_migrated', '' ) ) {
		return;
	}

	$option_suffixes = array(
		'enabled',
		'min_days',
		'max_days',
		'cutoff_hour',
		'cutoff_min',
		'exclude_weekends',
		'excluded_days',
		'holidays',
		'show_product',
		'show_shop',
		'show_cart',
		'show_checkout',
		'show_countdown',
		'text_single',
		'text_range',
		'text_countdown',
		'date_format',
		'icon',
		'text_color',
		'bg_color',
	);

	foreach ( $option_suffixes as $suffix ) {
		$old_key = 'quickship_' . $suffix;
		$new_key = 'quickshipd_' . $suffix;
		if ( null !== get_option( $old_key, null ) && null === get_option( $new_key, null ) ) {
			update_option( $new_key, get_option( $old_key ) );
		}
	}

	if ( get_option( 'quickship_db_repaired_v2' ) && ! get_option( 'quickshipd_db_repaired_v2' ) ) {
		update_option( 'quickshipd_db_repaired_v2', '1' );
	}

	global $wpdb;

	foreach (
		array(
			'quickship_min_days' => 'quickshipd_min_days',
			'quickship_max_days' => 'quickshipd_max_days',
		) as $old_tail => $new_tail
	) {
		$like = '%' . $wpdb->esc_like( $old_tail );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		foreach ( (array) $names as $old_name ) {
			$new_name = str_replace( $old_tail, $new_tail, $old_name );
			if ( $new_name !== $old_name && null === get_option( $new_name, null ) ) {
				update_option( $new_name, get_option( $old_name ) );
			}
		}
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$legacy_meta = $wpdb->get_results(
		"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ( '_quickship_disabled', '_quickship_min_days', '_quickship_max_days' )",
		ARRAY_A
	);

	foreach ( (array) $legacy_meta as $row ) {
		$post_id = (int) $row['post_id'];
		$new_key = str_replace( '_quickship_', '_quickshipd_', $row['meta_key'] );
		if ( ! metadata_exists( 'post', $post_id, $new_key ) ) {
			update_post_meta( $post_id, $new_key, maybe_unserialize( $row['meta_value'] ) );
		}
		delete_post_meta( $post_id, $row['meta_key'] );
	}

	update_option( 'quickshipd_legacy_migrated', '1' );
}

// -------------------------------------------------------------------------
// Boot the plugin after all plugins are loaded so WooCommerce is available.
// -------------------------------------------------------------------------

add_action(
	'plugins_loaded',
	static function (): void {
		quickshipd_migrate_legacy_from_quickship();

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
		'quickshipd_bg_color'         => '',
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
		update_option( 'quickshipd_text_single', 'Get it by {date}' );
		update_option( 'quickshipd_text_range', 'Get it {start} – {end}' );
		update_option( 'quickshipd_text_countdown', 'Order within {countdown} to get it by {date}' );
		update_option( 'quickshipd_date_format', 'D, M j' );
		update_option( 'quickshipd_icon', 'truck' );
	}
}
