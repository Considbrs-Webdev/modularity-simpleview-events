<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents;

/**
 * Integrates Simpleview events with Typesense search indexing.
 *
 * For Simpleview post types, uses the image from simpleview_event_image_json
 * (built by SimpleviewEventMetaBuilder) as the search result thumbnail instead
 * of the WordPress featured image.
 */
class TypesenseSearchIntegration
{
    private const META_IMAGE_JSON = 'simpleview_event_image_json';

    public function __construct()
    {
        add_filter(
            'Municipio/TypesenseSearch/DocumentBuilder/build',
            [$this, 'useSimpleviewImageAsThumbnail'],
            10,
            2
        );
    }

    /**
     * Replace the default thumbnail with the Simpleview event image for
     * Simpleview post types that have simpleview_event_image_json meta.
     *
     * @param array<string, mixed> $document The Typesense document array.
     * @param \WP_Post             $post    The source post.
     * @return array<string, mixed>
     */
    public function useSimpleviewImageAsThumbnail(array $document, \WP_Post $post): array
    {
        if (!$this->isSimpleviewPostType($post->post_type)) {
            return $document;
        }

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
     * Prefers small/medium-sized variant for search result thumbnails.
     *
     * @param array<string, mixed> $image Decoded simpleview_event_image_json.
     * @return string
     */
    private function extractThumbnailUrl(array $image): string
    {
        $variants = $image['variants'] ?? [];
        if (is_array($variants)) {
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
