=== QuickShip — Estimated Delivery Date for WooCommerce ===
Contributors: quickship
Tags: woocommerce, delivery date, estimated delivery, shipping date, countdown
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 7.0
WC tested up to: 10.0
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Estimated delivery dates for WooCommerce (product, cart, checkout): shipping-aware ranges, per-product overrides, optional countdown. **Requires WooCommerce.**

== Description ==

**Your customers leave when they don't know when their order will arrive.**

Multiple studies confirm it: displaying an estimated delivery date increases add-to-cart rates by 17–33%. QuickShip puts that date front and centre — on your product pages, cart, and checkout — in a clean, fast, Amazon-style format.

**"Get it by Wed, Apr 15"** — that's the kind of clear, single-line promise your customers want to see.

= Why QuickShip? =

Every free alternative makes you pay for the features that actually matter. WPC Estimated Delivery Date locks shipping-method awareness behind a $29/year plan. PI Web Solution's plugin puts per-product overrides behind a $34–69 paywall. QuickShip gives you all of that — **free, forever**.

= Free tier features =

* **Amazon-style delivery dates** — "Get it by Thu, Apr 17" or "Get it Mon, Apr 14 – Wed, Apr 16"
* **Countdown timer** — "Order within 3h 34m to get it by Thu" with live JS ticking
* **Shipping method–aware dates** — assign min/max days per shipping zone method (flat rate, free shipping, local pickup, custom methods). **Customers see the right date when they switch methods at checkout.**
* **Per-product overrides** — set different min/max days per product, or disable the estimate entirely for specific products
* **Recurring holiday support** — mark XXXX-MM-DD for yearly events like Christmas, New Year's Day
* **Custom excluded weekdays** — runs on Saturdays but not Sundays? Sorted.
* **Order cutoff time** — set a daily cutoff (e.g. 2 PM); orders after that count as next-day
* **Configurable everywhere** — product pages, shop archives, cart, checkout
* **Localized date names** — uses WordPress date_i18n() so "Thu" appears in your store's language
* **Zero jQuery on the frontend** — vanilla JS only, under 1.5 KB minified
* **Ultra-light CSS** — under 0.5 KB, fully theme-agnostic
* **2-minute setup** — activate → set your days → done. No onboarding wizard.
* **HPOS compatible** — works with WooCommerce High-Performance Order Storage
* **Block checkout compatible** — does not break the Gutenberg checkout block

= How it works =

1. Install and activate **WooCommerce**, then install and activate **QuickShip**
2. Go to **WooCommerce → QuickShip**
3. Set your minimum and maximum delivery days and your daily order cutoff time
4. Optionally mark any non-delivery days (weekends, holidays, specific weekdays)
5. Done — the delivery estimate appears immediately on your store

= Shipping method integration (free) =

In **WooCommerce → Settings → Shipping**, each shipping method instance now has two new fields: "QuickShip: Min delivery days" and "QuickShip: Max delivery days". Set these on your Express (1–2 days) and Standard (5–7 days) methods. When a customer switches between methods at checkout, the delivery estimate updates automatically.

= Supported locations =

* Single product pages
* Shop and archive pages (optional, off by default)
* Cart page (per-item delivery estimate as a cart item data row)
* Checkout page (combined estimate for the full order, updates on shipping method change)

= Variable products =

For variable products, the delivery date is hidden until a variation is selected, then updated via a lightweight AJAX call. Each variation can have its own min/max days.

= Pro version =

The Pro version (coming soon) adds:

* Time-slot booking (morning / afternoon / evening delivery windows)
* Multi-zone scheduling (different cutoff times per shipping zone)
* Blackout date ranges (not just single dates)
* Wording A/B testing
* Priority email support

**Pro upsells appear only in the settings page footer — never on the frontend, never as dismissible admin notices.**

= Developer notes =

* All calculation logic lives in `QuickShip_Calculator` — a pure class with no WordPress I/O, fully unit-testable
* REST endpoint at `/wp-json/quickship/v1/date` for headless / external integrations
* Full PHPDoc coverage
* Follows WordPress Coding Standards (WPCS)

== Installation ==

