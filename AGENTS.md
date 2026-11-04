# AGENTS.md

Instructions for AI tools working in this repository. Read this first.

## Project

WordPress plugin (module for the Municipio/Modularity stack) that syncs events from the Simpleview API into WordPress. A post type and a category taxonomy are created dynamically per Simpleview mediaChannel (calendar). Built with PHP 8.3+ (PSR-4, namespace `ModularitySimpleviewEvents\`), ACF, and Vite/Sass for assets. Used on pitea.se.

## Setup and commands

| Task | Command |
| --- | --- |
| Install | `composer install` and `npm ci` |
| Run locally | Place in `wp-content/plugins/`, activate in WP admin, configure under **Simpleview Events > Settings** |
| Sync events | `wp simpleview-events sync` (`--wipe` to wipe first) |
| Other CLI | `wp simpleview-events wipe`, `wp simpleview-events stats` |
| Test | Not set up yet (see Roadmap) |
| Lint / format | Not set up yet (see Roadmap) |
| Build | `npm run build` (dev: `npm run build:dev`, watch: `npm run watch`) or `php build.php` (composer dump-autoload + npm ci + build) |

## Structure

- `modularity-simpleview-events.php` - plugin bootstrap (constants, autoload, ACF import, activation hook).
- `source/php/` - PHP source, one namespace per folder: `Api` (Simpleview client), `Sync` (synchronizer, mappers, archiver, wiper), `PostType` / `Taxonomy` (dynamic registration), `Cron`, `Cli`, `Admin`, `AcfFields`, `Query`, `Cache`, `Customizer`, `PostStatus`, `Module` (upcoming events module and views), `Helper`.
- `source/php/AcfFields/` - ACF field groups, auto-exported/imported by AcfExportManager. Edit the groups in ACF, not by hand.
- `source/js`, `source/sass` - asset sources built by Vite.
- `views/` - plugin views. `languages/` - `.pot`/`.po`/`.mo` (text domain `modularity-simpleview-events`).
- Off-limits / generated: `vendor/`, `node_modules/`, `assets/dist/` (all gitignored), `debug.log`, `simpleview*.json`, and the `.mo` file (compiled from `.po`).

## Conventions

- Follow `docs/CODING_STANDARDS.md` for new and changed code.
- Do not refactor unrelated code. Deviations in older code are not a reason to rewrite it.
- Comments explain why, briefly. Never refer to documents from code comments; restate the relevant information in the comment instead.
- Write and update tests together with the code. Run the tests before saying work is done. (No test setup exists yet; see Roadmap.)
- User-facing strings use the text domain `modularity-simpleview-events`.

## Release

- Development happens on `dev`. When done, `dev` is merged into `main` and the release is tagged from `main`.
- The version is declared in the `Version:` header of `modularity-simpleview-events.php`. `package.json` is private and its version is not bumped. Changes are logged in `CHANGELOG.md`, and its entry is reused verbatim as the GitHub release notes.
- `CHANGELOG.md` and GitHub release notes are always written in English.
- `assets/dist/` is not committed; the Municipio deploy builds it by running `npm run build` or `php build.php`.

## Roadmap

- Add automated tests (proposal: PHPUnit with Brain Monkey, tests in `tests/`, starting with `Sync`, `Query` and `Api`).
- Add a linter/formatter (proposal: PHP_CodeSniffer with PSR-12).
- Review capability and nonce checks in `source/php/Admin/`.
- Until these exist, do not add test or lint tooling unless asked.

## Where to find more

- `README.md` - installation, configuration, CLI commands.
- `HOW_IT_WORKS.md` - architecture and sync data flow.

## Security and data

- Secrets come from environment variables or the project's secret management. Never read, print or commit their values, and never put them in instructions, code or logs.
- The Simpleview API base URL and key are stored in WordPress/ACF settings. Never log or print them, and do not commit `simpleview*.json` dumps.
- Personal, customer and other sensitive data may only be used when approved by the customer and project owner. Use synthetic or anonymized data where possible.
- Project-specific restrictions: none beyond the above.

## Working with AI

- The developer is responsible for understanding, reviewing and verifying everything AI produces.
- If a guard or permission blocks an action, do not work around it. Report it and let the developer decide.
- If these instructions cause unnecessary steps or unwanted behavior, tell the developer so this file can be improved.
