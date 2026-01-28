<?php

namespace ModularitySimpleviewEvents;

use ModularitySimpleviewEvents\Admin\Settings;
use ModularitySimpleviewEvents\Cron\SyncScheduler;
use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;
use ModularitySimpleviewEvents\Taxonomy\DynamicTaxonomyManager;
use ModularitySimpleviewEvents\PostStatus\ArchivedPostStatus;

/**
 * Class App
 * 
 * Main application bootstrap class.
 * Initialize your plugin components here.
 * 
 * @package ModularitySimpleviewEvents
 */
class App
{
    public function __construct()
    {
        // Initialize settings page
        new Settings();

        // Initialize cron scheduler
        new SyncScheduler();

        // Register archived post status on init
        add_action('init', [$this, 'registerArchivedPostStatus'], 10);

        // Register dynamic post types and taxonomies on init (they're created during sync)
        // Must be registered on every init to appear in admin menu
        add_action('init', [$this, 'registerDynamicPostTypes'], 20);
        add_action('init', [$this, 'registerDynamicTaxonomies'], 21);
    }

    /**
     * Register the archived post status
     * 
     * @return void
     */
    public function registerArchivedPostStatus(): void
    {
        $archivedStatus = new ArchivedPostStatus();
        $archivedStatus->register();
    }

    /**
     * Register dynamic post types that were created during sync
     * 
     * This ensures post types are available even if sync hasn't run yet.
     * Post types MUST be registered on every init hook to appear in admin menu.
     * 
     * @return void
     */
    public function registerDynamicPostTypes(): void
    {
        $postTypeManager = new DynamicPostTypeManager();
        $optionKey = 'simpleview_events_registered_post_types';
        $optionValue = get_option($optionKey, 'NOT_FOUND');
        $registered = is_array($optionValue) ? $optionValue : [];

        if (empty($registered)) {
            // Try to flush options cache and re-read
            wp_cache_delete($optionKey, 'options');
            $registered = get_option($optionKey, []);
            
            // Fallback: If option is still empty, discover post types from existing posts
            if (empty($registered)) {
                $registered = $this->discoverPostTypesFromDatabase();
                
                // Save discovered post types to option
                if (!empty($registered)) {
                    update_option($optionKey, $registered);
                }
            }
        }

        foreach ($registered as $postTypeSlug => $info) {
            // Always register on init - WordPress handles duplicates gracefully
            // Post types must be registered on every page load to appear in admin menu
            $postTypeManager->registerPostTypeForMediaChannel(
                $info['name'] ?? '',
                $info['id'] ?? ''
            );
        }
    }

    /**
     * Register dynamic taxonomies that were created during sync
     * 
     * Taxonomies must be registered on every init hook to appear in admin.
     * 
     * @return void
     */
    public function registerDynamicTaxonomies(): void
    {
        $taxonomyManager = new DynamicTaxonomyManager();
        $registered = get_option('simpleview_events_registered_post_types', []);

        foreach ($registered as $postTypeSlug => $info) {
            // Always register taxonomy - WordPress handles duplicates gracefully
            $taxonomyManager->registerCategoryTaxonomyForPostType(
                $postTypeSlug,
                $info['name'] ?? ''
            );
        }
    }

    /**
     * Discover post types from existing posts in database
     * 
     * Fallback method when option is empty - finds post types that start with 'sv_'
     * and have posts, then reconstructs the option data from post meta
     * 
     * @return array Array of post type data in same format as option
     */
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
        
        if (empty($postTypes)) {
            return [];
        }
        
        $discovered = [];
        
        foreach ($postTypes as $postTypeSlug) {
            // Try to find a post with this post type that has simpleview_id meta
            $postId = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND pm.meta_key = 'simpleview_id'
                AND p.post_status != 'trash'
                LIMIT 1",
                $postTypeSlug
            ));
            
            if ($postId) {
                // Get mediaChannel info from post meta (stored during sync)
                $mediaChannelName = get_post_meta($postId, 'simpleview_media_channel_name', true);
                $mediaChannelId = get_post_meta($postId, 'simpleview_media_channel_id', true);
                
                // Fallback: reconstruct name from post type slug if meta not found
                if (empty($mediaChannelName)) {
                    $mediaChannelName = str_replace('sv_', '', $postTypeSlug);
                    $mediaChannelName = str_replace('_', ' ', $mediaChannelName);
                    $mediaChannelName = ucwords($mediaChannelName);
                }
                
                $discovered[$postTypeSlug] = [
                    'name' => $mediaChannelName,
                    'id' => $mediaChannelId ?: 'discovered',
                    'registered_at' => current_time('mysql'),
                ];
            }
        }
        
        return $discovered;
    }

    /**
     * Handle plugin deactivation
     * 
     * Note: We don't delete posts or terms, just clean up tracking
     * 
     * @return void
     */
    public function onDeactivation(): void
    {
        // Optionally clean up registered post types tracking
        // We keep it so post types can be re-registered on reactivation
    }
}
