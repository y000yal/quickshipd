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
	'qs-help-start'        => __( 'Quick start', 'quickshipd' ),
	'qs-help-dates'        => __( 'How dates work', 'quickshipd' ),
	'qs-help-sameday'      => __( 'Same-day delivery', 'quickshipd' ),
	'qs-help-where'        => __( 'Where it shows', 'quickshipd' ),
	'qs-help-shortcode'    => __( 'Shortcode', 'quickshipd' ),
	'qs-help-placeholders' => __( 'Text placeholders', 'quickshipd' ),
	'qs-help-overrides'    => __( 'Overrides', 'quickshipd' ),
	'qs-help-faq'          => __( 'Troubleshooting', 'quickshipd' ),
	'qs-help-filters'      => __( 'For developers', 'quickshipd' ),
	'qs-help-notes'        => __( 'Good to know', 'quickshipd' ),
);

/**
 * Print a copy-to-clipboard button for a snippet.
 *
 * @param string $qs_snippet Text placed on the clipboard.
 */
$qs_copy_button = static function ( string $qs_snippet ): void {
	printf(
		'<button type="button" class="qs-copy" data-copy="%1$s" aria-label="%2$s" title="%2$s">'
		. '<svg class="qs-copy__idle" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
		. '<svg class="qs-copy__done" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
		. '</button>',
		esc_attr( $qs_snippet ),
		esc_attr__( 'Copy to clipboard', 'quickshipd' )
	);
};
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
	</div>

	<div class="qs-help-body">

		<nav class="qs-help-side" aria-label="<?php esc_attr_e( 'Help sections', 'quickshipd' ); ?>">
			<?php foreach ( $qs_help_nav as $qs_anchor => $qs_label ) : ?>
				<a class="qs-help-side__item" href="#<?php echo esc_attr( $qs_anchor ); ?>" data-help-target="<?php echo esc_attr( $qs_anchor ); ?>"><?php echo esc_html( $qs_label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="qs-help-content">

	<!-- Quick start -->
	<section class="qs-card qs-help-panel" id="qs-help-start">
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
	<section class="qs-card qs-help-panel" id="qs-help-dates">
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

	<!-- Same-day dispatch and delivery -->
	<section class="qs-card qs-help-panel" id="qs-help-sameday">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<?php esc_html_e( 'Same-day dispatch and delivery', 'quickshipd' ); ?>
		</h3>

		<p class="qs-card__lead"><?php esc_html_e( 'Delivery days are counted after dispatch, so 0 days means the parcel arrives on the day it goes out. Set the minimum to 0 and the cutoff becomes your same-day deadline.', 'quickshipd' ); ?></p>

		<h4 class="qs-subhead"><?php esc_html_e( 'Pick your numbers', 'quickshipd' ); ?></h4>

		<table class="qs-table qs-table--attrs">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'What you promise', 'quickshipd' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Minimum', 'quickshipd' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Maximum', 'quickshipd' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Always same day', 'quickshipd' ); ?></td>
					<td><code>0</code></td>
					<td><code>0</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Same day, sometimes next day', 'quickshipd' ); ?></td>
					<td><code>0</code></td>
					<td><code>1</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Next day at the earliest', 'quickshipd' ); ?></td>
					<td><code>1</code></td>
					<td><code>2</code></td>
				</tr>
			</tbody>
		</table>

		<h4 class="qs-subhead"><?php esc_html_e( 'Set it up', 'quickshipd' ); ?></h4>

		<ol class="qs-steps">
			<li>
				<h4><?php esc_html_e( 'Set minimum delivery days to 0', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'On the Delivery tab. Set the maximum to 0 as well for a single date, or to 1 if a late order might slip to tomorrow.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<h4><?php esc_html_e( 'Set the cutoff to your last dispatch run', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'This is the moment same-day stops being possible. If your courier collects at 11am, set 11:00. Orders after it automatically move to the next dispatch day, so you never promise a delivery you cannot make.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<h4><?php esc_html_e( 'Match the non-dispatch days to your opening days', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'Delivering seven days a week? Untick every day. Closed at weekends? Leave Saturday and Sunday ticked, and weekend orders will quote the next working day instead.', 'quickshipd' ); ?></p>
			</li>
			<li>
				<h4><?php esc_html_e( 'Say so in the wording', 'quickshipd' ); ?></h4>
				<p><?php esc_html_e( 'On the Style tab, a countdown line such as "Order within {countdown} for delivery today" turns the cutoff into a reason to buy now. Keep the countdown switched on for this.', 'quickshipd' ); ?></p>
			</li>
		</ol>

		<div class="qs-callout">
			<span class="qs-callout__label"><?php esc_html_e( 'Worked example', 'quickshipd' ); ?></span>
			<p><?php esc_html_e( 'Minimum 0, maximum 0, cutoff 11:00, closed at weekends. An order at 10:30 on Monday shows Monday, with 30 minutes left on the countdown. At 11:30 the same order shows Tuesday and the countdown disappears. An order on Saturday shows Monday, because nothing is dispatched at the weekend.', 'quickshipd' ); ?></p>
		</div>

		<p class="qs-note"><?php esc_html_e( 'Same-day can also be set for one product only, or for one shipping method such as local delivery, using the overrides described in the Overrides section.', 'quickshipd' ); ?></p>
	</section>

	<!-- Where it shows -->
	<section class="qs-card qs-help-panel" id="qs-help-where">
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
				<p><?php esc_html_e( 'Saved onto the order line at checkout, so it stays fixed on the order record. Display > Orders & Emails controls whether it is saved at all, and which emails it appears in.', 'quickshipd' ); ?></p>
			</div>
		</div>
	</section>

	<!-- Shortcode -->
	<section class="qs-card qs-help-panel" id="qs-help-shortcode">
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
			<?php $qs_copy_button( '[quickshipd]' ); ?>
		</div>

		<h4 class="qs-subhead"><?php esc_html_e( 'Attributes', 'quickshipd' ); ?></h4>

		<table class="qs-table qs-table--attrs">
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

		<h4 class="qs-subhead"><?php esc_html_e( 'Where each one works', 'quickshipd' ); ?></h4>

		<div class="qs-callout qs-callout--warn">
			<span class="qs-callout__label"><?php esc_html_e( 'Read this first', 'quickshipd' ); ?></span>
			<p><?php esc_html_e( 'Without product_id, the shortcode needs a product in context. It works on a product page, in an Elementor or Divi product template, and inside a product loop. On a normal page, a post, a footer, or a text widget there is no product to read, so it prints nothing. Pass product_id there.', 'quickshipd' ); ?></p>
		</div>

		<table class="qs-table qs-table--shortcodes">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Shortcode', 'quickshipd' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Works here', 'quickshipd' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Prints nothing here', 'quickshipd' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<div class="qs-code qs-code--sm">
							<code>[quickshipd]</code>
							<?php $qs_copy_button( '[quickshipd]' ); ?>
						</div>
					</td>
					<td><?php esc_html_e( 'Single product pages, page builder product templates, and product loops such as the shop, a category, or a products block.', 'quickshipd' ); ?></td>
					<td><?php esc_html_e( 'Normal pages and posts, widgets, headers and footers.', 'quickshipd' ); ?></td>
				</tr>
				<tr>
					<td>
						<div class="qs-code qs-code--sm">
							<code>[quickshipd context="shop"]</code>
							<?php $qs_copy_button( '[quickshipd context="shop"]' ); ?>
						</div>
					</td>
					<td><?php esc_html_e( 'Same places as above. Only the styling changes: compact, and no countdown.', 'quickshipd' ); ?></td>
					<td><?php esc_html_e( 'Same as above. context does not supply a product, so add product_id off a product page.', 'quickshipd' ); ?></td>
				</tr>
				<tr>
					<td>
						<div class="qs-code qs-code--sm">
							<code>[quickshipd product_id="123"]</code>
							<?php $qs_copy_button( '[quickshipd product_id="123"]' ); ?>
						</div>
					</td>
					<td><?php esc_html_e( 'Anywhere at all, including normal pages, posts, and widgets. Replace 123 with the product id.', 'quickshipd' ); ?></td>
					<td><?php esc_html_e( 'Only if that id is not a product, or the product is out of stock.', 'quickshipd' ); ?></td>
				</tr>
				<tr>
					<td>
						<div class="qs-code qs-code--sm">
							<code>[quickshipd product_id="123" context="shop"]</code>
							<?php $qs_copy_button( '[quickshipd product_id="123" context="shop"]' ); ?>
						</div>
					</td>
					<td><?php esc_html_e( 'Anywhere, in the compact style. Useful in sidebars and tight layouts.', 'quickshipd' ); ?></td>
					<td><?php esc_html_e( 'Same as the row above.', 'quickshipd' ); ?></td>
				</tr>
			</tbody>
		</table>

		<p class="qs-note"><?php esc_html_e( 'A product id is the number in the URL when you edit a product, for example post=123. The shortcode respects every setting on the other tabs, including per-product overrides, and prints nothing for a product that is out of stock or has QuickShipD switched off.', 'quickshipd' ); ?></p>
	</section>

	<!-- Placeholders -->
	<section class="qs-card qs-help-panel" id="qs-help-placeholders">
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
	<section class="qs-card qs-help-panel" id="qs-help-overrides">
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
	<section class="qs-card qs-help-panel" id="qs-help-faq">
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

		<details class="qs-faq">
			<summary><?php esc_html_e( 'My estimates moved a day later after updating', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'That is the dispatch day fix in 1.0.5 and it is working as intended. Before it, an order arriving on a day you do not dispatch counted your next working day as a delivery day, which quoted a date you could not meet. Counting now starts after dispatch. If you want the old, shorter quote back, lower the minimum delivery days by one.', 'quickshipd' ); ?></p>
		</details>

		<details class="qs-faq">
			<summary><?php esc_html_e( 'It is printing on my packing slips or PDF invoices', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'PDF invoice and packing slip plugins read the order line meta directly, so unticking an email does not remove it from a printed slip. Switch off "Save on the order" under Display, Orders & Emails to stop storing it altogether, or use the quickshipd_save_order_item_date filter to skip it for specific orders. Existing orders keep whatever they were given at checkout.', 'quickshipd' ); ?></p>
		</details>

		<details class="qs-faq">
			<summary><?php esc_html_e( 'The estimate is missing from order emails', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'Check Display, Orders & Emails. "Save on the order" must be on, and the email in question must still be ticked. Only orders placed while the setting was on carry the estimate, so older orders will not gain one retroactively.', 'quickshipd' ); ?></p>
		</details>

		<details class="qs-faq">
			<summary><?php esc_html_e( 'A holiday is not being skipped', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'Add the whole period as one range rather than a line per day, and tick "Repeats every year" if you close on the same dates annually. A range has to stay inside one calendar year, so a Christmas to New Year closure needs two entries. The live preview reflects holidays immediately, so use it to confirm the dates land where you expect.', 'quickshipd' ); ?></p>
		</details>

		<details class="qs-faq">
			<summary><?php esc_html_e( 'The plugin is not in my language', 'quickshipd' ); ?></summary>
			<p><?php esc_html_e( 'Every string is translatable and translations are hosted on translate.wordpress.org. If your language is incomplete, you can submit strings there and WordPress will pull them in automatically once approved. Anything you type yourself, such as the message templates on the Style tab, is shown exactly as you wrote it and is never translated.', 'quickshipd' ); ?></p>
		</details>
	</section>

	<!-- Developer filters -->
	<section class="qs-card qs-help-panel" id="qs-help-filters">
		<h3 class="qs-card__title">
			<span class="qs-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none"><path d="M8 6l-6 6 6 6M16 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<?php esc_html_e( 'For developers', 'quickshipd' ); ?>
		</h3>

		<p class="qs-card__lead"><?php esc_html_e( 'Two filters cover the order record, for cases the settings above do not.', 'quickshipd' ); ?></p>

		<table class="qs-table qs-table--filters">
			<tbody>
				<tr>
					<td><code>quickshipd_save_order_item_date</code></td>
					<td><?php esc_html_e( 'Return false to keep the estimate off an order line entirely. Receives the line item and the cart item data, so it can be decided per product or per order.', 'quickshipd' ); ?></td>
				</tr>
				<tr>
					<td><code>quickshipd_show_in_email</code></td>
					<td><?php esc_html_e( 'Return false to hide the estimate in one email. Receives the WooCommerce email id, for example customer_processing_order.', 'quickshipd' ); ?></td>
				</tr>
			</tbody>
		</table>
	</section>

	<!-- Good to know -->
	<section class="qs-card qs-card--plain qs-help-panel" id="qs-help-notes">
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

	</div><!-- /.qs-help-content -->
	</div><!-- /.qs-help-body -->

</div>
