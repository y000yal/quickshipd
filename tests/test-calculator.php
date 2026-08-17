<?php
/**
 * Unit tests for QuickShipD_Calculator.
 *
 * Run from the plugin root with:
 *   composer require --dev phpunit/phpunit
 *   vendor/bin/phpunit tests/
 *
 * These tests exercise pure calculation logic only — no WordPress functions
 * are called (wp_date, get_option, etc.) so no WordPress bootstrap is
 * required.
 *
 * @package QuickShipD
 * @since   1.0.0
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Stub wp_timezone() if running outside WordPress.
if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone(): \DateTimeZone {
		return new \DateTimeZone( 'UTC' );
	}
}

// Stub wp_date() if running outside WordPress.
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, ?int $timestamp = null ): string {
		return $timestamp !== null ? date( $format, $timestamp ) : date( $format );
	}
}

// Stub get_option() if running outside WordPress.
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		return $default;
	}
}

// Stub __() if running outside WordPress.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-quickshipd-calculator.php';

/**
 * Class Test_QuickShipD_Calculator
 *
 * @since 1.0.0
 */
class Test_QuickShipD_Calculator extends TestCase {

	// -----------------------------------------------------------------------
	// Helper: build a calculator with standard settings.
	// -----------------------------------------------------------------------

	private function make( int $min = 3, int $max = 5, int $cutoff_hour = 14, int $cutoff_min = 0, array $excluded = array( 0, 6 ), array $holidays = array() ): QuickShipD_Calculator {
		return new QuickShipD_Calculator( $min, $max, $cutoff_hour, $cutoff_min, $excluded, $holidays );
	}

	// -----------------------------------------------------------------------
	// Basic business-day counting.
	// -----------------------------------------------------------------------

