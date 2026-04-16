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
	'quickshipd_text_single',
	'quickshipd_text_range',
	'quickshipd_text_countdown',
	'quickshipd_date_format',
	'quickshipd_icon',
	'quickshipd_text_color',
	'quickshipd_bg_color',
	'quickshipd_db_repaired_v2',
);

foreach ( $quickshipd_options_to_delete as $quickshipd_option_name ) {
	delete_option( $quickshipd_option_name );
}

global $wpdb;

// -------------------------------------------------------------------------
// Delete WooCommerce shipping instance options (quickshipd_min/max_days).
// -------------------------------------------------------------------------

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'%' . $wpdb->esc_like( 'quickshipd_min_days' ),
		'%' . $wpdb->esc_like( 'quickshipd_max_days' )
	)
);

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
