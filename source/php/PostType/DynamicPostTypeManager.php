<?php

namespace ModularitySimpleviewEvents\PostType;

use ModularitySimpleviewEvents\Customizer\ArchiveDefaultsApplicator;

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

    public const FLUSH_REWRITE_ADD_OPTION = 'simpleview_events_flush_rewrite_add';

    public const FLUSH_REWRITE_REMOVE_OPTION = 'simpleview_events_flush_rewrite_remove';

    /** @deprecated Legacy flag; treated as deferred removal flush on init. */
    public const FLUSH_REWRITE_RULES_OPTION = 'simpleview_events_flush_rewrite_rules';

    /**
     * Register a post type for a mediaChannel
     * 
     * @param string $mediaChannelName The name of the mediaChannel
     * @param string $mediaChannelId The ID of the mediaChannel
     * @param bool $persistRegistry Whether to update the durable registry (sync only)
     * @return string The post type slug
     */
    public function registerPostTypeForMediaChannel(
        string $mediaChannelName,
        string $mediaChannelId,
        bool $persistRegistry = true
    ): string {
        $postTypeSlug = $this->getPostTypeSlug($mediaChannelName);
        $cleanPostTypeSlug = $this->cleanPostTypeSlug($postTypeSlug);

        $labels = [
            'name'                  => $mediaChannelName,
            'singular_name'         => $mediaChannelName,
            'archives'              => $mediaChannelName,
            'menu_name'             => $mediaChannelName,
            'name_admin_bar'        => $mediaChannelName,
            'add_new'               => __('Add New', 'modularity-simpleview-events'),
            'add_new_item'          => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('Add New %s', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
            'new_item'              => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('New %s', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
            'edit_item'             => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('Edit %s', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
            'view_item'             => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('View %s', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
            'all_items'             => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('All %s', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
            'search_items'          => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('Search %s', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
            'parent_item_colon'     => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('Parent %s:', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
            'not_found'             => sprintf(
                /* translators: %s: Media channel (post type) name (lowercase). */
                __('No %s found.', 'modularity-simpleview-events'),
                strtolower($mediaChannelName)
            ),
            'not_found_in_trash'    => sprintf(
                /* translators: %s: Media channel (post type) name (lowercase). */
                __('No %s found in Trash.', 'modularity-simpleview-events'),
                strtolower($mediaChannelName)
            ),
            'featured_image'        => __('Featured Image', 'modularity-simpleview-events'),
            'set_featured_image'    => __('Set featured image', 'modularity-simpleview-events'),
            'remove_featured_image' => __('Remove featured image', 'modularity-simpleview-events'),
            'use_featured_image'    => __('Use as featured image', 'modularity-simpleview-events'),
            'insert_into_item'      => sprintf(
                /* translators: %s: Media channel (post type) name (lowercase). */
                __('Insert into %s', 'modularity-simpleview-events'),
                strtolower($mediaChannelName)
            ),
            'uploaded_to_this_item' => sprintf(
                /* translators: %s: Media channel (post type) name (lowercase). */
                __('Uploaded to this %s', 'modularity-simpleview-events'),
                strtolower($mediaChannelName)
            ),
            'filter_items_list'     => sprintf(
                /* translators: %s: Media channel (post type) name (lowercase). */
                __('Filter %s list', 'modularity-simpleview-events'),
                strtolower($mediaChannelName)
            ),
            'items_list_navigation' => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('%s list navigation', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
            'items_list'            => sprintf(
                /* translators: %s: Media channel (post type) name. */
                __('%s list', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
        ];

        $args = [
            'labels'             => $labels,
            'description'        => sprintf(
                /* translators: %s: Media channel name. */
                __('Events synced from Simpleview API for %s', 'modularity-simpleview-events'),
                $mediaChannelName
            ),
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

        if ($persistRegistry) {
            $this->trackPostType($postTypeSlug, $mediaChannelName, $mediaChannelId);
        }

        (new ArchiveDefaultsApplicator())->applyDefaults($postTypeSlug);

        return $postTypeSlug;
    }

    /**
     * Generate post type slug from mediaChannel name.
     * 
     * WordPress enforces a maximum of 20 characters for post type slugs.
     * The 'sv_' prefix uses 3 characters, leaving 17 for the name portion.
     * 
     * @param string $mediaChannelName The mediaChannel name
     * @return string The sanitized post type slug (max 20 chars)
     */
    public function getPostTypeSlug(string $mediaChannelName): string
    {
        $slug = sanitize_title($mediaChannelName);
        $slug = str_replace('-', '_', $slug);
        $slug = 'sv_' . $slug;

        if (strlen($slug) > 20) {
            $slug = substr($slug, 0, 20);
            $slug = rtrim($slug, '_');
        }

        return $slug;
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
     * Get the durable post type registry with idempotent migration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRegisteredPostTypes(): array
    {
        $registered = get_option(self::OPTION_KEY, []);

        if (!is_array($registered)) {
            return [];
        }

        $normalized = [];
        $needsPersist = false;

        foreach ($registered as $postTypeSlug => $info) {
            if (!is_string($postTypeSlug) || !is_array($info)) {
                $needsPersist = true;
                continue;
            }

            $entry = self::normalizeRegistryEntry($info);

            if ($entry !== $info) {
                $needsPersist = true;
            }

            $normalized[$postTypeSlug] = $entry;
        }

        if ($needsPersist) {
            update_option(self::OPTION_KEY, $normalized);
        }

        return $normalized;
    }

    /**
     * Normalize a single registry entry to the current schema.
     *
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    public static function normalizeRegistryEntry(array $info): array
    {
        if (empty($info['first_seen_at'])) {
            $info['first_seen_at'] = $info['registered_at'] ?? current_time('mysql');
        }

        unset($info['registered_at']);

        if (!array_key_exists('keep_when_empty', $info)) {
            $info['keep_when_empty'] = true;
        } else {
            $info['keep_when_empty'] = (bool) $info['keep_when_empty'];
        }

        return $info;
    }

    /**
     * Build a new registry entry for first-time discovery.
     *
     * @param string $mediaChannelName
     * @param string $mediaChannelId
     * @return array<string, mixed>
     */
    public static function createRegistryEntry(string $mediaChannelName, string $mediaChannelId): array
    {
        $now = current_time('mysql');

        return [
            'name' => $mediaChannelName,
            'id' => $mediaChannelId,
            'first_seen_at' => $now,
            'last_seen_in_api_at' => $now,
            'keep_when_empty' => true,
        ];
    }

    /**
     * Get all registered post types
     * 
     * @return array Array of post type slugs
     */
    public function getAllRegisteredPostTypes(): array
    {
        return array_keys($this->getRegisteredPostTypes());
    }

    /**
     * Update keep-when-empty for a registry entry.
     *
     * @param string $postTypeSlug
     * @param bool $keepWhenEmpty
     * @return bool
     */
    public function setKeepWhenEmpty(string $postTypeSlug, bool $keepWhenEmpty): bool
    {
        $registered = $this->getRegisteredPostTypes();

        if (!isset($registered[$postTypeSlug])) {
            return false;
        }

        $registered[$postTypeSlug]['keep_when_empty'] = $keepWhenEmpty;
        update_option(self::OPTION_KEY, $registered);

        return true;
    }

    /**
     * Count non-trash posts for a post type.
     *
     * @param string $postTypeSlug
     * @return int
     */
    public function getPostCount(string $postTypeSlug): int
    {
        $counts = wp_count_posts($postTypeSlug);

        if (!$counts) {
            return 0;
        }

        $total = 0;

        foreach ((array) $counts as $status => $count) {
            if ($status === 'trash' || $status === 'auto-draft') {
                continue;
            }

            $total += (int) $count;
        }

        return $total;
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
        $registered = $this->getRegisteredPostTypes();
        $isNew = !isset($registered[$postTypeSlug]);
        $now = current_time('mysql');

        if ($isNew) {
            $registered[$postTypeSlug] = self::createRegistryEntry($mediaChannelName, $mediaChannelId);
            update_option(self::FLUSH_REWRITE_ADD_OPTION, 1);
        } else {
            $registered[$postTypeSlug]['name'] = $mediaChannelName;
            $registered[$postTypeSlug]['id'] = $mediaChannelId;
            $registered[$postTypeSlug]['last_seen_in_api_at'] = $now;
        }

        update_option(self::OPTION_KEY, $registered);
    }

    /**
     * Flush rewrite rules after sync registers a new dynamic post type.
     *
     * Only runs for additions. Removals defer to the next init so flushed rules
     * do not still include post types registered earlier in the same request.
     *
     * @return void
     */
    public static function flushRewriteRulesAfterSync(): void
    {
        if (!get_option(self::FLUSH_REWRITE_ADD_OPTION)) {
            return;
        }

        flush_rewrite_rules();
        delete_option(self::FLUSH_REWRITE_ADD_OPTION);
    }

    /**
     * Flush rewrite rules on init after an archive was removed from the registry.
     *
     * @return void
     */
    public static function flushDeferredRewriteRulesIfNeeded(): void
    {
        if (
            !get_option(self::FLUSH_REWRITE_REMOVE_OPTION)
            && !get_option(self::FLUSH_REWRITE_RULES_OPTION)
        ) {
            return;
        }

        flush_rewrite_rules();
        delete_option(self::FLUSH_REWRITE_REMOVE_OPTION);
        delete_option(self::FLUSH_REWRITE_RULES_OPTION);
    }

    /**
     * Unregister a post type (cleanup)
     * 
     * Note: WordPress doesn't have an unregister_post_type function,
     * but we can remove it from tracking and it won't be re-registered.
     * 
     * @param string $postTypeSlug The post type slug to unregister
     * @return bool Whether the slug was removed from the registry
     */
    public function unregisterPostType(string $postTypeSlug): bool
    {
        $registered = $this->getRegisteredPostTypes();

        if (!isset($registered[$postTypeSlug])) {
            return false;
        }

        unset($registered[$postTypeSlug]);
        update_option(self::OPTION_KEY, $registered);
        update_option(self::FLUSH_REWRITE_REMOVE_OPTION, 1);

        return true;
    }

    /**
     * Clean up post types that are no longer in use
     * 
     * @param array $activePostTypeSlugs Array of active post type slugs
     * @return void
     */
    public function cleanupUnusedPostTypes(array $activePostTypeSlugs): void
    {
        $registered = $this->getRegisteredPostTypes();

        foreach ($registered as $postTypeSlug => $info) {
            if (in_array($postTypeSlug, $activePostTypeSlugs, true)) {
                continue;
            }

            if (!empty($info['keep_when_empty'])) {
                continue;
            }

            $this->unregisterPostType($postTypeSlug);
        }
    }
}
