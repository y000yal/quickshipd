<?php
/**
 * Delivery estimate end to end: settings, dispatch day, cutoff, holidays.
 *
 * @package QuickShipD
 */

/**
 * Class Test_QuickShipD_Delivery_Estimate
 */
class Test_QuickShipD_Delivery_Estimate extends QuickShipD_Test_Case {

	/**
	 * Reset to a known schedule before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'timezone_string', 'UTC' );
		update_option( 'quickshipd_enabled', 'yes' );
		update_option( 'quickshipd_min_days', 1 );
		update_option( 'quickshipd_max_days', 2 );
		update_option( 'quickshipd_cutoff_hour', 14 );
		update_option( 'quickshipd_cutoff_min', 0 );
		update_option( 'quickshipd_excluded_days', array( 0, 6 ) );
		update_option( 'quickshipd_holidays', array() );
	}

	/**
	 * Build a calculator from the stored options and run it at a fixed moment.
	 *
	 * @param  string $when Date/time in the site timezone.
	 * @return array
	 */
	private function estimate_at( string $when ): array {
		$calc = QuickShipD_Calculator::from_settings();
		return $calc->calculate( new DateTime( $when, wp_timezone() ) );
	}

	/**
	 * @test
	 * The reported bug: a Saturday order must not treat Monday, the dispatch
	 * day, as the first delivery day.
	 */
	public function test_order_on_a_non_dispatch_day_counts_from_the_dispatch_day(): void {
		$result = $this->estimate_at( '2026-08-15 09:00:00' ); // Saturday.

		$this->assertSame( '2026-08-18', $result['min_date']->format( 'Y-m-d' ), 'Tuesday' );
		$this->assertSame( '2026-08-19', $result['max_date']->format( 'Y-m-d' ), 'Wednesday' );
	}

	/**
	 * @test
	 * A working day before the cutoff is unaffected by the roll-forward.
	 */
	public function test_working_day_before_cutoff_is_unchanged(): void {
		$result = $this->estimate_at( '2026-08-14 09:00:00' ); // Friday.

		$this->assertSame( '2026-08-17', $result['min_date']->format( 'Y-m-d' ), 'Monday' );
		$this->assertSame( '2026-08-18', $result['max_date']->format( 'Y-m-d' ), 'Tuesday' );
	}

	/**
	 * @test
	 * Past the cutoff, dispatch moves to the next working day.
	 */
	public function test_past_cutoff_moves_to_the_next_dispatch_day(): void {
		$result = $this->estimate_at( '2026-08-14 16:00:00' ); // Friday, after 14:00.

		$this->assertSame( '2026-08-18', $result['min_date']->format( 'Y-m-d' ) );
		$this->assertSame( 0, $result['countdown_seconds'] );
	}

	/**
	 * @test
	 * The countdown only runs while today can still dispatch.
	 */
	public function test_countdown_only_on_a_dispatch_day(): void {
		$monday = $this->estimate_at( '2026-08-17 09:00:00' );
		$this->assertSame( 5 * HOUR_IN_SECONDS, $monday['countdown_seconds'] );

		$saturday = $this->estimate_at( '2026-08-15 09:00:00' );
		$this->assertSame( 0, $saturday['countdown_seconds'], 'Nothing ships Saturday, so the cutoff is meaningless.' );
	}

	/**
	 * @test
	 * Zero days means same-day: delivered on the dispatch day itself.
	 */
	public function test_zero_days_is_the_dispatch_day(): void {
		update_option( 'quickshipd_min_days', 0 );
		update_option( 'quickshipd_max_days', 0 );

		$weekday = $this->estimate_at( '2026-08-17 09:00:00' );
		$this->assertSame( '2026-08-17', $weekday['max_date']->format( 'Y-m-d' ) );

		$weekend = $this->estimate_at( '2026-08-15 09:00:00' );
		$this->assertSame( '2026-08-17', $weekend['max_date']->format( 'Y-m-d' ), 'Rolls to Monday.' );
	}

