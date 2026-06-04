# VT Auto Internal Linker

A WordPress plugin that automatically inserts internal links into your content based on keyword→URL rules you define.

**Author:** [Mahmoud Kazemi](https://mahmoudkazemi.ir)  
**Company:** [Vira Team](https://vira-team.com)  
**License:** GPL-2.0-or-later  
**Requires WordPress:** 6.0+  
**Requires PHP:** 7.4+

---

## Features

- Define keyword→URL rules from the WordPress admin
- Auto-links keywords in post/page content on render (no hardcoded changes to your DB content)
- Per-rule controls: case sensitivity, max links per post, nofollow, open in new tab
- Skips existing links — never double-links
- Skips `<code>` and `<pre>` blocks
- Multisite compatible

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate via **Plugins** screen in WordPress admin
3. Go to **Settings → VT Auto Internal Linker** to add your first rule

## Development

### Requirements
- PHP 7.4+
- WordPress 6.0+
- Composer (optional, for dev tools)

### Setup
```bash
git clone https://github.com/mkvira/vt-auto-internal-linker.git
cd vt-auto-internal-linker
```

### Branch strategy
- `main` — stable, released code only
- `develop` — integration branch
- `phase*/` or `feat/*` — feature branches, merged via PR

### Commit format
```
feat(scope): short description
fix(scope): short description
docs: short description
chore: short description
```

## Contributing

Contributions are welcome in the future. For now, please open an issue to discuss before submitting a PR.

## Changelog

See [CHANGELOG.md](CHANGELOG.md)

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)
