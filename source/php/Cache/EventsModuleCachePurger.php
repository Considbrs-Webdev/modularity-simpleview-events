<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\Cache;

use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;

/**
 * Clears cached output of the upcoming events module when events change.
 *
 * Modularity fragment-caches module/block output in the object cache (group
 * "modules[-blogId]"), keyed by the post id of the page holding a block, or
 * the module post id for module posts. Page caches (LiteSpeed) hold the full
 * page. Neither knows that Simpleview events were added, changed, archived or
 * deleted, so this purges both for every module or block listing the changed
 * post type.
 *
 * Purges are collected and executed once on shutdown, so a sync that touches
 * hundreds of events results in one purge pass per post type.
 */
class EventsModuleCachePurger
{
    private const MODULE_POST_TYPE = 'mod-sv-events';
    private const BLOCK_NAME = 'acf/sv-events';
    private const FRAGMENT_CACHE_GROUP = 'modules';

    /**
     * @var array<string, true> Post types with pending purges.
     */
    private array $pendingPostTypes = [];

    private bool $shutdownRegistered = false;

    public function __construct()
    {
        add_action('save_post', [$this, 'onPostChanged'], 100, 2);
        add_action('deleted_post', [$this, 'onPostDeleted'], 10, 2);
    }

    /**
     * @param int      $postId
     * @param \WP_Post $post
     */
    public function onPostChanged($postId, $post = null): void
    {
        if (wp_is_post_revision((int) $postId) || wp_is_post_autosave((int) $postId)) {
            return;
        }

        $this->queue($post instanceof \WP_Post ? $post->post_type : (string) get_post_type((int) $postId));
    }

    /**
     * @param int           $postId
     * @param \WP_Post|null $post
     */
    public function onPostDeleted($postId, $post = null): void
    {
        $this->queue($post instanceof \WP_Post ? $post->post_type : '');
    }

    private function queue(string $postType): void
    {
        if ($postType === '' || !$this->isSimpleviewPostType($postType)) {
            return;
        }

        $this->pendingPostTypes[$postType] = true;

        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            add_action('shutdown', [$this, 'purgePending'], 0);
        }
    }

    private function isSimpleviewPostType(string $postType): bool
    {
        return array_key_exists($postType, (new DynamicPostTypeManager())->getRegisteredPostTypes());
    }

    public function purgePending(): void
    {
        $postTypes = array_keys($this->pendingPostTypes);
        $this->pendingPostTypes = [];

        // Pages to purge from the page cache, keyed by id to avoid duplicates.
        $pageIds = [];

        foreach ($postTypes as $postType) {
            foreach ($this->getModulePostIds($postType) as $moduleId) {
                wp_cache_delete($moduleId, $this->getFragmentCacheGroup());

                foreach ($this->getPagesUsingModulePost($moduleId) as $pageId) {
                    $pageIds[$pageId] = true;
                }
            }

            foreach ($this->getPageIdsWithBlock($postType) as $pageId) {
                wp_cache_delete($pageId, $this->getFragmentCacheGroup());
                $pageIds[$pageId] = true;
            }
        }

        $this->purgePageCache(array_keys($pageIds));
    }

    /**
     * Same group name as Modularity\Helper\Cache (kept in sync manually).
     */
    private function getFragmentCacheGroup(): string
    {
        return is_multisite() ? self::FRAGMENT_CACHE_GROUP . '-' . get_current_blog_id() : self::FRAGMENT_CACHE_GROUP;
    }

    /**
     * @return int[] Modularity module posts configured to list this post type.
     */
    private function getModulePostIds(string $postType): array
    {
        $ids = get_posts([
            'post_type' => self::MODULE_POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'post_type',
                    'value' => $postType,
                    'compare' => '=',
                ],
            ],
        ]);

        return array_map('intval', $ids ?: []);
    }

    /**
     * @return int[] Pages where a module post is placed.
     */
    private function getPagesUsingModulePost(int $moduleId): array
    {
        if (!class_exists('\Modularity\ModuleManager')) {
            return [];
        }

        $usage = \Modularity\ModuleManager::getModuleUsage($moduleId);

        if (!is_array($usage)) {
            return [];
        }

        return array_map('intval', array_filter(array_column($usage, 'post_id')));
    }

    /**
     * @return int[] Published posts whose content has an events block for this post type.
     */
    private function getPageIdsWithBlock(string $postType): array
    {
        global $wpdb;

        $blockLike = '%' . $wpdb->esc_like('<!-- wp:' . self::BLOCK_NAME . ' ') . '%';
        $postTypeLike = '%' . $wpdb->esc_like('"post_type":"' . $postType . '"') . '%';

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type != 'revision'
               AND post_content LIKE %s
               AND post_content LIKE %s",
            $blockLike,
            $postTypeLike
        ));

        return array_map('intval', $ids ?: []);
    }

    /**
     * Purge pages from LiteSpeed Cache, only when it is active.
     *
     * @param int[] $pageIds
     */
    private function purgePageCache(array $pageIds): void
    {
        if (empty($pageIds) || !has_action('litespeed_purge_post')) {
            return;
        }

        foreach ($pageIds as $pageId) {
            do_action('litespeed_purge_post', $pageId);
        }
    }
}
