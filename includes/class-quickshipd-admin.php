<?php
/**
 * Admin settings page and WooCommerce submenu entry.
 *
 * @package QuickShipD
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShipD_Admin
 *
 * Registers the WooCommerce > QuickShipD settings page using the WordPress
 * Settings API. All settings are stored in wp_options. No custom tables.
 *
 * @since 1.0.0
 */
class QuickShipD_Admin {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'quickshipd_settings';

	/**
	 * Valid tabs.
	 *
	 * @var string[]
	 */
	private $tabs = array(
		'delivery' => '',
		'display'  => '',
		'style'    => '',
	);

	/**
	 * Current active tab.
	 *
	 * @var string
	 */
	private $current_tab = 'delivery';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_quickshipd_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_quickshipd_restore_defaults', array( $this, 'ajax_restore_defaults' ) );
	}

	/**
	 * Add WooCommerce > QuickShipD submenu.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'QuickShipD — Delivery Dates', 'quickshipd' ),
			__( 'QuickShipD', 'quickshipd' ),
			'manage_woocommerce',
			'quickshipd-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register all settings fields via the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// ------------------------------------------------------------------ //
		// Delivery tab.
		// ------------------------------------------------------------------ //
		$this->register_section( 'quickshipd_delivery_general', __( 'General', 'quickshipd' ), 'delivery' );

		$this->register_field(
			'quickshipd_enabled',
			__( 'Enable QuickShipD', 'quickshipd' ),
			'render_checkbox',
			'delivery',
			'quickshipd_delivery_general',
			array(
				'id'      => 'quickshipd_enabled',
				'default' => 'yes',
				'tooltip' => __( 'Enables or disables estimated delivery dates across your entire store', 'quickshipd' ),
			)
		);

		$this->register_section( 'quickshipd_delivery_timing', __( 'Timing', 'quickshipd' ), 'delivery' );

		$this->register_field(
			'quickshipd_min_days',
			__( 'Minimum delivery days', 'quickshipd' ),
			'render_number',
			'delivery',
			'quickshipd_delivery_timing',
			array(
				'id'      => 'quickshipd_min_days',
				'default' => 3,
				'min'     => 0,
				'max'     => 365,
				'tooltip' => __( 'Minimum number of business days between order and delivery', 'quickshipd' ),
			)
		);

		$this->register_field(
			'quickshipd_max_days',
			__( 'Maximum delivery days', 'quickshipd' ),
			'render_number',
			'delivery',
			'quickshipd_delivery_timing',
			array(
				'id'      => 'quickshipd_max_days',
				'default' => 5,
				'min'     => 0,
				'max'     => 365,
				'tooltip' => __( 'Maximum delivery days. Set equal to minimum to show a single date instead of a range', 'quickshipd' ),
			)
		);

		$this->register_field(
			'quickshipd_cutoff_hour',
			__( 'Order cutoff time', 'quickshipd' ),
			'render_cutoff',
			'delivery',
			'quickshipd_delivery_timing',
			array(
				'id'      => 'quickshipd_cutoff_hour',
				'default' => 14,
				'tooltip' => __( 'Orders placed after this time count as next-day for delivery calculation', 'quickshipd' ),
			)
		);

		$this->register_section( 'quickshipd_delivery_schedule', __( 'Schedule', 'quickshipd' ), 'delivery' );

		$this->register_field(
			'quickshipd_exclude_weekends',
			__( 'Exclude weekends', 'quickshipd' ),
			'render_checkbox',
			'delivery',
			'quickshipd_delivery_schedule',
			array(
				'id'      => 'quickshipd_exclude_weekends',
				'default' => 'yes',
				'tooltip' => __( 'Skip Saturday and Sunday when counting delivery days', 'quickshipd' ),
			)
		);

		$this->register_field(
			'quickshipd_excluded_days',
			__( 'Non-delivery days', 'quickshipd' ),
			'render_weekdays',
			'delivery',
			'quickshipd_delivery_schedule',
			array(
				'id'      => 'quickshipd_excluded_days',
				'tooltip' => __( 'Select specific days of the week when you do not dispatch orders', 'quickshipd' ),
			)
		);

		$this->register_field(
			'quickshipd_holidays',
			__( 'Holidays', 'quickshipd' ),
			'render_textarea',
			'delivery',
			'quickshipd_delivery_schedule',
			array(
				'id'      => 'quickshipd_holidays',
				'default' => '',
				'rows'    => 8,
				'tooltip' => __( 'One date per line. Use YYYY-MM-DD for a one-off date or XXXX-MM-DD to repeat yearly (e.g. XXXX-12-25 for Christmas). Lines starting with # are ignored.', 'quickshipd' ),
			)
		);

		// Each tab has its own option group so saving one tab never wipes another's options.

		// ------------------------------------------------------------------ //
		// Display tab.
		// ------------------------------------------------------------------ //
		$this->register_section( 'quickshipd_display_locations', __( 'Show on', 'quickshipd' ), 'display' );

		$display_locations = array(
			'quickshipd_show_product'  => array(
				'label'   => __( 'Product pages', 'quickshipd' ),
				'default' => 'yes',
			),
			'quickshipd_show_shop'     => array(
				'label'   => __( 'Shop / archive pages', 'quickshipd' ),
				'default' => 'no',
			),
			'quickshipd_show_cart'     => array(
				'label'   => __( 'Cart page (per item)', 'quickshipd' ),
				'default' => 'yes',
			),
			'quickshipd_show_checkout' => array(
				'label'   => __( 'Checkout page', 'quickshipd' ),
				'default' => 'yes',
			),
		);

		foreach ( $display_locations as $key => $args ) {
			$this->register_field(
				$key,
				$args['label'],
				'render_checkbox',
				'display',
				'quickshipd_display_locations',
				array(
					'id'      => $key,
					'label'   => $args['label'],
					'default' => $args['default'],
				)
			);
		}

		$this->register_section( 'quickshipd_display_options', __( 'Options', 'quickshipd' ), 'display' );

		$this->register_field(
			'quickshipd_show_countdown',
			__( 'Show countdown timer', 'quickshipd' ),
			'render_checkbox',
			'display',
			'quickshipd_display_options',
			array(
				'id'      => 'quickshipd_show_countdown',
				'default' => 'yes',
				'tooltip' => __( 'Shows an "Order within Xh Ym" countdown timer on product pages to encourage urgency', 'quickshipd' ),
			)
		);

		$this->register_field(
			'quickshipd_show_countdown_seconds',
			__( 'Show live seconds', 'quickshipd' ),
			'render_checkbox',
			'display',
			'quickshipd_display_options',
			array(
				'id'      => 'quickshipd_show_countdown_seconds',
				'default' => 'yes',
				'tooltip' => __( 'Tick seconds in real-time (e.g. 2h 30m 14s). When off, only hours and minutes are shown.', 'quickshipd' ),
			)
		);

		// ------------------------------------------------------------------ //
		// Style tab.
		// ------------------------------------------------------------------ //
		$this->register_section( 'quickshipd_style_text', __( 'Text Templates', 'quickshipd' ), 'style' );

		$text_fields = array(
			'quickshipd_text_single'    => array(
				'label'   => __( 'Single-date text', 'quickshipd' ),
				'default' => 'Get it by {date}',
				'tooltip' => __( 'Available placeholders: {date}', 'quickshipd' ),
			),
			'quickshipd_text_range'     => array(
				'label'   => __( 'Date-range text', 'quickshipd' ),
				'default' => 'Get it {start} – {end}',
				'tooltip' => __( 'Available placeholders: {start}, {end}', 'quickshipd' ),
			),
			'quickshipd_text_countdown' => array(
				'label'   => __( 'Countdown text', 'quickshipd' ),
				'default' => 'Order within {countdown} to get it by {date}',
				'tooltip' => __( 'Available placeholders: {countdown}, {date}', 'quickshipd' ),
			),
		);

		foreach ( $text_fields as $key => $args ) {
			$this->register_field(
				$key,
				$args['label'],
				'render_text',
				'style',
				'quickshipd_style_text',
				array(
					'id'      => $key,
					'default' => $args['default'],
					'tooltip' => $args['tooltip'],
				)
			);
		}

		$this->register_section( 'quickshipd_style_format', __( 'Date Format &amp; Icon', 'quickshipd' ), 'style' );

		$this->register_field(
			'quickshipd_date_format',
			__( 'Date format', 'quickshipd' ),
			'render_date_format',
			'style',
			'quickshipd_style_format',
			array( 'id' => 'quickshipd_date_format' )
		);

		$this->register_field(
			'quickshipd_icon',
			__( 'Icon', 'quickshipd' ),
			'render_icon_select',
			'style',
			'quickshipd_style_format',
			array( 'id' => 'quickshipd_icon' )
		);

		$this->register_section( 'quickshipd_style_colors', __( 'Colors &amp; Background', 'quickshipd' ), 'style' );

		$this->register_field(
			'quickshipd_text_color',
			__( 'Primary text color', 'quickshipd' ),
			'render_color',
			'style',
			'quickshipd_style_colors',
			array(
				'id'      => 'quickshipd_text_color',
				'default' => '#16a34a',
				'tooltip' => __( 'Applied to the delivery date line and the bold time in the countdown', 'quickshipd' ),
			)
		);

		$this->register_field(
			'quickshipd_secondary_color',
			__( 'Secondary text color', 'quickshipd' ),
			'render_color',
			'style',
			'quickshipd_style_colors',
			array(
				'id'      => 'quickshipd_secondary_color',
				'default' => '#6b7280',
				'tooltip' => __( 'Applied to the "Order within … to get it by" surrounding text', 'quickshipd' ),
			)
		);

		$this->register_field(
			'quickshipd_bg_color',
			__( 'Background color', 'quickshipd' ),
			'render_color',
			'style',
			'quickshipd_style_colors',
			array(
				'id'      => 'quickshipd_bg_color',
				'default' => '#f0fdf4',
				'tooltip' => __( 'Background behind the delivery widget. Leave blank for transparent.', 'quickshipd' ),
			)
		);

		$this->register_field(
			'quickshipd_border_radius',
			__( 'Border radius', 'quickshipd' ),
			'render_range',
			'style',
			'quickshipd_style_colors',
			array(
				'id'      => 'quickshipd_border_radius',
				'default' => 8,
				'min'     => 0,
				'max'     => 50,
				'unit'    => 'px',
			)
		);

		$this->register_field(
			'quickshipd_padding',
			__( 'Padding', 'quickshipd' ),
			'render_range',
			'style',
			'quickshipd_style_colors',
			array(
				'id'      => 'quickshipd_padding',
				'default' => 10,
				'min'     => 0,
				'max'     => 40,
				'unit'    => 'px',
			)
		);
	}

	// -----------------------------------------------------------------------
	// Page render.
	// -----------------------------------------------------------------------

	/**
	 * Render the full settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'quickshipd' ) );
		}

		$tabs = array(
			'delivery' => array(
				'label' => __( 'Delivery', 'quickshipd' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M1 3h15v13H1V3z" stroke="currentColor" stroke-width="2" fill="none"/><path d="M16 8h4l3 4v5h-7V8z" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="5.5" cy="18.5" r="2" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="18.5" cy="18.5" r="2" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
			),
			'display'  => array(
				'label' => __( 'Display', 'quickshipd' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2" fill="none"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
			),
			'style'    => array(
				'label' => __( 'Style', 'quickshipd' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
			),
		);
		?>
		<div class="wrap quickshipd-settings-wrap">

			<div class="quickshipd-page-header">
				<h1 class="quickshipd-page-title">
					<span class="quickshipd-title-icon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M1 3h15v13H1V3z" stroke="currentColor" stroke-width="2" fill="none"/>
							<path d="M16 8h4l3 4v5h-7V8z" stroke="currentColor" stroke-width="2" fill="none"/>
							<circle cx="5.5" cy="18.5" r="2" stroke="currentColor" stroke-width="2" fill="none"/>
							<circle cx="18.5" cy="18.5" r="2" stroke="currentColor" stroke-width="2" fill="none"/>
						</svg>
					</span>
					<?php esc_html_e( 'QuickShipD', 'quickshipd' ); ?>
				</h1>
				<span class="quickshipd-version-badge">v<?php echo esc_html( QUICKSHIPD_VERSION ); ?></span>
			</div>

			<div class="quickshipd-layout">

				<!-- Left: tabs + settings -->
				<div class="quickshipd-layout-left">

					<nav class="quickshipd-tab-nav" aria-label="<?php esc_attr_e( 'Settings tabs', 'quickshipd' ); ?>">
						<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
							<button type="button" class="quickshipd-tab-btn <?php echo 'delivery' === $tab_key ? 'is-active' : ''; ?>" data-tab="<?php echo esc_attr( $tab_key ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG.
								echo $tab_data['icon'];
								echo esc_html( $tab_data['label'] );
								?>
							</button>
						<?php endforeach; ?>
					</nav>

					<div class="quickshipd-settings-form">
						<?php foreach ( $tabs as $tab_key => $unused ) : ?>
							<div class="quickshipd-tab-pane <?php echo 'delivery' === $tab_key ? 'is-active' : ''; ?>" id="quickshipd-tab-<?php echo esc_attr( $tab_key ); ?>" data-tab="<?php echo esc_attr( $tab_key ); ?>">
								<?php do_settings_sections( 'quickshipd-' . $tab_key ); ?>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="quickshipd-save-bar">
						<button type="button" id="quickshipd-save-btn" class="button button-primary">
							<?php esc_html_e( 'Save Settings', 'quickshipd' ); ?>
						</button>
						<span class="quickshipd-save-status" id="quickshipd-save-status"></span>
						<button type="button" id="quickshipd-restore-btn" class="button button-secondary qs-restore-btn">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="vertical-align:middle;margin-right:4px;margin-top:-2px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 3v5h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<?php esc_html_e( 'Restore Defaults', 'quickshipd' ); ?>
						</button>
					</div>

				</div><!-- /.quickshipd-layout-left -->

				<!-- Right: sticky live preview -->
				<div class="quickshipd-layout-right">
					<?php $this->render_live_preview(); ?>
				</div>

			</div><!-- /.quickshipd-layout -->

			<footer class="quickshipd-settings-footer">
				<div class="qs-footer-left">
					<svg class="qs-footer-logo" viewBox="0 0 24 24" fill="none" aria-hidden="true">
						<path d="M1 3h15v13H1V3z" stroke="currentColor" stroke-width="1.5" fill="none"/>
						<path d="M16 8h4l3 4v5h-7V8z" stroke="currentColor" stroke-width="1.5" fill="none"/>
						<circle cx="5.5" cy="18.5" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
						<circle cx="18.5" cy="18.5" r="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
					</svg>
					<?php
					printf(
						/* translators: plugin version */
						esc_html__( 'QuickShipD v%s', 'quickshipd' ),
						esc_html( QUICKSHIPD_VERSION )
					);
					?>
				</div>
			</footer>

		</div>
		<?php
	}

	/**
	 * Render the live preview card.
	 *
	 * @return void
	 */
	private function render_live_preview(): void {
		$calc   = QuickShipD_Calculator::from_settings();
		$result = $calc->calculate();
		$html   = QuickShipD_Display::build_html( $result, null, 'product' );
		?>
		<div class="quickshipd-preview-card" id="quickshipd-live-preview">
			<div class="quickshipd-preview-header">
				<p class="quickshipd-preview-label"><?php esc_html_e( 'Live Preview For', 'quickshipd' ); ?></p>
				<span class="quickshipd-preview-context"><?php esc_html_e( 'Product page/Shop Page', 'quickshipd' ); ?></span>
			</div>
			<div class="quickshipd-preview-stage">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from our own builder, already escaped internally.
				echo $html;
				?>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Field renderers.
	// -----------------------------------------------------------------------

	/**
	 * Render a yes/no field as a toggle switch.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_checkbox( array $args ): void {
		$id      = esc_attr( $args['id'] );
		$default = isset( $args['default'] ) ? $args['default'] : 'no';
		$value   = get_option( $id, $default );
		printf(
			'<label class="quickshipd-toggle"><span class="quickshipd-toggle__switch"><input type="checkbox" class="quickshipd-toggle__input" id="%1$s" name="%1$s" value="yes" %2$s><span class="quickshipd-toggle__track" aria-hidden="true"></span></span></label>',
			esc_attr( $id ),
			checked( 'yes', $value, false )
		);
	}

	/**
	 * Render a number input field.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_number( array $args ): void {
		$id      = esc_attr( $args['id'] );
		$default = isset( $args['default'] ) ? (int) $args['default'] : 0;
		$min     = isset( $args['min'] ) ? (int) $args['min'] : 0;
		$max     = isset( $args['max'] ) ? (int) $args['max'] : 9999;
		$value   = (int) get_option( $id, $default );
		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$s" min="%3$s" max="%4$s" class="small-text">',
			esc_attr( $id ),
			esc_attr( (string) $value ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max )
		);
	}

	/**
	 * Render a range slider with a live value badge.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_range( array $args ): void {
		$id      = esc_attr( $args['id'] );
		$default = isset( $args['default'] ) ? (int) $args['default'] : 0;
		$min     = isset( $args['min'] ) ? (int) $args['min'] : 0;
		$max     = isset( $args['max'] ) ? (int) $args['max'] : 100;
		$unit    = isset( $args['unit'] ) ? esc_html( $args['unit'] ) : '';
		$value   = (int) get_option( $id, $default );
		printf(
			'<div class="qs-range-wrap"><input type="range" id="%1$s" name="%1$s" value="%2$s" min="%3$s" max="%4$s" class="qs-range" oninput="this.nextElementSibling.textContent=this.value+\'%5$s\'"><span class="qs-range-val">%2$s%5$s</span></div>',
			esc_attr( $id ),
			esc_attr( (string) $value ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max ),
			$unit
		);
	}

	/**
	 * Render a text input field.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_text( array $args ): void {
		$id      = esc_attr( $args['id'] );
		$default = isset( $args['default'] ) ? $args['default'] : '';
		$value   = get_option( $id, $default );
		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text quickshipd-text-template" data-setting="%1$s">',
			esc_attr( $id ),
			esc_attr( $value )
		);
	}

	/**
	 * Render a textarea field.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_textarea( array $args ): void {
		$id      = esc_attr( $args['id'] );
		$default = isset( $args['default'] ) ? $args['default'] : '';
		$rows    = isset( $args['rows'] ) ? (int) $args['rows'] : 5;
		$value   = get_option( $id, $default );
		printf(
			'<textarea id="%1$s" name="%1$s" rows="%2$s" class="large-text code">%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( (string) $rows ),
			esc_textarea( $value )
		);
	}

	/**
	 * Render the cutoff time (hour + minute) selector.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_cutoff( array $args ): void {
		$hour  = (int) get_option( 'quickshipd_cutoff_hour', 14 );
		$min   = (int) get_option( 'quickshipd_cutoff_min', 0 );
		$value = sprintf( '%02d:%02d', $hour, $min );
		?>
		<div class="qs-time-wrap">
			<input
				type="time"
				id="quickshipd_cutoff_time"
				name="quickshipd_cutoff_time"
				value="<?php echo esc_attr( $value ); ?>"
				class="qs-time-input"
			>
		</div>
		<?php
	}

	/**
	 * Render the weekday multi-checkbox for non-delivery days.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_weekdays( array $args ): void {
		$days = array(
			0 => _x( 'Sunday', 'weekday', 'quickshipd' ),
			1 => _x( 'Monday', 'weekday', 'quickshipd' ),
			2 => _x( 'Tuesday', 'weekday', 'quickshipd' ),
			3 => _x( 'Wednesday', 'weekday', 'quickshipd' ),
			4 => _x( 'Thursday', 'weekday', 'quickshipd' ),
			5 => _x( 'Friday', 'weekday', 'quickshipd' ),
			6 => _x( 'Saturday', 'weekday', 'quickshipd' ),
		);

		$selected = (array) get_option( 'quickshipd_excluded_days', array() );
		$selected = array_map( 'intval', $selected );

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Non-delivery days', 'quickshipd' ) . '</legend>';
		foreach ( $days as $num => $label ) {
			printf(
				'<label class="quickshipd-toggle"><span class="quickshipd-toggle__switch"><input type="checkbox" class="quickshipd-toggle__input" id="quickshipd_excluded_day_%1$d" name="quickshipd_excluded_days[]" value="%1$d" %2$s><span class="quickshipd-toggle__track" aria-hidden="true"></span></span><span class="quickshipd-toggle__text">%3$s</span></label>',
				(int) $num,
				checked( in_array( $num, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Render date format selector.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_date_format( array $args ): void {
		$current = get_option( 'quickshipd_date_format', 'D, M j' );
		$formats = array(
			'D, M j'  => date_i18n( 'D, M j' ),
			'l, M j'  => date_i18n( 'l, M j' ),
			'M j'     => date_i18n( 'M j' ),
			'M j, Y'  => date_i18n( 'M j, Y' ),
			'd/m/Y'   => date_i18n( 'd/m/Y' ),
			'd.m.Y'   => date_i18n( 'd.m.Y' ),
		);
		echo '<select id="quickshipd_date_format" name="quickshipd_date_format">';
		foreach ( $formats as $fmt => $example ) {
			printf(
				'<option value="%1$s" %2$s>%3$s &mdash; <em>%4$s</em></option>',
				esc_attr( $fmt ),
				selected( $current, $fmt, false ),
				esc_html( $fmt ),
				esc_html( $example )
			);
		}
		echo '</select>';
	}

	/**
	 * Render icon selector.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_icon_select( array $args ): void {
		$current = get_option( 'quickshipd_icon', 'truck' );
		$options = array(
			'truck' => __( 'Truck', 'quickshipd' ),
			'box'   => __( 'Box', 'quickshipd' ),
			'none'  => __( 'None', 'quickshipd' ),
		);
		echo '<select id="quickshipd_icon" name="quickshipd_icon">';
		foreach ( $options as $val => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $val ),
				selected( $current, $val, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render a color picker input.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_color( array $args ): void {
		$id      = esc_attr( $args['id'] );
		$default = isset( $args['default'] ) ? $args['default'] : '';
		$value   = get_option( $id, $default );
		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="quickshipd-color-picker" data-default-color="%3$s">',
			esc_attr( $id ),
			esc_attr( $value ),
			esc_attr( $default )
		);
	}

	// -----------------------------------------------------------------------
	// Asset loading.
	// -----------------------------------------------------------------------

	/**
	 * Enqueue admin scripts and styles on the QuickShipD settings page.
	 *
	 * @param  string $hook  Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_quickshipd-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// Frontend stylesheet is needed for the live preview card.
		wp_enqueue_style(
			'quickshipd-frontend',
			QUICKSHIPD_URL . 'assets/css/frontend' . $suffix . '.css',
			array(),
			QUICKSHIPD_VERSION
		);

		wp_enqueue_style(
			'quickshipd-admin',
			QUICKSHIPD_URL . 'assets/css/admin' . $suffix . '.css',
			array( 'quickshipd-frontend' ),
			QUICKSHIPD_VERSION
		);

		wp_enqueue_script(
			'quickshipd-admin',
			QUICKSHIPD_URL . 'assets/js/admin' . $suffix . '.js',
			array( 'wp-color-picker', 'jquery' ),
			QUICKSHIPD_VERSION,
			true
		);

		$tz  = wp_timezone();
		$now = new \DateTime( 'now', $tz );

		wp_localize_script(
			'quickshipd-admin',
			'quickshipdAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'saveNonce'     => wp_create_nonce( 'quickshipd_save_settings' ),
				'restoreNonce'  => wp_create_nonce( 'quickshipd_restore_defaults' ),
				'savedText'     => __( 'Settings saved.', 'quickshipd' ),
				'restoredText'  => __( 'Defaults restored.', 'quickshipd' ),
				'errorText'     => __( 'Could not save. Please try again.', 'quickshipd' ),
				'confirmText'   => __( 'Reset all settings to their default values?', 'quickshipd' ),
				'nowTimestamp'  => $now->getTimestamp(),
				'siteUtcOffset' => $tz->getOffset( $now ),
			)
		);
	}

	/**
	 * AJAX handler: save settings for a single tab.
	 *
	 * Each tab owns its options — saving one tab never touches another's values.
	 *
	 * @return void
	 */
	public function ajax_save_settings(): void {
		check_ajax_referer( 'quickshipd_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by check_ajax_referer() above.
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';

		switch ( $tab ) {
			case 'delivery':
				update_option(
					'quickshipd_enabled',
					$this->sanitize_checkbox(
						isset( $_POST['quickshipd_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['quickshipd_enabled'] ) ) : null
					)
				);
				update_option( 'quickshipd_min_days', absint( wp_unslash( $_POST['quickshipd_min_days'] ?? 3 ) ) );
				update_option( 'quickshipd_max_days', absint( wp_unslash( $_POST['quickshipd_max_days'] ?? 5 ) ) );
				$cutoff_raw   = sanitize_text_field( wp_unslash( $_POST['quickshipd_cutoff_time'] ?? '14:00' ) );
				$cutoff_parts = explode( ':', $cutoff_raw );
				update_option( 'quickshipd_cutoff_hour', $this->sanitize_cutoff_hour( $cutoff_parts[0] ?? '14' ) );
				update_option( 'quickshipd_cutoff_min', $this->sanitize_cutoff_min( $cutoff_parts[1] ?? '0' ) );
				update_option(
					'quickshipd_exclude_weekends',
					$this->sanitize_checkbox(
						isset( $_POST['quickshipd_exclude_weekends'] ) ? sanitize_text_field( wp_unslash( $_POST['quickshipd_exclude_weekends'] ) ) : null
					)
				);
				$excluded_raw = array();
				if ( isset( $_POST['quickshipd_excluded_days'] ) && is_array( $_POST['quickshipd_excluded_days'] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each element sanitized via map_deep().
					$excluded_raw = map_deep( wp_unslash( $_POST['quickshipd_excluded_days'] ), 'sanitize_text_field' );
				}
				update_option( 'quickshipd_excluded_days', $this->sanitize_excluded_days( $excluded_raw ) );
				update_option( 'quickshipd_holidays', sanitize_textarea_field( wp_unslash( $_POST['quickshipd_holidays'] ?? '' ) ) );
				break;

			case 'display':
				update_option(
					'quickshipd_show_product',
					$this->sanitize_checkbox(
						isset( $_POST['quickshipd_show_product'] ) ? sanitize_text_field( wp_unslash( $_POST['quickshipd_show_product'] ) ) : null
					)
				);
				update_option(
					'quickshipd_show_shop',
					$this->sanitize_checkbox(
						isset( $_POST['quickshipd_show_shop'] ) ? sanitize_text_field( wp_unslash( $_POST['quickshipd_show_shop'] ) ) : null
					)
				);
				update_option(
					'quickshipd_show_cart',
					$this->sanitize_checkbox(
						isset( $_POST['quickshipd_show_cart'] ) ? sanitize_text_field( wp_unslash( $_POST['quickshipd_show_cart'] ) ) : null
					)
				);
				update_option(
					'quickshipd_show_checkout',
					$this->sanitize_checkbox(
						isset( $_POST['quickshipd_show_checkout'] ) ? sanitize_text_field( wp_unslash( $_POST['quickshipd_show_checkout'] ) ) : null
					)
				);
				update_option(
					'quickshipd_show_countdown',
					$this->sanitize_checkbox(
						isset( $_POST['quickshipd_show_countdown'] ) ? sanitize_text_field( wp_unslash( $_POST['quickshipd_show_countdown'] ) ) : null
					)
				);
				update_option(
					'quickshipd_show_countdown_seconds',
					$this->sanitize_checkbox(
						isset( $_POST['quickshipd_show_countdown_seconds'] ) ? sanitize_text_field( wp_unslash( $_POST['quickshipd_show_countdown_seconds'] ) ) : null
					)
				);
				break;

			case 'style':
				update_option( 'quickshipd_text_single', sanitize_text_field( wp_unslash( $_POST['quickshipd_text_single'] ?? 'Get it by {date}' ) ) );
				update_option( 'quickshipd_text_range', sanitize_text_field( wp_unslash( $_POST['quickshipd_text_range'] ?? 'Get it {start} – {end}' ) ) );
				update_option( 'quickshipd_text_countdown', sanitize_text_field( wp_unslash( $_POST['quickshipd_text_countdown'] ?? 'Order within {countdown} to get it by {date}' ) ) );
				update_option( 'quickshipd_date_format', sanitize_text_field( wp_unslash( $_POST['quickshipd_date_format'] ?? 'D, M j' ) ) );
				update_option( 'quickshipd_icon', $this->sanitize_icon( sanitize_text_field( wp_unslash( $_POST['quickshipd_icon'] ?? 'truck' ) ) ) );
				$qs_primary = sanitize_hex_color( wp_unslash( $_POST['quickshipd_text_color'] ?? '#16a34a' ) );
				update_option( 'quickshipd_text_color', $qs_primary ?: '#16a34a' );
				$qs_secondary = sanitize_hex_color( wp_unslash( $_POST['quickshipd_secondary_color'] ?? '#6b7280' ) );
				update_option( 'quickshipd_secondary_color', $qs_secondary ?: '#6b7280' );
				update_option( 'quickshipd_bg_color', $this->sanitize_maybe_color( sanitize_text_field( wp_unslash( $_POST['quickshipd_bg_color'] ?? '#f0fdf4' ) ) ) );
				update_option( 'quickshipd_border_radius', min( 50, max( 0, absint( wp_unslash( $_POST['quickshipd_border_radius'] ?? 8 ) ) ) ) );
				update_option( 'quickshipd_padding', min( 40, max( 0, absint( wp_unslash( $_POST['quickshipd_padding'] ?? 10 ) ) ) ) );
				break;

			default:
				wp_send_json_error( array( 'message' => 'unknown_tab' ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_send_json_success( array( 'message' => 'saved' ) );
	}

	/**
	 * AJAX handler: reset all settings to factory defaults.
	 *
	 * @return void
	 */
	public function ajax_restore_defaults(): void {
		check_ajax_referer( 'quickshipd_restore_defaults', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		quickshipd_repair_options( true ); // force = true resets everything.

		// Clear the one-time repair flag so the auto-repair can run again if needed.
		delete_option( 'quickshipd_db_repaired_v1' );

		wp_send_json_success( array( 'message' => 'restored' ) );
	}

	// -----------------------------------------------------------------------
	// Sanitize callbacks.
	// -----------------------------------------------------------------------

	/**
	 * Sanitize a yes/no checkbox value.
	 *
	 * @param  mixed $value Raw value.
	 * @return string 'yes' or 'no'.
	 */
	public function sanitize_checkbox( $value ): string {
		return 'yes' === $value ? 'yes' : 'no';
	}

	/**
	 * Sanitize cutoff hour (0–23).
	 *
	 * @param  mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_cutoff_hour( $value ): int {
		return min( 23, max( 0, absint( $value ) ) );
	}

	/**
	 * Sanitize cutoff minute (0–59).
	 *
	 * @param  mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_cutoff_min( $value ): int {
		$min = (int) $value;
		return ( $min >= 0 && $min <= 59 ) ? $min : 0;
	}

	/**
	 * Sanitize excluded days array (array of ints 0–6).
	 *
	 * @param  mixed $value Raw value.
	 * @return int[]
	 */
	public function sanitize_excluded_days( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values(
			array_filter(
				array_map( 'absint', $value ),
				static function ( int $d ): bool {
					return $d >= 0 && $d <= 6;
				}
			)
		);
	}

	/**
	 * Sanitize icon option value.
	 *
	 * @param  mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_icon( $value ): string {
		return in_array( $value, array( 'truck', 'box', 'none' ), true ) ? $value : 'truck';
	}

	/**
	 * Sanitize a color field that may be empty (for transparent background).
	 *
	 * @param  mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_maybe_color( $value ): string {
		if ( '' === trim( (string) $value ) ) {
			return '';
		}
		$sanitized = sanitize_hex_color( $value );
		return $sanitized ?? '';
	}

	// -----------------------------------------------------------------------
	// Helpers.
	// -----------------------------------------------------------------------

	/**
	 * Shorthand to add a settings section for a given tab page slug.
	 *
	 * @param  string $id    Section ID.
	 * @param  string $title Section title.
	 * @param  string $tab   Tab slug.
	 * @return void
	 */
	private function register_section( string $id, string $title, string $tab ): void {
		add_settings_section( $id, $title, '__return_false', 'quickshipd-' . $tab );
	}

	/**
	 * Shorthand to add a settings field bound to a section on a tab page.
	 *
	 * @param string $id       Option name / field ID.
	 * @param string $title    Field label.
	 * @param string $callback Method name on this class.
	 * @param string $tab      Tab slug.
	 * @param string $section  Section ID.
	 * @param array  $args     Arguments passed to the callback.
	 * @return void
	 */
	private function register_field(
		string $id,
		string $title,
		string $callback,
		string $tab,
		string $section,
		array $args = array()
	): void {
		if ( ! empty( $args['tooltip'] ) ) {
			$tip    = esc_attr( $args['tooltip'] );
			$title .= ' <span class="qs-tip" data-tip="' . $tip . '" tabindex="0" aria-label="' . $tip . '">'
				. '<svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false">'
				. '<circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>'
				. '<line x1="7" y1="6.5" x2="7" y2="10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
				. '<circle cx="7" cy="4.25" r="0.85" fill="currentColor"/>'
				. '</svg>'
				. '</span>';
		}
		add_settings_field(
			$id,
			$title,
			array( $this, $callback ),
			'quickshipd-' . $tab,
			$section,
			$args
		);
	}
}