	/**
	 * @test
	 * Monday 09:00, cutoff 14:00, min=1, max=1 — should be Tuesday.
	 */
	public function test_next_business_day_before_cutoff(): void {
		$calc = $this->make( 1, 1, 14, 0 );
		// Monday 2024-01-08 09:00 UTC.
		$now    = new \DateTime( '2024-01-08 09:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );

		$this->assertSame( '2024-01-09', $result['min_date']->format( 'Y-m-d' ) ); // Tuesday.
		$this->assertSame( '2024-01-09', $result['max_date']->format( 'Y-m-d' ) );
		$this->assertFalse( $result['is_range'] );
		$this->assertGreaterThan( 0, $result['countdown_seconds'] );
	}

	/**
	 * @test
	 * Monday 16:00, cutoff 14:00 — past cutoff, should count from Tuesday.
	 * min=1 → Wednesday.
	 */
	public function test_past_cutoff_counts_from_tomorrow(): void {
		$calc = $this->make( 1, 1, 14, 0 );
		// Monday 16:00 UTC — past 14:00 cutoff.
		$now    = new \DateTime( '2024-01-08 16:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );

		// Start = Tuesday 2024-01-09 (because past cutoff).
		// +1 business day = Wednesday 2024-01-10.
		$this->assertSame( '2024-01-10', $result['max_date']->format( 'Y-m-d' ) );
		$this->assertSame( 0, $result['countdown_seconds'] );
	}

	/**
	 * @test
	 * Friday before cutoff, min=1 — weekend skipped, should be Monday.
	 */
	public function test_skips_weekend(): void {
		$calc = $this->make( 1, 1, 14, 0, array( 0, 6 ) );
		// Friday 2024-01-05 09:00.
		$now    = new \DateTime( '2024-01-05 09:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );

		$this->assertSame( '2024-01-08', $result['min_date']->format( 'Y-m-d' ) ); // Monday.
	}

	/**
	 * @test
	 * A range (min != max) sets is_range = true.
	 */
	public function test_range_flag(): void {
		$calc   = $this->make( 3, 5, 14, 0 );
		$now    = new \DateTime( '2024-01-08 09:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );

		$this->assertTrue( $result['is_range'] );
		$this->assertLessThan( $result['max_date'], $result['min_date'] );
	}

	// -----------------------------------------------------------------------
	// Dispatch-day roll-forward.
	// -----------------------------------------------------------------------

	/**
	 * @test
	 * Saturday order, weekends excluded, min=1 max=2. Monday is the dispatch
	 * day, not a delivery day, so the estimate is Tuesday–Wednesday.
	 */
	public function test_order_on_excluded_day_counts_from_next_dispatch_day(): void {
		$calc = $this->make( 1, 2, 14, 0, array( 0, 6 ) );
		// Saturday 2024-01-06 09:00.
		$now    = new \DateTime( '2024-01-06 09:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );

		$this->assertSame( '2024-01-09', $result['min_date']->format( 'Y-m-d' ) ); // Tuesday.
		$this->assertSame( '2024-01-10', $result['max_date']->format( 'Y-m-d' ) ); // Wednesday.
	}

	/**
	 * @test
	 * Friday before cutoff is unaffected: Friday still dispatches, so min=1
	 * lands on Monday and max=2 on Tuesday.
	 */
	public function test_dispatch_day_order_is_unaffected(): void {
		$calc = $this->make( 1, 2, 14, 0, array( 0, 6 ) );
		// Friday 2024-01-05 09:00.
		$now    = new \DateTime( '2024-01-05 09:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );

		$this->assertSame( '2024-01-08', $result['min_date']->format( 'Y-m-d' ) ); // Monday.
		$this->assertSame( '2024-01-09', $result['max_date']->format( 'Y-m-d' ) ); // Tuesday.
	}

	/**
	 * @test
	 * min=0 means same-day: delivered on the dispatch day itself.
	 */
	public function test_zero_days_is_the_dispatch_day(): void {
		$calc = $this->make( 0, 0, 14, 0, array( 0, 6 ) );
		// Saturday 2024-01-06 09:00 — dispatch rolls to Monday.
		$now    = new \DateTime( '2024-01-06 09:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );

		$this->assertSame( '2024-01-08', $result['max_date']->format( 'Y-m-d' ) ); // Monday.
	}

	/**
	 * @test
	 * The cutoff countdown is meaningless on a non-dispatch day.
	 */
	public function test_no_countdown_on_a_non_dispatch_day(): void {
		$calc = $this->make( 1, 2, 14, 0, array( 0, 6 ) );
		// Saturday 09:00 — well before the 14:00 cutoff, but nothing ships today.
		$now    = new \DateTime( '2024-01-06 09:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );

		$this->assertSame( 0, $result['countdown_seconds'] );
	}

	/**
	 * @test
	 * A holiday landing on the order date rolls dispatch forward too.
	 */
	public function test_holiday_on_order_date_rolls_dispatch_forward(): void {
		$calc = $this->make( 1, 1, 14, 0, array(), array( '2024-01-08' ) ); // Monday is a holiday.
		$now  = new \DateTime( '2024-01-08 09:00:00', new \DateTimeZone( 'UTC' ) );

		$result = $calc->calculate( $now );
		// Dispatch rolls to Tuesday, +1 day = Wednesday.
		$this->assertSame( '2024-01-10', $result['min_date']->format( 'Y-m-d' ) );
		$this->assertSame( 0, $result['countdown_seconds'] );
	}

	// -----------------------------------------------------------------------
	// Holiday logic.
	// -----------------------------------------------------------------------

	/**
	 * @test
	 * A specific holiday (Y-m-d) is skipped.
	 */
	public function test_specific_holiday_is_skipped(): void {
		$calc = $this->make( 1, 1, 14, 0, array(), array( '2024-01-09' ) ); // Skip Tuesday.
		$now  = new \DateTime( '2024-01-08 09:00:00', new \DateTimeZone( 'UTC' ) );

		$result = $calc->calculate( $now );
		// Tuesday is a holiday, so +1 business day lands on Wednesday.
		$this->assertSame( '2024-01-10', $result['min_date']->format( 'Y-m-d' ) );
	}

	/**
	 * @test
	 * A recurring holiday (XXXX-m-d) matches any year.
	 */
	public function test_recurring_holiday(): void {
		$calc = $this->make( 1, 1, 14, 0, array(), array( 'XXXX-01-09' ) ); // Every Jan 9th.
		$now  = new \DateTime( '2024-01-08 09:00:00', new \DateTimeZone( 'UTC' ) );

		$result = $calc->calculate( $now );
		// Jan 9 is excluded; next day = Jan 10.
		$this->assertSame( '2024-01-10', $result['min_date']->format( 'Y-m-d' ) );
	}

	// -----------------------------------------------------------------------
	// parse_holidays.
	// -----------------------------------------------------------------------

	/**
	 * @test
	 * parse_holidays strips blank lines and comment lines.
	 */
	public function test_parse_holidays_filters_comments(): void {
		$raw    = "2024-12-25\n# Christmas\nXXXX-01-01\n\n2024-11-28";
		$result = QuickShipD_Calculator::parse_holidays( $raw );

		$this->assertCount( 3, $result );
		$this->assertContains( '2024-12-25', $result );
		$this->assertContains( 'XXXX-01-01', $result );
		$this->assertContains( '2024-11-28', $result );
	}

	/**
	 * @test
	 * Legacy textarea migrates into structured single entries.
	 */
	public function test_migrate_textarea_holidays(): void {
		$raw     = "2024-12-25\n# skip\nXXXX-01-01\n";
		$entries = QuickShipD_Calculator::migrate_textarea_holidays( $raw );

		$this->assertCount( 2, $entries );
		$this->assertSame( 'single', $entries[0]['type'] );
		$this->assertSame( '2024-12-25', $entries[0]['start'] );
		$this->assertFalse( $entries[0]['recurring'] );
		$this->assertSame( '2024-01-01', $entries[1]['start'] );
		$this->assertTrue( $entries[1]['recurring'] );
	}

	/**
	 * @test
	 * expand_holidays expands a one-off range into each Y-m-d day.
	 */
	public function test_expand_one_off_range(): void {
		$keys = QuickShipD_Calculator::expand_holidays(
			array(
				array(
					'type'      => 'range',
					'start'     => '2024-12-24',
					'end'       => '2024-12-26',
					'recurring' => false,
				),
			)
		);

		$this->assertSame( array( '2024-12-24', '2024-12-25', '2024-12-26' ), $keys );
	}

	/**
	 * @test
	 * expand_holidays expands a recurring range into XXXX-m-d keys.
	 */
	public function test_expand_recurring_range(): void {
		$keys = QuickShipD_Calculator::expand_holidays(
			array(
				array(
					'type'      => 'range',
					'start'     => '2024-12-24',
					'end'       => '2024-12-26',
					'recurring' => true,
				),
			)
		);

		$this->assertSame( array( 'XXXX-12-24', 'XXXX-12-25', 'XXXX-12-26' ), $keys );
	}

	/**
	 * @test
	 * Year-crossing ranges are rejected.
	 */
	public function test_year_crossing_range_rejected(): void {
		$entry = QuickShipD_Calculator::sanitize_holiday_entry(
			array(
				'type'      => 'range',
				'start'     => '2024-12-30',
				'end'       => '2025-01-02',
				'recurring' => false,
			)
		);
		$this->assertNull( $entry );

		$keys = QuickShipD_Calculator::expand_holidays(
			array(
				array(
					'type'      => 'range',
					'start'     => '2024-12-30',
					'end'       => '2025-01-02',
					'recurring' => false,
				),
			)
		);
		$this->assertSame( array(), $keys );
	}

	/**
	 * @test
	 * Expanded holiday range is skipped by the calculator.
	 */
	public function test_holiday_range_is_skipped(): void {
		$keys = QuickShipD_Calculator::expand_holidays(
			array(
				array(
					'type'      => 'range',
					'start'     => '2024-01-09',
					'end'       => '2024-01-10',
					'recurring' => false,
				),
			)
		);
		$calc = $this->make( 1, 1, 14, 0, array(), $keys );
		$now  = new \DateTime( '2024-01-08 09:00:00', new \DateTimeZone( 'UTC' ) );

		$result = $calc->calculate( $now );
		// Jan 9–10 holidays; +1 business day from Mon → Jan 11.
		$this->assertSame( '2024-01-11', $result['min_date']->format( 'Y-m-d' ) );
	}

	// -----------------------------------------------------------------------
	// format_countdown.
	// -----------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_format_countdown_hours_and_minutes(): void {
		$this->assertSame( '3h 34m 0s', QuickShipD_Calculator::format_countdown( 3 * 3600 + 34 * 60 ) );
	}

	/**
	 * @test
	 */
	public function test_format_countdown_hours_minutes_seconds(): void {
		$this->assertSame( '3h 34m 27s', QuickShipD_Calculator::format_countdown( 3 * 3600 + 34 * 60 + 27 ) );
	}

	/**
	 * @test
	 */
	public function test_format_countdown_minutes_only(): void {
		$this->assertSame( '45m 0s', QuickShipD_Calculator::format_countdown( 45 * 60 ) );
	}

	/**
	 * @test
	 */
	public function test_format_countdown_seconds_only(): void {
		$this->assertSame( '42s', QuickShipD_Calculator::format_countdown( 42 ) );
	}

	/**
	 * @test
	 */
	public function test_format_countdown_zero_returns_empty(): void {
		$this->assertSame( '', QuickShipD_Calculator::format_countdown( 0 ) );
	}

	// -----------------------------------------------------------------------
	// min_days > max_days guard.
	// -----------------------------------------------------------------------

	/**
	 * @test
	 * If min > max is accidentally passed, the constructor should correct it.
	 */
	public function test_constructor_corrects_min_greater_than_max(): void {
		$calc = $this->make( 7, 3 ); // Deliberately reversed.
		$this->assertSame( 7, $calc->get_min_days() );
		$this->assertSame( 7, $calc->get_max_days() ); // Max raised to match min.
	}

	// -----------------------------------------------------------------------
	// Countdown is 0 when past cutoff.
	// -----------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_countdown_is_zero_past_cutoff(): void {
		$calc   = $this->make( 3, 5, 14, 0 );
		$now    = new \DateTime( '2024-01-08 15:00:00', new \DateTimeZone( 'UTC' ) );
		$result = $calc->calculate( $now );
		$this->assertSame( 0, $result['countdown_seconds'] );
	}
}
