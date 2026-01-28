# Modularity Simpleview Events

A WordPress plugin for integrating events from the Simpleview API into WordPress.

## Features

- Dynamically create post types and taxonomies from the Simpleview API
- Automatic synchronization via WP Cron
- Manual sync trigger from admin settings
- Configurable API credentials and sync frequency

## Installation

1. Place the plugin in `wp-content/plugins/modularity-simpleview-events/`
2. Run `composer dump` to create a new autoloader
3. Activate the plugin through the WordPress admin

## Configuration

1. Navigate to **Simpleview Events > Settings**
2. Configure your API credentials:
   - API Base URL
   - API Key
3. Set your preferred sync frequency (hourly, twice daily, or daily)
4. Use the "Sync Now" button to perform an initial manual sync

## CLI Commands

The plugin provides WP-CLI commands for managing Simpleview events data:

### Sync Events

Sync events from the Simpleview API:

```bash
wp simpleview-events sync
```

**Options:**
- `--wipe` - Wipe all existing data before syncing (requires confirmation)

**Examples:**
```bash
# Run sync
wp simpleview-events sync

# Wipe all existing data and sync fresh
wp simpleview-events sync --wipe
```

### Wipe Data

Delete all synced Simpleview events data (posts, terms, and options):

```bash
wp simpleview-events wipe
```

**Options:**
- `--confirm` - Skip confirmation prompt (use with caution)

**Examples:**
```bash
# Wipe all data (with confirmation prompt)
wp simpleview-events wipe

# Wipe all data (skip confirmation)
wp simpleview-events wipe --confirm
```

### Show Statistics

Display sync statistics including total events, post types, and last sync time:

```bash
wp simpleview-events stats
```

This command shows:
- Total events count
- Draft events count
- Number of post types
- Last sync timestamp
- Post type breakdown with media channel information

## Development

The plugin structure follows the pattern established by `modularity-municipal-calendar`:

- `source/php/` - PHP source files
- `source/php/AcfFields/` - ACF fields for the general settings page
- `source/php/Admin/` - Admin functionality
- `source/php/Api/` - API client for Simpleview
- `source/php/cli/` - CLI commands for syncing events
- `source/php/Cron/` - WP Cron scheduling
- `source/php/PostType/` - Post type mapping logic
- `source/php/Sync/` - Synchronization logic
- `source/php/Taxonomy/` - Taxonomy mapping logic

## License

MIT
