<?php
/**
 * Settings screen: sanitizers, saving, the Help tab, and the weekend migration.
 *
 * @package QuickShipD
 */

/**
 * Class Test_QuickShipD_Settings
 */
class Test_QuickShipD_Settings extends QuickShipD_Test_Case {

	/**
	 * Admin instance under test.
	 *
	 * @var QuickShipD_Admin
	 */
	private $admin;

	/**
	 * Load the admin class, which the plugin only requires on admin requests.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-quickshipd-admin.php';
		$this->admin = new QuickShipD_Admin();
	}

	/**
	 * @test
	 * Non-dispatch days accept 0 to 6 and reject anything else.
	 */
	public function test_excluded_days_sanitizer(): void {
		$this->assertSame( array( 0, 6 ), $this->admin->sanitize_excluded_days( array( '0', '6' ) ) );
		$this->assertSame( array( 3 ), $this->admin->sanitize_excluded_days( array( 3, 9, 42 ) ) );
		$this->assertSame( array(), $this->admin->sanitize_excluded_days( 'nonsense' ) );
	}

	/**
	 * @test
	 * The cutoff is clamped to a real time.
	 */
	public function test_cutoff_sanitizers(): void {
		$this->assertSame( 14, $this->admin->sanitize_cutoff_hour( '14' ) );
		$this->assertSame( 23, $this->admin->sanitize_cutoff_hour( 99 ), 'Clamped to the last hour of the day.' );
		$this->assertSame( 0, $this->admin->sanitize_cutoff_hour( 'not a number' ) );
		$this->assertSame( 30, $this->admin->sanitize_cutoff_min( 30 ) );
		$this->assertSame( 0, $this->admin->sanitize_cutoff_min( 75 ), 'Out of range falls back to the hour.' );
		$this->assertSame( 0, $this->admin->sanitize_cutoff_min( -1 ) );
	}

	/**
	 * @test
	 * Checkboxes store yes or no, never anything else.
	 */
	public function test_checkbox_sanitizer(): void {
		$this->assertSame( 'yes', $this->admin->sanitize_checkbox( 'yes' ) );
		$this->assertSame( 'no', $this->admin->sanitize_checkbox( null ) );
		$this->assertSame( 'no', $this->admin->sanitize_checkbox( 'anything' ) );
	}

	/**
	 * @test
	 * Email choices are stored inverted, so an email added later by an
	 * extension shows the estimate by default rather than being hidden.
	 */
	public function test_email_exclude_is_stored_inverted(): void {
		$all = array_keys( QuickShipD_Display::get_order_emails() );

		$this->assertNotEmpty( $all );
		$this->assertSame( array(), $this->admin->sanitize_email_exclude( $all ), 'Everything ticked excludes nothing.' );
		$this->assertSame(
			array( 'new_order' ),
			array_values( $this->admin->sanitize_email_exclude( array_diff( $all, array( 'new_order' ) ) ) )
		);
		$this->assertCount( count( $all ), $this->admin->sanitize_email_exclude( array() ), 'Nothing ticked excludes everything.' );
	}

	/**
	 * @test
	 * The Help tab renders, including the shortcode reference.
	 */
	public function test_help_tab_renders(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		// WooCommerce grants this on install; the test role does not always have it.
		get_user_by( 'id', $user )->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user );

		ob_start();
		$this->admin->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="quickshipd-tab-help"', $html );
		$this->assertStringContainsString( '[quickshipd]', $html );
		$this->assertStringContainsString( 'quickshipd_save_order_item_date', $html );
		$this->assertStringNotContainsString( 'quickshipd_exclude_weekends', $html, 'Removed setting must be gone.' );
	}

	/**
	 * @test
	 * The old Exclude weekends toggle folds into the non-dispatch day list.
	 */
	public function test_weekend_setting_migrates(): void {
		update_option( 'quickshipd_exclude_weekends', 'yes' );
		update_option( 'quickshipd_excluded_days', array( 3 ) );

		quickshipd_migrate_exclude_weekends();

		$days = get_option( 'quickshipd_excluded_days' );

		$this->assertContains( 0, $days );
		$this->assertContains( 6, $days );
		$this->assertContains( 3, $days, 'An existing choice must survive.' );
		$this->assertFalse( get_option( 'quickshipd_exclude_weekends' ), 'Legacy option is dropped.' );
	}

	/**
	 * @test
	 * With the toggle off, the migration must not add weekends.
	 */
	public function test_weekend_migration_respects_off(): void {
		update_option( 'quickshipd_exclude_weekends', 'no' );
		update_option( 'quickshipd_excluded_days', array( 3 ) );

		quickshipd_migrate_exclude_weekends();

		$this->assertSame( array( 3 ), get_option( 'quickshipd_excluded_days' ) );
	}

	/**
	 * @test
	 * Holiday entries survive a round trip through storage.
	 */
	public function test_holiday_entries_round_trip(): void {
		$entries = QuickShipD_Calculator::normalize_holiday_entries(
			array(
				array(
					'type'      => 'range',
					'start'     => '2026-12-24',
					'end'       => '2026-12-26',
					'recurring' => false,
				),
			)
		);

		$this->assertSame(
			array( '2026-12-24', '2026-12-25', '2026-12-26' ),
			QuickShipD_Calculator::expand_holidays( $entries )
		);
	}
}
