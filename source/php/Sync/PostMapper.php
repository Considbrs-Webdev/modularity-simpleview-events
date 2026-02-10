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
        $simpleviewId = $eventData['@id'] ?? $eventData['id'] ?? '';
        $title = $eventData['name'] ?? __('Untitled Event', 'modularity-simpleview-events');
        $content = '';
        $excerpt = '';

        if (isset($eventData['textList']['text'])) {
            $texts = $eventData['textList']['text'];
            $textItems = isset($texts[0]) ? $texts : [$texts];

            foreach ($textItems as $text) {
                $type = $text['@type'] ?? '';
                if ($type === 'HOVED' || $type === 'HOVED_HTML') {
                    $content = $text['#text'] ?? '';
                } elseif ($type === 'INGRESS') {
                    $excerpt = $text['#text'] ?? '';
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

        $productCategories = $this->taxonomyMapper->extractCategoriesFromProduct($eventData);

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
        $simpleviewId = $eventData['@id'] ?? $eventData['id'] ?? '';

        if (empty($simpleviewId)) {
            return new \WP_Error(
                'missing_id',
                __('Event data missing Simpleview ID', 'modularity-simpleview-events')
            );
        }

        $existingPostId = $this->findExistingPost((string) $simpleviewId, $postTypeSlug);

        if ($existingPostId && $this->postArchiver->isArchived($existingPostId)) {
            $this->postArchiver->restorePost($existingPostId);
        }

        $postData = $this->mapToPost($eventData, $postTypeSlug);
        $postData['post_status'] = 'publish';

        if ($mediaChannelName) {
            $postData['meta_input']['simpleview_media_channel_name'] = $mediaChannelName;
        }
        if ($mediaChannelId) {
            $postData['meta_input']['simpleview_media_channel_id'] = $mediaChannelId;
        }

        $taxonomyTerms = $this->getTaxonomyTerms($eventData, $taxonomySlug, $categories);

        if ($existingPostId) {
            $postData['ID'] = $existingPostId;
            $postId = wp_update_post($postData, true);
        } else {
            $postId = wp_insert_post($postData, true);
        }

        if (is_wp_error($postId)) {
            return $postId;
        }

        foreach ($taxonomyTerms as $taxonomy => $termIds) {
            if (!empty($termIds)) {
                wp_set_object_terms($postId, $termIds, $taxonomy);
            }
        }

        return $postId;
    }
}
