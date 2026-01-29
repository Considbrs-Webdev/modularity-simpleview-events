<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\ApplyDecorator;

use Municipio\PostDecorators\PostDecorator;
use Municipio\PostDecorators\NullDecorator;
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

        $postId = $post->ID;

        // Placeholder data for now (we'll enrich later)
        $mediaChannelName = get_post_meta($postId, 'simpleview_media_channel_name', true) ?: null;
        $mediaChannelId = get_post_meta($postId, 'simpleview_media_channel_id', true) ?: null;
        $simpleviewId = get_post_meta($postId, 'simpleview_id', true) ?: null;

        // Attach computed data to the post object
        $post->simpleviewEventData = (object) [
            'mediaChannelName' => $mediaChannelName,
            'mediaChannelId' => $mediaChannelId,
            'simpleviewId' => $simpleviewId,
            'ariaLabel' => $post->post_title ?? '',
        ];

        return $post;
    }
}
