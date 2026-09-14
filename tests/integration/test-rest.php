<?php
/**
 * REST endpoint.
 *
 * @package QuickShipD
 */

/**
 * Class Test_QuickShipD_Rest
 */
class Test_QuickShipD_Rest extends QuickShipD_Test_Case {

	/**
	 * Spin up the REST server so routes register.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'timezone_string', 'UTC' );
		update_option( 'quickshipd_min_days', 1 );
		update_option( 'quickshipd_max_days', 2 );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * @test
	 * The route is registered even though the class loads lazily.
	 */
	public function test_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/quickshipd/v1/date', $routes );
	}

	/**
	 * @test
	 * The endpoint returns rendered markup.
	 */
	public function test_endpoint_returns_markup(): void {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/quickshipd/v1/date' ) );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'html', $data );
		$this->assertStringContainsString( 'quickshipd-delivery', $data['html'] );
	}
}
