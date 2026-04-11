<?php
/**
 * Admin settings page and WooCommerce submenu entry.
 *
 * @package QuickShip
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuickShip_Admin
 *
 * Registers the WooCommerce > QuickShip settings page using the WordPress
 * Settings API. All settings are stored in wp_options. No custom tables.
 *
 * @since 1.0.0
 */
class QuickShip_Admin {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'quickship_settings';

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
		add_action( 'wp_ajax_quickship_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_quickship_restore_defaults', array( $this, 'ajax_restore_defaults' ) );
	}

	/**
	 * Add WooCommerce > QuickShip submenu.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'QuickShip — Delivery Dates', 'quickship-delivery-date' ),
			__( 'QuickShip', 'quickship-delivery-date' ),
			'manage_woocommerce',
			'quickship-settings',
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
		$this->register_section( 'quickship_delivery_general', __( 'General', 'quickship-delivery-date' ), 'delivery' );

		$this->register_field(
			'quickship_enabled',
			__( 'Enable QuickShip', 'quickship-delivery-date' ),
			'render_checkbox',
			'delivery',
			'quickship_delivery_general',
			array(
				'id'      => 'quickship_enabled',
				'label'   => __( 'Show estimated delivery dates on your store', 'quickship-delivery-date' ),
				'default' => 'yes',
			)
		);

		$this->register_section( 'quickship_delivery_timing', __( 'Timing', 'quickship-delivery-date' ), 'delivery' );

		$this->register_field(
			'quickship_min_days',
			__( 'Minimum delivery days', 'quickship-delivery-date' ),
			'render_number',
			'delivery',
			'quickship_delivery_timing',
			array(
				'id'          => 'quickship_min_days',
				'default'     => 3,
				'min'         => 0,
				'max'         => 365,
				'description' => __( 'Minimum number of business days for delivery.', 'quickship-delivery-date' ),
			)
		);

		$this->register_field(
			'quickship_max_days',
			__( 'Maximum delivery days', 'quickship-delivery-date' ),
			'render_number',
			'delivery',
			'quickship_delivery_timing',
			array(
				'id'          => 'quickship_max_days',
				'default'     => 5,
				'min'         => 0,
				'max'         => 365,
				'description' => __( 'Maximum number of business days for delivery. Set equal to minimum for a single date.', 'quickship-delivery-date' ),
			)
		);

		$this->register_field(
			'quickship_cutoff_hour',
			__( 'Order cutoff time', 'quickship-delivery-date' ),
			'render_cutoff',
			'delivery',
			'quickship_delivery_timing',
			array(
				'id'          => 'quickship_cutoff_hour',
				'default'     => 14,
				'description' => __( 'Orders placed after this time count as next-day.', 'quickship-delivery-date' ),
			)
		);

		$this->register_section( 'quickship_delivery_schedule', __( 'Schedule', 'quickship-delivery-date' ), 'delivery' );

		$this->register_field(
			'quickship_exclude_weekends',
			__( 'Exclude weekends', 'quickship-delivery-date' ),
			'render_checkbox',
			'delivery',
			'quickship_delivery_schedule',
			array(
				'id'      => 'quickship_exclude_weekends',
				'label'   => __( 'Skip Saturday and Sunday when counting delivery days', 'quickship-delivery-date' ),
				'default' => 'yes',
			)
		);

		$this->register_field(
			'quickship_excluded_days',
			__( 'Non-delivery days', 'quickship-delivery-date' ),
			'render_weekdays',
			'delivery',
			'quickship_delivery_schedule',
			array(
				'id'          => 'quickship_excluded_days',
				'description' => __( 'Select specific days when you do not dispatch orders.', 'quickship-delivery-date' ),
			)
		);

		$this->register_field(
			'quickship_holidays',
			__( 'Holidays', 'quickship-delivery-date' ),
			'render_textarea',
			'delivery',
			'quickship_delivery_schedule',
			array(
				'id'          => 'quickship_holidays',
				'default'     => '',
				'description' => __( 'One date per line. Format: YYYY-MM-DD (one-off) or XXXX-MM-DD (recurring yearly). Lines starting with # are ignored.', 'quickship-delivery-date' ),
				'rows'        => 8,
			)
		);

		// Each tab has its own option group so saving one tab never wipes another's options.

		// ------------------------------------------------------------------ //
		// Display tab.
		// ------------------------------------------------------------------ //
		$this->register_section( 'quickship_display_locations', __( 'Show on', 'quickship-delivery-date' ), 'display' );

		$display_locations = array(
			'quickship_show_product'  => array(
				'label'   => __( 'Product pages', 'quickship-delivery-date' ),
				'default' => 'yes',
			),
			'quickship_show_shop'     => array(
				'label'   => __( 'Shop / archive pages', 'quickship-delivery-date' ),
				'default' => 'no',
			),
			'quickship_show_cart'     => array(
				'label'   => __( 'Cart page (per item)', 'quickship-delivery-date' ),
				'default' => 'yes',
			),
			'quickship_show_checkout' => array(
				'label'   => __( 'Checkout page', 'quickship-delivery-date' ),
				'default' => 'yes',
			),
		);

		foreach ( $display_locations as $key => $args ) {
			$this->register_field(
				$key,
				$args['label'],
				'render_checkbox',
				'display',
				'quickship_display_locations',
				array(
					'id'      => $key,
					'label'   => $args['label'],
					'default' => $args['default'],
				)
			);
		}

		$this->register_section( 'quickship_display_options', __( 'Options', 'quickship-delivery-date' ), 'display' );

		$this->register_field(
			'quickship_show_countdown',
			__( 'Show countdown timer', 'quickship-delivery-date' ),
			'render_checkbox',
			'display',
			'quickship_display_options',
			array(
				'id'      => 'quickship_show_countdown',
				'label'   => __( 'Show "Order within Xh Ym" countdown on product pages', 'quickship-delivery-date' ),
				'default' => 'yes',
			)
		);

		// ------------------------------------------------------------------ //
		// Style tab.
		// ------------------------------------------------------------------ //
		$this->register_section( 'quickship_style_text', __( 'Text Templates', 'quickship-delivery-date' ), 'style' );

		$text_fields = array(
			'quickship_text_single'    => array(
				'label'       => __( 'Single-date text', 'quickship-delivery-date' ),
				'default'     => 'Get it by {date}',
				'description' => __( 'Available: {date}', 'quickship-delivery-date' ),
			),
			'quickship_text_range'     => array(
				'label'       => __( 'Date-range text', 'quickship-delivery-date' ),
				'default'     => 'Get it {start} – {end}',
				'description' => __( 'Available: {start}, {end}', 'quickship-delivery-date' ),
			),
			'quickship_text_countdown' => array(
				'label'       => __( 'Countdown text', 'quickship-delivery-date' ),
				'default'     => 'Order within {countdown} to get it by {date}',
				'description' => __( 'Available: {countdown}, {date}', 'quickship-delivery-date' ),
			),
		);

		foreach ( $text_fields as $key => $args ) {
			$this->register_field(
				$key,
				$args['label'],
				'render_text',
				'style',
				'quickship_style_text',
				array(
					'id'          => $key,
					'default'     => $args['default'],
					'description' => $args['description'],
				)
			);
		}

		$this->register_section( 'quickship_style_format', __( 'Date Format &amp; Icon', 'quickship-delivery-date' ), 'style' );

		$this->register_field(
			'quickship_date_format',
			__( 'Date format', 'quickship-delivery-date' ),
			'render_date_format',
			'style',
			'quickship_style_format',
			array( 'id' => 'quickship_date_format' )
		);

		$this->register_field(
			'quickship_icon',
			__( 'Icon', 'quickship-delivery-date' ),
			'render_icon_select',
			'style',
			'quickship_style_format',
			array( 'id' => 'quickship_icon' )
		);

		$this->register_section( 'quickship_style_colors', __( 'Colors', 'quickship-delivery-date' ), 'style' );

		$this->register_field(
			'quickship_text_color',
			__( 'Text color', 'quickship-delivery-date' ),
			'render_color',
			'style',
			'quickship_style_colors',
			array(
				'id'      => 'quickship_text_color',
				'default' => '#16a34a',
			)
		);

		$this->register_field(
			'quickship_bg_color',
			__( 'Background color', 'quickship-delivery-date' ),
			'render_color',
			'style',
			'quickship_style_colors',
			array(
				'id'          => 'quickship_bg_color',
				'default'     => '',
				'description' => __( 'Leave blank for transparent.', 'quickship-delivery-date' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'quickship-delivery-date' ) );
		}

		$tabs = array(
			'delivery' => array(
				'label' => __( 'Delivery', 'quickship-delivery-date' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M1 3h15v13H1V3z" stroke="currentColor" stroke-width="2" fill="none"/><path d="M16 8h4l3 4v5h-7V8z" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="5.5" cy="18.5" r="2" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="18.5" cy="18.5" r="2" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
			),
			'display'  => array(
				'label' => __( 'Display', 'quickship-delivery-date' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2" fill="none"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
			),
			'style'    => array(
				'label' => __( 'Style', 'quickship-delivery-date' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
			),
		);
		?>
		<div class="wrap quickship-settings-wrap">

			<div class="quickship-page-header">
				<h1 class="quickship-page-title">
					<span class="quickship-title-icon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
							<path d="M1 3h15v13H1V3z" stroke="currentColor" stroke-width="2" fill="none"/>
							<path d="M16 8h4l3 4v5h-7V8z" stroke="currentColor" stroke-width="2" fill="none"/>
							<circle cx="5.5" cy="18.5" r="2" stroke="currentColor" stroke-width="2" fill="none"/>
							<circle cx="18.5" cy="18.5" r="2" stroke="currentColor" stroke-width="2" fill="none"/>
						</svg>
					</span>
					<?php esc_html_e( 'QuickShip', 'quickship-delivery-date' ); ?>
				</h1>
				<span class="quickship-version-badge">v<?php echo esc_html( QUICKSHIP_VERSION ); ?></span>
			</div>

			<div class="quickship-layout">

				<!-- Left: tabs + settings -->
				<div class="quickship-layout-left">

					<nav class="quickship-tab-nav" aria-label="<?php esc_attr_e( 'Settings tabs', 'quickship-delivery-date' ); ?>">
						<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
							<button type="button"
							        class="quickship-tab-btn <?php echo 'delivery' === $tab_key ? 'is-active' : ''; ?>"
							        data-tab="<?php echo esc_attr( $tab_key ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG.
								echo $tab_data['icon'];
								echo esc_html( $tab_data['label'] );
								?>
							</button>
						<?php endforeach; ?>
					</nav>

					<div class="quickship-settings-form">
						<?php foreach ( $tabs as $tab_key => $unused ) : ?>
							<div class="quickship-tab-pane <?php echo 'delivery' === $tab_key ? 'is-active' : ''; ?>"
							     id="quickship-tab-<?php echo esc_attr( $tab_key ); ?>"
							     data-tab="<?php echo esc_attr( $tab_key ); ?>">
								<?php do_settings_sections( 'quickship-' . $tab_key ); ?>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="quickship-save-bar">
						<button type="button" id="quickship-save-btn" class="button button-primary">
							<?php esc_html_e( 'Save Settings', 'quickship-delivery-date' ); ?>
						</button>
						<span class="quickship-save-status" id="quickship-save-status"></span>
						<button type="button" id="quickship-restore-btn" class="button button-secondary qs-restore-btn">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="vertical-align:middle;margin-right:4px;margin-top:-2px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 3v5h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<?php esc_html_e( 'Restore Defaults', 'quickship-delivery-date' ); ?>
						</button>
					</div>

				</div><!-- /.quickship-layout-left -->

				<!-- Right: sticky live preview -->
				<div class="quickship-layout-right">
					<?php $this->render_live_preview(); ?>
				</div>

			</div><!-- /.quickship-layout -->

			<footer class="quickship-settings-footer">
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
						esc_html__( 'QuickShip v%s', 'quickship-delivery-date' ),
						esc_html( QUICKSHIP_VERSION )
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
		$calc   = QuickShip_Calculator::from_settings();
		$result = $calc->calculate();
		$html   = QuickShip_Display::build_html( $result, null, 'product' );
		?>
		<div class="quickship-preview-card" id="quickship-live-preview">
			<div class="quickship-preview-header">
				<p class="quickship-preview-label"><?php esc_html_e( 'Live Preview', 'quickship-delivery-date' ); ?></p>
				<span class="quickship-preview-context"><?php esc_html_e( 'Product page', 'quickship-delivery-date' ); ?></span>
			</div>
			<div class="quickship-preview-stage">
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
	 * Render a checkbox field.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_checkbox( array $args ): void {
		$id      = esc_attr( $args['id'] );
		$label   = isset( $args['label'] ) ? $args['label'] : '';
		$default = isset( $args['default'] ) ? $args['default'] : 'no';
		$value   = get_option( $id, $default );
		printf(
			'<label><input type="checkbox" id="%1$s" name="%1$s" value="yes" %2$s> %3$s</label>',
			esc_attr( $id ),
			checked( 'yes', $value, false ),
			esc_html( $label )
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
			'<input type="number" id="%1$s" name="%1$s" value="%2$d" min="%3$d" max="%4$d" class="small-text">',
			esc_attr( $id ),
			$value,
			$min,
			$max
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
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
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text quickship-text-template" data-setting="%1$s">',
			esc_attr( $id ),
			esc_attr( $value )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
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
			'<textarea id="%1$s" name="%1$s" rows="%2$d" class="large-text code">%3$s</textarea>',
			esc_attr( $id ),
			$rows,
			esc_textarea( $value )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render the cutoff time (hour + minute) selector.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_cutoff( array $args ): void {
		$hour    = (int) get_option( 'quickship_cutoff_hour', 14 );
		$min     = (int) get_option( 'quickship_cutoff_min', 0 );

		echo '<select id="quickship_cutoff_hour" name="quickship_cutoff_hour">';
		for ( $h = 0; $h <= 23; $h++ ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				$h,
				selected( $hour, $h, false ),
				esc_html( sprintf( '%02d', $h ) )
			);
		}
		echo '</select>';

		echo ' : ';

		echo '<select id="quickship_cutoff_min" name="quickship_cutoff_min">';
		foreach ( array( 0, 15, 30, 45 ) as $m ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				$m,
				selected( $min, $m, false ),
				esc_html( sprintf( '%02d', $m ) )
			);
		}
		echo '</select>';

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render the weekday multi-checkbox for non-delivery days.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_weekdays( array $args ): void {
		$days = array(
			0 => _x( 'Sunday', 'weekday', 'quickship-delivery-date' ),
			1 => _x( 'Monday', 'weekday', 'quickship-delivery-date' ),
			2 => _x( 'Tuesday', 'weekday', 'quickship-delivery-date' ),
			3 => _x( 'Wednesday', 'weekday', 'quickship-delivery-date' ),
			4 => _x( 'Thursday', 'weekday', 'quickship-delivery-date' ),
			5 => _x( 'Friday', 'weekday', 'quickship-delivery-date' ),
			6 => _x( 'Saturday', 'weekday', 'quickship-delivery-date' ),
		);

		$selected = (array) get_option( 'quickship_excluded_days', array() );
		$selected = array_map( 'intval', $selected );

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Non-delivery days', 'quickship-delivery-date' ) . '</legend>';
		foreach ( $days as $num => $label ) {
			printf(
				'<label style="margin-right:12px;"><input type="checkbox" name="quickship_excluded_days[]" value="%1$d" %2$s> %3$s</label>',
				$num,
				checked( in_array( $num, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render date format selector.
	 *
	 * @param  array $args Field arguments.
	 * @return void
	 */
	public function render_date_format( array $args ): void {
		$current = get_option( 'quickship_date_format', 'D, M j' );
		$formats = array(
			'D, M j'  => date_i18n( 'D, M j' ),
			'l, M j'  => date_i18n( 'l, M j' ),
			'M j'     => date_i18n( 'M j' ),
			'M j, Y'  => date_i18n( 'M j, Y' ),
			'd/m/Y'   => date_i18n( 'd/m/Y' ),
			'd.m.Y'   => date_i18n( 'd.m.Y' ),
		);
		echo '<select id="quickship_date_format" name="quickship_date_format">';
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
		$current = get_option( 'quickship_icon', 'truck' );
		$options = array(
			'truck' => __( 'Truck', 'quickship-delivery-date' ),
			'box'   => __( 'Box', 'quickship-delivery-date' ),
			'none'  => __( 'None', 'quickship-delivery-date' ),
		);
		echo '<select id="quickship_icon" name="quickship_icon">';
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
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="quickship-color-picker" data-default-color="%3$s">',
			esc_attr( $id ),
			esc_attr( $value ),
			esc_attr( $default )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	// -----------------------------------------------------------------------
	// Asset loading.
	// -----------------------------------------------------------------------

	/**
	 * Enqueue admin scripts and styles on the QuickShip settings page.
	 *
	 * @param  string $hook  Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_quickship-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Frontend stylesheet is needed for the live preview card.
		wp_enqueue_style(
			'quickship-frontend',
			QUICKSHIP_URL . 'assets/css/frontend.css',
			array(),
			QUICKSHIP_VERSION
		);

		wp_enqueue_style(
			'quickship-admin',
			QUICKSHIP_URL . 'assets/css/admin.css',
			array( 'quickship-frontend' ),
			QUICKSHIP_VERSION
		);

		wp_enqueue_script(
			'quickship-admin',
			QUICKSHIP_URL . 'assets/js/admin.js',
			array( 'wp-color-picker', 'jquery' ),
			QUICKSHIP_VERSION,
			true
		);

		$tz  = wp_timezone();
		$now = new \DateTime( 'now', $tz );

		wp_localize_script(
			'quickship-admin',
			'quickshipAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'saveNonce'     => wp_create_nonce( 'quickship_save_settings' ),
				'restoreNonce'  => wp_create_nonce( 'quickship_restore_defaults' ),
				'savedText'     => __( 'Settings saved.', 'quickship-delivery-date' ),
				'restoredText'  => __( 'Defaults restored.', 'quickship-delivery-date' ),
				'errorText'     => __( 'Could not save. Please try again.', 'quickship-delivery-date' ),
				'confirmText'   => __( 'Reset all settings to their default values?', 'quickship-delivery-date' ),
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
		check_ajax_referer( 'quickship_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';

		switch ( $tab ) {
			case 'delivery':
				update_option( 'quickship_enabled', $this->sanitize_checkbox( $_POST['quickship_enabled'] ?? null ) );
				update_option( 'quickship_min_days', absint( wp_unslash( $_POST['quickship_min_days'] ?? 3 ) ) );
				update_option( 'quickship_max_days', absint( wp_unslash( $_POST['quickship_max_days'] ?? 5 ) ) );
				update_option( 'quickship_cutoff_hour', $this->sanitize_cutoff_hour( wp_unslash( $_POST['quickship_cutoff_hour'] ?? 14 ) ) );
				update_option( 'quickship_cutoff_min', $this->sanitize_cutoff_min( wp_unslash( $_POST['quickship_cutoff_min'] ?? 0 ) ) );
				update_option( 'quickship_exclude_weekends', $this->sanitize_checkbox( $_POST['quickship_exclude_weekends'] ?? null ) );
				update_option( 'quickship_excluded_days', $this->sanitize_excluded_days( $_POST['quickship_excluded_days'] ?? array() ) );
				update_option( 'quickship_holidays', sanitize_textarea_field( wp_unslash( $_POST['quickship_holidays'] ?? '' ) ) );
				break;

			case 'display':
				update_option( 'quickship_show_product', $this->sanitize_checkbox( $_POST['quickship_show_product'] ?? null ) );
				update_option( 'quickship_show_shop', $this->sanitize_checkbox( $_POST['quickship_show_shop'] ?? null ) );
				update_option( 'quickship_show_cart', $this->sanitize_checkbox( $_POST['quickship_show_cart'] ?? null ) );
				update_option( 'quickship_show_checkout', $this->sanitize_checkbox( $_POST['quickship_show_checkout'] ?? null ) );
				update_option( 'quickship_show_countdown', $this->sanitize_checkbox( $_POST['quickship_show_countdown'] ?? null ) );
				break;

			case 'style':
				update_option( 'quickship_text_single', sanitize_text_field( wp_unslash( $_POST['quickship_text_single'] ?? 'Get it by {date}' ) ) );
				update_option( 'quickship_text_range', sanitize_text_field( wp_unslash( $_POST['quickship_text_range'] ?? 'Get it {start} – {end}' ) ) );
				update_option( 'quickship_text_countdown', sanitize_text_field( wp_unslash( $_POST['quickship_text_countdown'] ?? 'Order within {countdown} to get it by {date}' ) ) );
				update_option( 'quickship_date_format', sanitize_text_field( wp_unslash( $_POST['quickship_date_format'] ?? 'D, M j' ) ) );
				update_option( 'quickship_icon', $this->sanitize_icon( $_POST['quickship_icon'] ?? 'truck' ) );
				update_option( 'quickship_text_color', sanitize_hex_color( wp_unslash( $_POST['quickship_text_color'] ?? '#16a34a' ) ) ?: '#16a34a' );
				update_option( 'quickship_bg_color', $this->sanitize_maybe_color( wp_unslash( $_POST['quickship_bg_color'] ?? '' ) ) );
				break;

			default:
				wp_send_json_error( array( 'message' => 'unknown_tab' ) );
		}

		wp_send_json_success( array( 'message' => 'saved' ) );
	}

	/**
	 * AJAX handler: reset all settings to factory defaults.
	 *
	 * @return void
	 */
	public function ajax_restore_defaults(): void {
		check_ajax_referer( 'quickship_restore_defaults', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		quickship_repair_options( true ); // force = true resets everything.

		// Clear the one-time repair flag so the auto-repair can run again if needed.
		delete_option( 'quickship_db_repaired_v1' );

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
	 * Sanitize cutoff minute (0, 15, 30, or 45).
	 *
	 * @param  mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_cutoff_min( $value ): int {
		return in_array( (int) $value, array( 0, 15, 30, 45 ), true ) ? (int) $value : 0;
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
		add_settings_section( $id, $title, '__return_false', 'quickship-' . $tab );
	}

	/**
	 * Shorthand to add a settings field bound to a section on a tab page.
	 *
	 * @param  string   $id       Option name / field ID.
	 * @param  string   $title    Field label.
	 * @param  string   $callback Method name on this class.
	 * @param  string   $tab      Tab slug.
	 * @param  string   $section  Section ID.
	 * @param  array    $args     Arguments passed to the callback.
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
		add_settings_field(
			$id,
			$title,
			array( $this, $callback ),
			'quickship-' . $tab,
			$section,
			$args
		);
	}
}
