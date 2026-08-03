=== QuickShipD — Estimated Delivery Date for WooCommerce ===
Contributors: y0000el
Tags: estimated delivery date, delivery date, estimated delivery, delivery time, order cutoff
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 7.0.2
WC tested up to: 10.9.4
Stable tag: 1.0.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Estimated delivery date plugin for WooCommerce. Show delivery dates, delivery time & cutoff countdown on product, cart & checkout.

== Description ==

https://youtu.be/bjN8RIzE6SY

Add delivery dates to your WooCommerce store with clear, accurate shipping estimates.

[QuickShipD](https://quickshipd.com/) is a powerful yet lightweight WooCommerce shipping and delivery date plugin that helps you show **estimated delivery dates, shipping timelines, and order cutoffs** directly on your store. Instead of leaving customers guessing, you can display messages like:

* "Get it by Tue, Apr 22"
* "Arrives Apr 21 - Apr 23"
* "Order within 5h 30m 23s for same-day dispatch"

This improves transparency, builds trust, and helps customers make faster buying decisions.

Whether you run a dropshipping store, local delivery business, or standard WooCommerce shop, QuickShipD makes it easy to **add delivery date functionality without coding or complex setup**.

Set your delivery rules once and automatically show accurate shipping estimates across product pages, cart, and checkout.

= Key Features =

= Dynamic Delivery Dates =

Show estimated delivery dates based on your shipping schedule:

* Minimum and maximum delivery days
* Automatic business day calculations
* Clean delivery date ranges

Example:

"Get it Tue, Apr 22 - Wed, Apr 23"

= Shipping-Aware Estimates =

Delivery dates update based on selected shipping methods:

* Standard shipping
* Express shipping
* Variable delivery speeds

Customers instantly see faster delivery options when switching methods.

= Countdown Timer (Order Cutoff) =

Create real urgency with a live timer:

* "Order within Xh Ym Zs"
* Based on your actual cutoff time
* Updates in real time

= Display Across Your Store =

Control where delivery dates appear:

* Product pages
* Cart (per item)
* Checkout

Maintain delivery visibility throughout the buying journey.

= Smart Holidays & Non-Delivery Days =

Handle real-world shipping constraints automatically:

* Exclude weekends or custom non-delivery days
* Add single dates or same-year date ranges with date pickers
* Mark holidays to repeat every year
* Ensure delivery dates remain accurate without manual updates

No need to constantly adjust your schedule - QuickShipD handles it for you.

= Custom Text & Styling =

Match delivery messages to your store design:

* Custom text templates ("Get it by {date}")
* Flexible date formats
* Color and style controls

No coding required.

= How It Works =

QuickShipD uses a simple setup system:

1. Install and activate the plugin
2. Set delivery days and cutoff time
3. Choose where to display delivery dates
4. Save settings

Delivery dates appear instantly across your store.

= Easy Setup & Management =

Manage your delivery settings without complexity:

* Update delivery schedule anytime
* Adjust cutoff times easily
* Enable or disable display per page
* Clean interface focused on quick setup

No technical knowledge required.

= What You Can Do with QuickShipD =

QuickShipD is designed for practical WooCommerce use cases:

* Show Delivery Dates on Product Pages -> help customers decide faster
* Add Urgency with Countdown Timer -> increase conversions
* Display Delivery Info in Cart & Checkout -> maintain trust
* Set Realistic Delivery Ranges -> avoid overpromising
* Automatically Handle Holidays & Non-Working Days -> keep estimates accurate

= Who Is It For? =

QuickShipD is ideal for:

* WooCommerce store owners
* Dropshipping businesses
* Stores with variable delivery times
* Local delivery businesses
* Anyone wanting to improve conversions with better delivery visibility

= Why Delivery Dates Matter =

When customers don't know when their order will arrive, they hesitate.

QuickShipD helps you:

* Reduce cart abandonment
* Build trust with clear expectations
* Improve conversion rates
* Provide a better shopping experience

= Built for WooCommerce =

* Designed specifically for WooCommerce
* Works with product, cart, and checkout pages
* Compatible with modern WooCommerce setups
* Lightweight and performance-friendly

= Plugin Title =

QuickShipD - WooCommerce Delivery Date & Shipping Estimate Plugin

= Video Tutorial =

Watch the QuickShipD setup and walkthrough video:
https://youtu.be/bjN8RIzE6SY

== Installation ==

1. Install and activate **WooCommerce** (this plugin depends on it). If WooCommerce is not installed, WordPress will prompt you to install it when you activate QuickShipD.
2. Install **QuickShipD**: upload the `quickshipd` folder to `/wp-content/plugins/`, or install from the plugin zip via **Plugins → Add New → Upload Plugin**.
3. Activate **QuickShipD** through the **Plugins** screen.
4. Go to **WooCommerce → QuickShipD** and set your min/max delivery days, cutoff time, and any holidays or excluded weekdays.

== Frequently Asked Questions ==

= Does QuickShipD work without WooCommerce? =

No. QuickShipD is built only for WooCommerce. It declares WooCommerce as a required plugin (WordPress 6.5+). If WooCommerce is inactive, QuickShipD shows an admin notice with a link to install WooCommerce and does not load its storefront features.

= Does QuickShipD work with WooCommerce Blocks (block-based checkout)? =

Yes — QuickShipD does not break the block-based checkout. Full block checkout integration (rendering the estimate inside the block) is on the roadmap for v1.1.

= Does it slow down my store? =

No. The delivery date is calculated in PHP on page load — no external API calls, no database queries beyond standard wp_options reads. Frontend assets (CSS + JS) are under 2 KB combined and are only loaded on pages where the delivery estimate is shown.

= Can I show a single date instead of a range? =

Yes — set Min delivery days and Max delivery days to the same value in **WooCommerce → QuickShipD → Delivery**. The plugin automatically switches to the single-date template ("Get it by {date}").

= How do I add public holidays? =

Go to **WooCommerce → QuickShipD → Delivery → Holidays**. Add a **single date** or a **date range** with the date pickers, optionally enable **Repeats every year** for annual holidays, then click Add. Ranges must stay within the same calendar year.

= Can I customise the wording? =

Yes — go to **WooCommerce → QuickShipD → Style → Text Templates**. Three templates are available: single-date text, date-range text, and countdown text. Each supports simple placeholders: `{date}`, `{start}`, `{end}`, `{countdown}`.

= Can I disable the estimate for a specific product? =

Yes — open the product in the WooCommerce product editor, go to the **Shipping** tab, and check "Disable delivery estimate for this product". You can also set per-product min/max days in the same tab.

= Does it support variable products? =

Yes. The delivery date is hidden until a variation is selected; then a lightweight AJAX request fetches the correct estimate for that variation.

= Does the countdown use JavaScript? =

Yes — a small vanilla JS script (no jQuery) ticks the countdown every second and hides it when it reaches zero. If JavaScript is disabled, the static delivery date text remains visible; only the countdown is affected.

= Will QuickShipD add nag banners or notices asking me to upgrade? =

No QuickShipD will always have a complete free version.

= Is it GDPR/CCPA compliant? =

Yes. QuickShipD makes no external HTTP requests, stores no personal data, and sets no cookies.

= I changed the timezone in WordPress settings but dates are wrong. =

QuickShipD reads the WordPress timezone setting (Settings → General) and uses it for all calculations. After changing your timezone, you may need to clear any page-caching plugins.

= Does it work with multi-currency / multi-language plugins? =

The plugin uses WordPress's `date_i18n()` function, so day and month names are automatically translated to your active locale. It is compatible with WPML, Polylang, and similar translation plugins.

== Screenshots ==

1. **Product page** — delivery estimate text with optional countdown timer below the Add to Cart button.
2. **Admin settings — Delivery tab** — min/max days, cutoff time, excluded weekdays, and holiday management.
3. **Cart page** — per-item estimated delivery dates displayed as a cart item data row.
4. **Shipping method integration** — QuickShipD min/max day fields added to a flat rate shipping method instance inside WooCommerce Shipping Zones.

== Changelog ==

= 1.0.2 =
* Feature - Holidays UI: add single dates or same-year ranges with optional yearly recurring (replaces textarea).
* Fix     - Live admin preview now respects holidays and excluded weekdays.

= 1.0.1 - 08/07/2026 =
* Fix   - Exclude weekends and non delivery days data not saving.

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
