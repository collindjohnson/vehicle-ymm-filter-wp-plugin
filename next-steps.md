# Next Steps — Connecting the Wheel YMM Filter Plugin

To go from "files on disk" to "working on your site," the following is needed. Roughly in order:

## 1. A Wheel-Size API key

Sign up at https://api.wheel-size.com/ and grab your `user-key`. Paste it into **WooCommerce → Wheel YMM → Wheel-Size API key**. Without this, every dropdown call returns an error.

Note the plan you pick — the free/trial tier has a low monthly call cap, which is why the plugin caches everything for 30 days.

## 2. Access to your WordPress site

One of these, depending on how you want to get the plugin deployed:

- **SFTP / SSH credentials** (host, user, key or password, path to `wp-content/plugins/`) so the plugin can be uploaded directly and logs can be tailed.
- **Admin login + a staging URL** to install the zip through the WP admin and click through the verification steps.
- **Self-install** — zip the `wheel-ymm-filter/` folder, upload via **Plugins → Add New → Upload Plugin**, activate, then report any errors.

## 3. Confirmation of your WooCommerce setup

- WooCommerce version (7.0+ required).
- Whether your wheels are **simple products** or **variable products** (one product with size variations). The fitment UI shows up in different places for each:
  - Simple products: **YMM Fitments** tab on the product edit screen.
  - Variable products: per-variation fitment panel inside each variation row.
- A sample product or two to use as a test case.

## 4. Your shop page URL

So the shortcode's default redirect points at the right place. If wheels live at `/shop/` it's automatic; if they live at something like `/wheels/` or a custom landing page, set the shortcode's `redirect` attribute:

```
[wheel_ymm_filter redirect="/wheels/"]
```

## 5. A real vehicle to test with

Pick one wheel and one vehicle you *know* it fits (e.g., "this wheel fits 2018–2022 Honda Civic"). Tag that product, then:

1. Filter the shop by that YMM → confirm the product appears.
2. Filter by a different YMM → confirm the product is hidden.
3. Click **Clear filter** → confirm the full catalog returns.

That's the end-to-end proof the whole pipeline works.

---

## Minimum to start

**#1 (API key) + #2 (install method).** The rest can be sorted out once the plugin is active.

## Verification checklist (once installed)

- [ ] Plugin activates without errors; `wp_wymm_fitments` table exists.
- [ ] **Test API** button on the settings page returns a make count.
- [ ] Editing a product shows the **YMM Fitments** tab (simple) or per-variation panel (variable).
- [ ] Cascading Make → Model → Year dropdowns populate from the API.
- [ ] "Add range" helper adds multiple years in one click.
- [ ] Saved fitments persist across reload.
- [ ] `[wheel_ymm_filter]` shortcode renders on a page and submits to the shop.
- [ ] Shop archive filters correctly; "Showing wheels for: …" notice appears.
- [ ] Clear filter link restores the full catalog.
- [ ] With `WP_DEBUG_LOG` on, the first API request is logged; subsequent identical requests within TTL are not (cache working).
