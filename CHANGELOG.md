# Changelog

All notable changes to VT Auto Internal Linker will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Plugin bootstrap: main plugin file with full WordPress header, constants (`VTAIL_VERSION`, `VTAIL_PATH`, `VTAIL_URL`), and `plugins_loaded` initialisation
- `VTAIL_Plugin` core class with hook registration skeleton
- `VTAIL_Rules_DB` class with `create_table()` — creates `{prefix}vtail_rules` on activation via `dbDelta()`
- Activation hook wires table creation; deactivation hook preserves data
- `readme.txt` in WordPress.org required format
- `CHANGELOG.md` initialised
