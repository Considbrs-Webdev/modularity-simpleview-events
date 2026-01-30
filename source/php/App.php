<?php

namespace ModularitySimpleviewEvents;

use ModularitySimpleviewEvents\Admin\Settings;
use ModularitySimpleviewEvents\Cron\SyncScheduler;
use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;
use ModularitySimpleviewEvents\Taxonomy\DynamicTaxonomyManager;
use ModularitySimpleviewEvents\PostStatus\ArchivedPostStatus;
use ModularitySimpleviewEvents\ApplyDecorator\ApplySimpleviewEventData;

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
    /**
     * Memoized dynamic post types list for current request.
     *
     * @var string[]|null
     */
    private ?array $registeredSimpleviewPostTypes = null;

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

        add_filter('Municipio/viewPaths', [$this, 'addViewPaths'], 999);

        add_filter('Municipio/DecoratePostObject', function ($postObject) {
            if (!is_object($postObject) || !method_exists($postObject, 'getPostType') || !method_exists($postObject, 'getId')) {
                return $postObject;
            }

            $postType = $postObject->getPostType();
            $dynamicPostTypes = $this->getRegisteredSimpleviewPostTypes();

            if (empty($dynamicPostTypes) || !in_array($postType, $dynamicPostTypes, true)) {
                return $postObject;
            }

            $postId = $postObject->getId();
            $wpPost = get_post($postId);

            if (!$wpPost || $wpPost->post_type !== $postType) {
                return $postObject;
            }

            $decoratedPost = (new ApplySimpleviewEventData())->apply($wpPost);

            // PostObjectInterface supports dynamic properties via __get/__set
            if (isset($decoratedPost->simpleviewEventData)) {
                $postObject->simpleviewEventData = $decoratedPost->simpleviewEventData;
            }

            return $postObject;
        }, 10, 1);
    }

    /**
     * Add plugin view paths to Municipio for custom templates
     *
     * NOTE: BladeService.makeView() prepends paths in a loop, which REVERSES the order!
     * So to be checked FIRST, our path must be LAST in the array.
     *
     * @param array $paths The existing view paths
     * @return array The modified view paths
     */
    public function addViewPaths(array $paths): array
    {
        if ($this->isSimpleviewEventsContext()) {
            // Add at the END - will be prepended LAST, so checked FIRST
            $paths[] = MODULARITYSIMPLEVIEWEVENTS_PATH . 'views';
        }

        return $paths;
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
     * Get registered dynamic post type slugs for Simpleview events.
     *
     * @return string[]
     */
    private function getRegisteredSimpleviewPostTypes(): array
    {
        if ($this->registeredSimpleviewPostTypes !== null) {
            return $this->registeredSimpleviewPostTypes;
        }

        $optionKey = 'simpleview_events_registered_post_types';
        $optionValue = get_option($optionKey, []);
        $registered = is_array($optionValue) ? $optionValue : [];

        if (empty($registered)) {
            wp_cache_delete($optionKey, 'options');
            $optionValue = get_option($optionKey, []);
            $registered = is_array($optionValue) ? $optionValue : [];

            if (empty($registered)) {
                $registered = $this->discoverPostTypesFromDatabase();
            }
        }

        $this->registeredSimpleviewPostTypes = array_values(array_filter(array_keys($registered), 'is_string'));

        return $this->registeredSimpleviewPostTypes;
    }

    /**
     * Determine whether the current request should use plugin templates.
     *
     * Applies to:
     * - Single pages for any dynamic sv_* post type
     * - Archive pages for any dynamic sv_* post type
     * - Category taxonomy archives for any dynamic sv_* post type (sv_*_category)
     */
    private function isSimpleviewEventsContext(): bool
    {
        $postTypes = $this->getRegisteredSimpleviewPostTypes();

        if (empty($postTypes)) {
            return false;
        }

        // More robust than conditional tags alone: check queried vars.
        // This helps ensure our view path is registered early enough for PostsList rendering.
        $queriedPostType = get_query_var('post_type');
        if (is_string($queriedPostType) && in_array($queriedPostType, $postTypes, true)) {
            return true;
        }
        if (is_array($queriedPostType) && !empty(array_intersect($queriedPostType, $postTypes))) {
            return true;
        }

        if (is_singular($postTypes) || is_post_type_archive($postTypes)) {
            return true;
        }

        $taxonomies = array_map(
            static fn(string $postType): string => $postType . '_category',
            $postTypes
        );

        return !empty($taxonomies) && is_tax($taxonomies);
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
