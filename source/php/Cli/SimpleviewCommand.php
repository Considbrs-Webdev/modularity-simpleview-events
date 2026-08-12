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
            'Sync completed: %d created, %d updated, %d archived, %d restored, %d pruned, %d errors across %d post types.',
            $result['created'] ?? 0,
            $result['updated'] ?? 0,
            $result['archived'] ?? 0,
            $result['restored'] ?? 0,
            $result['pruned'] ?? 0,
            count($result['errors'] ?? []),
            count($result['post_types'] ?? $result['media_channels'] ?? [])
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
     * Test API connection and show debug info
     * 
     * ## OPTIONS
     * 
     * [--raw]
     * : Show raw API response (first 2000 chars)
     * 
     * [--full]
     * : Show full raw API response
     * 
     * ## EXAMPLES
     * 
     *     # Test API connection
     *     $ wp simpleview-events test
     * 
     *     # Show raw response
     *     $ wp simpleview-events test --raw
     * 
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function test(array $args, array $assoc_args): void
    {
        $showRaw = isset($assoc_args['raw']) && $assoc_args['raw'];
        $showFull = isset($assoc_args['full']) && $assoc_args['full'];

        $client = new \ModularitySimpleviewEvents\Api\SimpleviewClient();

        \WP_CLI::line('=== Simpleview API Debug ===');
        \WP_CLI::line('');

        $baseUrl = $client->getBaseUrl();
        $apiKey = get_field('api_key', 'simpleview-events-settings');

        \WP_CLI::line('Configuration:');
        \WP_CLI::line('  Base URL: ' . ($baseUrl ?: '(not set)'));
        \WP_CLI::line('  API Key: ' . ($apiKey ? substr($apiKey, 0, 8) . '...' : '(not set)'));
        \WP_CLI::line('  Is Configured: ' . ($client->isConfigured() ? 'Yes' : 'No'));
        \WP_CLI::line('');

        if (!$client->isConfigured()) {
            \WP_CLI::error('API is not configured. Please set Base URL and API Key in Settings > Simpleview Events.');
        }

        \WP_CLI::log('Testing API connection...');
        
        $result = $client->fetchEvents();

        if (is_wp_error($result)) {
            \WP_CLI::error('API request failed: ' . $result->get_error_message());
        }

        \WP_CLI::success('API connection successful!');
        \WP_CLI::line('');

        // Show response structure
        \WP_CLI::line('Response Structure:');
        \WP_CLI::line('  Type: ' . gettype($result));
        \WP_CLI::line('  Top-level keys: ' . implode(', ', array_keys($result)));

        if (isset($result['productList'])) {
            $productList = $result['productList'];
            \WP_CLI::line('  productList keys: ' . implode(', ', array_keys($productList)));

            if (isset($productList['product'])) {
                $products = $productList['product'];
                if (is_array($products)) {
                    // Check if it's a single product (associative) or array of products
                    $isSingleProduct = isset($products['@id']);
                    $productCount = $isSingleProduct ? 1 : count($products);
                    \WP_CLI::line('  Product count: ' . $productCount);

                    // Show first product structure
                    $firstProduct = $isSingleProduct ? $products : ($products[0] ?? null);
                    if ($firstProduct) {
                        \WP_CLI::line('');
                        \WP_CLI::line('First Product Structure:');
                        \WP_CLI::line('  Keys: ' . implode(', ', array_keys($firstProduct)));
                        \WP_CLI::line('  @id: ' . ($firstProduct['@id'] ?? 'N/A'));
                        \WP_CLI::line('  name: ' . ($firstProduct['name'] ?? 'N/A'));

                        // Show mediaChannelList structure
                        if (isset($firstProduct['mediaChannelList'])) {
                            $mediaChannelList = $firstProduct['mediaChannelList'];
                            \WP_CLI::line('  mediaChannelList keys: ' . implode(', ', array_keys($mediaChannelList)));
                            
                            if (isset($mediaChannelList['mediaChannel'])) {
                                $mc = $mediaChannelList['mediaChannel'];
                                if (isset($mc['@id'])) {
                                    // Single mediaChannel
                                    \WP_CLI::line('    mediaChannel: @id=' . $mc['@id'] . ', name=' . ($mc['name'] ?? 'N/A') . ', typeId=' . ($mc['typeId'] ?? 'N/A'));
                                } else {
                                    // Array of mediaChannels
                                    \WP_CLI::line('    mediaChannel count: ' . count($mc));
                                    foreach (array_slice($mc, 0, 3) as $channel) {
                                        \WP_CLI::line('      - @id=' . ($channel['@id'] ?? 'N/A') . ', name=' . ($channel['name'] ?? 'N/A') . ', typeId=' . ($channel['typeId'] ?? 'N/A'));
                                    }
                                }
                            }
                        }

                        // Show categoryList structure
                        if (isset($firstProduct['categoryList'])) {
                            \WP_CLI::line('  categoryList: present');
                        }
                    }

                    // Count products per mediaChannel
                    \WP_CLI::line('');
                    \WP_CLI::line('MediaChannel Distribution:');
                    $mediaChannelCounts = [];
                    $allProducts = $isSingleProduct ? [$products] : $products;
                    
                    foreach ($allProducts as $prod) {
                        if (!isset($prod['mediaChannelList']['mediaChannel'])) continue;
                        
                        $mcData = $prod['mediaChannelList']['mediaChannel'];
                        $channels = isset($mcData['@id']) ? [$mcData] : $mcData;
                        
                        foreach ($channels as $ch) {
                            if (($ch['typeId'] ?? '') !== 'WEBSITECONTENT') continue;
                            $key = ($ch['@id'] ?? '?') . ': ' . ($ch['name'] ?? '?');
                            $mediaChannelCounts[$key] = ($mediaChannelCounts[$key] ?? 0) + 1;
                        }
                    }
                    
                    foreach ($mediaChannelCounts as $mc => $count) {
                        \WP_CLI::line("  $mc => $count products");
                    }
                }
            }
        }

        // Show raw response if requested
        if ($showRaw || $showFull) {
            \WP_CLI::line('');
            \WP_CLI::line('=== Raw Response ===');
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($showFull) {
                \WP_CLI::line($json);
            } else {
                \WP_CLI::line(substr($json, 0, 2000) . '...');
            }
        }
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

        \WP_CLI::line('=== Simpleview Events Statistics ===');
        \WP_CLI::line('');
        \WP_CLI::line('Total Events:    ' . number_format($stats['total_events']));
        \WP_CLI::line('Draft Events:    ' . number_format($stats['draft_events']));
        \WP_CLI::line('Archived Events: ' . number_format($stats['archived_events'] ?? 0));
        \WP_CLI::line('Post Types:      ' . number_format($stats['post_types']));
        \WP_CLI::line('Last Sync:       ' . ($stats['last_sync'] ?: 'Never'));

        // Show post type breakdown if available
        if ($stats['post_types'] > 0) {
            \WP_CLI::line('');
            \WP_CLI::line('Post Type Breakdown:');

            $postTypeManager = new \ModularitySimpleviewEvents\PostType\DynamicPostTypeManager();
            $registeredPostTypes = $postTypeManager->getRegisteredPostTypes();

            $postTypeTable = [];

            foreach ($registeredPostTypes as $postTypeSlug => $info) {
                $counts = wp_count_posts($postTypeSlug);
                $published = $counts->publish ?? 0;
                $archived = $counts->archived ?? 0;
                $draft = $counts->draft ?? 0;
                
                $postTypeTable[] = [
                    'post_type' => $postTypeSlug,
                    'media_channel' => $info['name'] ?? 'Unknown',
                    'keep_when_empty' => !empty($info['keep_when_empty']) ? 'yes' : 'no',
                    'first_seen' => $info['first_seen_at'] ?? '—',
                    'last_in_api' => $info['last_seen_in_api_at'] ?? '—',
                    'published' => number_format($published),
                    'archived' => number_format($archived),
                    'draft' => number_format($draft),
                ];
            }

            \WP_CLI\Utils\format_items(
                'table',
                $postTypeTable,
                ['post_type', 'media_channel', 'keep_when_empty', 'first_seen', 'last_in_api', 'published', 'archived', 'draft']
            );
        }
    }
}
