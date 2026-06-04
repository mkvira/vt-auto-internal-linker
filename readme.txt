=== VT Auto Internal Linker ===
Contributors: mkvira
Tags: internal links, seo, auto linking, links, keywords
Requires at least: 5.5
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically insert internal links into your content based on user-defined keyword-to-URL rules. No coding required.

== Description ==

VT Auto Internal Linker scans your posts and pages on render and automatically wraps configured keywords with internal links — without touching your stored content.

**Features:**

* Define keyword → URL rules from a simple admin screen
* Per-rule controls: case sensitivity, maximum occurrences per post, nofollow, open in new tab
* Rules are applied at render time via the `the_content` filter — your database content is never modified
* Skips replacement inside existing `<a>`, `<code>`, and `<pre>` tags to avoid double-linking
* Compatible with WordPress Multisite
* Fully internationalised (`.pot` file included)

== Installation ==

1. Upload the `vt-auto-internal-linker` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings > Auto Internal Linker** to add your first keyword-to-URL rule.
4. Rules take effect immediately on the front end.

== Frequently Asked Questions ==

= Does the plugin modify my post content in the database? =

No. All replacements happen at render time via the `the_content` filter. Your stored content is never changed.

= What happens if I deactivate the plugin? =

Your content will display exactly as it was before — no stored links are left behind. Your rules are preserved in the database and will be available when you reactivate.

= Is it compatible with page builders and block editors? =

Yes, as long as the page builder or block editor outputs content through the standard `the_content` filter.

== Changelog ==

= 1.0.0 =
* Initial release.
* Keyword-to-URL auto-linking via the `the_content` filter — database content is never modified.
* Admin UI under Settings with full CRUD (add, edit, delete) for keyword rules.
* Per-rule controls: case sensitivity, max occurrences per post, nofollow, open in new tab, priority.
* Skips replacement inside existing links, code blocks, pre blocks, and all HTML tag attributes.
* Self-link prevention: rules whose URL matches the current page are automatically skipped.
* Fully internationalised with `.pot` file included.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
