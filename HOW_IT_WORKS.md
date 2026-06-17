# How Modularity Simpleview Events Works

## Overview

The Modularity Simpleview Events plugin synchronizes event data from the Simpleview API into WordPress, creating dynamic post types and taxonomies based on the mediaChannels (calendars) found in the Simpleview data. Each calendar gets its own post type and category taxonomy, allowing for clean separation and leveraging Municipio's built-in archive editor without query manipulation.

## Architecture

### Dynamic Post Types

Instead of using a single post type for all events, the plugin dynamically creates a separate post type for each **mediaChannel** found in the Simpleview API response. This approach:

- Eliminates the need for query manipulation on taxonomy archives
- Allows Modularity's archive editor to work out of the box
- Properly isolates categories per calendar
- Scales automatically as new calendars are added

### Post Type Naming

Post types are created with the following naming convention:
- **Slug**: `sv_{sanitized_mediachannel_name}` (e.g., `sv_naringslivskalender`, `sv_gora`)
- **Labels**: Uses the mediaChannel name from Simpleview

### Category Taxonomies

Each post type gets its own category taxonomy:
- **Slug**: `{post_type_slug}_category` (e.g., `sv_naringslivskalender_category`)
- **Structure**: Flat taxonomy (no hierarchy needed)
- **Categories**: Extracted from products within each mediaChannel

## Data Flow

```
1. Simpleview API → Fetch Products
   ↓
2. Extract products from API response
   (Handles both single product and product array)
   ↓
3. Group products by mediaChannel
   (Products can belong to multiple mediaChannels)
   ↓
4. For each mediaChannel:
   ├─→ Register Post Type (if new)
   ├─→ Register Category Taxonomy (if new)
   ├─→ Extract unique Categories from all products in mediaChannel
   ├─→ Sync Categories to Taxonomy
   └─→ Sync Products to Post Type
       └─→ Extract categories from each product's categoryList
       └─→ Assign categories to post
```

## Key Components

### 1. EventSynchronizer

**Location**: `source/php/Sync/EventSynchronizer.php`

Orchestrates the entire sync process:

- Fetches products from Simpleview API
- Groups products by mediaChannel
- For each mediaChannel:
  - Registers post type and taxonomy
  - Syncs categories
  - Syncs products
- Cleans up unused post types

**Key Methods**:
- `sync()` - Main sync method
- `extractProductsFromResponse()` - Parses API response
- `groupProductsByMediaChannel()` - Groups products by mediaChannel
- `syncMediaChannel()` - Syncs a single mediaChannel

### 2. DynamicPostTypeManager

**Location**: `source/php/PostType/DynamicPostTypeManager.php`

Manages dynamic post type registration and cleanup:

- Registers post types based on mediaChannel names
- Tracks registered post types in WordPress options
- Cleans up unused post types
- Generates post type slugs from mediaChannel names

**Key Methods**:
- `registerPostTypeForMediaChannel()` - Registers a post type
- `getPostTypeSlug()` - Generates slug from name
- `cleanupUnusedPostTypes()` - Removes unused post types

### 3. DynamicTaxonomyManager

**Location**: `source/php/Taxonomy/DynamicTaxonomyManager.php`

Manages category taxonomy registration for each post type:

- Registers category taxonomy for each post type
- Generates taxonomy slugs
- Ensures each post type has its own isolated taxonomy

**Key Methods**:
- `registerCategoryTaxonomyForPostType()` - Registers taxonomy
- `getTaxonomySlug()` - Generates taxonomy slug

### 4. TaxonomyMapper

**Location**: `source/php/Sync/TaxonomyMapper.php`

Extracts and syncs categories from Simpleview products:

- Extracts categories from product's `categoryList.category.categorySubType1List.categorySubType1`
- Handles both single category and category arrays
- Creates/updates taxonomy terms
- Stores Simpleview category ID in term meta for reference

**Key Methods**:
- `syncCategories()` - Syncs categories for a mediaChannel
- `extractCategoriesFromProduct()` - Extracts categories from a single product

### 5. PostMapper

**Location**: `source/php/Sync/PostMapper.php`

Maps Simpleview product data to WordPress posts:

- Maps product fields to post fields (title, content, excerpt)
- Extracts categories from product's categoryList
- Maps categories to synced taxonomy terms
- Creates or updates posts
- Restores archived posts when they return to API

**Key Methods**:
- `mapToPost()` - Maps product data to post array
- `getTaxonomyTerms()` - Extracts and maps categories
- `createOrUpdatePost()` - Creates or updates a post
- `findExistingPost()` - Finds posts including archived ones

### 6. ApiResponseValidator

