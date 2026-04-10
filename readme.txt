=== Wheel YMM Filter ===
Contributors: collinjohnson
Tags: woocommerce, wheels, fitment, ymm, vehicle
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Filter WooCommerce wheel products by Year / Make / Model. Uses the Wheel-Size API in the admin and your own fitment database on the frontend for fast, accurate dropdowns.

== Description ==

Wheel YMM Filter gives a WooCommerce wheel store a cascading Year / Make / Model filter. Store admins manually tag which vehicles each wheel (and each wheel-size variation) fits — that fitment data is queried on the shop page to return only matching products.

**Features**

* Cascading Year → Make → Model dropdowns on the frontend, sourced from your fitment database — only makes/models/years with published products are shown
* Wheel-Size API used in the admin editor to populate all available makes, models, and years when assigning fitments
* Per-product and per-variation fitment assignment in the WooCommerce product editor
* Bulk "year range" helper — add e.g. Civic 2015–2023 in one click
* Configurable cache TTL for API data (default 7 days); adjustable on the settings page
* Shortcode `[wheel_ymm_filter]` for placing the filter anywhere
* Automatic filtering of the WooCommerce shop archive via `woocommerce_product_query`
* "Clear filter" notice shown above the product grid when a filter is active
* API key can be loaded from outside WordPress — `WYMM_API_KEY` constant in `wp-config.php` or an `WYMM_API_KEY` environment variable — so the key is never stored in the database or editable in the admin UI
* Built-in rate limiting: 60 Wheel-Size API calls per minute site-wide and 30 AJAX requests per IP per minute
* Covering index on the fitment table keeps shop-page queries fast at scale
* Automatic database schema upgrade on plugin load — no manual migration needed

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

== Secure install (recommended) ==

For production sites we recommend loading the API key from outside WordPress so it is never stored in the database, never editable in the admin UI, and never rendered back to the browser. When any of the methods below are used, the settings-page input is disabled and shows **"Managed outside WordPress."**, and any stale copy of the key in `wp_options` is automatically deleted on the next admin page load.

Pick the most restrictive method your host supports.

**Method A — Secrets file above the web root (best)**

1. Create a file **above** `public_html` (not inside your WordPress install), e.g. `/home/USERNAME/wymm-secrets.php`:

`<?php
define( 'WYMM_API_KEY', 'your-user-key-here' );`

2. Set the file permissions to `400` (owner read-only) so only the web user can read it.
3. Edit `wp-config.php` and add the following line **above** the `/* That's all, stop editing! */` comment:

`require_once '/home/USERNAME/wymm-secrets.php';`

The secrets file sits outside the web root, so it cannot be fetched over HTTP even if a misconfiguration or backup exposes files under `public_html`. It is also not touched by plugin updates or reinstalls.

**Method B — Constant in wp-config.php**

Add the following to `wp-config.php` above the `/* That's all, stop editing! */` comment:

`define( 'WYMM_API_KEY', 'your-user-key-here' );`

The key is never written to the database and the settings field is locked, but `wp-config.php` itself lives inside the WordPress root.

**Method C — Environment variable**

Set `WYMM_API_KEY` as an environment variable via your host's control panel, a PHP-FPM pool config, or `.htaccess`:

`SetEnv WYMM_API_KEY "your-user-key-here"`

Only works if `getenv()` is populated on your host. Verify with **WooCommerce → Wheel YMM → Diagnostics → Test API** after applying.

**For distributed users / customers**

If none of the methods above are used, the plugin falls back to storing the key in the `wymm_api_key` WordPress option (not autoloaded) and lets you paste the key into the **WooCommerce → Wheel YMM** settings page. The field is write-only — the current key is never rendered back to the browser.

== Frequently Asked Questions ==

= How do I clear the cached makes/models/years? =
Settings page → **Clear taxonomy cache**.

= Why don't I see a make/model in the frontend filter? =
The frontend dropdowns are built from your own fitment data. A make or model only appears if at least one published product has been assigned that fitment. Check the YMM Fitments tab on the relevant products.

= Does this require WooCommerce? =
Yes. The plugin hooks into WooCommerce product queries and the product editor.

= How do I change the cache duration? =
Settings page → **Cache TTL (seconds)**. The default is 604800 (7 days). The minimum is 60 seconds.

== Changelog ==

= 1.2.0 =
* Hardened API-key storage: the key can now also be sourced from a `WYMM_API_KEY` environment variable, and the `wymm_api_key` option is automatically removed from the database when an external source is present.
* Settings page now shows "Managed outside WordPress." whenever the key is sourced from a PHP constant or environment variable, without leaking which source is in use.
* Removed the `wymm_api_key` filter to eliminate a potential exfiltration vector via hostile third-party code.
* Added `.gitignore` to the plugin root to prevent accidental commits of local secrets files.

= 1.1.0 =
* Frontend dropdowns now sourced from the fitment database rather than the Wheel-Size API — only makes/models/years with published products are shown.
* Added support for defining the API key via the `WYMM_API_KEY` PHP constant.
* Added configurable cache TTL setting (default changed from 30 days to 7 days).
* Added site-wide API rate limiting (60 requests/minute) and per-IP AJAX throttle (30 requests/minute).
* Fitment table now uses a covering index (`make_slug, model_slug, year, product_id`) for faster shop-page queries.
* All variations' fitments are now prefetched in a single query when opening a variable product editor.
* Fitment object-cache entries use version-based keys for atomic, race-condition-free invalidation.
* Automatic database schema upgrade runs on plugin load — no manual steps needed when updating.
* Year dropdown now appears before Make in the frontend filter form.

= 1.0.0 =
* Initial release.
