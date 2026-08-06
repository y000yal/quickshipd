<?php
/**
 * Runs when the plugin is uninstalled via the WordPress admin.
 *
 * Deletes ALL plugin data: wp_options entries and wp_postmeta entries.
 * This file is only executed when the user explicitly uninstalls (deletes)
 * the plugin from the WordPress admin panel. It is NOT called on deactivation.
 *
 * @package QuickShipD
 * @since   1.0.0
 */

// Prevent direct execution.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Delete all wp_options entries.
// -------------------------------------------------------------------------

$quickshipd_options_to_delete = array(
	'quickshipd_enabled',
	'quickshipd_min_days',
	'quickshipd_max_days',
	'quickshipd_cutoff_hour',
	'quickshipd_cutoff_min',
	'quickshipd_exclude_weekends',
	'quickshipd_excluded_days',
	'quickshipd_holidays',
	'quickshipd_show_product',
	'quickshipd_show_shop',
	'quickshipd_show_cart',
	'quickshipd_show_checkout',
	'quickshipd_show_countdown',
	'quickshipd_show_countdown_seconds',
	'quickshipd_text_single',
	'quickshipd_text_range',
	'quickshipd_text_countdown',
	'quickshipd_date_format',
	'quickshipd_icon',
	'quickshipd_text_color',
	'quickshipd_secondary_color',
	'quickshipd_bg_color',
	'quickshipd_border_radius',
	'quickshipd_padding',
	'quickshipd_db_repaired_v1',
	'quickshipd_db_repaired_v2',
);

foreach ( $quickshipd_options_to_delete as $quickshipd_option_name ) {
	delete_option( $quickshipd_option_name );
}

global $wpdb;

// -------------------------------------------------------------------------
// Strip our per-instance overrides from WooCommerce shipping method settings.
// These live as keys inside the serialised woocommerce_<method>_<id>_settings
// option, so the whole option must be rewritten rather than deleted.
// -------------------------------------------------------------------------

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$quickshipd_shipping_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s",
		$wpdb->esc_like( 'woocommerce_' ) . '%' . $wpdb->esc_like( '_settings' ),
		'%' . $wpdb->esc_like( 'quickshipd_' ) . '%'
	)
);

foreach ( $quickshipd_shipping_options as $quickshipd_shipping_option ) {
	$quickshipd_settings = get_option( $quickshipd_shipping_option );

	if ( ! is_array( $quickshipd_settings ) ) {
		continue;
	}

	if ( ! isset( $quickshipd_settings['quickshipd_min_days'] ) && ! isset( $quickshipd_settings['quickshipd_max_days'] ) ) {
		continue;
	}

	unset( $quickshipd_settings['quickshipd_min_days'], $quickshipd_settings['quickshipd_max_days'] );
	update_option( $quickshipd_shipping_option, $quickshipd_settings );
}

// -------------------------------------------------------------------------
// Delete all wp_postmeta entries for per-product overrides.
// -------------------------------------------------------------------------

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	 WHERE meta_key IN (
	   '_quickshipd_disabled',
	   '_quickshipd_min_days',
	   '_quickshipd_max_days'
	 )"
);

// Order line item "Est. Delivery" meta is intentionally kept: it is part of the
// customer's historical order record, not plugin configuration.
