<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents;

/**
 * Integrates Simpleview events with Typesense search indexing.
 *
 * - Uses the image from simpleview_event_image_json as the search result thumbnail.
 * - Adds event-specific fields (date_overlay, date_time_label, media_channel_name, location_name).
 * - Registers hit template, post type mapping, and placeholder mappings for the event card.
 */
class TypesenseSearchIntegration
{
    private const META_IMAGE_JSON = 'simpleview_event_image_json';
    private const TEMPLATE_KEY = 'simpleview-event';

    public function __construct()
    {
        add_filter(
            'Municipio/TypesenseSearch/DocumentBuilder/build',
            [$this, 'enrichSimpleviewDocument'],
            10,
            2
        );
        add_filter('Municipio/TypesenseSearch/hitTemplates', [$this, 'addHitTemplate']);
        add_filter('Municipio/TypesenseSearch/hitTemplateView', [$this, 'resolveHitTemplateView'], 10, 2);
        add_filter('Municipio/TypesenseSearch/postTypeToTemplate', [$this, 'mapPostTypesToTemplate']);
        add_filter('Municipio/TypesenseSearch/placeholderMappings', [$this, 'addPlaceholderMappings']);
    }

    /**
     * @param string[] $templates
     * @return string[]
     */
    public function addHitTemplate(array $templates): array
    {
        $templates[] = self::TEMPLATE_KEY;
        return $templates;
    }

    /**
     * @param string $view
     * @param string $key
     * @return string
     */
    public function resolveHitTemplateView(string $view, string $key): string
    {
        if ($key === self::TEMPLATE_KEY) {
            return 'templates.hits.simpleview-event';
        }
        return $view;
    }

    /**
     * @param array<string, string> $mapping
     * @return array<string, string>
     */
    public function mapPostTypesToTemplate(array $mapping): array
    {
        $registered = get_option('simpleview_events_registered_post_types', []);
        if (!is_array($registered)) {
            return $mapping;
        }
        foreach (array_keys($registered) as $postType) {
            $mapping[$postType] = self::TEMPLATE_KEY;
        }
        return $mapping;
    }

    /**
     * @param array<string, string> $mappings
     * @return array<string, string>
     */
    public function addPlaceholderMappings(array $mappings): array
    {
        return array_merge($mappings, [
            'SEARCH_HIT_DATE_OVERLAY'    => 'date_overlay',
            'SEARCH_HIT_DATE_TIME_LABEL' => 'date_time_label',
            'SEARCH_HIT_MEDIA_CHANNEL'   => 'media_channel_name',
            'SEARCH_HIT_LOCATION'        => 'location_name',
        ]);
    }

    /**
     * Enrich the Typesense document with Simpleview event data.
     *
     * @param array<string, mixed> $document The Typesense document array.
     * @param \WP_Post             $post    The source post.
     * @return array<string, mixed>
     */
    public function enrichSimpleviewDocument(array $document, \WP_Post $post): array
    {
        if (!$this->isSimpleviewPostType($post->post_type)) {
            return $document;
        }

        $document = $this->useSimpleviewImageAsThumbnail($document, $post);
        $document = $this->addEventFields($document, $post);

        return $document;
    }

    /**
     * Replace the default thumbnail with the Simpleview event image.
     *
     * @param array<string, mixed> $document
     * @param \WP_Post             $post
     * @return array<string, mixed>
     */
    private function useSimpleviewImageAsThumbnail(array $document, \WP_Post $post): array
    {
        $imageJson = get_post_meta($post->ID, self::META_IMAGE_JSON, true);
        if (!is_string($imageJson) || trim($imageJson) === '') {
            return $document;
        }

        $image = json_decode($imageJson, true);
        if (!is_array($image)) {
            return $document;
        }

        $thumbnailUrl = $this->extractThumbnailUrl($image);
        if ($thumbnailUrl !== '') {
            $document['thumbnail'] = $thumbnailUrl;
        }

        return $document;
    }

    /**
     * Add event-specific fields for the hit template.
     *
     * @param array<string, mixed> $document
     * @param \WP_Post             $post
     * @return array<string, mixed>
     */
    private function addEventFields(array $document, \WP_Post $post): array
    {
        $startDate = get_post_meta($post->ID, 'start_date', true);
        $endDate = get_post_meta($post->ID, 'simpleview_event_end_date', true);
        $mediaChannelName = get_post_meta($post->ID, 'simpleview_media_channel_name', true);
        $locationName = get_post_meta($post->ID, 'simpleview_event_location_name', true);

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

        if ($startTimestamp !== null) {
            $document['date_overlay'] = date_i18n('j M', $startTimestamp);

            $dayAndDate = date_i18n('l, j F', $startTimestamp);
            if (is_string($dayAndDate) && $dayAndDate !== '') {
                $first = mb_substr($dayAndDate, 0, 1, 'UTF-8');
                $rest = mb_substr($dayAndDate, 1, null, 'UTF-8');
                $dayAndDate = mb_strtoupper($first, 'UTF-8') . $rest;
            }
            $startTime = date_i18n('H.i', $startTimestamp);
            $document['date_time_label'] = $dayAndDate . ', ' . $startTime;
            if ($endTimestamp !== null) {
                $endTime = date_i18n('H.i', $endTimestamp);
                $document['date_time_label'] .= ' - ' . $endTime;
            }
        } else {
            $document['date_overlay'] = '';
            $document['date_time_label'] = '';
        }

        $document['media_channel_name'] = is_string($mediaChannelName) ? $mediaChannelName : '';
        $document['location_name'] = is_string($locationName) ? $locationName : '';

        return $document;
    }

    private function isSimpleviewPostType(string $postType): bool
    {
        $registered = get_option('simpleview_events_registered_post_types', []);
        if (!is_array($registered)) {
            return false;
        }

        return array_key_exists($postType, $registered);
    }

    /**
     * Extract a suitable thumbnail URL from the SimpleviewEventMetaBuilder image JSON.
     * Prefers large variant for sharper display in search result cards (especially on Retina).
     *
     * @param array<string, mixed> $image Decoded simpleview_event_image_json.
     * @return string
     */
    private function extractThumbnailUrl(array $image): string
    {
        $variants = $image['variants'] ?? [];
        if (is_array($variants)) {
            $large = $variants['large'] ?? null;
            if (is_array($large) && !empty($large['src']) && is_string($large['src'])) {
                return trim($large['src']);
            }
            $small = $variants['small'] ?? null;
            if (is_array($small) && !empty($small['src']) && is_string($small['src'])) {
                return trim($small['src']);
            }
            $thumbnail = $variants['thumbnail'] ?? null;
            if (is_array($thumbnail) && !empty($thumbnail['src']) && is_string($thumbnail['src'])) {
                return trim($thumbnail['src']);
            }
        }

        $src = $image['src'] ?? null;
        return is_string($src) && trim($src) !== '' ? trim($src) : '';
    }
}
