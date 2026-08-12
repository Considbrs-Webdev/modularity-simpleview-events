<?php

namespace ModularitySimpleviewEvents\Sync;

use ModularitySimpleviewEvents\Api\SimpleviewClient;
use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;
use ModularitySimpleviewEvents\Taxonomy\DynamicTaxonomyManager;
use ModularitySimpleviewEvents\PostStatus\ArchivedPostStatus;

/**
 * Class EventSynchronizer
 * 
 * Orchestrates the synchronization of events from Simpleview API to WordPress.
 * Groups products by mediaChannel and creates dynamic post types for each.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class EventSynchronizer
{
    private SimpleviewClient $client;
    private TaxonomyMapper $taxonomyMapper;
    private PostMapper $postMapper;
    private DynamicPostTypeManager $postTypeManager;
    private DynamicTaxonomyManager $taxonomyManager;
    private ApiResponseValidator $validator;
    private PostArchiver $postArchiver;

    /**
     * Constructor
     * 
     * @param SimpleviewClient|null $client API client instance
     * @param TaxonomyMapper|null $taxonomyMapper Taxonomy mapper instance
     * @param PostMapper|null $postMapper Post mapper instance
     * @param DynamicPostTypeManager|null $postTypeManager Post type manager instance
     * @param DynamicTaxonomyManager|null $taxonomyManager Taxonomy manager instance
     * @param ApiResponseValidator|null $validator API response validator instance
     * @param PostArchiver|null $postArchiver Post archiver instance
     */
    public function __construct(
        ?SimpleviewClient $client = null,
        ?TaxonomyMapper $taxonomyMapper = null,
        ?PostMapper $postMapper = null,
        ?DynamicPostTypeManager $postTypeManager = null,
        ?DynamicTaxonomyManager $taxonomyManager = null,
        ?ApiResponseValidator $validator = null,
        ?PostArchiver $postArchiver = null
    ) {
        $this->client = $client ?? new SimpleviewClient();
        $this->taxonomyMapper = $taxonomyMapper ?? new TaxonomyMapper();
        $this->postMapper = $postMapper ?? new PostMapper($this->taxonomyMapper);
        $this->postTypeManager = $postTypeManager ?? new DynamicPostTypeManager();
        $this->taxonomyManager = $taxonomyManager ?? new DynamicTaxonomyManager();
        $this->validator = $validator ?? new ApiResponseValidator();
        $this->postArchiver = $postArchiver ?? new PostArchiver();
    }

    /**
     * Perform full synchronization
     * 
     * @return array|WP_Error Sync results or WP_Error on failure
     */
    public function sync(): array|\WP_Error
    {
        $lockKey = 'simpleview-events-sync';
        if (get_transient($lockKey)) {
            return new \WP_Error(
                'already_syncing',
                __('Simpleview Events Sync is already in progress', 'modularity-simpleview-events')
            );
        }

        set_transient($lockKey, true, 3600);

        try {
            if (!$this->client->isConfigured()) {
                return new \WP_Error(
                    'not_configured',
                    __('Simpleview API credentials are not configured', 'modularity-simpleview-events')
                );
            }

            $apiResponse = $this->client->fetchEvents();

            if (is_wp_error($apiResponse)) {
                error_log('Simpleview Events Sync Error: ' . $apiResponse->get_error_message());
                return $apiResponse;
            }

            $validation = $this->validator->validate($apiResponse);

            if (!$validation['valid']) {
                $errorMessage = implode('; ', $validation['warnings']);
                error_log('Simpleview Events Sync Error: ' . $errorMessage);
                return new \WP_Error(
                    'invalid_response',
                    $errorMessage
                );
            }

            $products = $this->extractProductsFromResponse($apiResponse);

            if (empty($products)) {
                return new \WP_Error(
                    'no_products',
                    __('No products returned from API', 'modularity-simpleview-events')
                );
            }

            $groupedProducts = $this->groupProductsByMediaChannel($products);
            $activePostTypeSlugs = array_keys($groupedProducts);

            $results = [
                'created' => 0,
                'updated' => 0,
                'archived' => 0,
                'restored' => 0,
                'pruned' => 0,
                'errors' => [],
                'warnings' => $validation['warnings'] ?? [],
                'post_types' => [],
            ];

            $retentionDays = (int) get_field('archive_retention_days', 'simpleview-events-settings') ?: 30;

            foreach ($groupedProducts as $postTypeSlug => $postTypeData) {
                $mediaChannelName = $postTypeData['name'];
                $mediaChannelIds = $postTypeData['ids'];
                $postTypeProducts = $postTypeData['products'];
                $primaryMediaChannelId = $mediaChannelIds[0] ?? '';

                $postTypeResult = $this->syncMediaChannel($postTypeProducts, $mediaChannelName, $primaryMediaChannelId, $retentionDays);

                $results['created'] += $postTypeResult['created'];
                $results['updated'] += $postTypeResult['updated'];
                $results['archived'] += $postTypeResult['archived'];
                $results['restored'] += $postTypeResult['restored'];
                $results['pruned'] += $postTypeResult['pruned'];
                $results['errors'] = array_merge($results['errors'], $postTypeResult['errors']);
                $results['post_types'][$postTypeSlug] = [
                    'name' => $mediaChannelName,
                    'media_channel_ids' => $mediaChannelIds,
                    'created' => $postTypeResult['created'],
                    'updated' => $postTypeResult['updated'],
                    'archived' => $postTypeResult['archived'],
                    'restored' => $postTypeResult['restored'],
                    'pruned' => $postTypeResult['pruned'],
                    'errors' => count($postTypeResult['errors']),
                ];
            }

            $this->postTypeManager->cleanupUnusedPostTypes($activePostTypeSlugs);
            update_option('simpleview_events_last_sync', current_time('mysql'));

            error_log(sprintf(
                'Simpleview Events Sync completed: %d created, %d updated, %d archived, %d restored, %d pruned, %d errors across %d post types',
                $results['created'],
                $results['updated'],
                $results['archived'],
                $results['restored'],
                $results['pruned'],
                count($results['errors']),
                count($results['post_types'])
            ));

            if (!empty($results['warnings'])) {
                foreach ($results['warnings'] as $warning) {
                    error_log('Simpleview Events Sync Warning: ' . $warning);
                }
            }

            DynamicPostTypeManager::flushRewriteRulesAfterSync();

            return $results;
        } finally {
            delete_transient($lockKey);
        }
    }

    /**
     * Extract products from API response
     * 
     * Handles the structure: productList.product (can be single object or array)
     * 
     * @param array $apiResponse The API response
     * @return array Array of product data
     */
    private function extractProductsFromResponse(array $apiResponse): array
    {
        if (!isset($apiResponse['productList']['product'])) {
            return [];
        }

        $productData = $apiResponse['productList']['product'];

        return isset($productData[0]) ? $productData : [$productData];
    }

    /**
     * Group products by post type slug (derived from mediaChannel name)
     * 
     * Products can belong to multiple mediaChannels, so we create entries for each.
     * Only includes mediaChannels with typeId === "WEBSITECONTENT".
     * Groups by post type slug to ensure all products for the same post type are synced together.
     * 
     * @param array $products Array of product data
     * @return array Array grouped by post type slug, each containing name, ids, and products
     */
    private function groupProductsByMediaChannel(array $products): array
    {
        $grouped = [];

        foreach ($products as $product) {
            $mediaChannels = $this->extractMediaChannelsFromProduct($product);

            foreach ($mediaChannels as $mediaChannel) {
                $mediaChannelId = $mediaChannel['id'];
                $mediaChannelName = $mediaChannel['name'];
                $postTypeSlug = $this->postTypeManager->getPostTypeSlug($mediaChannelName);

                if (!isset($grouped[$postTypeSlug])) {
                    $grouped[$postTypeSlug] = [
                        'name' => $mediaChannelName,
                        'ids' => [],
                        'products' => [],
                    ];
                }

                if (!in_array($mediaChannelId, $grouped[$postTypeSlug]['ids'])) {
                    $grouped[$postTypeSlug]['ids'][] = $mediaChannelId;
                }

                $simpleviewId = $product['@id'] ?? $product['id'] ?? '';
                $alreadyAdded = false;
                foreach ($grouped[$postTypeSlug]['products'] as $existingProduct) {
                    $existingId = $existingProduct['@id'] ?? $existingProduct['id'] ?? '';
                    if ($existingId === $simpleviewId) {
                        $alreadyAdded = true;
                        break;
                    }
                }

                if (!$alreadyAdded) {
                    $grouped[$postTypeSlug]['products'][] = $product;
                }
            }
        }

        return $grouped;
    }

    /**
     * Extract mediaChannels from a product
     * 
     * Handles both single mediaChannel object and mediaChannel[] array
     * Filters by typeId === "WEBSITECONTENT"
     * 
     * @param array $product Single product data
     * @return array Array of mediaChannel data with 'id' and 'name' keys
     */
    private function extractMediaChannelsFromProduct(array $product): array
    {
        $mediaChannels = [];

        if (!isset($product['mediaChannelList']['mediaChannel'])) {
            return $mediaChannels;
        }

        $mediaChannelData = $product['mediaChannelList']['mediaChannel'];
        $channels = isset($mediaChannelData[0]) ? $mediaChannelData : [$mediaChannelData];

        foreach ($channels as $channel) {
            if (($channel['typeId'] ?? '') === 'WEBSITECONTENT' && isset($channel['@id'], $channel['name'])) {
                $mediaChannels[] = [
                    'id' => (string) $channel['@id'],
                    'name' => $channel['name'],
                ];
            }
        }

        return $mediaChannels;
    }

    /**
     * Sync a single mediaChannel
     * 
     * @param array $products Products for this mediaChannel
     * @param string $mediaChannelName The mediaChannel name
     * @param string $mediaChannelId The mediaChannel ID
     * @param int $retentionDays Number of days to retain archived posts
     * @return array Sync results for this mediaChannel
     */
    private function syncMediaChannel(array $products, string $mediaChannelName, string $mediaChannelId, int $retentionDays): array
    {
        $postTypeSlug = $this->postTypeManager->registerPostTypeForMediaChannel($mediaChannelName, $mediaChannelId);
        $taxonomySlug = $this->taxonomyManager->registerCategoryTaxonomyForPostType($postTypeSlug, $mediaChannelName);
        $categories = $this->taxonomyMapper->syncCategories($products, $taxonomySlug, $postTypeSlug);
        $existingPostIds = $this->getExistingPostIds($postTypeSlug);
        $syncedPostIds = [];

        $results = [
            'created' => 0,
            'updated' => 0,
            'archived' => 0,
            'restored' => 0,
            'pruned' => 0,
            'errors' => [],
        ];

        foreach ($products as $productData) {
            $simpleviewId = $productData['@id'] ?? $productData['id'] ?? '';

            $result = $this->postMapper->createOrUpdatePost($productData, $postTypeSlug, $taxonomySlug, $categories, $mediaChannelName, $mediaChannelId);

            if (is_wp_error($result)) {
                $results['errors'][] = [
                    'event' => $simpleviewId ?: 'unknown',
                    'error' => $result->get_error_message(),
                ];
                error_log(sprintf(
                    'Simpleview Events Sync Error for event %s in mediaChannel %s: %s',
                    $simpleviewId ?: 'unknown',
                    $mediaChannelName,
                    $result->get_error_message()
                ));
            } else {
                $postId = $result['post_id'];
                $action = $result['action'];
                $syncedPostIds[] = $postId;

                if ($action === 'created') {
                    $results['created']++;
                } elseif (!empty($result['restored'])) {
                    $results['restored']++;
                } elseif ($action === 'updated') {
                    $results['updated']++;
                }
            }
        }

        $postsToArchive = array_diff($existingPostIds, $syncedPostIds);
        foreach ($postsToArchive as $postId) {
            if (!$this->postArchiver->isArchived($postId)) {
                if ($this->postArchiver->archivePost($postId)) {
                    $results['archived']++;
                }
            }
        }

        $dateArchived = $this->postArchiver->archivePastEndDatePosts($postTypeSlug);
        $results['archived'] += count($dateArchived);

        $prunedPosts = $this->postArchiver->pruneExpiredArchives($postTypeSlug, $retentionDays);
        $results['pruned'] = count($prunedPosts);

        return $results;
    }

    /**
     * Get all existing post IDs for a post type (all statuses except trash)
     * 
     * @param string $postTypeSlug The post type slug
     * @return array Array of post IDs
     */
    private function getExistingPostIds(string $postTypeSlug): array
    {
        $posts = get_posts([
            'post_type' => $postTypeSlug,
            'post_status' => ['publish', 'draft', 'archived'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        return $posts ?: [];
    }

    /**
     * Get sync statistics
     * 
     * @return array Statistics about synced events across all post types
     */
    public function getStats(): array
    {
        $this->ensureArchivedStatusRegistered();

        $registeredPostTypes = $this->postTypeManager->getAllRegisteredPostTypes();
        $totalEvents = 0;
        $draftEvents = 0;
        $archivedEvents = 0;

        foreach ($registeredPostTypes as $postType) {
            $counts = wp_count_posts($postType);
            $totalEvents += $counts->publish ?? 0;
            $draftEvents += $counts->draft ?? 0;
            $archivedEvents += $counts->archived ?? 0;
        }

        return [
            'total_events' => $totalEvents,
            'draft_events' => $draftEvents,
            'archived_events' => $archivedEvents,
            'post_types' => count($registeredPostTypes),
            'last_sync' => get_option('simpleview_events_last_sync', null),
        ];
    }

    /**
     * Ensure the archived post status is registered
     * 
     * @return void
     */
    private function ensureArchivedStatusRegistered(): void
    {
        if (!get_post_status_object('archived')) {
            $archivedStatus = new ArchivedPostStatus();
            $archivedStatus->register();
        }
    }
}
