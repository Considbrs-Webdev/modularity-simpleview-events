<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\ApplyDecorator;

use Municipio\PostDecorators\PostDecorator;
use Municipio\PostDecorators\NullDecorator;
use Municipio\Helper\WpService;
use WP_Post;

/**
 * Decorator to enrich simpleview_event posts with event data
 */
class ApplySimpleviewEventData implements PostDecorator
{
    public function __construct(
        private ?PostDecorator $inner = null
    ) {
        if ($inner === null) {
            $inner = new NullDecorator();
        }
        $this->inner = $inner;
    }

    public function apply(WP_Post $post): WP_Post
    {

        // Apply inner decorator first
        $post = $this->inner->apply($post);

        // Only decorate dynamic Simpleview post types (sv_*)
        $optionKey = 'simpleview_events_registered_post_types';
        $optionValue = get_option($optionKey, []);
        $dynamicPostTypes = is_array($optionValue) ? array_keys($optionValue) : [];

        if (empty($dynamicPostTypes) || !in_array($post->post_type, $dynamicPostTypes, true)) {
            return $post;
        }
        $wpService = WpService::get();

        $postId = isset($post->ID) ? $post->ID : null;
        if ($postId === null) {
            return $post;
        }

        // Placeholder data for now (we'll enrich later)
        $mediaChannelName = $wpService->getPostMeta($postId, 'simpleview_media_channel_name', true) ?: null;
        $mediaChannelId = $wpService->getPostMeta($postId, 'simpleview_media_channel_id', true) ?: null;
        $simpleviewId = $wpService->getPostMeta($postId, 'simpleview_id', true) ?: null;
        $startDate = $wpService->getPostMeta($postId, 'start_date', true) ?: null; // Y-m-d H:i:s (Europe/Stockholm)
        $locationName = $wpService->getPostMeta($postId, 'simpleview_event_location_name', true) ?: null;

        $startTimestamp = null;
        if (is_string($startDate) && $startDate !== '') {
            $timestamp = strtotime($startDate);
            if ($timestamp !== false) {
                $startTimestamp = $timestamp;
            }
        }

        // Attach computed data to the post object
        $post->simpleviewEventData = (object) [
            'mediaChannelName' => $mediaChannelName,
            'mediaChannelId' => $mediaChannelId,
            'simpleviewId' => $simpleviewId,
            'startDate' => $startDate,
            'startTimestamp' => $startTimestamp,
            'locationName' => $locationName,
            'ariaLabel' => $post->post_title ?? '',
        ];

        return $post;
    }
}
