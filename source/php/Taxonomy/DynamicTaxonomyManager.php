<?php

namespace ModularitySimpleviewEvents\Taxonomy;

/**
 * Class DynamicTaxonomyManager
 * 
 * Manages category taxonomies for each dynamic post type.
 * 
 * @package ModularitySimpleviewEvents\Taxonomy
 */
class DynamicTaxonomyManager
{
    /**
     * Register a category taxonomy for a post type
     * 
     * @param string $postTypeSlug The post type slug
     * @param string $mediaChannelName The mediaChannel name (for labels)
     * @return string The taxonomy slug
     */
    public function registerCategoryTaxonomyForPostType(string $postTypeSlug, string $mediaChannelName): string
    {
        $taxonomySlug = $this->getTaxonomySlug($postTypeSlug);

        $labels = [
            'name'                       => sprintf(__('%s Categories', 'modularity-simpleview-events'), $mediaChannelName),
            'singular_name'              => sprintf(__('%s Category', 'modularity-simpleview-events'), $mediaChannelName),
            'menu_name'                  => sprintf(__('%s Categories', 'modularity-simpleview-events'), $mediaChannelName),
            'all_items'                  => sprintf(__('All %s Categories', 'modularity-simpleview-events'), $mediaChannelName),
            'parent_item'                => __('Parent Category', 'modularity-simpleview-events'),
            'parent_item_colon'          => __('Parent Category:', 'modularity-simpleview-events'),
            'new_item_name'              => __('New Category Name', 'modularity-simpleview-events'),
            'add_new_item'               => __('Add New Category', 'modularity-simpleview-events'),
            'edit_item'                  => __('Edit Category', 'modularity-simpleview-events'),
            'update_item'                => __('Update Category', 'modularity-simpleview-events'),
            'view_item'                  => __('View Category', 'modularity-simpleview-events'),
            'separate_items_with_commas' => __('Separate categories with commas', 'modularity-simpleview-events'),
            'add_or_remove_items'        => __('Add or remove categories', 'modularity-simpleview-events'),
            'choose_from_most_used'      => __('Choose from the most used', 'modularity-simpleview-events'),
            'popular_items'              => __('Popular Categories', 'modularity-simpleview-events'),
            'search_items'               => __('Search Categories', 'modularity-simpleview-events'),
            'not_found'                  => __('Not Found', 'modularity-simpleview-events'),
            'no_terms'                   => __('No categories', 'modularity-simpleview-events'),
            'items_list'                 => __('Categories list', 'modularity-simpleview-events'),
            'items_list_navigation'      => __('Categories list navigation', 'modularity-simpleview-events'),
        ];

        $args = [
            'labels'            => $labels,
            'description'       => sprintf(__('Categories for %s events', 'modularity-simpleview-events'), $mediaChannelName),
            'hierarchical'      => false, // Categories are flat (no parent-child relationship needed)
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug' => $taxonomySlug,
                'with_front' => false,
            ],
        ];

        register_taxonomy($taxonomySlug, [$postTypeSlug], $args);

        return $taxonomySlug;
    }

    /**
     * Generate taxonomy slug from post type slug
     * 
     * @param string $postTypeSlug The post type slug
     * @return string The taxonomy slug
     */
    public function getTaxonomySlug(string $postTypeSlug): string
    {
        return $postTypeSlug . '_category';
    }
}
