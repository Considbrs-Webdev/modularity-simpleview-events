<?php

namespace ModularitySimpleviewEvents\Sync;

/**
 * Class PostMapper
 * 
 * Maps Simpleview API event data to WordPress post objects.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class PostMapper
{
    /**
     * Map API event data to WordPress post array
     * 
     * This is a placeholder method. The actual mapping logic
     * will be implemented once the API data structure is provided.
     * 
     * @param array $eventData Single event data from API
     * @param array $departments Array of department term IDs mapped by department identifier
     * @param array $categories Array of category term IDs mapped by category identifier
     * @return array WordPress post array ready for wp_insert_post or wp_update_post
     */
    public function mapToPost(array $eventData, array $departments, array $categories): array
    {
        // TODO: Map actual API fields once structure is known
        // Example structure (to be updated):
        // $post = [
        //     'post_title' => $eventData['title'] ?? '',
        //     'post_content' => $eventData['description'] ?? '',
        //     'post_excerpt' => $eventData['excerpt'] ?? '',
        //     'post_status' => 'publish',
        //     'post_type' => 'simpleview_event',
        //     'meta_input' => [
        //         'simpleview_id' => $eventData['id'] ?? '',
        //         'simpleview_url' => $eventData['url'] ?? '',
        //         // Add other event-specific meta fields
        //     ],
        // ];

        // Placeholder structure
        $post = [
            'post_title' => $eventData['title'] ?? __('Untitled Event', 'modularity-simpleview-events'),
            'post_content' => $eventData['description'] ?? '',
            'post_excerpt' => $eventData['excerpt'] ?? '',
            'post_status' => 'publish',
            'post_type' => 'simpleview_event',
            'meta_input' => [
                'simpleview_id' => $eventData['id'] ?? '',
            ],
        ];

        return $post;
    }

    /**
     * Get taxonomy term IDs for an event
     * 
     * Events are assigned to the category term (child). WordPress taxonomy queries
     * will automatically include events when viewing parent department archives
     * due to hierarchical taxonomy behavior (include_children defaults to true).
     * 
     * @param array $eventData Single event data from API
     * @param array $departments Array of department term IDs mapped by department identifier
     * @param array $categories Array of category term IDs mapped by category identifier
     * @return array Associative array with taxonomy slug as key and array of term IDs as value
     */
    public function getTaxonomyTerms(array $eventData, array $departments, array $categories): array
    {
        $terms = [
            'sv_event_category' => [],
        ];

        // TODO: Extract category ID from event data once structure is known
        // Events should be assigned to the category term (child), not the department term (parent)
        // Example structure (to be updated):
        // if (isset($eventData['category']['id']) && isset($categories[$eventData['category']['id']])) {
        //     $terms['sv_event_category'][] = $categories[$eventData['category']['id']];
        // }

        return $terms;
    }

    /**
     * Find existing post by Simpleview ID
     * 
     * @param string $simpleviewId Simpleview event ID
     * @return int|null Post ID or null if not found
     */
    public function findExistingPost(string $simpleviewId): ?int
    {
        $posts = get_posts([
            'post_type' => 'simpleview_event',
            'meta_key' => 'simpleview_id',
            'meta_value' => $simpleviewId,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Create or update a post from event data
     * 
     * @param array $eventData Single event data from API
     * @param array $departments Array of department term IDs mapped by department identifier
     * @param array $categories Array of category term IDs mapped by category identifier
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public function createOrUpdatePost(array $eventData, array $departments, array $categories): int|\WP_Error
    {
        $simpleviewId = $eventData['id'] ?? '';

        if (empty($simpleviewId)) {
            return new \WP_Error(
                'missing_id',
                __('Event data missing Simpleview ID', 'modularity-simpleview-events')
            );
        }

        // Check if post already exists
        $existingPostId = $this->findExistingPost($simpleviewId);

        // Map event data to post array
        $postData = $this->mapToPost($eventData, $departments, $categories);

        // Get taxonomy terms
        $taxonomyTerms = $this->getTaxonomyTerms($eventData, $departments, $categories);

        if ($existingPostId) {
            // Update existing post
            $postData['ID'] = $existingPostId;
            $postId = wp_update_post($postData, true);
        } else {
            // Create new post
            $postId = wp_insert_post($postData, true);
        }

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Set taxonomy terms
        foreach ($taxonomyTerms as $taxonomy => $termIds) {
            if (!empty($termIds)) {
                wp_set_object_terms($postId, $termIds, $taxonomy);
            }
        }

        return $postId;
    }
}