**Location**: `source/php/Sync/ApiResponseValidator.php`

Validates API responses before sync proceeds:

- Checks response structure and content
- Validates product count > 0
- Warns if product count drops significantly (>50%)
- Prevents sync on invalid responses

**Key Methods**:
- `validate()` - Validates API response
- `getLastSyncProductCount()` - Gets last sync product count

### 7. PostArchiver

**Location**: `source/php/Sync/PostArchiver.php`

Handles archiving, restoring, and pruning of posts:

- Archives posts not in API
- Restores archived posts when they return
- Prunes expired archived posts
- Manages archive timestamps

**Key Methods**:
- `archivePost()` - Archive a post
- `restorePost()` - Restore an archived post
- `pruneExpiredArchives()` - Delete expired archived posts
- `isArchived()` - Check if post is archived

### 8. ArchivedPostStatus

**Location**: `source/php/PostStatus/ArchivedPostStatus.php`

Registers the custom `archived` post status:

- Custom post status for archived posts
- Visible in admin but not on frontend
- Shows in "All" posts list for debugging

## Data Structure

### Simpleview API Response

The plugin expects the following structure from Simpleview API:

```json
{
  "productList": {
    "product": [
      {
        "@id": "12345",
        "name": "Event Name",
        "mediaChannelList": {
          "mediaChannel": {
            "@id": "370",
            "name": "Näringslivskalender",
            "typeId": "WEBSITECONTENT"
          }
        },
        "categoryList": {
          "category": {
            "@id": "39",
            "name": "Category Name",
            "categorySubType1List": {
              "categorySubType1": {
                "@id": "2422",
                "name": "Näringsliv"
              }
            }
          }
        },
        "textList": {
          "text": {
            "@type": "HOVED",
            "#text": "Event description..."
          }
        }
      }
    ]
  }
}
```

### MediaChannel Detection

- Products are filtered by `typeId === "WEBSITECONTENT"`
- Handles both single `mediaChannel` object and `mediaChannel[]` array
- Products can belong to multiple mediaChannels (creates post in each)

### Category Extraction

Categories are extracted from:
- Path: `categoryList.category.categorySubType1List.categorySubType1`
- Fields: `@id` (category ID) and `name` (category name)
- Handles both single category and category arrays

## Sync Process

### Initial Sync

1. **Fetch Products**: Calls Simpleview API and retrieves all products
2. **Extract Products**: Parses the API response (handles single/array)
3. **Group by MediaChannel**: Organizes products by their mediaChannel(s)
4. **For Each MediaChannel**:
   - Register post type (if not exists)
   - Register category taxonomy (if not exists)
   - Extract all unique categories from products
   - Sync categories to taxonomy
   - For each product:
     - Extract categories from product
     - Map to synced taxonomy terms
     - Create or update post
     - Assign categories to post

### Category Assignment

Categories are assigned to posts during sync:

1. Each product's `categoryList` is extracted
2. Category IDs/names are mapped to synced taxonomy terms
3. Terms are assigned to the post using `wp_set_object_terms()`

This is straightforward because the category data is already in the product object.

### Post Type Tracking

Registered post types are tracked in WordPress options:
- **Option Key**: `simpleview_events_registered_post_types`
- **Structure**: `[post_type_slug => [name, id, registered_at]]`

This allows:
- Post types to be re-registered on plugin reactivation
- Cleanup of unused post types
- Tracking of which mediaChannels are active

### Archive & Prune Lifecycle

The plugin archives posts during sync using two independent rules. Both use the same `archived` post status and `_simpleview_archived_at` timestamp — there is no separate "expired" status.

**Archive triggers:**

| Trigger | Condition |
|---------|-----------|
| API removal | Product no longer in Simpleview API for this mediaChannel |
| Date expiry | `simpleview_event_end_date` meta exists **and** value is in the past |

**Date expiry rules:**

- Only `simpleview_event_end_date` is used — **`start_date` is never used for archiving**
- Events **without** an end date meta key are never date-archived (they stay published until removed from the API)
- End date is written only when Simpleview provides `toTime` in the schedule
- Expiry is immediate when `end_date < now` (Europe/Stockholm)
- Events still in the API but past end date stay `archived`; content/meta are refreshed each sync without restoring to `publish`
- Events return to `publish` only if back in the API **and** end date is not past (e.g. extended in Simpleview)

**Post Lifecycle:**

1. **Active Post** (`publish` status)
   - Post exists in Simpleview API and is not past its end date (if end date exists)
   - Visible on frontend and in admin

