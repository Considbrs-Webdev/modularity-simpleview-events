<?php

namespace ModularitySimpleviewEvents\Customizer;

/**
 * Sets default Municipio archive Customizer settings when a Simpleview post type is first registered.
 * Only applies values that have not been set by the user.
 */
class ArchiveDefaultsApplicator
{
    /**
     * Default archive settings for event post types (sv_*).
     * Keys match Municipio's archive_{post_type}_{setting} theme mod pattern.
     */
    private const DEFAULTS = [
        'style' => 'cards',
        'post_count' => 12,
        'number_of_columns' => 1,
        'order_by' => 'start_date_timestamp',
        'order_direction' => 'asc',
        'date_field' => 'start_date',
        'date_format' => 'date',
        'display_openstreetmap' => 0,
        'display_archive_loop' => 0,
        'display_google_maps_link' => 0,
        'filter_type' => 'or',
        'reading_time' => 0,
    ];

    /**
     * Default enabled filters: text search, date range, and category taxonomy.
     * The category slug is appended per post type in applyDefaults().
     */
    private const FILTER_TEXT_SEARCH = 'text_search';
    private const FILTER_DATE_RANGE = 'date_range';

    /**
     * Apply default archive settings for a post type if not already set.
     *
     * @param string $postTypeSlug Post type slug (e.g. sv_lov)
     * @return void
     */
    public function applyDefaults(string $postTypeSlug): void
    {
        if (!str_starts_with($postTypeSlug, 'sv_')) {
            return;
        }

        foreach (self::DEFAULTS as $key => $value) {
            $themeModKey = "archive_{$postTypeSlug}_{$key}";

            if (get_theme_mod($themeModKey) === false) {
                set_theme_mod($themeModKey, $value);
            }
        }

        $enabledFiltersKey = "archive_{$postTypeSlug}_enabled_filters";
        if (get_theme_mod($enabledFiltersKey) === false) {
            $categoryTaxonomySlug = $postTypeSlug . '_category';
            $enabledFilters = [
                self::FILTER_TEXT_SEARCH,
                self::FILTER_DATE_RANGE,
                $categoryTaxonomySlug,
            ];
            set_theme_mod($enabledFiltersKey, $enabledFilters);
        }
    }
}
