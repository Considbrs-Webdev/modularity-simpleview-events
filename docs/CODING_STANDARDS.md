# Coding standards

Applies to new and changed code. Deviations in older code are not by themselves a reason to
rewrite it. Each rule is marked as enforced by tooling or as an observed pattern the team has
chosen to keep. All rules below are proposals until the developer confirms them.

## Language and tooling

- PHP with typed properties and parameters (`?string`, `: bool`). Minimum version is PHP 8.3, declared in `composer.json` and the plugin header. `enforced by tooling` (composer)
- Classes autoloaded via PSR-4: `ModularitySimpleviewEvents\` maps to `source/php/`. `enforced by tooling` (composer)
- Assets built with Vite and Sass: `npm run build`. `enforced by tooling`
- No linter or formatter yet. Roadmap: add PHP_CodeSniffer (PSR-12) so the rules below can be enforced.

## Naming and structure

- One class per file, filename equals class name, in a folder named after its area (`Sync`, `Api`, `Cron`, ...). `observed pattern`
- Namespaces follow the directory path. `enforced by tooling`
- PSR-12 style: 4-space indent, braces on a new line for classes and methods, `camelCase` methods, `UPPER_CASE` class constants, `private` by default. `observed pattern`
- Every class and public method has a PHPDoc block with `@param` and `@return`. `observed pattern`
- Dynamic post type slugs are `sv_{sanitized_mediachannel}`; taxonomies are `{post_type}_category`. `observed pattern`
- Settings live in ACF fields; ACF groups are exported to `source/php/AcfFields/`. `observed pattern`
- Worth improving: some files have trailing whitespace in docblocks and mixed array alignment; a formatter would settle this.

## Error handling

- WordPress functions that can fail are called with the `WP_Error` return enabled and checked with `is_wp_error()`. `observed pattern`
- Failures are logged with `error_log()` using the prefix `Simpleview Events:` and the method returns `false` rather than throwing. `observed pattern`
- Never log API credentials. `observed pattern`

## Testing

- Roadmap: no test framework yet. Proposal: PHPUnit with Brain Monkey or the WordPress test suite, tests in `tests/`, covering the `Sync`, `Query` and `Api` classes first.

## Security

- Escape output and sanitize input with WordPress functions (`esc_*`, `sanitize_*`, `wp_unslash`). `observed pattern`
- Secrets and API keys are never committed or printed. `observed pattern`
- Check capabilities and nonces in admin actions. Roadmap: review that this is applied everywhere in `source/php/Admin/`.

## Dependencies

- PHP dependencies via Composer, JS dependencies via npm; lock files are committed. `observed pattern`
- Municipio, Modularity and ACF are provided by the host site and are not bundled. `observed pattern`

## Git and reviews

- Work on `dev` or feature branches named `feature/...`, `fix/...`, `change/...`; merge into `dev` through pull requests; `dev` is synced to `main` and releases are tagged from `main`. `observed pattern`
- Commit messages are short, imperative sentences in English without prefixes (for example "Add option to hide the upcoming events module when there are no events"). `observed pattern`
