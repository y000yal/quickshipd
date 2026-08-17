<?php
/**
 * Help tab markup. Required from QuickShipD_Admin::render_page(), so it is only
 * ever parsed when the settings page is actually rendered.
 *
 * @package QuickShipD
 * @since   1.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qs_help_nav = array(
	'qs-help-start'     => __( 'Quick start', 'quickshipd' ),
	'qs-help-dates'     => __( 'How dates work', 'quickshipd' ),
	'qs-help-where'     => __( 'Where it shows', 'quickshipd' ),
	'qs-help-shortcode' => __( 'Shortcode', 'quickshipd' ),
	'qs-help-overrides' => __( 'Overrides', 'quickshipd' ),
	'qs-help-faq'       => __( 'Troubleshooting', 'quickshipd' ),
);
?>

<div class="qs-help">

	<!-- Hero -->
	<div class="qs-help-hero">
		<div class="qs-help-hero__main">
			<h2 class="qs-help-hero__title"><?php esc_html_e( 'Everything you can do with QuickShipD', 'quickshipd' ); ?></h2>
			<p class="qs-help-hero__text">
				<?php esc_html_e( 'QuickShipD works out when an order will arrive and shows it to the customer before they buy. Nothing here needs installing, it is all live already.', 'quickshipd' ); ?>
			</p>
		</div>
		<nav class="qs-help-nav" aria-label="<?php esc_attr_e( 'Help sections', 'quickshipd' ); ?>">
			<?php foreach ( $qs_help_nav as $qs_anchor => $qs_label ) : ?>
				<a class="qs-help-nav__pill" href="#<?php echo esc_attr( $qs_anchor ); ?>"><?php echo esc_html( $qs_label ); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>

	<!-- Quick start -->
	<section class="qs-card" id="qs-help-start">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<?php esc_html_e( 'Quick start', 'quickshipd' ); ?>
		</h3>

		<ol class="qs-steps">
			<li>
				<h4><?php esc_html_e( 'Set your delivery window', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'On the Delivery tab, enter the minimum and maximum days a parcel takes to arrive after you hand it to the carrier. Set both to the same number for a single date instead of a range. Use 0 for same-day delivery.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<h4><?php esc_html_e( 'Set your order cutoff', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'Orders placed before this time go out the same day. Anything after it moves to the next dispatch day.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<h4><?php esc_html_e( 'Tick your non-dispatch days', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'The weekdays you do not send orders out, usually Saturday and Sunday. They are never counted toward the estimate.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<h4><?php esc_html_e( 'Add your holidays', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'Single dates or date ranges you are closed. Tick "Repeats every year" for fixed holidays such as Christmas.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<h4><?php esc_html_e( 'Choose where it appears', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'The Display tab controls product pages, shop archives, cart, and checkout. The Style tab controls wording and colours. The live preview updates as you type.', 'quickshipd' ); ?></p>
			</li>
		</ol>
	</section>

	<!-- How dates are worked out -->
	<section class="qs-card" id="qs-help-dates">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</span>
			<?php esc_html_e( 'How the date is worked out', 'quickshipd' ); ?>
		</h3>

		<p class="qs-card__lead"><?php esc_html_e( 'QuickShipD keeps the day you dispatch separate from the days a parcel spends in transit.', 'quickshipd' ); ?></p>

		<div class="qs-flow">
			<div class="qs-flow__step">
				<span class="qs-flow__num">1</span>
				<h4><?php esc_html_e( 'Start from today', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'If the cutoff time has already passed, move to tomorrow.', 'quickshipd' ); ?></p>
			</div>
			<div class="qs-flow__step">
				<span class="qs-flow__num">2</span>
				<h4><?php esc_html_e( 'Find the dispatch day', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'If that day is a non-dispatch day or a holiday, roll forward until it is not.', 'quickshipd' ); ?></p>
			</div>
			<div class="qs-flow__step">
				<span class="qs-flow__num">3</span>
				<h4><?php esc_html_e( 'Count the transit days', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'Count forward from the dispatch day, skipping non-dispatch days and holidays.', 'quickshipd' ); ?></p>
			</div>
		</div>

		<div class="qs-callout">
			<span class="qs-callout__label"><?php esc_html_e( 'Worked example', 'quickshipd' ); ?></span>
			<p><?php esc_html_e( 'Weekends off, delivery 1 to 2 days, a customer orders on Saturday. Nothing ships at the weekend, so the dispatch day rolls to Monday. One to two days after Monday gives Tuesday to Wednesday. Monday is never shown as the arrival date, because that is the day the parcel leaves you.', 'quickshipd' ); ?></p>
		</div>

		<p class="qs-note"><?php esc_html_e( 'The countdown is hidden on days you do not dispatch, because beating the cutoff on those days cannot change the date.', 'quickshipd' ); ?></p>
	</section>

	<!-- Where it shows -->
	<section class="qs-card" id="qs-help-where">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><rect x="2" y="3" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</span>
			<?php esc_html_e( 'Where the estimate shows', 'quickshipd' ); ?>
		</h3>

		<div class="qs-grid">
			<div class="qs-tile">
				<h4><?php esc_html_e( 'Product page', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'Full estimate with icon and the cutoff countdown. Variable products update as the shopper picks a variation.', 'quickshipd' ); ?></p>
			</div>
			<div class="qs-tile">
				<h4><?php esc_html_e( 'Shop and archives', 'quickshipd' ); ?> <span class="qs-tag"><?php esc_html_e( 'off by default', 'quickshipd' ); ?></span></h4>
				<p><?php esc_html_e( 'Compact estimate, no countdown.', 'quickshipd' ); ?></p>
			</div>
			<div class="qs-tile">
				<h4><?php esc_html_e( 'Cart', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'An "Est. Delivery" line under each item. Works on the classic and the block cart.', 'quickshipd' ); ?></p>
			</div>
			<div class="qs-tile">
				<h4><?php esc_html_e( 'Checkout', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'A summary row above shipping on the classic checkout. On the block checkout the per-item line shows instead.', 'quickshipd' ); ?></p>
			</div>
			<div class="qs-tile">
				<h4><?php esc_html_e( 'Emails and orders', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'Saved onto the order line at checkout, so it stays fixed on the order record in emails, admin orders, and My Account.', 'quickshipd' ); ?></p>
			</div>
		</div>
	</section>

	<!-- Shortcode -->
	<section class="qs-card" id="qs-help-shortcode">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><path d="M8 6l-6 6 6 6M16 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<?php esc_html_e( 'Shortcode', 'quickshipd' ); ?>
		</h3>

		<p class="qs-card__lead">
			<?php esc_html_e( 'Place the estimate yourself anywhere on the site. This is the answer whenever a page builder such as Elementor or Divi builds its own product layout, never fires the standard WooCommerce hooks, and the estimate does not appear on its own.', 'quickshipd' ); ?>
		</p>

		<div class="qs-code">
			<code>[quickshipd]</code>
			<button type="button" class="qs-copy" data-copy="[quickshipd]"><?php esc_html_e( 'Copy', 'quickshipd' ); ?></button>
		</div>

		<h4 class="qs-subhead"><?php esc_html_e( 'Attributes', 'quickshipd' ); ?></h4>

		<table class="qs-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Attribute', 'quickshipd' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Default', 'quickshipd' ); ?></th>
					<th scope="col"><?php esc_html_e( 'What it does', 'quickshipd' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>product_id</code></td>
					<td><span class="qs-muted"><?php esc_html_e( 'current product', 'quickshipd' ); ?></span></td>
					<td><?php esc_html_e( 'Which product to estimate for. Leave it out on a product page or inside a product loop and the current product is used. Required anywhere else.', 'quickshipd' ); ?></td>
				</tr>
				<tr>
					<td><code>context</code></td>
					<td><code>product</code></td>
					<td><?php esc_html_e( 'Which style to use. "product" gives the full estimate with countdown, "shop" gives the compact one without.', 'quickshipd' ); ?></td>
				</tr>
			</tbody>
		</table>

		<h4 class="qs-subhead"><?php esc_html_e( 'Examples', 'quickshipd' ); ?></h4>

		<ul class="qs-examples">
			<li>
				<div class="qs-code qs-code--sm">
					<code>[quickshipd]</code>
					<button type="button" class="qs-copy" data-copy="[quickshipd]"><?php esc_html_e( 'Copy', 'quickshipd' ); ?></button>
				</div>
				<p><?php esc_html_e( 'On a product page or in an Elementor product template. Picks up the product on its own.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<div class="qs-code qs-code--sm">
					<code>[quickshipd product_id="123"]</code>
					<button type="button" class="qs-copy" data-copy='[quickshipd product_id="123"]'><?php esc_html_e( 'Copy', 'quickshipd' ); ?></button>
				</div>
				<p><?php esc_html_e( 'On a landing page, a widget, or any page that is not a product.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<div class="qs-code qs-code--sm">
					<code>[quickshipd context="shop"]</code>
					<button type="button" class="qs-copy" data-copy='[quickshipd context="shop"]'><?php esc_html_e( 'Copy', 'quickshipd' ); ?></button>
				</div>
				<p><?php esc_html_e( 'Compact style with no countdown, for tight layouts and product grids.', 'quickshipd' ); ?></p>
			</li>
		</ul>

		<p class="qs-note"><?php esc_html_e( 'The shortcode respects every setting on the other tabs, including per-product overrides. It outputs nothing for a product that is out of stock or has QuickShipD switched off.', 'quickshipd' ); ?></p>
	</section>

	<!-- Placeholders -->
	<section class="qs-card" id="qs-help-placeholders">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><path d="M4 7V5h16v2M9 20h6M12 5v15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<?php esc_html_e( 'Text placeholders', 'quickshipd' ); ?>
		</h3>

		<p class="qs-card__lead"><?php esc_html_e( 'Drop these into the message templates on the Style tab.', 'quickshipd' ); ?></p>

		<div class="qs-chips">
			<div class="qs-chip">
				<code>{date}</code>
				<span><?php esc_html_e( 'The single or latest delivery date', 'quickshipd' ); ?></span>
			</div>
			<div class="qs-chip">
				<code>{start}</code>
				<span><?php esc_html_e( 'The earliest date in a range', 'quickshipd' ); ?></span>
			</div>
			<div class="qs-chip">
				<code>{end}</code>
				<span><?php esc_html_e( 'The latest date in a range', 'quickshipd' ); ?></span>
			</div>
			<div class="qs-chip">
				<code>{countdown}</code>
				<span><?php esc_html_e( 'Time left before the order cutoff', 'quickshipd' ); ?></span>
			</div>
		</div>
	</section>

	<!-- Overrides -->
	<section class="qs-card" id="qs-help-overrides">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</span>
			<?php esc_html_e( 'Overrides', 'quickshipd' ); ?>
		</h3>

		<p class="qs-card__lead"><?php esc_html_e( 'The Delivery tab sets the store default. Two things can override it, in this order.', 'quickshipd' ); ?></p>

		<div class="qs-grid qs-grid--2">
			<div class="qs-tile">
				<h4><?php esc_html_e( '1. Per product', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'Edit a product, open the Shipping tab in Product data, and set its own minimum and maximum days. You can also switch QuickShipD off for that product entirely.', 'quickshipd' ); ?></p>
			</div>
			<div class="qs-tile">
				<h4><?php esc_html_e( '2. Per shipping method', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'In WooCommerce, Settings, Shipping, open a zone and edit a method. Each method has its own minimum and maximum, so express can quote a faster date than standard. Leave blank to use the store default.', 'quickshipd' ); ?></p>
			</div>
		</div>
	</section>

	<!-- Troubleshooting -->
	<section class="qs-card" id="qs-help-faq">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M9.1 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<?php esc_html_e( 'Troubleshooting', 'quickshipd' ); ?>
		</h3>

		<details class="qs-faq">
			<summary><?php esc_html_e( 'It does not show on my product page', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'Check the product is in stock and that QuickShipD is not switched off on it. If the page is built with Elementor, Divi, or another builder, the theme never fires the WooCommerce hook, so drop the [quickshipd] shortcode into the layout where you want it.', 'quickshipd' ); ?></p>
		</details>

		<details class="qs-faq">
			<summary><?php esc_html_e( 'The dates on my site do not match the preview', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'Both use your WordPress timezone from Settings, General. If they still differ, a page cache is most likely serving an older copy of the page. Clear it and check again.', 'quickshipd' ); ?></p>
		</details>

		<details class="qs-faq">
			<summary><?php esc_html_e( 'The countdown is missing', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'The countdown only appears on product pages, only when it is switched on, and only before the cutoff on a day you actually dispatch. After the cutoff there is nothing left to count down to.', 'quickshipd' ); ?></p>
		</details>

		<details class="qs-faq">
			<summary><?php esc_html_e( 'Changing shipping method did not change the date', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'Method overrides only apply at cart and checkout, where a method has actually been chosen. Product pages always use the store default, since no method is selected yet.', 'quickshipd' ); ?></p>
		</details>
	</section>

	<!-- Good to know -->
	<section class="qs-card qs-card--plain" id="qs-help-notes">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 16v-5M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</span>
			<?php esc_html_e( 'Good to know', 'quickshipd' ); ?>
		</h3>

		<ul class="qs-ticks">
			<li><?php esc_html_e( 'Dates are worked out in PHP on page load. No external services are contacted and no customer data leaves your site.', 'quickshipd' ); ?></li>
			<li><?php esc_html_e( 'Frontend CSS and JavaScript load only on pages that actually show an estimate.', 'quickshipd' ); ?></li>
			<li><?php esc_html_e( 'Compatible with HPOS and the block cart and checkout.', 'quickshipd' ); ?></li>
			<li><?php esc_html_e( 'Restore Defaults at the bottom of this page resets every setting, including holidays and non-dispatch days.', 'quickshipd' ); ?></li>
		</ul>
	</section>

</div>
