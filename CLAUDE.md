# CLAUDE.md — VT Auto Internal Linker

## Author & Plugin Identity

| Field         | Value                              |
|---------------|------------------------------------|
| Author        | Mahmoud Kazemi                     |
| Author Email  | mahmoudk.v92@gmail.com             |
| Author URI    | https://mahmoudkazemi.ir           |
| Company       | Vira Team                          |
| Company URI   | https://vira-team.com              |
| Plugin Name   | VT Auto Internal Linker            |
| Plugin Slug   | vt-auto-internal-linker            |
| Text Domain   | vt-auto-internal-linker            |
| GitHub        | https://github.com/mkvira          |
| License       | GPL-2.0-or-later                   |

---

## Project Overview

A WordPress plugin that automatically inserts internal links into post/page content based on user-defined keyword→URL rules. Targets both personal site owners and agencies managing multiple WordPress installs.

Distributed via WordPress.org plugin repository. Must comply with all WordPress.org plugin guidelines.

## Goals
- Keyword-based auto-linking on content render (not hardcoded to DB)
- Admin UI to manage keyword/URL rules (CRUD)
- Per-rule controls: case sensitivity, max occurrences per post, link attributes (nofollow, target)
- Works on both single-site and Multisite WordPress installations

## Out of Scope (DO NOT implement unless explicitly asked)
- Click tracking / analytics
- Broken link detection
- AI-based link suggestions
- Import/export functionality
- Gutenberg sidebar integration
- Any front-end JavaScript beyond admin UI

---

## Architecture

```
vt-auto-internal-linker/
├── vt-auto-internal-linker.php   ← Main plugin file, bootstrap only
├── includes/
│   ├── class-plugin.php          ← Core: hooks registration
│   ├── class-linker.php          ← Content processing logic (regex engine)
│   ├── class-rules-db.php        ← DB abstraction for rules table
│   └── class-admin.php           ← Admin menu, settings page, CRUD UI
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── languages/                    ← i18n .pot file + translations
├── README.md                     ← GitHub readme (developer-facing)
├── readme.txt                    ← WordPress.org readme (required format)
├── CHANGELOG.md                  ← Full version history
└── CONTRIBUTING.md               ← Contribution guidelines (future)
```

---

## Tech Stack
- PHP 7.4+ with strict types, OOP — no global functions except plugin bootstrap
- WordPress Hooks API exclusively
- No DB calls outside `class-rules-db.php`
- Vanilla JS + WP built-in jQuery for admin UI (no React/Vue)
- Custom DB table: `{prefix}vtail_rules`

---

## Database Schema

Table: `{prefix}vtail_rules`
```sql
id             INT AUTO_INCREMENT PRIMARY KEY
keyword        VARCHAR(255) NOT NULL
url            VARCHAR(2083) NOT NULL
case_sensitive TINYINT(1) DEFAULT 0
max_per_post   INT DEFAULT 1
nofollow       TINYINT(1) DEFAULT 0
new_tab        TINYINT(1) DEFAULT 0
priority       INT DEFAULT 10
active         TINYINT(1) DEFAULT 1
created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
```

---

## Versioning Strategy (Semantic Versioning)

Format: `MAJOR.MINOR.PATCH`

| Type    | When to increment                                      | Example       |
|---------|--------------------------------------------------------|---------------|
| PATCH   | Bug fixes, no new features                             | 1.0.0 → 1.0.1 |
| MINOR   | New feature, backward compatible                       | 1.0.1 → 1.1.0 |
| MAJOR   | Breaking change or major architecture shift            | 1.1.0 → 2.0.0 |

**Release checklist (before every version bump):**
- [ ] CHANGELOG.md updated with date and changes
- [ ] Version number updated in: plugin header, `VTAIL_VERSION` constant, readme.txt `Stable tag`
- [ ] All tests pass
- [ ] Git tag created: `git tag v1.0.0 && git push origin v1.0.0`

Starting version: `1.0.0`

---

## WordPress.org Compliance Rules

These are hard requirements for WordPress.org plugin repository acceptance:

