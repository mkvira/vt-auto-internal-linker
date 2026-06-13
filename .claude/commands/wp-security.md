Perform a security audit of the VT Auto Internal Linker plugin. Read every PHP file in `includes/` and `vt-auto-internal-linker.php`, then check each category below. Report PASS ✅ or FAIL ❌ with file path and line number for every issue.

**1. Direct access guard**
Every PHP file must begin with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.

**2. Nonce verification**
Every POST handler and every delete action must call `check_admin_referer()` or `wp_verify_nonce()` *before* reading `$_POST`/`$_GET` values. AJAX handlers must use `check_ajax_referer()`.

**3. Capability checks**
Every admin operation must confirm `current_user_can( 'manage_options' )` (or appropriate cap) before executing.

**4. Input sanitization**
All values read from `$_POST`, `$_GET`, `$_REQUEST`, `$_COOKIE` must pass through a sanitizing function:
- Text → `sanitize_text_field()` + `wp_unslash()`
- URLs → `esc_url_raw()` + `wp_unslash()`
- Integers → `absint()` or `intval()`
- Keys → `sanitize_key()`

**5. Output escaping**
All echoed values must be wrapped:
- HTML text → `esc_html()`
- HTML attributes → `esc_attr()`
- URLs in href/src → `esc_url()`
- Raw HTML with allowed tags → `wp_kses_post()` or `wp_kses()`

**6. SQL injection prevention**
Any `$wpdb` query with a variable must use `$wpdb->prepare()`. No string concatenation into SQL.

**7. Open redirect prevention**
`wp_safe_redirect()` must be used instead of `wp_redirect()` for admin redirects based on user input.

After checking all categories, give a summary table and list any recommended fixes with corrected code snippets.

$ARGUMENTS
