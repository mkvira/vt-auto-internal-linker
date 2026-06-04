# Changelog

All notable changes to VT Auto Internal Linker will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
