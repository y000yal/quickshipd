<?php
/**
 * Per shipping method delivery days.
 *
 * Guards the regression where the storefront read the override through
 * WC_Shipping_Method::get_option(), which only routes to instance settings for
 * keys present in get_instance_form_fields() — and those are filtered in on
 * admin screens only.
 *
 * @package QuickShipD
 */

/**
 * Class Test_QuickShipD_Shipping_Overrides
 */
class Test_QuickShipD_Shipping_Overrides extends QuickShipD_Test_Case {

	/**
	 * Instance ids of the two flat rate methods created for each test.
	 *
	 * @var int[]
	 */
	private $instances = array();

	/**
	 * Two methods in one zone, with different delivery windows.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'timezone_string', 'UTC' );
		update_option( 'quickshipd_min_days', 3 );
		update_option( 'quickshipd_max_days', 5 );
		update_option( 'quickshipd_excluded_days', array( 0, 6 ) );

		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Test Zone' );
		$zone->save();

		$this->instances = array(
			'slow' => $zone->add_shipping_method( 'flat_rate' ),
			'fast' => $zone->add_shipping_method( 'flat_rate' ),
		);
		$zone->save();

		$this->set_method_days( $this->instances['slow'], '2', '4' );
		$this->set_method_days( $this->instances['fast'], '1', '2' );

		$this->start_session();
	}

	/**
	 * Store QuickShipD days on a shipping method instance, as the admin form does.
	 *
	 * @param int    $instance_id Instance id.
	 * @param string $min         Minimum days.
	 * @param string $max         Maximum days.
	 */
	private function set_method_days( int $instance_id, string $min, string $max ): void {
		$method   = WC_Shipping_Zones::get_shipping_method( $instance_id );
		$key      = $method->get_instance_option_key();
		$settings = (array) get_option( $key, array() );

		$settings['quickshipd_min_days'] = $min;
		$settings['quickshipd_max_days'] = $max;

		update_option( $key, $settings );
	}

	/**
	 * Read the overrides the way the storefront does.
	 *
	 * @param  int $instance_id Chosen instance id.
	 * @return array
	 */
	private function overrides_for( int $instance_id ): array {
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate:' . $instance_id ) );

		$method = new ReflectionMethod( QuickShipD_Display::class, 'get_selected_shipping_method_overrides' );
		$method->setAccessible( true );

		return $method->invoke( new QuickShipD_Display() );
	}

	/**
	 * @test
	 * Saved per-method days are read on the storefront, where the admin field
	 * filters never run.
	 */
	public function test_overrides_are_read_outside_the_admin(): void {
		$this->assertFalse( is_admin(), 'This must exercise the storefront path.' );

		$this->assertSame(
			array(
				'min_days' => 2,
				'max_days' => 4,
			),
			$this->overrides_for( $this->instances['slow'] )
		);

		$this->assertSame(
			array(
				'min_days' => 1,
				'max_days' => 2,
			),
			$this->overrides_for( $this->instances['fast'] )
		);
	}

	/**
	 * @test
	 * Different methods must produce different dates. This is the customer
	 * facing symptom: all three methods quoted the same range.
	 */
	public function test_each_method_quotes_its_own_dates(): void {
		$now = new DateTime( '2026-08-17 09:00:00', wp_timezone() ); // Monday.

		$slow = QuickShipD_Calculator::from_settings( $this->overrides_for( $this->instances['slow'] ) )->calculate( $now );
		$fast = QuickShipD_Calculator::from_settings( $this->overrides_for( $this->instances['fast'] ) )->calculate( $now );

		$this->assertSame( '2026-08-19', $slow['min_date']->format( 'Y-m-d' ) );
		$this->assertSame( '2026-08-21', $slow['max_date']->format( 'Y-m-d' ) );

		$this->assertSame( '2026-08-18', $fast['min_date']->format( 'Y-m-d' ) );
		$this->assertSame( '2026-08-19', $fast['max_date']->format( 'Y-m-d' ) );

		$this->assertNotSame(
			$slow['min_date']->format( 'Y-m-d' ),
			$fast['min_date']->format( 'Y-m-d' )
		);
	}

	/**
	 * @test
	 * A method with no QuickShipD days set falls back to the store default.
	 */
	public function test_method_without_overrides_uses_the_store_default(): void {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Bare Zone' );
		$zone->save();
		$bare = $zone->add_shipping_method( 'flat_rate' );
		$zone->save();

		$this->assertSame( array(), $this->overrides_for( $bare ) );

		$calc = QuickShipD_Calculator::from_settings( $this->overrides_for( $bare ) );
		$this->assertSame( 3, $calc->get_min_days() );
		$this->assertSame( 5, $calc->get_max_days() );
	}

	/**
	 * @test
	 * No chosen method means no override.
	 */
	public function test_no_chosen_method_means_no_override(): void {
		WC()->session->set( 'chosen_shipping_methods', array() );

		$method = new ReflectionMethod( QuickShipD_Display::class, 'get_selected_shipping_method_overrides' );
		$method->setAccessible( true );

		$this->assertSame( array(), $method->invoke( new QuickShipD_Display() ) );
	}

	/**
	 * @test
	 * The admin fields are still offered on shipping method forms.
	 */
	public function test_admin_form_exposes_the_fields(): void {
		$core   = QuickShipD_Core::get_instance();
		$fields = $core->add_shipping_method_fields( array() );

		$this->assertArrayHasKey( 'quickshipd_min_days', $fields );
		$this->assertArrayHasKey( 'quickshipd_max_days', $fields );
	}
}