2. **Archived Post** (`archived` status)
   - Post removed from Simpleview API **or** past its end date
   - Moved to `archived` status during sync
   - Archive timestamp stored in `_simpleview_archived_at` meta
   - Not visible on frontend (public = false)
   - Visible in admin "All" posts list for debugging
   - Can be restored if product returns to API with a future end date

3. **Pruned Post** (permanently deleted)
   - Post has been archived longer than retention period (default: 30 days)
   - Permanently deleted during sync
   - Cannot be recovered

**Sync Process with Archiving:**

1. **Pre-Sync Validation**
   - Validates API response structure
   - Checks for empty or malformed responses (aborts sync)
   - Warns if product count drops significantly (>50%) but proceeds

2. **Sync Products**
   - Creates/updates posts that exist in API
   - Tracks which post IDs were synced
   - Restores archived posts if they return to API and end date is not past
   - Archives posts with past end date after upsert (without restoring first)

3. **Archive Missing Posts**
   - Compares existing posts with synced posts
   - Archives posts not in synced list (if not already archived)
   - Stores archive timestamp

4. **Archive Past End Date Posts**
   - Finds published posts with `simpleview_event_end_date` in the past
   - Archives any not already archived (safety net / backfill)

5. **Prune Expired Archives**
   - Finds archived posts older than retention period
   - Permanently deletes expired archived posts

**Key Components:**

- **ApiResponseValidator**: Validates API responses before sync
- **PostArchiver**: Handles archive/restore/prune and end-date expiry
- **ArchivedPostStatus**: Registers custom `archived` post status

**Configuration:**

- **Archive Retention Days**: Configurable in settings (default: 30 days)
- Controls how long archived posts are retained before deletion
- Range: 1-365 days

## Configuration

### Settings Page

The plugin provides a settings page at **Settings → Simpleview Events** with:

- **API Base URL**: The Simpleview API endpoint
- **API Key**: Authentication key for the API
- **Sync Frequency**: How often to automatically sync (hourly, twice daily, daily)
- **Manual Sync**: Button to trigger sync immediately

### Post Type Registration

Dynamic post types are registered:
- **During Sync**: When products are synced from Simpleview
- **On Init**: Post types are re-registered on WordPress init (from tracking data)

This ensures post types are available even if sync hasn't run yet.

## Benefits

✅ **No Query Manipulation**: Modularity's archive editor works out of the box  
✅ **Category Isolation**: Categories are properly separated per calendar  
✅ **Scalable**: New calendars automatically get post types  
✅ **Clean Architecture**: Each calendar is self-contained  
✅ **Easy Filtering**: Taxonomy archives work correctly without hacks  

## Technical Details

### Post Type Features

Each dynamic post type includes:
- Public archive pages
- REST API support
- Standard post features (title, editor, thumbnail, excerpt)
- Custom rewrite rules

### Taxonomy Features

Each category taxonomy includes:
- Public visibility
- REST API support
- Admin column display
- Tag cloud support

### Cleanup