1. Install and activate **WooCommerce** (this plugin depends on it). If WooCommerce is not installed, WordPress will prompt you to install it when you activate QuickShip.
2. Install **QuickShip**: upload the `quickship-delivery-date` folder to `/wp-content/plugins/`, or install from the plugin zip via **Plugins → Add New → Upload Plugin**.
3. Activate **QuickShip** through the **Plugins** screen.
4. Go to **WooCommerce → QuickShip** and set your min/max delivery days, cutoff time, and any holidays or excluded weekdays.

== Frequently Asked Questions ==

= Does QuickShip work without WooCommerce? =

No. QuickShip is built only for WooCommerce. It declares WooCommerce as a required plugin (WordPress 6.5+). If WooCommerce is inactive, QuickShip shows an admin notice with a link to install WooCommerce and does not load its storefront features.

= Does QuickShip work with WooCommerce Blocks (block-based checkout)? =

Yes — QuickShip does not break the block-based checkout. Full block checkout integration (rendering the estimate inside the block) is on the roadmap for v1.1.

= Does it slow down my store? =

No. The delivery date is calculated in PHP on page load — no external API calls, no database queries beyond standard wp_options reads. Frontend assets (CSS + JS) are under 2 KB combined and are only loaded on pages where the delivery estimate is shown.

= Can I show a single date instead of a range? =

Yes — set Min delivery days and Max delivery days to the same value in **WooCommerce → QuickShip → Delivery**. The plugin automatically switches to the single-date template ("Get it by {date}").

= How do I add public holidays? =

Go to **WooCommerce → QuickShip → Delivery → Holidays**. Enter one date per line. Use `YYYY-MM-DD` for a one-off date or `XXXX-MM-DD` for a date that recurs every year. Lines starting with `#` are treated as comments.

= Can I customise the wording? =

Yes — go to **WooCommerce → QuickShip → Style → Text Templates**. Three templates are available: single-date text, date-range text, and countdown text. Each supports simple placeholders: `{date}`, `{start}`, `{end}`, `{countdown}`.

= Can I disable the estimate for a specific product? =

Yes — open the product in the WooCommerce product editor, go to the **Shipping** tab, and check "Disable delivery estimate for this product". You can also set per-product min/max days in the same tab.

= Does it support variable products? =

Yes. The delivery date is hidden until a variation is selected; then a lightweight AJAX request fetches the correct estimate for that variation.

= Does the countdown use JavaScript? =

Yes — a small vanilla JS script (no jQuery) ticks the countdown every second and hides it when it reaches zero. If JavaScript is disabled, the static delivery date text remains visible; only the countdown is affected.

= Will QuickShip add nag banners or notices asking me to upgrade? =

Never. Pro upsells are shown only in the footer of the QuickShip settings page. There are no dashboard notices, no frontend banners, and no dismissible nag screens.

= Is it GDPR/CCPA compliant? =

Yes. QuickShip makes no external HTTP requests, stores no personal data, and sets no cookies.

= I changed the timezone in WordPress settings but dates are wrong. =

QuickShip reads the WordPress timezone setting (Settings → General) and uses it for all calculations. After changing your timezone, you may need to clear any page-caching plugins.

= Does it work with multi-currency / multi-language plugins? =

The plugin uses WordPress's `date_i18n()` function, so day and month names are automatically translated to your active locale. It is compatible with WPML, Polylang, and similar translation plugins.

== Screenshots ==

1. **Product page** — Amazon-style delivery estimate with countdown timer below the Add to Cart button.
2. **Cart page** — per-item estimated delivery dates displayed as a cart item data row.
3. **Admin settings — Delivery tab** — min/max days, cutoff time, excluded weekdays, and holiday management.
4. **Shipping method integration** — QuickShip min/max day fields added to a flat rate shipping method instance inside WooCommerce Shipping Zones.

== Changelog ==

= 1.0.0 =
* Initial release.
* Declares **WooCommerce** as a required plugin (`Requires Plugins: woocommerce`). Requires **WordPress 6.5+** so the dependency UI is available.
* Product page, cart, and checkout display.
* Countdown timer (vanilla JS, no jQuery).
* Shipping method–aware delivery dates (free tier differentiator).
* Per-product min/max days and disable flag.
* Recurring yearly holiday support.
* Custom excluded weekdays.
* Order cutoff time with hour and minute precision.
* Live admin preview.
* Full REST API endpoint.
* HPOS and block checkout compatibility declarations.
* WordPress.org–compliant: no tracking, no external requests, GPLv3 or later.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade needed.
