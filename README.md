[🇮🇷 نسخه فارسی](FA_README.md)

# VT Auto Internal Linker — WordPress Auto Internal Linking Plugin

**VT Auto Internal Linker** is a lightweight WordPress plugin for automatic internal linking. It scans your posts and pages at render time and wraps configured keywords with internal links based on rules you define — without ever modifying your stored content.

**Author:** [Mahmoud Kazemi](https://mahmoudkazemi.ir)  
**Company:** [Vira Team](https://vira-team.com)  
**License:** GPL-2.0-or-later  
**Requires WordPress:** 6.0+  
**Requires PHP:** 7.4+

---

## Features

- Define keyword→URL rules from the WordPress admin — no coding required
- Multiple keywords per URL rule, each with independent settings
- Keyword-based automatic internal linking at render time (zero changes to your database content)
- Per-keyword SEO controls: priority, max links per post, site-wide total limit, anchor (`#section`), case sensitivity, nofollow, open in new tab
- Rule-level cap: limit total links to a URL per post across all its keywords combined
- Block protection: never links inside existing `<a>`, `<code>`, or `<pre>` tags
- Self-link prevention: rules whose URL matches the current page are skipped automatically
- Manual links count against `max_per_post` — no duplicate linking on top of existing anchors
- Link stats: on-demand scan shows which posts contain each keyword link
- WordPress Multisite compatible
- Fully internationalised (`.pot` file included, Persian `fa_IR` translation bundled)

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate via the **Plugins** screen in WordPress admin
3. Navigate to **Settings → Auto Internal Linker** to add your first keyword-to-URL rule

---

## Development

### Requirements

- PHP 7.4+
- WordPress 6.0+

### Setup

```bash
git clone https://github.com/mkvira/vt-auto-internal-linker.git
cd vt-auto-internal-linker
```

### Branch strategy

- `main` — stable, released code only
- `develop` — integration branch
- `feat/*` / `fix/*` — short-lived branches, merged via PR into develop

### Commit format

```
feat(scope): short description
fix(scope): short description
docs: short description
chore: short description
```

---

## Contributing

Contributions are welcome. Please open an issue to discuss your proposed change before submitting a pull request.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md)

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)
