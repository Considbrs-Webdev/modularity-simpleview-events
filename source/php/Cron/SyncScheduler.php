<?php

namespace ModularitySimpleviewEvents\Cron;

use ModularitySimpleviewEvents\Sync\EventSynchronizer;

/**
 * Class SyncScheduler
 * 
 * Manages WP Cron scheduling for automatic event synchronization.
 * 
 * @package ModularitySimpleviewEvents\Cron
 */
class SyncScheduler
{
    private const CRON_HOOK = 'simpleview_events_sync';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action(self::CRON_HOOK, [$this, 'runSync']);

        // Reschedule if settings changed
        add_action('acf/save_post', [$this, 'maybeReschedule'], 20);

        // Schedule on initialization
        $this->schedule();
    }

    /**
     * Schedule the sync cron job
     * 
     * @return void
     */
    public function schedule(): void
    {
        // Unschedule any existing events first
        $this->unschedule();

        $frequency = get_field('sync_frequency', 'simpleview-events-settings') ?: 'daily';

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), $frequency, self::CRON_HOOK);
        }
    }

    /**
     * Unschedule the sync cron job
     * 
     * @return void
     */
    public function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);

        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }

        // Also clear any orphaned events
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Run the synchronization
     * 
     * @return void
     */
    public function runSync(): void
    {
        $synchronizer = new EventSynchronizer();
        $result = $synchronizer->sync();

        // Last sync timestamp is updated by EventSynchronizer
        if (is_wp_error($result)) {
            error_log('Simpleview Events Cron Sync Error: ' . $result->get_error_message());
        }
    }

    /**
     * Reschedule if settings page was saved
     * 
     * @param int|string $postId The post ID or options page identifier
     * @return void
     */
    public function maybeReschedule($postId): void
    {
        // Only reschedule if it's our options page
        if ($postId === 'simpleview-events-settings') {
            $this->schedule();
        }
    }
}
