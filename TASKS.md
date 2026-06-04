# TASKS.md — VT Auto Internal Linker

## نحوه استفاده

هر session با Claude Code:
1. یه task از این فایل انتخاب کن
2. با قالب زیر به Claude Code بده
3. بعد از اتمام: بررسی کد → commit → وضعیت CLAUDE.md رو آپدیت کن

**هیچ‌وقت بیشتر از یه task در یه session نده.**

---

## قالب استاندارد task

```
Read CLAUDE.md first.

Context: [وضعیت فعلی پروژه]
Phase: [Phase X]
Task: [دقیقاً چی باید انجام بشه]
Files to modify: [لیست فایل‌ها]
Files NOT to touch: [فایل‌هایی که نباید تغییر کنن]
Done when: [شرط تکمیل]
```

---

## Phase 1 — Foundation

### Task 1.1 — Plugin bootstrap
```
Read CLAUDE.md first.

Context: Project not started. Creating plugin foundation.
Phase: Phase 1
Task:
  - Create vt-auto-internal-linker.php with full WordPress plugin header
    (Plugin Name, Description, Version, Author, Author URI, License, Text Domain — all from CLAUDE.md)
  - Define constants: VTAIL_VERSION, VTAIL_PATH, VTAIL_URL
  - Create includes/class-plugin.php with constructor and empty init() method
  - Instantiate plugin via hook: add_action('plugins_loaded', ...)
  - No business logic yet — bootstrap only
Files to modify: vt-auto-internal-linker.php, includes/class-plugin.php
Files NOT to touch: Everything else
Done when: Plugin appears in WP admin panel and activates without any PHP errors or warnings.
```

### Task 1.2 — Database setup
```
Read CLAUDE.md first.

Context: Plugin bootstrap exists. Adding DB layer.
Phase: Phase 1
Task:
  - Create includes/class-rules-db.php with static method create_table()
  - Table schema is in CLAUDE.md (table: {prefix}vtail_rules)
  - Use dbDelta() for table creation (WordPress standard)
  - Register activation hook in main plugin file to call Rules_DB::create_table()
  - Add deactivation hook (does NOT drop table — data must persist)
Files to modify: includes/class-rules-db.php, vt-auto-internal-linker.php
Files NOT to touch: includes/class-plugin.php
Done when: Table {prefix}vtail_rules exists in DB after plugin activation.
```

### Task 1.3 — Project documentation files
```
Read CLAUDE.md first.

Context: Plugin bootstrap and DB exist.
Phase: Phase 1
Task:
  - Create readme.txt in WordPress.org required format:
    (Plugin Name, Contributors, Tags, Requires at least, Tested up to,
     Stable tag, License, short description, == Description ==, == Installation ==,
     == Changelog == sections)
  - Create CHANGELOG.md with initial entry for v1.0.0 (Unreleased)
  - Author info comes from CLAUDE.md
Files to modify: readme.txt, CHANGELOG.md
Files NOT to touch: All PHP files
Done when: readme.txt passes WordPress.org format requirements. CHANGELOG.md initialized.
```

---

## Phase 2 — Rules CRUD

### Task 2.1 — DB access methods
```
Read CLAUDE.md first.

Context: DB table exists. Adding data access layer.
Phase: Phase 2
Task:
  Add these methods to class-rules-db.php (all use $wpdb, all sanitize input):
  - get_all(): array — returns all rules ordered by priority
  - get_by_id(int $id): ?array — returns single rule or null
  - insert(array $data): int|false — returns new row ID or false
  - update(int $id, array $data): bool
  - delete(int $id): bool
  Follow Clean Code standards from CLAUDE.md (type hints, descriptive names, early return).
Files to modify: includes/class-rules-db.php
Files NOT to touch: All other files
Done when: All five methods exist with correct signatures and input sanitization.
```

### Task 2.2 — Admin menu and list table
```
Read CLAUDE.md first.

Context: CRUD methods exist in class-rules-db.php.
Phase: Phase 2
Task:
  - Create includes/class-admin.php
  - Register admin menu page under Settings with capability 'manage_options'
  - Display rules using WP_List_Table with columns: keyword, url, max_per_post, active, actions (edit/delete)
  - Add hook registrations in class-plugin.php only (not in class-admin.php constructor)
Files to modify: includes/class-admin.php, includes/class-plugin.php
Files NOT to touch: class-rules-db.php, main plugin file
Done when: Admin menu appears under Settings. List table shows rules. No PHP errors.
```

