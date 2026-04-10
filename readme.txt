=== Wheel YMM Filter ===
Contributors: collinjohnson
Tags: woocommerce, wheels, fitment, ymm, vehicle
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Filter WooCommerce wheel products by Year / Make / Model. Uses the Wheel-Size API for the vehicle taxonomy and lets admins manually attach fitments to each product or variation.

== Description ==

Wheel YMM Filter gives a WooCommerce wheel store a cascading Year / Make / Model filter. Store admins manually tag which vehicles each wheel (and each wheel-size variation) fits — that fitment data is queried on the shop page to return only matching products.

**Features**

* Cascading Make → Model → Year dropdowns sourced from the Wheel-Size API
* Per-product and per-variation fitment assignment in the WooCommerce product editor
* Bulk "year range" helper — add e.g. Civic 2015–2023 in one click
* Aggressive transient caching of API data (default 30-day TTL)
* Shortcode `[wheel_ymm_filter]` for placing the filter anywhere
* Automatic filtering of the WooCommerce shop archive via `woocommerce_product_query`
* "Clear filter" notice shown above the product grid when a filter is active

== Installation ==

1. Upload the `wheel-ymm-filter` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce → Wheel YMM** and enter your Wheel-Size API key (user-key). Get one from https://api.wheel-size.com/.
4. Click **Test API** to verify the connection.
5. Edit a wheel product, open the **YMM Fitments** tab, and add the vehicles that product fits. For variable products, use the per-variation fitment panels inside each variation.
6. Place `[wheel_ymm_filter]` on any page (e.g. home, shop header).

== Shortcode attributes ==

* `redirect` — URL to submit the filter to. Defaults to the WooCommerce shop page.
* `layout` — `horizontal` (default) or `stacked`.

Example: `[wheel_ymm_filter redirect="/wheels/" layout="horizontal"]`

== Frequently Asked Questions ==

= How do I clear the cached makes/models/years? =
Settings page → **Clear taxonomy cache**.

= Does this require WooCommerce? =
Yes. The plugin hooks into WooCommerce product queries and the product editor.

== Changelog ==

= 1.0.0 =
* Initial release.
