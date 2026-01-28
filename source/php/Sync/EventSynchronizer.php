<?php

namespace ModularitySimpleviewEvents\Sync;

use ModularitySimpleviewEvents\Api\SimpleviewClient;
use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;
use ModularitySimpleviewEvents\Taxonomy\DynamicTaxonomyManager;

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
        // Check if API is configured
        if (!$this->client->isConfigured()) {
            return new \WP_Error(
                'not_configured',
                __('Simpleview API credentials are not configured', 'modularity-simpleview-events')
            );
        }

        // Fetch products from API
        $apiResponse = $this->client->fetchEvents();

        if (is_wp_error($apiResponse)) {
            error_log('Simpleview Events Sync Error: ' . $apiResponse->get_error_message());
            return $apiResponse;
        }

        // Validate API response before proceeding
        $validation = $this->validator->validate($apiResponse);
        
        if (!$validation['valid']) {
            $errorMessage = implode('; ', $validation['warnings']);
            error_log('Simpleview Events Sync Error: ' . $errorMessage);
            return new \WP_Error(
                'invalid_response',
                $errorMessage
            );
        }

        // Extract products from API response
        $products = $this->extractProductsFromResponse($apiResponse);

        if (empty($products)) {
            return new \WP_Error(
                'no_products',
                __('No products returned from API', 'modularity-simpleview-events')
            );
        }

        // Group products by mediaChannel
        $groupedProducts = $this->groupProductsByMediaChannel($products);

        // Track active mediaChannels for cleanup
        $activeMediaChannelIds = array_keys($groupedProducts);

        // Sync each mediaChannel
        $results = [
            'created' => 0,
            'updated' => 0,
            'archived' => 0,
            'restored' => 0,
            'pruned' => 0,
            'errors' => [],
            'warnings' => $validation['warnings'] ?? [],
            'media_channels' => [],
        ];

        // Get retention days from settings (default 30)
        $retentionDays = (int) get_field('archive_retention_days', 'option') ?: 30;

        foreach ($groupedProducts as $mediaChannelId => $mediaChannelData) {
            $mediaChannelName = $mediaChannelData['name'];
            $mediaChannelProducts = $mediaChannelData['products'];

            $mediaChannelResult = $this->syncMediaChannel($mediaChannelProducts, $mediaChannelName, $mediaChannelId, $retentionDays);

            $results['created'] += $mediaChannelResult['created'];
            $results['updated'] += $mediaChannelResult['updated'];
            $results['archived'] += $mediaChannelResult['archived'];
            $results['restored'] += $mediaChannelResult['restored'];
            $results['pruned'] += $mediaChannelResult['pruned'];
            $results['errors'] = array_merge($results['errors'], $mediaChannelResult['errors']);
            $results['media_channels'][$mediaChannelId] = [
                'name' => $mediaChannelName,
                'created' => $mediaChannelResult['created'],
                'updated' => $mediaChannelResult['updated'],
                'archived' => $mediaChannelResult['archived'],
                'restored' => $mediaChannelResult['restored'],
                'pruned' => $mediaChannelResult['pruned'],
                'errors' => count($mediaChannelResult['errors']),
            ];
        }

        // Cleanup unused post types
        $this->postTypeManager->cleanupUnusedPostTypes($activeMediaChannelIds);

        // Update last sync timestamp
        update_option('simpleview_events_last_sync', current_time('mysql'));

        // Log summary
        error_log(sprintf(
            'Simpleview Events Sync completed: %d created, %d updated, %d archived, %d restored, %d pruned, %d errors across %d media channels',
            $results['created'],
            $results['updated'],
            $results['archived'],
            $results['restored'],
            $results['pruned'],
            count($results['errors']),
            count($results['media_channels'])
        ));

        // Log warnings if any
        if (!empty($results['warnings'])) {
            foreach ($results['warnings'] as $warning) {
                error_log('Simpleview Events Sync Warning: ' . $warning);
            }
        }

        return $results;
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
        $products = [];

        if (isset($apiResponse['productList']['product'])) {
            $productData = $apiResponse['productList']['product'];

            // Handle both single object and array
            if (isset($productData[0])) {
                // Array of products
                $products = $productData;
            } else {
                // Single product object
                $products = [$productData];
            }
        }

        return $products;
    }

    /**
     * Group products by mediaChannel
     * 
     * Products can belong to multiple mediaChannels, so we create entries for each.
     * Only includes mediaChannels with typeId === "WEBSITECONTENT"
     * 
     * @param array $products Array of product data
     * @return array Array grouped by mediaChannel ID, each containing name and products
     */
    private function groupProductsByMediaChannel(array $products): array
    {
        $grouped = [];

        foreach ($products as $product) {
            // Extract mediaChannels from product
            $mediaChannels = $this->extractMediaChannelsFromProduct($product);

            foreach ($mediaChannels as $mediaChannel) {
                $mediaChannelId = $mediaChannel['id'];
                $mediaChannelName = $mediaChannel['name'];

                // Initialize if not exists
                if (!isset($grouped[$mediaChannelId])) {
                    $grouped[$mediaChannelId] = [
                        'name' => $mediaChannelName,
                        'products' => [],
                    ];
                }

                // Add product to this mediaChannel
                $grouped[$mediaChannelId]['products'][] = $product;
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

        // Handle both single object and array
        if (isset($mediaChannelData[0])) {
            // Array of mediaChannels
            foreach ($mediaChannelData as $channel) {
                if (isset($channel['typeId']) && $channel['typeId'] === 'WEBSITECONTENT') {
                    if (isset($channel['@id']) && isset($channel['name'])) {
                        $mediaChannels[] = [
                            'id' => (string) $channel['@id'],
                            'name' => $channel['name'],
                        ];
                    }
                }
            }
        } else {
            // Single mediaChannel object
            if (isset($mediaChannelData['typeId']) && $mediaChannelData['typeId'] === 'WEBSITECONTENT') {
                if (isset($mediaChannelData['@id']) && isset($mediaChannelData['name'])) {
                    $mediaChannels[] = [
                        'id' => (string) $mediaChannelData['@id'],
                        'name' => $mediaChannelData['name'],
                    ];
                }
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
        // Register post type for this mediaChannel
        $postTypeSlug = $this->postTypeManager->registerPostTypeForMediaChannel($mediaChannelName, $mediaChannelId);

        // Register category taxonomy for this post type
        $taxonomySlug = $this->taxonomyManager->registerCategoryTaxonomyForPostType($postTypeSlug, $mediaChannelName);

        // Sync categories from products in this mediaChannel
        $categories = $this->taxonomyMapper->syncCategories($products, $taxonomySlug, $postTypeSlug);

        // Get all existing post IDs for this post type (including archived)
        $existingPostIds = $this->getExistingPostIds($postTypeSlug);
        $syncedPostIds = [];

        // Sync posts
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
            $existingPostId = $this->postMapper->findExistingPost((string) $simpleviewId, $postTypeSlug);

            // Check if post was archived and restore it
            $wasArchived = false;
            if ($existingPostId && $this->postArchiver->isArchived($existingPostId)) {
                $wasArchived = true;
            }

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
                $syncedPostIds[] = $result;

                // Check if it was an update or create
                if ($existingPostId && $existingPostId === $result) {
                    if ($wasArchived) {
                        $results['restored']++;
                    } else {
                        $results['updated']++;
                    }
                } else {
                    $results['created']++;
                }
            }
        }

        // Archive posts that are not in the synced list
        $postsToArchive = array_diff($existingPostIds, $syncedPostIds);
        foreach ($postsToArchive as $postId) {
            // Only archive if not already archived
            if (!$this->postArchiver->isArchived($postId)) {
                if ($this->postArchiver->archivePost($postId)) {
                    $results['archived']++;
                }
            }
        }

        // Prune expired archived posts
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
}
