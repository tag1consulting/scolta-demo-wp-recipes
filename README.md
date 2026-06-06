# Recipes Demo

A WordPress demonstration site for [Scolta](https://scolta.io) — a zero-infrastructure, AI-powered search re-ranking engine built on Pagefind.

The site is a recipe catalog of ~3,700 recipes exposed through a custom `recipe` post type. It showcases Scolta's **Recipe & Content Catalog** tuning: strong title-match weighting, broad sub-word query expansion, and an AI overview that curates the best matching recipes for a search.

## Quick Start

```bash
git clone <this-repo> scolta-demo-wp-recipes
cd scolta-demo-wp-recipes
ddev start
```

`ddev start` automatically installs the plugin's Composer dependencies, imports the database, fixes WordPress URLs, and activates the plugins. The site is ready at the URL shown at the end of startup (`https://wp-scolta-demo.ddev.site`).

WordPress admin: `/wp-admin/` — credentials as configured in the DDEV environment.

## Requirements

- [DDEV](https://ddev.com) v1.22+
- [OrbStack](https://orbstack.dev), Docker Desktop, or compatible container runtime

## Structure

```
.ddev/                              DDEV project configuration (+ post-start auto-import hooks)
Dockerfile                          Multi-stage production image (Apache + PHP 8.4 + WP-CLI)
.github/workflows/
  build-image.yml                   Tag (v*) -> build & push quay.io/tag1consulting/scolta-demo-wp-recipes
  smoke-test.yml                    Push/PR to main -> build image, verify container + search index
tests/smoke-test.sh                 Smoke test: image builds, container serves, index has content
db/dump.sql.gz                      Full database export (imported on ddev start)
recipes/                            Recipe fixture files (Markdown) imported by the content plugin
wp-content/
  plugins/
    scolta/                         Scolta AI Search plugin (released tag1/scolta-wp)
    scolta-demo-recipes/            Demo content plugin: registers the Recipe CPT + imports fixtures
  uploads/scolta/                   Committed Pagefind search index (served at runtime)
```

## Scolta Integration

The released `tag1/scolta-wp` plugin lives at `wp-content/plugins/scolta/` and consumes `tag1/scolta-php`
via Composer (pinned to the published stable release; no VCS or path repositories). The browser-side
WASM scoring engine and `scolta.js` ship from the `scolta-php` vendor assets and are copied into the
plugin's `assets/` directory by Composer's `post-install`/`post-update` hooks.

Search configuration uses the **`content_catalog`** preset, which raises sub-word expansion frequency to
`0.10` and prioritizes title matches — the tuning the recipe corpus needs.

### Common commands

```bash
# Update plugin dependencies (run on the host)
cd wp-content/plugins/scolta && composer update

# Rebuild the search index
ddev wp scolta build --force

# Plugin status / diagnostics
ddev wp scolta status

# Flush WordPress cache
ddev wp cache flush

# Run plugin tests
ddev exec -d /var/www/html/wp-content/plugins/scolta ./vendor/bin/phpunit
```

## Database

The database export at `db/dump.sql.gz` contains all recipes, pages, settings, and the applied Scolta
configuration. It is imported automatically on `ddev start` via the `post-start` hook in
`.ddev/config.yaml`.

## Deployment

A tag matching `v*` triggers `.github/workflows/build-image.yml`, which builds and pushes the production
image to `quay.io/tag1consulting/scolta-demo-wp-recipes`. The image is a self-contained Apache + PHP 8.4
build serving the committed Pagefind index. Pushes and PRs to `main` run `tests/smoke-test.sh`, which
builds the image, starts the container, and verifies the search index is served.
