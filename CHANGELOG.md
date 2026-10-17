# Changelog

All notable changes to this project will be documented in this file.

## 1.1.0 - 2026-10-17

The migration now ships as a publishable stub, like the other packages: `create_translation_lines_table`, declared with `hasMigrations()`. It is no longer loaded from the package.

New installs:

```bash
php artisan vendor:publish --tag="translation-manager-migrations"
php artisan migrate

```
Upgrading from 1.0 (which ran the migration from the package): nothing to do. Publishing and migrating is safe too — the migration skips the table when it already exists.

## 1.0.0 - 2026-10-07

First release: database overrides for app, JSON and package translations, `translation-manager:scan` and `translation-manager:export`.

## [Unreleased]
