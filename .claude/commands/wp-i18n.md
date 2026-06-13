Audit internationalization for the VT Auto Internal Linker plugin and report what needs updating.

**Step 1 — Scan for untranslated strings**
Read all PHP files in `includes/` and `vt-auto-internal-linker.php`. Find any user-visible string literal that is NOT wrapped in an i18n function (`__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `esc_html_e()`, `esc_attr_e()`). List each one with file path and line number.

**Step 2 — Check text domain consistency**
Verify every i18n call uses `'vt-auto-internal-linker'` as text domain. List any calls with a wrong or missing domain.

**Step 3 — Compare .pot vs .po**
Read `languages/vt-auto-internal-linker.pot` and `languages/vt-auto-internal-linker-fa_IR.po`.
- List any `msgid` in the .pot file that has an empty `msgstr` in the .po file (untranslated strings)
- List any `msgid` in the .po file that does NOT appear in the .pot file (obsolete translations — safe to remove)

**Step 4 — Regenerate instructions**
Print the WP-CLI command to regenerate the .pot file:
```bash
wp i18n make-pot . languages/vt-auto-internal-linker.pot \
  --domain=vt-auto-internal-linker \
  --exclude=".git,.claude,node_modules,vendor"
```
And to compile .po → .mo:
```bash
wp i18n make-mo languages/
```

**Step 5 — Summary**
Print counts: total translatable strings, translated in fa_IR, missing, obsolete.

$ARGUMENTS
