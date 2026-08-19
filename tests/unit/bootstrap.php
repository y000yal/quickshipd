<?php
/**
 * Bootstrap for the unit suite.
 *
 * The calculator is deliberately free of WordPress I/O, so these tests stub the
 * handful of WordPress functions it touches and run with no WordPress install,
 * no database, and no WooCommerce.
 *
 * @package QuickShipD
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone(): \DateTimeZone {
		return new \DateTimeZone( 'UTC' );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, ?int $timestamp = null ): string {
		return gmdate( $format, $timestamp ?? time() );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default_value = false ) {
		return $default_value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-quickshipd-calculator.php';
