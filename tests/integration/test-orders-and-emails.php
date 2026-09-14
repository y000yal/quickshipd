<?php
/**
 * The estimate on the order record, and which emails render it.
 *
 * @package QuickShipD
 */

/**
 * Class Test_QuickShipD_Orders_And_Emails
 */
class Test_QuickShipD_Orders_And_Emails extends QuickShipD_Test_Case {

	/**
	 * Captured wp_mail payloads.
	 *
	 * @var array
	 */
	private $sent = array();

	/**
	 * Capture mail instead of sending it.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'timezone_string', 'UTC' );
		update_option( 'quickshipd_enabled', 'yes' );
		update_option( 'quickshipd_show_order_meta', 'yes' );
		delete_option( 'quickshipd_email_exclude' );

		$this->sent = array();

		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->sent[] = $atts;
				return true;
			},
			10,
			2
		);
	}

	/**
	 * Build an order carrying the estimate on its only line.
	 *
	 * @return WC_Order
	 */
	private function make_order_with_estimate(): WC_Order {
		$product = $this->make_product();

		$order = wc_create_order();
		$item  = new WC_Order_Item_Product();
		$item->set_props(
			array(
				'product_id' => $product->get_id(),
				'quantity'   => 1,
				'name'       => $product->get_name(),
				'total'      => 10,
			)
		);
		$item->add_meta_data( 'Est. Delivery', 'Tue, Aug 18', true );
		$order->add_item( $item );
		$order->set_billing_email( 'shopper@example.com' );
		$order->save();

		return $order;
	}

	/**
	 * Trigger one WooCommerce email and report whether the estimate is in it.
	 *
	 * @param  string $email_id WooCommerce email id.
	 * @param  int    $order_id Order id.
	 * @return bool
	 */
	private function email_contains_estimate( string $email_id, int $order_id ): bool {
		$this->sent = array();

		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email->id === $email_id ) {
				$email->trigger( $order_id );
				break;
			}
		}

		foreach ( $this->sent as $mail ) {
			if ( isset( $mail['message'] ) && false !== strpos( $mail['message'], 'Est. Delivery' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @test
	 * Checkout stores the estimate on the line item.
	 */
	public function test_estimate_is_saved_on_the_order_line(): void {
		$product = $this->make_product();
		$item    = new WC_Order_Item_Product();
		$display = new QuickShipD_Display();

		$display->save_order_item_date(
			$item,
			'key',
			array(
				'product_id'   => $product->get_id(),
				'variation_id' => 0,
			)
		);

		$this->assertNotSame( '', (string) $item->get_meta( 'Est. Delivery', true ) );
	}

	/**
	 * @test
	 * quickshipd_save_order_item_date can veto the write.
	 */
	public function test_save_filter_can_block_the_estimate(): void {
		$product = $this->make_product();
		$item    = new WC_Order_Item_Product();
		$display = new QuickShipD_Display();

		add_filter( 'quickshipd_save_order_item_date', '__return_false' );
		$display->save_order_item_date(
			$item,
			'key',
			array(
				'product_id'   => $product->get_id(),
				'variation_id' => 0,
			)
		);
		remove_filter( 'quickshipd_save_order_item_date', '__return_false' );

		$this->assertSame( '', (string) $item->get_meta( 'Est. Delivery', true ) );
	}

	/**
	 * @test
	 * Turning the setting off unhooks the write entirely.
	 */
	public function test_setting_off_unhooks_the_write(): void {
		update_option( 'quickshipd_show_order_meta', 'no' );

		$display = new QuickShipD_Display();
		$display->init();

		$this->assertFalse(
			has_action( 'woocommerce_checkout_create_order_line_item', array( $display, 'save_order_item_date' ) )
		);
	}

	/**
	 * @test
	 * With nothing excluded, order emails carry the estimate.
	 */
	public function test_all_emails_include_the_estimate_by_default(): void {
		$order = $this->make_order_with_estimate();

		$this->assertTrue( $this->email_contains_estimate( 'customer_processing_order', $order->get_id() ) );
		$this->assertTrue( $this->email_contains_estimate( 'customer_completed_order', $order->get_id() ) );
	}

	/**
	 * @test
	 * Unticking one email hides it there and nowhere else.
	 */
	public function test_excluding_one_email_only_affects_that_email(): void {
		$order = $this->make_order_with_estimate();

		update_option( 'quickshipd_email_exclude', array( 'customer_processing_order' ) );

		$this->assertFalse( $this->email_contains_estimate( 'customer_processing_order', $order->get_id() ) );
		$this->assertTrue( $this->email_contains_estimate( 'customer_completed_order', $order->get_id() ) );
	}

	/**
	 * @test
	 * Hiding an email leaves the order record itself alone.
	 */
	public function test_excluding_an_email_keeps_the_order_meta(): void {
		$order = $this->make_order_with_estimate();
		update_option( 'quickshipd_email_exclude', array( 'customer_processing_order' ) );
		$this->email_contains_estimate( 'customer_processing_order', $order->get_id() );

		$keys = array();
		foreach ( wc_get_order( $order->get_id() )->get_items() as $line ) {
			foreach ( $line->get_formatted_meta_data() as $meta ) {
				$keys[] = $meta->key;
			}
		}

		$this->assertContains( 'Est. Delivery', $keys );
	}

	/**
	 * @test
	 * quickshipd_show_in_email overrides the setting.
	 */
	public function test_show_in_email_filter_wins(): void {
		add_filter( 'quickshipd_show_in_email', '__return_false' );
		$this->assertFalse( QuickShipD_Display::email_shows_estimate( 'customer_processing_order' ) );
		remove_filter( 'quickshipd_show_in_email', '__return_false' );

		$this->assertTrue( QuickShipD_Display::email_shows_estimate( 'customer_processing_order' ) );
	}

	/**
	 * @test
	 * The email list offers order emails and skips those with no item table.
	 */
	public function test_email_list_contents(): void {
		$emails = QuickShipD_Display::get_order_emails();

		$this->assertArrayHasKey( 'customer_processing_order', $emails );
		$this->assertArrayHasKey( 'new_order', $emails );
		$this->assertArrayNotHasKey( 'customer_new_account', $emails );
		$this->assertArrayNotHasKey( 'customer_reset_password', $emails );
	}

	/**
	 * @test
	 * WooCommerce ships an admin and a customer copy of some emails under the
	 * same title, so the chooser has to tell them apart.
	 */
	public function test_email_labels_are_unique(): void {
		$labels = array_values( QuickShipD_Display::get_order_emails() );

		$this->assertSame( $labels, array_unique( $labels ), 'Two entries must never share a label.' );
		$this->assertStringContainsString( 'admin', QuickShipD_Display::get_order_emails()['cancelled_order'] );
		$this->assertStringContainsString( 'customer', QuickShipD_Display::get_order_emails()['customer_cancelled_order'] );
	}
}
