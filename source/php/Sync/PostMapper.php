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
    ) {
        $this->taxonomyMapper = $taxonomyMapper ?? new TaxonomyMapper();
        $this->postArchiver = $postArchiver ?? new PostArchiver();
        $this->eventMetaBuilder = $eventMetaBuilder ?? new SimpleviewEventMetaBuilder();
    }

    /**
     * Convert Simpleview ISO 8601 date to WordPress Y-m-d H:i:s format.
     *
     * @param string|null $simpleviewDate e.g. "2026-02-09T08:26:54"
     * @return string|null WordPress format or null if invalid
     */
    private function toWordPressDate(?string $simpleviewDate): ?string
    {
        if (!is_string($simpleviewDate) || trim($simpleviewDate) === '') {
            return null;
        }
        $normalized = str_replace('T', ' ', trim($simpleviewDate));
        return strlen($normalized) >= 19 ? $normalized : null;
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

        $now = current_time('mysql');
        $postDate = $this->toWordPressDate($eventData['@created'] ?? null) ?? $now;
        $postModified = $this->toWordPressDate($eventData['@modified'] ?? null)
            ?? $this->toWordPressDate($eventData['@created'] ?? null)
            ?? $now;

        $post = [
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => 'publish',
            'post_type' => $postTypeSlug,
            'post_date' => $postDate,
            'post_date_gmt' => get_gmt_from_date($postDate),
            'post_modified' => $postModified,
            'post_modified_gmt' => get_gmt_from_date($postModified),
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
     * Skips update when @modified matches existing post_modified (content unchanged).
     * 
     * @param array $eventData Single event data from API
     * @param string $postTypeSlug The post type slug
     * @param string $taxonomySlug The taxonomy slug
     * @param array $categories Array of category term IDs mapped by Simpleview category ID
     * @return array{post_id: int, action: 'created'|'updated'|'skipped'}|\WP_Error Result on success, WP_Error on failure
     */
    public function createOrUpdatePost(array $eventData, string $postTypeSlug, string $taxonomySlug, array $categories, ?string $mediaChannelName = null, ?string $mediaChannelId = null): array|\WP_Error
    {
        $simpleviewId = $eventData['@id'] ?? $eventData['id'] ?? '';

        if (empty($simpleviewId)) {
            return new \WP_Error(
                'missing_id',
                __('Event data missing Simpleview ID', 'modularity-simpleview-events')
            );
        }

        $existingPostId = $this->findExistingPost((string) $simpleviewId, $postTypeSlug);
        $wasArchived = $existingPostId && $this->postArchiver->isArchived($existingPostId);

        if ($existingPostId && $wasArchived) {
            $this->postArchiver->restorePost($existingPostId);
        }

        if ($existingPostId) {
            $existingPost = get_post($existingPostId);
            $incomingModified = $this->toWordPressDate($eventData['@modified'] ?? null);

            if ($existingPost && $incomingModified && $existingPost->post_modified === $incomingModified) {
                return [
                    'post_id' => $existingPostId,
                    'action' => 'skipped',
                ];
            }
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
            $action = 'updated';
        } else {
            $postId = wp_insert_post($postData, true);
            $action = 'created';
        }

        if (is_wp_error($postId)) {
            return $postId;
        }

        // WordPress overwrites post_modified when wp_update_post runs. We must set it
        // directly so the skip check works on the next sync.
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            [
                'post_modified' => $postData['post_modified'],
                'post_modified_gmt' => $postData['post_modified_gmt'],
            ],
            ['ID' => $postId],
            ['%s', '%s'],
            ['%d']
        );

        foreach ($taxonomyTerms as $taxonomy => $termIds) {
            if (!empty($termIds)) {
                wp_set_object_terms($postId, $termIds, $taxonomy);
            }
        }

        return [
            'post_id' => $postId,
            'action' => $action,
        ];
    }
}
