<?php
/**
 * Runs when the plugin is uninstalled via the WordPress admin.
 *
 * Deletes ALL plugin data: wp_options entries and wp_postmeta entries.
 * This file is only executed when the user explicitly uninstalls (deletes)
 * the plugin from the WordPress admin panel. It is NOT called on deactivation.
 *
 * @package QuickShip
 * @since   1.0.0
 */

// Prevent direct execution.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Delete all wp_options entries.
// -------------------------------------------------------------------------

$options_to_delete = array(
	'quickship_enabled',
	'quickship_min_days',
	'quickship_max_days',
	'quickship_cutoff_hour',
	'quickship_cutoff_min',
	'quickship_exclude_weekends',
	'quickship_excluded_days',
	'quickship_holidays',
	'quickship_show_product',
	'quickship_show_shop',
	'quickship_show_cart',
	'quickship_show_checkout',
	'quickship_show_countdown',
	'quickship_text_single',
	'quickship_text_range',
	'quickship_text_countdown',
	'quickship_date_format',
	'quickship_icon',
	'quickship_text_color',
	'quickship_bg_color',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// -------------------------------------------------------------------------
// Delete all wp_postmeta entries for per-product overrides.
// -------------------------------------------------------------------------

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	 WHERE meta_key IN (
	   '_quickship_disabled',
	   '_quickship_min_days',
	   '_quickship_max_days'
	 )"
);
