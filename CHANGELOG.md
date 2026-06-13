# Changelog

All notable changes to VT Auto Internal Linker will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0] - 2026-06-13

### Added
- Multi-keyword rule system: each URL rule now holds multiple keywords managed from a single edit screen
- New DB table `{prefix}vtail_keywords` with per-keyword fields: `keyword`, `max_per_post`, `priority`, `total_limit`, `case_sensitive`, `nofollow`, `new_tab`, `anchor`
- `VTAIL_DB_VERSION` constant (value: `2`) and `VTAIL_Rules_DB::maybe_upgrade()` — runs on `plugins_loaded`, migrates existing v1 rules automatically
- Rule-level `max_per_post` cap: limits total links to a URL per post across all its keywords combined
- Per-keyword `total_limit`: stops linking a keyword site-wide once it has been linked in that many posts
- Per-keyword `anchor`: appends `#section` to the rule URL (e.g. `URL#about`) without storing a full duplicate URL
- Inline AJAX keyword editor on the rule edit page — add, edit, delete keywords without a full page reload
- Stats system: `vtail_scan_stats` AJAX endpoint scans published posts in batches of 15; results stored in `vtail_stats` option (`autoload=no`)
- "Update Link Stats" button with real-time progress bar on the rules list page and stats detail page
- Stats detail admin page (`?action=stats&keyword_id=N`) lists every post containing a given keyword link with title, URL, and edit link
- `VTAIL_Rules_DB::get_active_rules_with_keywords()` — single JOIN query, static-cached per request, used by the linker
- `VTAIL_Rules_DB` keyword CRUD: `get_keyword_by_id()`, `get_keywords_by_rule()`, `insert_keyword()`, `update_keyword()`, `delete_keyword()` (cascade-deletes stats)
- `VTAIL_Rules_DB` rule CRUD: `insert_rule()`, `update_rule()`, `delete_rule()` (cascade-deletes keywords and stats)
- `VTAIL_Rules_DB` stats helpers: `get_stats()`, `update_stats()`, `get_keyword_stats()`, `delete_keyword_stats()`
- `assets/js/admin.js` — keyword form reveal/populate, AJAX save/delete, scan progress loop
- i18n: 73 translatable strings (up from 24 in v1.0.0); Persian (fa_IR) translation fully updated

### Changed
- Rules list table now shows URL, keyword count, max-per-post, and active status (replaces old keyword-centric columns)
- Rule add/edit form split: rule-level fields (URL, max_per_post, active) at top; keyword management section below
- Linker now uses `get_active_rules_with_keywords()` instead of `get_all()`; respects rule-level cap alongside per-keyword cap
- `is_self_link()` now strips `#anchor` from the rule URL before comparing with the current permalink
- Added `is_singular()` bail-early check in `process_content()` — linker skips archives, search, and feeds
- `admin.css` extended with keyword table, settings badge, anchor input prefix, and scan progress bar styles

### Fixed
- `Global Settings`, `Exclude Tags`, `Save Settings`, and `Settings saved.` strings were in v1.0.0 admin code but missing from the `.pot` file

---

## [1.0.0] - 2026-06-04

### Added
- Plugin bootstrap: main plugin file with full WordPress header, constants (`VTAIL_VERSION`, `VTAIL_PATH`, `VTAIL_URL`), and `plugins_loaded` initialisation
- `VTAIL_Plugin` core class — central hook registration for all plugin components
- `VTAIL_Rules_DB` class — DB abstraction with `create_table()`, `get_all()`, `get_by_id()`, `insert()`, `update()`, `delete()`; activation hook creates `{prefix}vtail_rules` via `dbDelta()`; deactivation preserves data
- `VTAIL_Admin` class — admin menu under **Settings → Auto Internal Linker** with `WP_List_Table` for rule management (list, add, edit, delete), nonce verification on all form submissions and delete actions, PRG redirect on success
- Per-rule fields: keyword, URL, case sensitivity, max occurrences per post, nofollow, open in new tab, priority, active toggle
- `VTAIL_Linker` class — regex-based keyword→link replacement hooked to `the_content` filter at priority 10
- Replacement respects `case_sensitive`, `max_per_post`, `active`, and `priority` per rule
- Protected regions: existing `<a>` blocks, `<pre>` blocks, `<code>` blocks, and all HTML tag attributes are never modified
- Self-link prevention: rules whose URL matches the current post's permalink are skipped automatically
- Word-boundary matching via `(?<!\w)..(?!\w)` lookarounds — safe with keywords containing special regex characters
- Admin CSS scoped to the plugin settings page via WordPress body class; stylesheet only enqueued on that page
- `load_plugin_textdomain()` with `languages/vt-auto-internal-linker.pot` — 24 translatable strings
- `readme.txt` in WordPress.org required format
