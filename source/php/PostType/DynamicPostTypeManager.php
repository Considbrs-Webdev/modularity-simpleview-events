<?php

namespace ModularitySimpleviewEvents\PostType;

/**
 * Class DynamicPostTypeManager
 * 
 * Manages registration and cleanup of dynamic post types based on Simpleview mediaChannel names.
 * 
 * @package ModularitySimpleviewEvents\PostType
 */
class DynamicPostTypeManager
{
    private const OPTION_KEY = 'simpleview_events_registered_post_types';

    /**
     * Register a post type for a mediaChannel
     * 
     * @param string $mediaChannelName The name of the mediaChannel
     * @param string $mediaChannelId The ID of the mediaChannel
     * @return string The post type slug
     */
    public function registerPostTypeForMediaChannel(string $mediaChannelName, string $mediaChannelId): string
    {
        $postTypeSlug = $this->getPostTypeSlug($mediaChannelName);
        $cleanPostTypeSlug = $this->cleanPostTypeSlug($postTypeSlug);

        // Always register - WordPress handles duplicate registrations gracefully
        // This ensures post types appear in admin menu on every page load
        // The check for existing post type is removed because:
        // 1. Post types must be registered on every init to appear in admin menu
        // 2. WordPress safely handles re-registration of existing post types
        // 3. During sync (AJAX), post types are registered, but they need to be
        //    re-registered on admin page load to appear in the sidebar

        $labels = [
            'name'                  => $mediaChannelName,
            'singular_name'         => $mediaChannelName,
            'archives'              => $mediaChannelName,
            'menu_name'             => $mediaChannelName,
            'name_admin_bar'        => $mediaChannelName,
            'add_new'               => __('Add New', 'modularity-simpleview-events'),
            'add_new_item'          => sprintf(__('Add New %s', 'modularity-simpleview-events'), $mediaChannelName),
            'new_item'              => sprintf(__('New %s', 'modularity-simpleview-events'), $mediaChannelName),
            'edit_item'             => sprintf(__('Edit %s', 'modularity-simpleview-events'), $mediaChannelName),
            'view_item'             => sprintf(__('View %s', 'modularity-simpleview-events'), $mediaChannelName),
            'all_items'             => sprintf(__('All %s', 'modularity-simpleview-events'), $mediaChannelName),
            'search_items'          => sprintf(__('Search %s', 'modularity-simpleview-events'), $mediaChannelName),
            'parent_item_colon'     => sprintf(__('Parent %s:', 'modularity-simpleview-events'), $mediaChannelName),
            'not_found'             => sprintf(__('No %s found.', 'modularity-simpleview-events'), strtolower($mediaChannelName)),
            'not_found_in_trash'    => sprintf(__('No %s found in Trash.', 'modularity-simpleview-events'), strtolower($mediaChannelName)),
            'featured_image'        => __('Featured Image', 'modularity-simpleview-events'),
            'set_featured_image'    => __('Set featured image', 'modularity-simpleview-events'),
            'remove_featured_image' => __('Remove featured image', 'modularity-simpleview-events'),
            'use_featured_image'    => __('Use as featured image', 'modularity-simpleview-events'),
            'insert_into_item'      => sprintf(__('Insert into %s', 'modularity-simpleview-events'), strtolower($mediaChannelName)),
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', 'modularity-simpleview-events'), strtolower($mediaChannelName)),
            'filter_items_list'     => sprintf(__('Filter %s list', 'modularity-simpleview-events'), strtolower($mediaChannelName)),
            'items_list_navigation' => sprintf(__('%s list navigation', 'modularity-simpleview-events'), $mediaChannelName),
            'items_list'            => sprintf(__('%s list', 'modularity-simpleview-events'), $mediaChannelName),
        ];

        $args = [
            'labels'             => $labels,
            'description'        => sprintf(__('Events synced from Simpleview API for %s', 'modularity-simpleview-events'), $mediaChannelName),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => [
                'slug' => $cleanPostTypeSlug,
                'with_front' => false,
            ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'show_in_rest'       => true,
        ];

        register_post_type($postTypeSlug, $args);

        // Track this post type (update tracking even if already registered)
        $this->trackPostType($postTypeSlug, $mediaChannelName, $mediaChannelId);

        return $postTypeSlug;
    }

    /**
     * Generate post type slug from mediaChannel name
     * 
     * @param string $mediaChannelName The mediaChannel name
     * @return string The sanitized post type slug
     */
    public function getPostTypeSlug(string $mediaChannelName): string
    {
        // Sanitize: lowercase, replace spaces/special chars with underscores, prefix with sv_
        $slug = sanitize_title($mediaChannelName);
        $slug = str_replace('-', '_', $slug);
        return 'sv_' . $slug;
    }

    /**
     * Clean post type slug by removing the sv_ prefix
     * 
     * @param string $postTypeSlug The post type slug
     * @return string The cleaned post type slug
     */
    public function cleanPostTypeSlug(string $postTypeSlug): string
    {
        return str_replace('sv_', '', $postTypeSlug);
    }

    /**
     * Get all registered post types
     * 
     * @return array Array of post type slugs
     */
    public function getAllRegisteredPostTypes(): array
    {
        $registered = get_option(self::OPTION_KEY, []);
        return array_keys($registered);
    }

    /**
     * Track a post type registration
     * 
     * @param string $postTypeSlug The post type slug
     * @param string $mediaChannelName The mediaChannel name
     * @param string $mediaChannelId The mediaChannel ID
     * @return void
     */
    private function trackPostType(string $postTypeSlug, string $mediaChannelName, string $mediaChannelId): void
    {
        $registered = get_option(self::OPTION_KEY, []);
        $registered[$postTypeSlug] = [
            'name' => $mediaChannelName,
            'id' => $mediaChannelId,
            'registered_at' => current_time('mysql'),
        ];
        update_option(self::OPTION_KEY, $registered);
    }

    /**
     * Unregister a post type (cleanup)
     * 
     * Note: WordPress doesn't have an unregister_post_type function,
     * but we can remove it from tracking and it won't be re-registered.
     * 
     * @param string $postTypeSlug The post type slug to unregister
     * @return void
     */
    public function unregisterPostType(string $postTypeSlug): void
    {
        $registered = get_option(self::OPTION_KEY, []);
        if (isset($registered[$postTypeSlug])) {
            unset($registered[$postTypeSlug]);
            update_option(self::OPTION_KEY, $registered);
        }
    }

    /**
     * Clean up post types that are no longer in use
     * 
     * @param array $activeMediaChannels Array of active mediaChannel IDs
     * @return void
     */
    public function cleanupUnusedPostTypes(array $activeMediaChannels): void
    {
        $registered = get_option(self::OPTION_KEY, []);

        foreach ($registered as $postTypeSlug => $info) {
            $mediaChannelId = $info['id'] ?? '';
            if (!in_array($mediaChannelId, $activeMediaChannels, true)) {
                $this->unregisterPostType($postTypeSlug);
            }
        }
    }
}