### Task 2.3 — Add and Edit rule forms
```
Read CLAUDE.md first.

Context: List table exists in admin. Adding create/edit functionality.
Phase: Phase 2
Task:
  - Add/Edit form in class-admin.php (single form handles both modes)
  - Fields: keyword, url, case_sensitive, max_per_post, nofollow, new_tab, priority, active
  - Nonce verification on form submit
  - Input sanitization: sanitize_text_field() for keyword, esc_url_raw() for url, absint() for integers
  - On success: redirect back to list with admin notice
  - On error: show inline error message
Files to modify: includes/class-admin.php
Files NOT to touch: All other files
Done when: Rules can be added and edited. Nonce verified. Inputs sanitized. Redirect on success.
```

### Task 2.4 — Delete rule with confirmation
```
Read CLAUDE.md first.

Context: Add/Edit forms work. Adding delete functionality.
Phase: Phase 2
Task:
  - Handle delete action in class-admin.php
  - Nonce verification on delete request
  - Use absint() on ID parameter
  - Show admin notice on success
  - No JS confirmation needed — nonce is sufficient security
Files to modify: includes/class-admin.php
Files NOT to touch: All other files
Done when: Rules delete correctly. Nonce verified. Success notice shown.
```

---

## Phase 3 — Linking Engine

### Task 3.1 — Core linker class
```
Read CLAUDE.md first.

Context: Admin CRUD fully works. Building the core feature.
Phase: Phase 3
Task:
  - Create includes/class-linker.php
  - Main public method: process_content(string $content): string
  - Fetch active rules from Rules_DB (ordered by priority, highest first)
  - For each rule: replace keyword with <a href="url"> using regex
  - Respect: case_sensitive flag, max_per_post limit, nofollow, new_tab
  - CRITICAL: Do NOT replace inside existing <a>...</a> tags
  - CRITICAL: Do NOT replace inside <code> or <pre> blocks
  - Hook the_content filter in class-plugin.php only (not in Linker class)
  Clean Code: extract regex logic to private methods, no method over 20 lines.
Files to modify: includes/class-linker.php, includes/class-plugin.php
Files NOT to touch: class-rules-db.php, class-admin.php, main plugin file
Done when: Keywords become links on frontend. Existing links not double-linked. Code/pre blocks unaffected.
```

### Task 3.2 — Edge case handling
```
Read CLAUDE.md first.

Context: Basic linker works. Hardening edge cases.
Phase: Phase 3
Task:
  Add these protections to class-linker.php:
  - Skip linking in post that is the target URL itself (prevent self-linking)
  - Handle keywords with special regex characters safely (preg_quote)
  - Preserve original HTML attributes and entities around replaced text
  - No change to class-plugin.php or any other file
Files to modify: includes/class-linker.php
Files NOT to touch: All other files
Done when: All edge cases handled. No regex errors on special characters in keywords.
```

---

## Phase 4 — Polish & Release

### Task 4.1 — Admin styles
```
Read CLAUDE.md first.

Context: All functionality complete. Adding visual polish.
Phase: Phase 4
Task:
  - Create assets/css/admin.css
  - Style the rules list table and add/edit form to match WP admin aesthetics
  - Enqueue only on plugin admin pages (use $hook parameter)
  - Enqueue hook registration in class-plugin.php only
Files to modify: assets/css/admin.css, includes/class-plugin.php
Files NOT to touch: All PHP logic files
Done when: Admin pages look clean. CSS only loads on plugin pages.
```

### Task 4.2 — i18n and POT file
```
Read CLAUDE.md first.

Context: Plugin complete. Preparing for WordPress.org submission.
Phase: Phase 4
Task:
  - Verify ALL user-visible strings use __() or _e() with text domain 'vt-auto-internal-linker'
  - Load text domain in class-plugin.php via load_plugin_textdomain()
  - Generate languages/vt-auto-internal-linker.pot using WP-CLI:
    wp i18n make-pot . languages/vt-auto-internal-linker.pot
Files to modify: languages/ directory, includes/class-plugin.php (text domain loading only)
Files NOT to touch: All logic files
Done when: .pot file generated. All strings translatable.
```

### Task 4.3 — Release v1.0.0
```
Read CLAUDE.md first.

Context: All phases complete. Preparing v1.0.0 release.
Phase: Phase 4
Task:
  - Update VTAIL_VERSION constant to '1.0.0'
  - Update readme.txt Stable tag to 1.0.0
  - Update CHANGELOG.md: move Unreleased to [1.0.0] with today's date
  - Verify plugin header version matches constant
  - Verify readme.txt passes WordPress.org validator
Files to modify: vt-auto-internal-linker.php, readme.txt, CHANGELOG.md
Files NOT to touch: All other files
Done when: Version consistent across all three files. Ready to tag.
```
