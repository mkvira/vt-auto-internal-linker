Run through the manual testing checklist for VT Auto Internal Linker. For each item, describe what to verify and what the expected result is. If the local WordPress site is accessible, use the run/verify skill to open the browser and confirm each item visually.

Test scenario: $ARGUMENTS

---

**1. Plugin lifecycle**
- Activate plugin → no PHP errors, no admin notices, `{prefix}vtail_rules` table exists
- Deactivate → no errors, table still exists, data preserved
- Re-activate → no duplicate table error

**2. Admin CRUD**
- Open Settings → Auto Internal Linker
- Add a rule: keyword="WordPress", URL="https://wordpress.org", max_per_post=2, active=Yes
- Verify rule appears in list with correct columns
- Edit the rule: change max_per_post to 1, save → "Rule saved successfully" notice
- Delete the rule → "Rule deleted successfully" notice, rule gone from list

**3. Global Settings**
- Set Exclude Tags to "h1,h2,h3" → save → "Settings saved" notice
- Verify the field repopulates with saved value on reload

**4. Basic linking**
- Add rule: keyword="WordPress", URL="https://wordpress.org", active=Yes
- Create/edit a post containing the word "WordPress" in body text
- View post on frontend → word "WordPress" should be a link to wordpress.org

**5. max_per_post enforcement**
- Set max_per_post=1 on the rule
- Add "WordPress" 3 times in post body
- View post → only the FIRST occurrence should be linked

**6. Block protection — existing links**
- Add `<a href="https://example.com">WordPress</a>` in post HTML
- View post → must NOT be double-linked (keyword inside existing `<a>` stays as-is)

**7. Block protection — code and pre**
- Add `<code>WordPress</code>` and `<pre>WordPress</pre>` in post
- View post → keyword inside code/pre must NOT be linked

**8. Block protection — excluded tags**
- Add "WordPress" inside an `<h2>` heading
- View post → keyword inside h2 must NOT be linked (h1-h6 excluded by default)

**9. Self-link prevention**
- Create a rule whose URL exactly matches the current post's permalink
- View that post → the keyword must NOT be linked

**10. Link attributes**
- Add rule with nofollow=Yes, new_tab=Yes
- View post → rendered `<a>` must have `rel="nofollow noreferrer noopener"` and `target="_blank"`

**11. Case sensitivity**
- Add rule: keyword="WordPress", case_sensitive=Yes
- Post contains "wordpress" (lowercase) → must NOT be linked
- Post contains "WordPress" (exact case) → must be linked

Report PASS ✅ / FAIL ❌ for each item with notes on what was observed.
