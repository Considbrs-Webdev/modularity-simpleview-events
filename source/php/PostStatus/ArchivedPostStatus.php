<?php

namespace ModularitySimpleviewEvents\PostStatus;

/**
 * Class ArchivedPostStatus
 * 
 * Registers the custom 'archived' post status for posts that are no longer
 * in the Simpleview API but haven't exceeded the retention period.
 * 
 * @package ModularitySimpleviewEvents\PostStatus
 */
class ArchivedPostStatus
{
    /**
     * Register the archived post status
     * 
     * @return void
     */
    public function register(): void
    {
        register_post_status('archived', [
            'label' => __('Archived', 'modularity-simpleview-events'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Archived <span class="count">(%s)</span>',
                'Archived <span class="count">(%s)</span>',
                'modularity-simpleview-events'
            ),
        ]);
    }
}
