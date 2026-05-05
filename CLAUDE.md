# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a full WordPress installation serving as a demo environment for the **Scolta AI Search plugin** (`wp-content/plugins/scolta-wp`). The WordPress core files are standard and not the focus of development. The primary work happens in the scolta-wp plugin and the scolta-demo-recipes plugin.

## Development Environment

This project uses **DDEV** for local development. Start it with:

```bash
ddev start
```

Site URL: `https://wp-scolta-demo.ddev.site`

WP-CLI is available via `ddev wp`:

```bash
ddev wp scolta build          # Build the search index
ddev wp scolta status         # Check plugin status
ddev wp scolta diagnose       # Run diagnostics
ddev wp scolta rebuild-index  # Rebuild Pagefind index
```

## Plugin Development Commands

All plugin tooling runs from inside `wp-content/plugins/scolta-wp/`. Run commands via `ddev exec` with the working directory set:

```bash
# Run all tests
ddev exec -d /var/www/html/wp-content/plugins/scolta-wp ./vendor/bin/phpunit

# Run a single test file
ddev exec -d /var/www/html/wp-content/plugins/scolta-wp ./vendor/bin/phpunit tests/SomeTest.php

# Lint (PHPCS with WordPress-Extra standard)
ddev exec -d /var/www/html/wp-content/plugins/scolta-wp composer lint

# Auto-fix formatting (PHPCBF)
ddev exec -d /var/www/html/wp-content/plugins/scolta-wp composer format

# Install/update PHP dependencies
ddev exec -d /var/www/html/wp-content/plugins/scolta-wp composer install
```

## Architecture

### Dependency Layers

```
scolta-wp  (this plugin — WordPress adapter only)
  └─ tag1/scolta-php  (Composer — core logic: indexing, AI clients, HTML cleaning)
       └─ scolta-core WASM  (browser-side scoring engine, shipped in vendor assets)
```

**scolta-wp handles only WordPress-specific concerns**: WP-CLI commands, Settings API, post hooks, shortcodes, REST endpoints, and the admin UI. All scoring, HTML cleaning, AI client calls, and index building logic lives in `scolta-php`.

### Key Plugin Files

| File | Role |
|------|------|
| `scolta.php` | Entry point, `SCOLTA_VERSION` constant, class loading |
| `admin/class-scolta-admin.php` | Settings UI via WordPress Settings API |
| `cli/class-scolta-cli.php` | All `wp scolta *` WP-CLI commands |
| `includes/class-scolta-tracker.php` | Tracks content changes in a custom DB table |
| `includes/class-scolta-content-gatherer.php` | Converts WP posts to `ContentItem` objects |
| `includes/class-scolta-rest-api.php` | REST endpoints at `/wp-json/scolta/v1/` |
| `includes/class-scolta-shortcode.php` | `[scolta_search]` shortcode |
| `includes/class-scolta-ai-service.php` | Wrapper for Anthropic/OpenAI calls |

### Settings Storage

All settings are stored in a single serialized WordPress option: `scolta_settings`. There is no per-setting `get_option()` / `update_option()` — always read and write the full array.

### API Key Storage

API keys must **never** be stored in the database from the admin form. They are read from environment variables or PHP constants only (with a legacy migration path).

## Critical Rules (from plugin CLAUDE.md)

- **Never** reimplement scoring, HTML cleaning, or prompt logic in scolta-wp — these belong in scolta-php.
- **Never** add `tag1/scolta-core` as a direct Composer dependency; always depend on `tag1/scolta-php`.
- Dependency constraint must use caret syntax: `"tag1/scolta-php": "^X.Y"`.
- Vendor asset paths must use `vendor/tag1/scolta-php/`, not `vendor/tag1/scolta/`.

### Version Management

The plugin version appears in **three places that must always match**:
1. `composer.json` (`version` field)
2. Plugin header comment in `scolta.php`
3. `SCOLTA_VERSION` constant in `scolta.php`

- If the current version ends in `-dev`, do not change it — accumulate commits on that dev version.
- If the current version is a bare release and you're making the first change after it, bump all three to the next target version with `-dev` suffix.
- Never commit a bare version without tagging it as a release.

### WordPress Conventions

- Snake_case method names; PHPDoc on all public methods.
- All user-facing strings use `__()` or `_e()` for i18n.
- REST endpoints registered with `register_rest_route()` and validation callbacks.

### Documentation

Every code-changing PR must add an entry to `CHANGELOG.md` under `## [Unreleased]`. Update `README.md` if the change affects installation, WP-CLI commands, REST API, shortcode, or settings behavior.

## Test Setup

Tests use a WP function stub bootstrap (`tests/bootstrap.php`) — no full WordPress install is required. The bootstrap creates a mock `/tmp/wordpress/wp-admin/includes/upgrade.php` for `dbDelta`.

## Demo Content

The `recipes/` directory contains 3,748 recipe fixture files used to populate the demo site. The `scolta-demo-recipes` plugin (`wp-content/plugins/scolta-demo-recipes/`) registers the Recipe custom post type used by this demo.
