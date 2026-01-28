<?php

namespace ModularitySimpleviewEvents\Sync;

/**
 * Class PostArchiver
 * 
 * Handles archiving, restoring, and pruning of posts that are no longer
 * present in the Simpleview API.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class PostArchiver
{
    private const ARCHIVED_AT_META_KEY = '_simpleview_archived_at';

    /**
     * Archive a post (set status to 'archived' and store timestamp)
     * 
     * @param int $postId The post ID to archive
     * @return bool True on success, false on failure
     */
    public function archivePost(int $postId): bool
    {
        // Don't archive if already archived
        if ($this->isArchived($postId)) {
            return true;
        }

        $result = wp_update_post([
            'ID' => $postId,
            'post_status' => 'archived',
        ], true);

        if (is_wp_error($result)) {
            error_log(sprintf(
                'Simpleview Events: Failed to archive post %d: %s',
                $postId,
                $result->get_error_message()
            ));
            return false;
        }

        // Store archive timestamp
        update_post_meta($postId, self::ARCHIVED_AT_META_KEY, current_time('mysql'));

        return true;
    }

    /**
     * Restore an archived post to publish status
     * 
     * @param int $postId The post ID to restore
     * @return bool True on success, false on failure
     */
    public function restorePost(int $postId): bool
    {
        $result = wp_update_post([
            'ID' => $postId,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($result)) {
            error_log(sprintf(
                'Simpleview Events: Failed to restore post %d: %s',
                $postId,
                $result->get_error_message()
            ));
            return false;
        }

        // Remove archive timestamp
        delete_post_meta($postId, self::ARCHIVED_AT_META_KEY);

        return true;
    }

    /**
     * Get all archived posts for a post type
     * 
     * @param string $postTypeSlug The post type slug
     * @return array Array of post IDs
     */
    public function getArchivedPosts(string $postTypeSlug): array
    {
        $posts = get_posts([
            'post_type' => $postTypeSlug,
            'post_status' => 'archived',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        return $posts ?: [];
    }

    /**
     * Prune expired archived posts (permanently delete)
     * 
     * @param string $postTypeSlug The post type slug
     * @param int $retentionDays Number of days to retain archived posts
     * @return array Array of deleted post IDs
     */
    public function pruneExpiredArchives(string $postTypeSlug, int $retentionDays): array
    {
        $archivedPosts = $this->getArchivedPosts($postTypeSlug);
        $prunedPosts = [];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        foreach ($archivedPosts as $postId) {
            $archivedAt = get_post_meta($postId, self::ARCHIVED_AT_META_KEY, true);

            if (empty($archivedAt)) {
                // If no timestamp, assume it's old enough (safety fallback)
                $archivedAt = date('Y-m-d H:i:s', strtotime('-999 days'));
            }

            if ($archivedAt < $cutoffDate) {
                // Permanently delete
                $deleted = wp_delete_post($postId, true);

                if ($deleted) {
                    $prunedPosts[] = $postId;
                } else {
                    error_log(sprintf(
                        'Simpleview Events: Failed to prune expired archived post %d',
                        $postId
                    ));
                }
            }
        }

        return $prunedPosts;
    }

    /**
     * Check if a post is archived
     * 
     * @param int $postId The post ID to check
     * @return bool True if archived, false otherwise
     */
    public function isArchived(int $postId): bool
    {
        $post = get_post($postId);
        return $post && $post->post_status === 'archived';
    }

    /**
     * Get archive timestamp for a post
     * 
     * @param int $postId The post ID
     * @return string|null Archive timestamp or null if not archived
     */
    public function getArchivedAt(int $postId): ?string
    {
        return get_post_meta($postId, self::ARCHIVED_AT_META_KEY, true) ?: null;
    }
}
