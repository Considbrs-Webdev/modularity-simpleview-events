# Changelog

## [1.0.0] - 2026-11-04

### Added
- Synchronization of events from the Simpleview API into WordPress, with manual and scheduled (WP Cron) sync and WP-CLI commands (`sync`, `wipe`, `stats`).
- A dedicated post type and category taxonomy per Simpleview calendar (mediaChannel), created dynamically during sync.
- Settings page for API credentials, filters (DB owner ID, distribution channel, language, country), a connection test and sync frequency.
- Archive table on the settings page and default values for archives in the Customizer.
- Event card and single view with a calendar badge, metadata and an accessible link.
- Upcoming events module with an editable calendar link label and an option to hide the module when there are no events.
- Typesense integration for searching and indexing events.
- Swedish translation.
- Purging of object cache fragments and LiteSpeed pages for event modules when events change.

### Changed
- Events are archived automatically when their end date has passed, and when a calendar no longer returns events from the API.
- Empty archives are kept when a calendar has no events.
- Lists show an event-specific message when there are no events.
- Minimum requirement is now PHP 8.3.

### Fixed
- Archived events no longer appear in frontend listings.
- Events without a date are shown in Municipio archives.
- Rewrite rules are flushed when a new calendar or a removed archive affects post types.