	/**
	 * @test
	 * The same-day setup documented on the Help tab: minimum 0, maximum 0,
	 * cutoff 11:00, closed at weekends.
	 */
	public function test_same_day_setup_matches_the_documentation(): void {
		update_option( 'quickshipd_min_days', 0 );
		update_option( 'quickshipd_max_days', 0 );
		update_option( 'quickshipd_cutoff_hour', 11 );

		$in_time = $this->estimate_at( '2026-08-17 10:30:00' ); // Monday, before cutoff.
		$this->assertSame( '2026-08-17', $in_time['max_date']->format( 'Y-m-d' ), 'Same day.' );
		$this->assertSame( 30 * MINUTE_IN_SECONDS, $in_time['countdown_seconds'] );
		$this->assertFalse( $in_time['is_range'], 'Equal min and max shows a single date.' );

		$missed = $this->estimate_at( '2026-08-17 11:30:00' ); // Monday, after cutoff.
		$this->assertSame( '2026-08-18', $missed['max_date']->format( 'Y-m-d' ), 'Rolls to the next day.' );
		$this->assertSame( 0, $missed['countdown_seconds'] );

		$weekend = $this->estimate_at( '2026-08-15 09:00:00' ); // Saturday, closed.
		$this->assertSame( '2026-08-17', $weekend['max_date']->format( 'Y-m-d' ), 'Rolls to Monday.' );
	}

	/**
	 * @test
	 * Same day or next day, the 0 to 1 setup.
	 */
	public function test_same_day_or_next_day_range(): void {
		update_option( 'quickshipd_min_days', 0 );
		update_option( 'quickshipd_max_days', 1 );

		$result = $this->estimate_at( '2026-08-17 09:00:00' ); // Monday.

		$this->assertSame( '2026-08-17', $result['min_date']->format( 'Y-m-d' ) );
		$this->assertSame( '2026-08-18', $result['max_date']->format( 'Y-m-d' ) );
		$this->assertTrue( $result['is_range'] );
	}

	/**
	 * @test
	 * A store open seven days a week delivers same day at the weekend too.
	 */
	public function test_same_day_when_open_every_day(): void {
		update_option( 'quickshipd_min_days', 0 );
		update_option( 'quickshipd_max_days', 0 );
		update_option( 'quickshipd_excluded_days', array() );

		$saturday = $this->estimate_at( '2026-08-15 09:00:00' );

		$this->assertSame( '2026-08-15', $saturday['max_date']->format( 'Y-m-d' ) );
		$this->assertGreaterThan( 0, $saturday['countdown_seconds'] );
	}

	/**
	 * @test
	 * A holiday on the order date pushes dispatch, and holidays are skipped
	 * while counting.
	 */
	public function test_holidays_are_skipped(): void {
		update_option(
			'quickshipd_holidays',
			array(
				array(
					'type'      => 'single',
					'start'     => '2026-08-17',
					'end'       => '',
					'recurring' => false,
				),
			)
		);

		$result = $this->estimate_at( '2026-08-17 09:00:00' ); // Monday, closed.

		$this->assertSame( '2026-08-19', $result['min_date']->format( 'Y-m-d' ), 'Dispatch Tuesday, +1 day.' );
		$this->assertSame( 0, $result['countdown_seconds'] );
	}

	/**
	 * @test
	 * Recurring holidays match in any year.
	 */
	public function test_recurring_holiday_matches_any_year(): void {
		update_option(
			'quickshipd_holidays',
			array(
				array(
					'type'      => 'single',
					'start'     => '2020-08-18',
					'end'       => '',
					'recurring' => true,
				),
			)
		);

		$result = $this->estimate_at( '2026-08-17 09:00:00' );

		$this->assertSame( '2026-08-19', $result['min_date']->format( 'Y-m-d' ), 'Aug 18 skipped.' );
	}

	/**
	 * @test
	 * Per-product min/max override the store default.
	 */
	public function test_per_product_override(): void {
		$product = $this->make_product();
		update_post_meta( $product->get_id(), '_quickshipd_min_days', 5 );
		update_post_meta( $product->get_id(), '_quickshipd_max_days', 7 );

		$calc = QuickShipD_Calculator::from_settings( array(), $product->get_id() );

		$this->assertSame( 5, $calc->get_min_days() );
		$this->assertSame( 7, $calc->get_max_days() );
	}

	/**
	 * @test
	 * Dates follow the site timezone, the 1.0.4 regression.
	 */
	public function test_dates_use_the_site_timezone(): void {
		update_option( 'timezone_string', 'Australia/Sydney' );

		$result = $this->estimate_at( '2026-08-17 09:00:00' );
		$label  = QuickShipD_Calculator::format_date( $result['min_date'], 'Y-m-d' );

		$this->assertSame( '2026-08-18', $label, 'Midnight must not roll back a day.' );
	}
}