Unused post types are cleaned up:
- On each sync, compares active mediaChannels with registered post types
- Removes tracking for post types that no longer have products
- Post types remain registered (posts aren't deleted)

## Usage

### Manual Sync

1. Go to **Settings → Simpleview Events**
2. Click **Sync Now** button
3. Monitor sync progress in error logs

### Automatic Sync

Sync runs automatically based on configured frequency:
- **Hourly**: Every hour
- **Twice Daily**: Every 12 hours
- **Daily**: Once per day

### Archive Pages

Each post type has its own archive page:
- URL: `/{post_type_slug}/` (e.g., `/sv_naringslivskalender/`)
- Uses Modularity's archive editor
- Supports taxonomy filtering
- Only shows published posts (archived posts are excluded)

### Category Archives

Each category taxonomy has archive pages:
- URL: `/{taxonomy_slug}/{category_slug}/` (e.g., `/sv_naringslivskalender_category/naringsliv/`)
- Shows all posts in that category
- Works with Modularity's filtering system

### WP-CLI Commands

The plugin provides WP-CLI commands for syncing and managing events data. This is especially useful for:
- **Development testing**: Easily wipe and re-sync data during development
- **Cron jobs**: More reliable than WordPress's pseudo-cron system
- **Server-side automation**: Scripts can call CLI commands without HTTP overhead

#### Available Commands

**Sync Events**
```bash
# Run sync from Simpleview API
wp simpleview-events sync

# Wipe all existing data and sync fresh
wp simpleview-events sync --wipe
```

The sync command now shows archive/prune statistics:
- Created: New posts created
- Updated: Existing posts updated
- Archived: Posts archived (no longer in API)
- Restored: Archived posts restored (returned to API)
- Pruned: Expired archived posts permanently deleted

**Wipe All Data**
```bash
# Wipe all synced data (with confirmation prompt)
wp simpleview-events wipe

# Wipe all synced data (skip confirmation - use with caution)
wp simpleview-events wipe --confirm
```

**Show Statistics**
```bash
# Display sync statistics and post type breakdown
wp simpleview-events stats
```

Statistics now include:
- Total Events: Published posts
- Draft Events: Draft posts
- Archived Events: Archived posts (not in API)
- Post Types: Number of registered post types
- Post Type Breakdown: Shows published and archived counts per post type

#### Using with System Cron

For reliable automated syncing, use system cron with WP-CLI instead of WordPress's pseudo-cron:

```bash
# Add to crontab (runs every hour)
0 * * * * cd /path/to/wordpress && wp simpleview-events sync --quiet

# Or run twice daily
0 */12 * * * cd /path/to/wordpress && wp simpleview-events sync --quiet
```

The `--quiet` flag suppresses output, making it suitable for cron jobs.

#### Development Workflow

When developing new sync features (e.g., archiving logic), use the wipe command to start fresh:

```bash
# Wipe existing data
wp simpleview-events wipe --confirm

# Test sync with new functionality
wp simpleview-events sync

# Check results
wp simpleview-events stats
```

## Troubleshooting

### Post Types Not Appearing

- Ensure sync has run at least once
- Check that products have valid mediaChannels
- Verify mediaChannels have `typeId === "WEBSITECONTENT"`

### Categories Not Assigning

- Check that products have `categoryList` data
- Verify category structure matches expected format
- Check error logs for sync errors

### Posts Not Appearing After Sync

- Check if posts were archived (view in admin "All" posts list)
- Verify posts exist in Simpleview API
- Check sync results for archive/prune counts
- Review error logs for sync warnings

### Archived Posts Not Being Deleted

- Check Archive Retention Days setting (default: 30 days)
- Verify posts have been archived longer than retention period
- Check sync logs for prune operations
- Ensure sync is running regularly (1-2 times per hour recommended)

### Archive Pages Not Working

- Flush rewrite rules: **Settings → Permalinks → Save**
- Ensure post types are registered (check `simpleview_events_registered_post_types` option)
- Verify archive support is enabled for post types

## Post Types Not Showing in Admin Sidebar - Fixes and Solutions

### Problem

Dynamic post types created during sync were not appearing in the WordPress admin sidebar, even though:
- Posts existed in the database with the correct post type
- Post types were registered during AJAX sync
- The `simpleview_events_registered_post_types` option contained the correct data

### Root Cause Analysis

The issue had multiple contributing factors:

1. **WordPress Admin Menu Requirement**: WordPress requires post types to be registered on **every page load** during the `init` hook to appear in the admin menu. Post types registered only during AJAX sync requests are not visible on subsequent admin page loads.

2. **Conditional Registration**: The original code checked `if (!post_type_exists($postTypeSlug))` before registering, which prevented re-registration on admin page loads. This check was intended to avoid duplicate registrations, but it prevented the admin menu from being populated.

3. **Option Persistence Issues**: In multisite environments or with certain caching configurations, the `simpleview_events_registered_post_types` option might not be reliably loaded on admin page loads, leaving the system without information about which post types to register.

4. **Missing Taxonomy Registration**: Taxonomies also need to be registered on every `init` hook to appear in the admin interface.

### Reliable Solution Implemented

The following changes ensure post types always appear in the admin sidebar:

#### 1. Always Register on Init Hook

**File**: `source/php/App.php`

Post types and taxonomies are now **always** registered on the `init` hook, regardless of whether they already exist:

```php
add_action('init', [$this, 'registerDynamicPostTypes'], 20);
add_action('init', [$this, 'registerDynamicTaxonomies'], 21);
```

**Why this works**: WordPress safely handles duplicate post type registrations. Re-registering an existing post type updates its configuration without errors, and ensures the admin menu is populated.

#### 2. Removed Conditional Checks

**Files**: 
- `source/php/PostType/DynamicPostTypeManager.php`
- `source/php/Taxonomy/DynamicTaxonomyManager.php`

Removed the `if (!post_type_exists($postTypeSlug))` and `if (!taxonomy_exists($taxonomySlug))` checks that prevented re-registration.

**Why this works**: WordPress's `register_post_type()` and `register_taxonomy()` functions are idempotent - calling them multiple times with the same arguments is safe and updates the registration.

#### 3. Database Discovery Fallback

**File**: `source/php/App.php`

Added `discoverPostTypesFromDatabase()` method that queries the database directly if the option is empty:

```php
private function discoverPostTypesFromDatabase(): array
{
    global $wpdb;
    
    // Find all post types that start with 'sv_' and have posts
    $postTypes = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT post_type FROM {$wpdb->posts} 
        WHERE post_type LIKE %s 
        AND post_status != 'trash'
        LIMIT 20",
        'sv_%'
    ));
    
    // Reconstruct post type info from post meta
    // ...
}
```

**Why this works**: Even if the option is lost or not loaded correctly, the system can recover by discovering existing post types from the database and reconstructing their metadata.

#### 4. MediaChannel Metadata Storage

**Files**:
- `source/php/Sync/PostMapper.php`
- `source/php/Sync/EventSynchronizer.php`

Each post now stores `simpleview_media_channel_name` and `simpleview_media_channel_id` in post meta during creation/update:

```php
if ($mediaChannelName) {
    $postData['meta_input']['simpleview_media_channel_name'] = $mediaChannelName;
}
if ($mediaChannelId) {
    $postData['meta_input']['simpleview_media_channel_id'] = $mediaChannelId;
}
```

**Why this works**: This metadata enables the database discovery fallback to correctly reconstruct post type information even when the option is missing.

#### 5. Options Cache Flushing

**File**: `source/php/App.php`

Added `wp_cache_delete($optionKey, 'options')` before reading the option to avoid stale cache issues:

```php
if (empty($registered)) {
    // Try to flush options cache and re-read
    wp_cache_delete($optionKey, 'options');
    $registered = get_option($optionKey, []);
    
    // Fallback to database discovery if still empty
    if (empty($registered)) {
        $registered = $this->discoverPostTypesFromDatabase();
        // ...
    }
}
```

**Why this works**: In multisite or heavily cached environments, options can be cached incorrectly. Flushing the cache ensures we read the latest value.

### How the Solution Works

1. **On Every Admin Page Load**:
   - `App::registerDynamicPostTypes()` is called on `init` hook (priority 20)
   - Reads `simpleview_events_registered_post_types` option
   - If empty, flushes cache and tries again
   - If still empty, discovers post types from database
   - Registers each post type (always, no conditional check)

2. **During Sync**:
   - Post types are registered during sync (AJAX or cron)
   - Option is updated with post type information
   - Posts are created with mediaChannel metadata

3. **Recovery**:
   - If option is lost, database discovery finds existing `sv_*` post types
   - Reconstructs metadata from post meta
   - Saves back to option for future use

### Verification Steps

To verify the solution is working:

1. **Check Post Types in Database**:
   ```sql
   SELECT DISTINCT post_type FROM wp_posts WHERE post_type LIKE 'sv_%';
   ```

2. **Check Option Value**:
   ```php
   $registered = get_option('simpleview_events_registered_post_types', []);
   var_dump($registered);
   ```

3. **Check Post Meta**:
   ```sql
   SELECT post_id, meta_key, meta_value 
   FROM wp_postmeta 
   WHERE meta_key IN ('simpleview_media_channel_name', 'simpleview_media_channel_id')
   LIMIT 10;
   ```

4. **Check Admin Sidebar**:
   - Navigate to WordPress admin
   - Post types should appear in the sidebar
   - If not, check error logs for registration errors

### Additional Troubleshooting

If post types still don't appear after these fixes:

1. **Check for Plugin Conflicts**:
   - Deactivate other plugins temporarily
   - Check if post types appear

2. **Check User Capabilities**:
   - Ensure user has `edit_posts` capability
   - Check if post types have `show_ui => true`

3. **Check for JavaScript Errors**:
   - Open browser console
   - Look for errors that might prevent menu rendering

4. **Verify Hook Priority**:
   - Ensure `init` hook priority (20, 21) doesn't conflict with other plugins
   - Try adjusting priorities if needed

5. **Check Multisite Context**:
   - In multisite, ensure you're on the correct site
   - Options are site-specific in multisite

6. **Manual Registration Test**:
   ```php
   // Add to functions.php temporarily
   add_action('init', function() {
       $registered = get_option('simpleview_events_registered_post_types', []);
       foreach ($registered as $slug => $info) {
           echo "Should register: {$slug}\n";
       }
   }, 999);
   ```

### Best Practices

To prevent this issue in the future:

1. **Always Register on Init**: Dynamic post types should always be registered on the `init` hook, not conditionally
2. **Store Recovery Metadata**: Store enough metadata in posts to reconstruct post type information
3. **Implement Fallbacks**: Always have a fallback mechanism if options fail
4. **Test in Multisite**: Test in multisite environments where options behave differently
5. **Cache Considerations**: Be aware of WordPress object cache and flush when necessary
