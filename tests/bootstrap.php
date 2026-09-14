<?php
/**
 * Bootstrap for the functional suite: WordPress plus WooCommerce.
 *
 * @package QuickShipD
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Locate WooCommerce. Beside this plugin when the suite runs inside a real
 * install, otherwise wherever CI checked it out to.
 *
 * @return string Absolute path to woocommerce.php, or '' if not found.
 */
function quickshipd_locate_woocommerce(): string {
	$candidates = array_filter(
		array(
			(string) getenv( 'WC_PLUGIN_FILE' ),
			dirname( __DIR__, 2 ) . '/woocommerce/woocommerce.php',
			rtrim( sys_get_temp_dir(), '/\\' ) . '/woocommerce/woocommerce.php',
		)
	);

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	return '';
}

$quickshipd_wc = quickshipd_locate_woocommerce();

if ( '' === $quickshipd_wc ) {
	echo 'Could not find WooCommerce. Set WC_PLUGIN_FILE or install it beside this plugin.' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

define( 'QUICKSHIPD_WC_FILE', $quickshipd_wc );

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		// WooCommerce first: QuickShipD bails out without it.
		require QUICKSHIPD_WC_FILE;
		require dirname( __DIR__ ) . '/quickshipd.php';
	}
);

// WooCommerce needs its tables in place before any test touches an order.
tests_add_filter(
	'setup_theme',
	static function (): void {
		if ( ! class_exists( 'WC_Install' ) ) {
			return;
		}
		WC_Install::install();
		WC()->mailer();
	}
);

require "{$_tests_dir}/includes/bootstrap.php";

require_once __DIR__ . '/integration/class-quickshipd-test-case.php';
