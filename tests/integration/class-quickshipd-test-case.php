<?php
/**
 * Shared base for the functional tests.
 *
 * WooCommerce's own WC_Helper_* factories live in its repository, not in the
 * distributed plugin, so the tests build what they need themselves.
 *
 * @package QuickShipD
 */

/**
 * Class QuickShipD_Test_Case
 */
abstract class QuickShipD_Test_Case extends WP_UnitTestCase {

	/**
	 * Create a saved, in-stock simple product.
	 *
	 * @param  array $props Optional property overrides.
	 * @return WC_Product_Simple
	 */
	protected function make_product( array $props = array() ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_props(
			array_merge(
				array(
					'name'          => 'Test product',
					'regular_price' => 10,
					'stock_status'  => 'instock',
				),
				$props
			)
		);
		$product->save();

		return $product;
	}

	/**
	 * Make sure the WooCommerce session and cart exist.
	 */
	protected function start_session(): void {
		if ( ! WC()->session ) {
			WC()->initialize_session();
		}
		if ( ! WC()->cart ) {
			WC()->initialize_cart();
		}
	}
}
