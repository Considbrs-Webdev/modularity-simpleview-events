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

        $mediaChannelName = $wpService->getPostMeta($postId, 'simpleview_media_channel_name', true) ?: null;
        $mediaChannelId = $wpService->getPostMeta($postId, 'simpleview_media_channel_id', true) ?: null;
        $simpleviewId = $wpService->getPostMeta($postId, 'simpleview_id', true) ?: null;
        $startDate = $wpService->getPostMeta($postId, 'start_date', true) ?: null; // Y-m-d H:i:s (Europe/Stockholm)
        $endDate = $wpService->getPostMeta($postId, 'simpleview_event_end_date', true) ?: null; // Y-m-d H:i:s (Europe/Stockholm), optional
        $locationName = $wpService->getPostMeta($postId, 'simpleview_event_location_name', true) ?: null;
        $imageJson = $wpService->getPostMeta($postId, 'simpleview_event_image_json', true) ?: null;
        $image = null;
        $imageHero = null;
        if (is_string($imageJson) && $imageJson !== '') {
            $decoded = json_decode($imageJson, true);
            if (is_array($decoded) && !empty($decoded['src'])) {
                // Shape matches Municipio card/image expectations (src/alt plus optional srcset/sizes)
                $image = [
                    'src' => (string) ($decoded['src'] ?? ''),
                    'alt' => (string) ($decoded['alt'] ?? ($post->post_title ?? '')),
                    'srcset' => !empty($decoded['srcset']) ? (string) $decoded['srcset'] : null,
                    'sizes' => !empty($decoded['sizes']) ? (string) $decoded['sizes'] : null,
                ];
                $heroSrc = $this->heroImageSrc($image['src']);
                $imageHero = [
                    'src' => $heroSrc,
                    'alt' => $image['alt'],
                    'srcset' => $image['srcset'],
                    'sizes' => '100vw',
                ];
            }
        }

        $startTimestamp = null;
        if (is_string($startDate) && $startDate !== '') {
            $tz = wp_timezone();
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startDate, $tz);
            if ($dt !== false) {
                $startTimestamp = $dt->getTimestamp();
            }
        }

        $endTimestamp = null;
        if (is_string($endDate) && $endDate !== '') {
            $tz = wp_timezone();
            $dtEnd = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endDate, $tz);
            if ($dtEnd !== false) {
                $endTimestamp = $dtEnd->getTimestamp();
            }
        }

        $date = $startTimestamp ? ['timestamp' => $startTimestamp, 'action' => 'formatDate'] : null;
        $dateBadge = !empty($image) && !empty($startTimestamp);

        $dateTimeLabel = null;
        if (!empty($startTimestamp)) {
            // Example desired: "Tisdag, 16 september, 12.00 - 16.00"
            $dayAndDate = date_i18n('l, j F', (int) $startTimestamp);
            // Capitalize first letter (Swedish weekdays are often lower-case)
            if (is_string($dayAndDate) && $dayAndDate !== '') {
                $first = mb_substr($dayAndDate, 0, 1, 'UTF-8');
                $rest = mb_substr($dayAndDate, 1, null, 'UTF-8');
                $dayAndDate = mb_strtoupper($first, 'UTF-8') . $rest;
            }

            $startTime = date_i18n('H.i', (int) $startTimestamp);
            $dateTimeLabel = $dayAndDate . ', ' . $startTime;

            if (!empty($endTimestamp)) {
                $endTime = date_i18n('H.i', (int) $endTimestamp);
                $dateTimeLabel .= ' - ' . $endTime;
            }
        }

        // Attach computed data to the post object
        $post->simpleviewEventData = (object) [
            'mediaChannelName' => $mediaChannelName,
            'mediaChannelId' => $mediaChannelId,
            'simpleviewId' => $simpleviewId,
            'startDate' => $startDate,
            'startTimestamp' => $startTimestamp,
            'endDate' => $endDate,
            'endTimestamp' => $endTimestamp,
            'dateTimeLabel' => $dateTimeLabel,
            'locationName' => $locationName,
            'image' => $image,
            'imageHero' => $imageHero,
            'date' => $date,
            'dateBadge' => $dateBadge,
            'ariaLabel' => $post->post_title ?? '',
        ];

        return $post;
    }

    /**
     * For single-view hero: return a higher-resolution image URL when the API uses dimension query params (e.g. dw=630).
     * Card/theme often use only src, so we must supply a larger URL here instead of relying on srcset/sizes.
     */
    private function heroImageSrc(string $src): string
    {
        if ($src === '') {
            return $src;
        }
        $heroWidth = 1920;
        $hasDw = preg_match('/[?&]dw=(\d+)/', $src, $dw);
        $hasDh = preg_match('/[?&]dh=(\d+)/', $src, $dh);
        if ($hasDw) {
            $w = (int) $dw[1];
            $newH = $hasDh && $w > 0
                ? (int) round((int) $dh[1] * $heroWidth / $w)
                : null;
            $src = preg_replace('/([?&])dw=\d+/', '${1}dw=' . $heroWidth, $src, 1);
            if ($newH !== null) {
                $src = preg_replace('/([?&])dh=\d+/', '${1}dh=' . $newH, $src, 1);
            }
        }
        return $src;
    }
}
