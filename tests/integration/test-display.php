<?php
/**
 * Storefront rendering: product hook, shop hook, cart line, shortcode, assets.
 *
 * @package QuickShipD
 */

/**
 * Class Test_QuickShipD_Display
 */
class Test_QuickShipD_Display extends QuickShipD_Test_Case {

	/**
	 * Product used across the tests.
	 *
	 * @var WC_Product
	 */
	private $product;

	/**
	 * Set a known schedule and a product.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'timezone_string', 'UTC' );
		update_option( 'quickshipd_enabled', 'yes' );
		update_option( 'quickshipd_min_days', 1 );
		update_option( 'quickshipd_max_days', 2 );
		update_option( 'quickshipd_excluded_days', array( 0, 6 ) );
		update_option( 'quickshipd_show_product', 'yes' );

		$this->product = $this->make_product();
	}

	/**
	 * @test
	 * The product hook renders the estimate markup.
	 */
	public function test_product_hook_renders_markup(): void {
		$GLOBALS['product'] = $this->product;

		$display = new QuickShipD_Display();
		ob_start();
		$display->render_product();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'quickshipd-delivery', $html );
		$this->assertStringContainsString( 'quickshipd-context-product', $html );
		$this->assertStringContainsString( 'quickshipd-date-text', $html );
	}

	/**
	 * @test
	 * Icons carry their own size, so a missing stylesheet cannot blow them up.
	 */
	public function test_icon_has_intrinsic_dimensions(): void {
		$GLOBALS['product'] = $this->product;

		$display = new QuickShipD_Display();
		ob_start();
		$display->render_product();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'width="18" height="18"', $html );
	}

	/**
	 * @test
	 * Rendering asks for the frontend assets wherever it happens.
	 */
	public function test_render_requests_the_frontend_assets(): void {
		$display = new QuickShipD_Display();
		$display->enqueue_assets();

		$this->assertTrue( wp_style_is( 'quickshipd-frontend', 'registered' ) );

		do_shortcode( '[quickshipd product_id="' . $this->product->get_id() . '"]' );

		$this->assertTrue( wp_style_is( 'quickshipd-frontend', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'quickshipd-frontend', 'enqueued' ) );
	}

	/**
	 * @test
	 * The shop context is compact and has no countdown.
	 */
	public function test_shop_context_has_no_countdown(): void {
		$html = do_shortcode( '[quickshipd product_id="' . $this->product->get_id() . '" context="shop"]' );

		$this->assertStringContainsString( 'quickshipd-context-shop', $html );
		$this->assertStringNotContainsString( 'quickshipd-countdown', $html );
	}

	/**
	 * @test
	 * The shortcode falls back to the current product.
	 */
	public function test_shortcode_uses_the_current_product(): void {
		$GLOBALS['product'] = $this->product;

		$html = do_shortcode( '[quickshipd]' );

		$this->assertStringContainsString( 'quickshipd-delivery', $html );
	}

	/**
	 * @test
	 * An unknown product renders nothing rather than a broken estimate.
	 */
	public function test_shortcode_without_a_product_renders_nothing(): void {
		unset( $GLOBALS['product'] );

		$this->assertSame( '', do_shortcode( '[quickshipd product_id="999999"]' ) );
	}

	/**
	 * @test
	 * Out of stock products get no estimate.
	 */
	public function test_out_of_stock_product_renders_nothing(): void {
		$this->product->set_stock_status( 'outofstock' );
		$this->product->save();

		$this->assertSame( '', do_shortcode( '[quickshipd product_id="' . $this->product->get_id() . '"]' ) );
	}

	/**
	 * @test
	 * The per-product disable flag is honoured.
	 */
	public function test_disabled_product_renders_nothing(): void {
		update_post_meta( $this->product->get_id(), '_quickshipd_disabled', 'yes' );

		$this->assertSame( '', do_shortcode( '[quickshipd product_id="' . $this->product->get_id() . '"]' ) );
	}

	/**
	 * @test
	 * The cart line carries the estimate, which is what the block cart reads.
	 */
	public function test_cart_item_data(): void {
		$display   = new QuickShipD_Display();
		$item_data = $display->render_cart_item(
			array(),
			array(
				'product_id'   => $this->product->get_id(),
				'variation_id' => 0,
			)
		);

		$this->assertCount( 1, $item_data );
		$this->assertSame( 'Est. Delivery', $item_data[0]['name'] );
		$this->assertNotSame( '', $item_data[0]['value'] );
	}

	/**
	 * @test
	 * Switching the plugin off registers no storefront hooks at all.
	 */
	public function test_disabling_the_plugin_registers_nothing(): void {
		update_option( 'quickshipd_enabled', 'no' );

		$display = new QuickShipD_Display();
		$display->init();

		$this->assertFalse( has_action( 'woocommerce_single_product_summary', array( $display, 'render_product' ) ) );
		$this->assertFalse( shortcode_exists( 'quickshipd_disabled_probe' ) );
	}
}
