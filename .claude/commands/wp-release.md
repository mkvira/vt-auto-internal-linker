Guide the release process for VT Auto Internal Linker. Follow these steps in order.

**Step 1 — Determine version bump**
Read the current version from `vt-auto-internal-linker.php` (plugin header `Version:` line and `VTAIL_VERSION` constant).
If `$ARGUMENTS` contains a version number (e.g. "1.1.0"), use it. Otherwise ask: "What is the new version? (current: X.Y.Z)"
Apply SemVer: PATCH for bug fixes, MINOR for new features, MAJOR for breaking changes.

**Step 2 — Update version in 3 places**
1. Plugin header: `Version: X.Y.Z` in `vt-auto-internal-linker.php`
2. Constant: `define( 'VTAIL_VERSION', 'X.Y.Z' )` in `vt-auto-internal-linker.php`
3. Stable tag: `Stable tag: X.Y.Z` in `readme.txt`

**Step 3 — Update CHANGELOG.md**
Add a new section above the previous release:
```
## [X.Y.Z] - YYYY-MM-DD

### Added
- ...

### Changed
- ...

### Fixed
- ...
```
Use today's date. Ask the user for the change summary if not provided in `$ARGUMENTS`.

**Step 4 — Review diff**
Show a `git diff` of all modified files before committing. Ask for confirmation.

**Step 5 — Commit**
```
git add vt-auto-internal-linker.php readme.txt CHANGELOG.md
git commit -m "chore(release): bump version to X.Y.Z"
```

**Step 6 — Remind about tag and push**
Print this reminder (do NOT run it automatically):
```bash
git tag vX.Y.Z
git push origin vX.Y.Z
git push origin main
git push snapp main
```

$ARGUMENTS
