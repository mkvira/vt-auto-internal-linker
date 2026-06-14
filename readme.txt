=== VT Auto Internal Linker ===
Contributors: mkvira
Tags: internal links, seo, auto linking, links, keywords
Requires at least: 5.5
Tested up to: 7.0
Stable tag: 1.1.3
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically insert internal links into your content based on user-defined keyword-to-URL rules. No coding required.

== Description ==

VT Auto Internal Linker scans your posts and pages on render and automatically wraps configured keywords with internal links — without touching your stored content.

**Features:**

* Define URL rules, each with multiple keywords managed from a single screen
* Per-keyword controls: priority, max occurrences per post, site-wide link limit, anchor section (#), case sensitivity, nofollow, open in new tab
* Rule-level cap: limit total links to a URL per post across all its keywords
* Rules are applied at render time via the `the_content` filter — your database content is never modified
* Skips replacement inside existing `<a>`, `<code>`, and `<pre>` tags to avoid double-linking
* Link stats: manual on-demand scan shows which posts contain each keyword link
* Compatible with WordPress Multisite
* Fully internationalised (`.pot` file included, Persian translation bundled)

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

= 1.1.2 =
* Fixed: stats scan no longer counts keywords found inside existing manual links (`<a>` tags).
* Fixed: manual links to a rule's URL now count against `max_per_post` — no duplicate auto-linking on top of existing anchors.
* Added Persian documentation (`FA_README.md`) with SEO-optimized content.
* Updated: Tested up to WordPress 6.8.

= 1.1.1 =
* Rules list: ID and Title columns added; Keywords column shows keyword text with per-keyword link counts.
* Scan button moved to page-title area; URL column is always LTR; Edit/Delete actions moved under Title.
* Fixed: stats scan no longer double-counts overlapping keywords — priority order is respected during scan.

= 1.1.0 =
* Multi-keyword rules: each URL rule now supports multiple keywords with individual settings.
* Per-keyword controls: priority, max per post, site-wide total limit, anchor (#section), case sensitivity, nofollow, open in new tab.
* Inline AJAX keyword editor on the rule edit screen — add, edit, and delete keywords without leaving the page.
* Link stats: manual scan button checks all published posts and shows where each keyword is linked.
* Stats detail page lists every post containing a given keyword link with direct edit access.
* DB migration to schema v2 with `vtail_keywords` table; existing rules migrated automatically on update.
* Linker rewritten: single JOIN query (static-cached per request), `is_singular()` bail-early, self-link check now strips `#anchor` before comparing URLs.
* i18n: 73 translatable strings (up from 24); Persian (fa_IR) translation updated.
* Fixed: Global Settings strings (Exclude Tags, Save Settings) were missing from the .pot file in v1.0.0.

= 1.0.0 =
* Initial release.
* Keyword-to-URL auto-linking via the `the_content` filter — database content is never modified.
* Admin UI under Settings with full CRUD (add, edit, delete) for keyword rules.
* Per-rule controls: case sensitivity, max occurrences per post, nofollow, open in new tab, priority.
* Skips replacement inside existing links, code blocks, pre blocks, and all HTML tag attributes.
* Self-link prevention: rules whose URL matches the current page are automatically skipped.
* Fully internationalised with `.pot` file included.

== Upgrade Notice ==

= 1.1.2 =
Bug fix update. Stats scan and max_per_post behaviour are now more accurate. No database changes.

= 1.1.1 =
Minor update with admin UI improvements and a stats scan bug fix. No database changes.

= 1.1.0 =
Database schema upgraded automatically on activation. Existing rules are migrated to the new multi-keyword structure — no manual action required.

= 1.0.0 =
Initial release.
