<?php

namespace ModularitySimpleviewEvents\Cron;

use ModularitySimpleviewEvents\Sync\EventSynchronizer;

/**
 * Class SyncScheduler
 * 
 * Registers the WP-Cron hook that external crontab triggers.
 * Scheduling is handled by the server's crontab, not by WP-Cron intervals.
 * 
 * @package ModularitySimpleviewEvents\Cron
 */
class SyncScheduler
{
    public const CRON_HOOK = 'simpleview_events_sync';

    public function __construct()
    {
        add_action(self::CRON_HOOK, [$this, 'runSync']);
    }

    /**
     * Run the synchronization (called by crontab via WP-Cron hook)
     * 
     * @return void
     */
    public function runSync(): void
    {
        $synchronizer = new EventSynchronizer();
        $result = $synchronizer->sync();

        if (is_wp_error($result)) {
            error_log('Simpleview Events Cron Sync Error: ' . $result->get_error_message());
        }
    }

    /**
     * Unschedule any leftover WP-Cron events (used during deactivation)
     * 
     * @return void
     */
    public function unschedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