1. **License**: GPL-2.0-or-later — all files must be compatible
2. **No external HTTP calls** without explicit user consent
3. **No obfuscated code**
4. **Sanitize all inputs**: `sanitize_text_field()`, `esc_url()`, `absint()` etc.
5. **Escape all outputs**: `esc_html()`, `esc_attr()`, `esc_url()` etc.
6. **Nonce verification** on every form submission and AJAX call
7. **Prefix everything**: all functions, classes, constants, hooks use `vtail_` or `VTAIL_` prefix
8. **readme.txt** must follow exact WordPress.org format (see readme.txt in repo)
9. **No calling of deprecated WordPress functions**
10. **Internationalization**: all user-visible strings use `__()` or `_e()` with text domain `vt-auto-internal-linker`

---

## Clean Code Standards

Follow these in every file, every function:

- **Single Responsibility**: each class does one thing. `Linker` processes content only. `Rules_DB` touches DB only.
- **Method length**: max ~20 lines per method. Extract if longer.
- **Naming**: descriptive names — `get_active_rules()` not `get_data()`, `replace_keyword_with_link()` not `process()`
- **No magic numbers**: use named constants — `VTAIL_MAX_RULES_PER_POST` not `50`
- **Comments**: explain *why*, not *what*. The code explains what.
- **Early return**: avoid deeply nested conditions
- **Type hints**: use PHP 7.4 type hints on all method signatures

```php
// ✅ Good
private function replace_keyword_with_link( string $content, Rule $rule ): string {

// ❌ Bad
private function process( $c, $r ) {
```

---

## Development Rules (read before every change)

1. **Scope**: only modify files listed in the task. Do NOT touch other files.
2. **No regression**: before editing any method, read it fully first.
3. **DB access**: ALL `$wpdb` calls go inside `class-rules-db.php` only.
4. **Hooks**: ALL `add_action`/`add_filter` calls go inside `class-plugin.php` only.
5. **Security**: nonce on every form + AJAX. Sanitize in, escape out.
6. **i18n**: wrap every user-visible string.
7. **Prefix**: every function, class, constant, option name uses `vtail_` / `VTAIL_`.
8. **One task at a time**: complete and verify before moving to next.

---

## Git Workflow

```bash
# Start every task
git checkout -b phase2/rules-crud

# Commit format: <type>(<scope>): <short description>
# Types: feat | fix | refactor | docs | style | chore
git commit -m "feat(admin): add rules list table with WP_List_Table"
git commit -m "fix(linker): prevent double-linking inside existing <a> tags"
git commit -m "docs: update CHANGELOG for v1.0.0"

# Finish task
git push origin phase2/rules-crud
# Then merge to main via PR (even when working solo — keeps history clean)
```

---

## Current State

### ✅ Completed
- Nothing yet — project not started

### 🔄 In Progress
- Phase 1: Plugin bootstrap + DB setup

### ❌ Not Started
- All phases

**When completing a phase, update this section.**

---

## Development Phases

### Phase 1 — Foundation
- [ ] Plugin header with full author info and WordPress.org fields
- [ ] Define constants: `VTAIL_VERSION`, `VTAIL_PATH`, `VTAIL_URL`
- [ ] `class-plugin.php` with hook registration skeleton
- [ ] DB table creation on activation via `class-rules-db.php`
- [ ] readme.txt in WordPress.org format
- [ ] CHANGELOG.md initialized
- [ ] Verified: plugin activates/deactivates cleanly

### Phase 2 — Rules CRUD
- [ ] `class-rules-db.php`: `get_all()`, `get_by_id()`, `insert()`, `update()`, `delete()`
- [ ] `class-admin.php`: admin menu + `WP_List_Table`
- [ ] Add/Edit rule form with nonce + validation
- [ ] Verified: rules save/edit/delete correctly in DB

### Phase 3 — Linking Engine
- [ ] `class-linker.php`: regex-based keyword→link replacement
- [ ] Hook into `the_content` filter (priority 10)
- [ ] Respect `max_per_post`, `case_sensitive`, `active` per rule
- [ ] Skip replacement inside `<a>`, `<code>`, `<pre>` tags
- [ ] Verified: links appear on frontend, edge cases handled

### Phase 4 — Polish & Release Prep
- [ ] Admin CSS
- [ ] Input validation & user-facing error messages
- [ ] Multisite compatibility
- [ ] `.pot` language file generated
- [ ] readme.txt complete with screenshots description
- [ ] All WordPress.org compliance rules verified
- [ ] Tag v1.0.0
