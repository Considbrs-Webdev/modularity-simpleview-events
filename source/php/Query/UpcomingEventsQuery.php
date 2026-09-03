<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\Query;

use ModularitySimpleviewEvents\ApplyDecorator\ApplySimpleviewEventData;
use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;
use WP_Post;

/**
 * Fetches upcoming Simpleview events for the listing module.
 */
class UpcomingEventsQuery
{
    private const LIMIT = 4;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingEvents(string $postType): array
    {
        if (!$this->isAllowedPostType($postType)) {
            return [];
        }

        $todayStart = (new \DateTimeImmutable('today', wp_timezone()))->getTimestamp();

        $query = new \WP_Query([
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => self::LIMIT,
            'meta_key' => 'start_date_timestamp',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'start_date_timestamp',
                    'value' => $todayStart,
                    'compare' => '>=',
                    'type' => 'NUMERIC',
                ],
            ],
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ]);

        if (!$query->have_posts()) {
            return [];
        }

        $decorator = new ApplySimpleviewEventData();
        $events = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $decorated = $decorator->apply($post);
            $eventData = $decorated->simpleviewEventData ?? null;

            if (!is_object($eventData)) {
                continue;
            }

            $imageSrc = '';
            if (is_array($eventData->image ?? null) && !empty($eventData->image['src'])) {
                $imageSrc = (string) $eventData->image['src'];
            }

            $startTimestamp = is_int($eventData->startTimestamp ?? null)
                ? $eventData->startTimestamp
                : null;

            $events[] = [
                'id' => (int) $post->ID,
                'title' => $post->post_title,
                'link' => get_permalink($post),
                'image' => $imageSrc,
                'badgeDate' => EventBadgeFormatter::fromTimestamp($startTimestamp),
                'dateSpan' => (string) ($eventData->dateTimeLabel ?? ''),
                'location' => (string) ($eventData->locationName ?? ''),
            ];
        }

        wp_reset_postdata();

        return $events;
    }

    private function isAllowedPostType(string $postType): bool
    {
        if ($postType === '' || !str_starts_with($postType, 'sv_')) {
            return false;
        }

        $registered = (new DynamicPostTypeManager())->getRegisteredPostTypes();

        return array_key_exists($postType, $registered);
    }
}
