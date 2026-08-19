<?php

namespace ModularitySimpleviewEvents\PostStatus;

/**
 * Class ArchivedPostStatus
 *
 * Registers the custom 'archived' post status for posts that are no longer
 * in the Simpleview API but haven't exceeded the retention period.
 *
 * Flags match modularity-municipal-calendar so either plugin can register last.
 * `public` is false so default archive queries exclude these posts. Simpleview
 * does not allow archived singles on the front end.
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
            'publicly_queryable' => false,
            'internal' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                /* translators: %s: Number of archived posts. */
                'Archived <span class="count">(%s)</span>',
                'Archived <span class="count">(%s)</span>',
                'modularity-simpleview-events'
            ),
        ]);
    }
}
