<?php

namespace ModularitySimpleviewEvents\Sync;

use ModularitySimpleviewEvents\Sync\TaxonomyMapper;
use ModularitySimpleviewEvents\Sync\PostArchiver;

/**
 * Class PostMapper
 * 
 * Maps Simpleview API event data to WordPress post objects.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class PostMapper
{
    private TaxonomyMapper $taxonomyMapper;
    private PostArchiver $postArchiver;
    private SimpleviewEventMetaBuilder $eventMetaBuilder;

    public function __construct(
        ?TaxonomyMapper $taxonomyMapper = null,
        ?PostArchiver $postArchiver = null,
        ?SimpleviewEventMetaBuilder $eventMetaBuilder = null
    )
    {
        $this->taxonomyMapper = $taxonomyMapper ?? new TaxonomyMapper();
        $this->postArchiver = $postArchiver ?? new PostArchiver();
        $this->eventMetaBuilder = $eventMetaBuilder ?? new SimpleviewEventMetaBuilder();
    }

    /**
     * Map API event data to WordPress post array
     * 
     * @param array $eventData Single event data from API
     * @param string $postTypeSlug The post type slug to use
     * @return array WordPress post array ready for wp_insert_post or wp_update_post
     */
    public function mapToPost(array $eventData, string $postTypeSlug): array
    {
        // Extract product ID - can be @id or id
        $simpleviewId = $eventData['@id'] ?? $eventData['id'] ?? '';

        // Extract name/title
        $title = $eventData['name'] ?? __('Untitled Event', 'modularity-simpleview-events');

        // Extract content from textList
        $content = '';
        $excerpt = '';

        if (isset($eventData['textList']['text'])) {
            $texts = $eventData['textList']['text'];

            // Handle both single object and array
            if (isset($texts[0])) {
                // Array of texts
                foreach ($texts as $text) {
                    if (isset($text['@type'])) {
                        if ($text['@type'] === 'HOVED' || $text['@type'] === 'HOVED_HTML') {
                            $content = $text['#text'] ?? $text['#text'] ?? '';
                        } elseif ($text['@type'] === 'INGRESS') {
                            $excerpt = $text['#text'] ?? $text['#text'] ?? '';
                        }
                    }
                }
            } else {
                // Single text object
                if (isset($texts['@type'])) {
                    if ($texts['@type'] === 'HOVED' || $texts['@type'] === 'HOVED_HTML') {
                        $content = $texts['#text'] ?? $texts['#text'] ?? '';
                    } elseif ($texts['@type'] === 'INGRESS') {
                        $excerpt = $texts['#text'] ?? $texts['#text'] ?? '';
                    }
                }
            }
        }

        $post = [
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => 'publish',
            'post_type' => $postTypeSlug,
            'meta_input' => [
                'simpleview_id' => (string) $simpleviewId,
            ],
        ];

        // Build additional event meta (e.g. start_date, location) from API payload
        $extraMeta = $this->eventMetaBuilder->buildMeta($eventData);
        if (!empty($extraMeta)) {
            $post['meta_input'] = array_merge($post['meta_input'], $extraMeta);
        }

        return $post;
    }

    /**
     * Get taxonomy term IDs for an event
     * 
     * Extracts categories directly from the product's categoryList and maps them
     * to the synced taxonomy terms.
     * 
     * @param array $eventData Single event data from API
     * @param string $taxonomySlug The taxonomy slug
     * @param array $categories Array of category term IDs mapped by Simpleview category ID
     * @return array Associative array with taxonomy slug as key and array of term IDs as value
     */
    public function getTaxonomyTerms(array $eventData, string $taxonomySlug, array $categories): array
    {
        $terms = [
            $taxonomySlug => [],
        ];

        // Extract categories from product's categoryList
        $productCategories = $this->taxonomyMapper->extractCategoriesFromProduct($eventData);

        // Map to synced taxonomy terms
        foreach ($productCategories as $productCategory) {
            $categoryId = $productCategory['id'] ?? '';
            if (!empty($categoryId) && isset($categories[$categoryId])) {
                $terms[$taxonomySlug][] = $categories[$categoryId];
            }
        }

        return $terms;
    }

    /**
     * Find existing post by Simpleview ID
     * 
     * Searches across all statuses including archived posts.
     * 
     * @param string $simpleviewId Simpleview event ID
     * @param string $postTypeSlug The post type slug to search in
     * @return int|null Post ID or null if not found
     */
    public function findExistingPost(string $simpleviewId, string $postTypeSlug): ?int
    {
        $posts = get_posts([
            'post_type' => $postTypeSlug,
            'post_status' => ['publish', 'draft', 'archived'],
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
     * @param string $postTypeSlug The post type slug
     * @param string $taxonomySlug The taxonomy slug
     * @param array $categories Array of category term IDs mapped by Simpleview category ID
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public function createOrUpdatePost(array $eventData, string $postTypeSlug, string $taxonomySlug, array $categories, ?string $mediaChannelName = null, ?string $mediaChannelId = null): int|\WP_Error
    {
        // Extract Simpleview ID
        $simpleviewId = $eventData['@id'] ?? $eventData['id'] ?? '';

        if (empty($simpleviewId)) {
            return new \WP_Error(
                'missing_id',
                __('Event data missing Simpleview ID', 'modularity-simpleview-events')
            );
        }

        // Check if post already exists
        $existingPostId = $this->findExistingPost((string) $simpleviewId, $postTypeSlug);

        // If post exists and is archived, restore it first
        if ($existingPostId && $this->postArchiver->isArchived($existingPostId)) {
            $this->postArchiver->restorePost($existingPostId);
        }

        // Map event data to post array
        $postData = $this->mapToPost($eventData, $postTypeSlug);

        // Ensure status is publish (in case it was archived)
        $postData['post_status'] = 'publish';

        // Add mediaChannel info to post meta if provided
        if ($mediaChannelName) {
            $postData['meta_input']['simpleview_media_channel_name'] = $mediaChannelName;
        }
        if ($mediaChannelId) {
            $postData['meta_input']['simpleview_media_channel_id'] = $mediaChannelId;
        }

        // Get taxonomy terms
        $taxonomyTerms = $this->getTaxonomyTerms($eventData, $taxonomySlug, $categories);

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
