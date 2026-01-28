<?php

namespace ModularitySimpleviewEvents\Sync;

use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;
use ModularitySimpleviewEvents\Taxonomy\DynamicTaxonomyManager;

/**
 * Class DataWiper
 * 
 * Handles deletion of all synced Simpleview events data.
 * Used for wiping data before re-sync during development/testing.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class DataWiper
{
    private DynamicPostTypeManager $postTypeManager;
    private DynamicTaxonomyManager $taxonomyManager;

    /**
     * Constructor
     * 
     * @param DynamicPostTypeManager|null $postTypeManager Post type manager instance
     * @param DynamicTaxonomyManager|null $taxonomyManager Taxonomy manager instance
     */
    public function __construct(
        ?DynamicPostTypeManager $postTypeManager = null,
        ?DynamicTaxonomyManager $taxonomyManager = null
    ) {
        $this->postTypeManager = $postTypeManager ?? new DynamicPostTypeManager();
        $this->taxonomyManager = $taxonomyManager ?? new DynamicTaxonomyManager();
    }

    /**
     * Wipe all synced data
     * 
     * Deletes:
     * - All posts for sv_* post types
     * - All terms for sv_*_category taxonomies
     * - Tracking options
     * 
     * @return array Statistics about deleted data
     */
    public function wipeAll(): array
    {
        $stats = [
            'posts_deleted' => 0,
            'terms_deleted' => 0,
            'post_types_cleared' => 0,
        ];

        // Get all registered post types
        $postTypeSlugs = $this->postTypeManager->getAllRegisteredPostTypes();

        // If no registered post types, try to find sv_* post types in database
        if (empty($postTypeSlugs)) {
            global $wpdb;
            $postTypeSlugs = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT post_type FROM {$wpdb->posts} 
                WHERE post_type LIKE %s 
                AND post_status != 'trash'
                LIMIT 50",
                'sv_%'
            ));
        }

        // Delete posts for each post type
        foreach ($postTypeSlugs as $postTypeSlug) {
            $posts = get_posts([
                'post_type' => $postTypeSlug,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'fields' => 'ids',
            ]);

            foreach ($posts as $postId) {
                wp_delete_post($postId, true); // Force delete, skip trash
                $stats['posts_deleted']++;
            }

            // Delete taxonomy terms for this post type
            $taxonomySlug = $this->taxonomyManager->getTaxonomySlug($postTypeSlug);
            
            if (taxonomy_exists($taxonomySlug)) {
                $terms = get_terms([
                    'taxonomy' => $taxonomySlug,
                    'hide_empty' => false,
                    'fields' => 'ids',
                ]);

                if (!is_wp_error($terms) && !empty($terms)) {
                    foreach ($terms as $termId) {
                        wp_delete_term($termId, $taxonomySlug);
                        $stats['terms_deleted']++;
                    }
                }
            }

            $stats['post_types_cleared']++;
        }

        // Clear tracking options
        delete_option('simpleview_events_registered_post_types');
        delete_option('simpleview_events_last_sync');

        return $stats;
    }
}
