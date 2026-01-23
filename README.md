# Modularity Simpleview Events

A WordPress plugin for integrating events from the Simpleview API into WordPress.

## Features

- Custom post type `simpleview_event` for synced events
- Hierarchical taxonomies: `sv_department` and `sv_category`
- Automatic synchronization via WP Cron
- Manual sync trigger from admin settings
- Configurable API credentials and sync frequency

## Installation

1. Place the plugin in `wp-content/plugins/modularity-simpleview-events/`
2. Run `composer install` to install dependencies
3. Activate the plugin through the WordPress admin

## Configuration

1. Navigate to **Simpleview Events > Settings**
2. Configure your API credentials:
   - API Base URL
   - API Key
3. Set your preferred sync frequency (hourly, twice daily, or daily)
4. Use the "Sync Now" button to perform an initial manual sync

## Development

The plugin structure follows the pattern established by `modularity-municipal-calendar`:

- `source/php/` - PHP source files
- `source/php/Api/` - API client for Simpleview
- `source/php/Sync/` - Synchronization logic
- `source/php/Cron/` - WP Cron scheduling
- `views/` - Blade templates (placeholder for future use)

## API Integration

The plugin includes placeholder methods for API integration. Once the Simpleview API structure is provided, update:

- `SimpleviewClient::fetchEvents()` - API endpoint and request format
- `TaxonomyMapper::syncDepartments()` - Department extraction logic
- `TaxonomyMapper::syncCategories()` - Category extraction logic
- `PostMapper::mapToPost()` - Event data to post mapping
- `PostMapper::getTaxonomyTerms()` - Taxonomy assignment logic

## License

MIT
