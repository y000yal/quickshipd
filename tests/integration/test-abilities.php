<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- PHPUnit discovers test-*.php files in this repository.
/**
 * Abilities API integration.
 *
 * @package QuickShipD
 */

/**
 * Exercise the registered abilities end to end.
 */
class QuickShipD_Abilities_Test extends QuickShipD_Test_Case {

	/**
	 * Skip when core has no Abilities API and start from a known schedule.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API needs WordPress 6.9+.' );
		}
		update_option( 'quickshipd_min_days', 1 );
		update_option( 'quickshipd_max_days', 2 );
		update_option( 'quickshipd_enabled', 'yes' );
	}

	/**
	 * Every ability is registered under the plugin category.
	 */
	public function test_abilities_are_registered(): void {
		foreach ( array( 'get-delivery-estimate', 'get-settings', 'update-settings', 'get-product-settings', 'update-product-settings' ) as $name ) {
			$ability = wp_get_ability( 'quickshipd/' . $name );
			$this->assertNotNull( $ability, $name );
			$this->assertSame( 'quickshipd', $ability->get_category() );
		}
	}

	/**
	 * Settings and estimate abilities need the right capability.
	 */
	public function test_permissions(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$result = wp_get_ability( 'quickshipd/update-settings' )->execute( array( 'min_days' => 9 ) );
		$this->assertWPError( $result );
		$this->assertSame( 1, (int) get_option( 'quickshipd_min_days' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$result = wp_get_ability( 'quickshipd/update-settings' )->execute( array( 'min_days' => 2 ) );
		$this->assertSame( 2, $result['min_days'] );
		$this->assertSame( 2, (int) get_option( 'quickshipd_min_days' ) );
	}

	/**
	 * A minimum above the maximum is rejected.
	 */
	public function test_update_settings_rejects_inverted_range(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertWPError( wp_get_ability( 'quickshipd/update-settings' )->execute( array( 'min_days' => 10 ) ) );
	}

	/**
	 * Product overrides round trip and feed the estimate, including on variations.
	 */
	public function test_product_overrides_drive_estimate(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$parent = new WC_Product_Variable();
		$parent->save();
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->save();

		$saved = wp_get_ability( 'quickshipd/update-product-settings' )->execute(
			array(
				'product_id' => $parent->get_id(),
				'min_days'   => 5,
				'max_days'   => 7,
			)
		);
		$this->assertSame( 5, $saved['min_days'] );

		$estimate = wp_get_ability( 'quickshipd/get-delivery-estimate' )->execute( array( 'product_id' => $variation->get_id() ) );
		$expected = QuickShipD_Calculator::from_settings( array(), $parent->get_id() )->calculate();
		$this->assertSame( $expected['min_date']->format( 'Y-m-d' ), $estimate['min_date'] );
		$this->assertSame( $expected['max_date']->format( 'Y-m-d' ), $estimate['max_date'] );

		wp_get_ability( 'quickshipd/update-product-settings' )->execute(
			array(
				'product_id' => $parent->get_id(),
				'min_days'   => null,
			)
		);
		$this->assertNull( wp_get_ability( 'quickshipd/get-product-settings' )->execute( array( 'product_id' => $parent->get_id() ) )['min_days'] );
	}
}
