<?php

namespace ModularitySimpleviewEvents\Sync;

use ModularitySimpleviewEvents\Api\SimpleviewClient;

/**
 * Class EventSynchronizer
 * 
 * Orchestrates the synchronization of events from Simpleview API to WordPress.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class EventSynchronizer
{
    private SimpleviewClient $client;
    private TaxonomyMapper $taxonomyMapper;
    private PostMapper $postMapper;

    /**
     * Constructor
     * 
     * @param SimpleviewClient|null $client API client instance
     * @param TaxonomyMapper|null $taxonomyMapper Taxonomy mapper instance
     * @param PostMapper|null $postMapper Post mapper instance
     */
    public function __construct(
        ?SimpleviewClient $client = null,
        ?TaxonomyMapper $taxonomyMapper = null,
        ?PostMapper $postMapper = null
    ) {
        $this->client = $client ?? new SimpleviewClient();
        $this->taxonomyMapper = $taxonomyMapper ?? new TaxonomyMapper();
        $this->postMapper = $postMapper ?? new PostMapper();
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

        // Fetch events from API
        $events = $this->client->fetchEvents();

        if (is_wp_error($events)) {
            error_log('Simpleview Events Sync Error: ' . $events->get_error_message());
            return $events;
        }

        if (empty($events) || !is_array($events)) {
            return new \WP_Error(
                'no_events',
                __('No events returned from API', 'modularity-simpleview-events')
            );
        }

        // Sync taxonomies first
        $departments = $this->taxonomyMapper->syncDepartments($events);
        $categories = $this->taxonomyMapper->syncCategories($events, $departments);

        // Sync posts
        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        foreach ($events as $eventData) {
            $simpleviewId = $eventData['id'] ?? '';
            $existingPostId = $this->postMapper->findExistingPost($simpleviewId);

            $result = $this->postMapper->createOrUpdatePost($eventData, $departments, $categories);

            if (is_wp_error($result)) {
                $results['errors'][] = [
                    'event' => $simpleviewId ?: 'unknown',
                    'error' => $result->get_error_message(),
                ];
                error_log('Simpleview Events Sync Error for event ' . ($simpleviewId ?: 'unknown') . ': ' . $result->get_error_message());
            } else {
                // Check if it was an update or create
                if ($existingPostId && $existingPostId === $result) {
                    $results['updated']++;
                } else {
                    $results['created']++;
                }
            }
        }

        // Update last sync timestamp
        update_option('simpleview_events_last_sync', current_time('mysql'));

        // Log summary
        error_log(sprintf(
            'Simpleview Events Sync completed: %d created, %d updated, %d errors',
            $results['created'],
            $results['updated'],
            count($results['errors'])
        ));

        return $results;
    }

    /**
     * Get sync statistics
     * 
     * @return array Statistics about synced events
     */
    public function getStats(): array
    {
        $totalPosts = wp_count_posts('simpleview_event');

        return [
            'total_events' => $totalPosts->publish ?? 0,
            'draft_events' => $totalPosts->draft ?? 0,
            'last_sync' => get_option('simpleview_events_last_sync', null),
        ];
    }
}
