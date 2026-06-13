Run a WordPress.org compliance check on the VT Auto Internal Linker plugin. Read all PHP files in `includes/` and `vt-auto-internal-linker.php`, plus `readme.txt`. Report PASS ✅ or FAIL ❌ with specifics for each item.

**1. License headers**
Every PHP file must include a GPL-2.0-or-later compatible license declaration (either in the file header comment or via the plugin header). Check all files.

**2. Prefix consistency**
All functions, classes, constants, option names, and hook names must use the `vtail_` or `VTAIL_` prefix. Search for any `add_option()`, `update_option()`, `get_option()`, `add_action()`, `add_filter()` calls to verify prefix. Flag any globals without it.

**3. No deprecated WordPress functions**
Search for known deprecated functions:
`get_currentuserinfo`, `wp_get_sites`, `get_all_category_ids`, `the_category_ID`, `wp_specialchars`, `attribute_escape`, `get_blogaddress_by_id`, `sanitize_url` (deprecated in WP 6.0 — use `esc_url_raw`), `clean_url`.

**4. Text domain consistency**
Every `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `esc_attr_e()`, `esc_html_e()` call must use `'vt-auto-internal-linker'` as text domain. Find any calls with a different or missing text domain. Find any hardcoded user-visible strings not wrapped in an i18n function.

**5. No external HTTP calls**
Confirm there are no `wp_remote_get()`, `wp_remote_post()`, `wp_remote_request()`, `curl_init()`, or `file_get_contents()` calls to external URLs without user-initiated action.

**6. No obfuscated code**
Confirm no `eval()`, `base64_decode()`, `str_rot13()`, `gzinflate()`, or similar obfuscation.

**7. readme.txt format**
Verify `readme.txt` contains all required sections:
- `=== Plugin Name ===` header block with: Contributors, Tags, Requires at least, Tested up to, Stable tag, License, License URI
- `== Description ==`
- `== Changelog ==`
Verify `Stable tag` matches `VTAIL_VERSION` constant.

**8. Direct file access protection**
Every PHP file must have `if ( ! defined( 'ABSPATH' ) ) { exit; }`.

Print a summary table at the end with PASS/FAIL for all 8 items.

$ARGUMENTS
