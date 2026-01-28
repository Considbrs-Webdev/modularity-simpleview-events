<?php

namespace ModularitySimpleviewEvents\Cli;

use ModularitySimpleviewEvents\Sync\EventSynchronizer;
use ModularitySimpleviewEvents\Sync\DataWiper;

/**
 * WP-CLI command for Simpleview Events plugin
 * 
 * Provides commands for syncing and managing Simpleview events data.
 * 
 * @package ModularitySimpleviewEvents\Cli
 */
class SimpleviewCommand
{
    /**
     * Sync events from Simpleview API
     * 
     * ## OPTIONS
     * 
     * [--wipe]
     * : Wipe all existing data before syncing
     * 
     * ## EXAMPLES
     * 
     *     # Run sync
     *     $ wp simpleview-events sync
     * 
     *     # Wipe and sync
     *     $ wp simpleview-events sync --wipe
     * 
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function sync(array $args, array $assoc_args): void
    {
        $wipe = isset($assoc_args['wipe']) && $assoc_args['wipe'];

        if ($wipe) {
            \WP_CLI::confirm('This will delete all existing Simpleview events data. Are you sure?');
            
            \WP_CLI::log('Wiping existing data...');
            $wiper = new DataWiper();
            $wipeStats = $wiper->wipeAll();
            
            \WP_CLI::success(sprintf(
                'Wiped %d posts, %d terms across %d post types.',
                $wipeStats['posts_deleted'],
                $wipeStats['terms_deleted'],
                $wipeStats['post_types_cleared']
            ));
        }

        \WP_CLI::log('Starting sync from Simpleview API...');

        $synchronizer = new EventSynchronizer();
        $result = $synchronizer->sync();

        if (is_wp_error($result)) {
            \WP_CLI::error('Sync failed: ' . $result->get_error_message());
        }

        \WP_CLI::success(sprintf(
            'Sync completed: %d created, %d updated, %d archived, %d restored, %d pruned, %d errors across %d media channels.',
            $result['created'] ?? 0,
            $result['updated'] ?? 0,
            $result['archived'] ?? 0,
            $result['restored'] ?? 0,
            $result['pruned'] ?? 0,
            count($result['errors'] ?? []),
            count($result['media_channels'] ?? [])
        ));

        if (!empty($result['warnings'] ?? [])) {
            \WP_CLI::warning(sprintf('Sync warnings (%d):', count($result['warnings'])));
            foreach ($result['warnings'] as $warning) {
                \WP_CLI::line('  - ' . $warning);
            }
        }

        if (!empty($result['errors'] ?? [])) {
            \WP_CLI::warning(sprintf('Encountered %d errors during sync:', count($result['errors'])));
            foreach (array_slice($result['errors'], 0, 10) as $error) {
                \WP_CLI::line('  - ' . ($error['event'] ?? 'unknown') . ': ' . $error['error']);
            }
            if (count($result['errors']) > 10) {
                \WP_CLI::line('  ... and ' . (count($result['errors']) - 10) . ' more errors.');
            }
        }
    }

    /**
     * Wipe all synced Simpleview events data
     * 
     * ## OPTIONS
     * 
     * [--confirm]
     * : Skip confirmation prompt (use with caution)
     * 
     * ## EXAMPLES
     * 
     *     # Wipe all data (with confirmation)
     *     $ wp simpleview-events wipe
     * 
     *     # Wipe all data (skip confirmation)
     *     $ wp simpleview-events wipe --confirm
     * 
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function wipe(array $args, array $assoc_args): void
    {
        $confirm = isset($assoc_args['confirm']) && $assoc_args['confirm'];

        if (!$confirm) {
            \WP_CLI::confirm('This will delete ALL Simpleview events data (posts, terms, and options). This cannot be undone. Are you sure?');
        }

        \WP_CLI::log('Wiping all Simpleview events data...');

        $wiper = new DataWiper();
        $stats = $wiper->wipeAll();

        \WP_CLI::success(sprintf(
            'Wiped %d posts, %d terms across %d post types. All tracking options cleared.',
            $stats['posts_deleted'],
            $stats['terms_deleted'],
            $stats['post_types_cleared']
        ));
    }

    /**
     * Show sync statistics
     * 
     * ## EXAMPLES
     * 
     *     # Show statistics
     *     $ wp simpleview-events stats
     * 
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function stats(array $args, array $assoc_args): void
    {
        $synchronizer = new EventSynchronizer();
        $stats = $synchronizer->getStats();

        $table = [];
        $table[] = [
            'Metric',
            'Value',
        ];

        $table[] = [
            'Total Events',
            number_format($stats['total_events']),
        ];

        $table[] = [
            'Draft Events',
            number_format($stats['draft_events']),
        ];

        $table[] = [
            'Archived Events',
            number_format($stats['archived_events'] ?? 0),
        ];

        $table[] = [
            'Post Types',
            number_format($stats['post_types']),
        ];

        $table[] = [
            'Last Sync',
            $stats['last_sync'] ?: 'Never',
        ];

        \WP_CLI\Utils\format_items('table', $table, ['Metric', 'Value']);

        // Show post type breakdown if available
        if ($stats['post_types'] > 0) {
            \WP_CLI::line('');
            \WP_CLI::line('Post Type Breakdown:');

            $postTypeManager = new \ModularitySimpleviewEvents\PostType\DynamicPostTypeManager();
            $registeredPostTypes = get_option('simpleview_events_registered_post_types', []);

            $postTypeTable = [];
            $postTypeTable[] = ['Post Type', 'Media Channel', 'Posts'];

            foreach ($registeredPostTypes as $postTypeSlug => $info) {
                $counts = wp_count_posts($postTypeSlug);
                $totalPosts = ($counts->publish ?? 0) + ($counts->draft ?? 0) + ($counts->archived ?? 0) + ($counts->trash ?? 0);
                
                $postTypeTable[] = [
                    $postTypeSlug,
                    $info['name'] ?? 'Unknown',
                    number_format($totalPosts) . ' (' . number_format($counts->publish ?? 0) . ' published, ' . number_format($counts->archived ?? 0) . ' archived)',
                ];
            }

            \WP_CLI\Utils\format_items('table', $postTypeTable, ['Post Type', 'Media Channel', 'Posts']);
        }
    }
}
