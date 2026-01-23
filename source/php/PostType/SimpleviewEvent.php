<?php

namespace ModularitySimpleviewEvents\PostType;

/**
 * Class SimpleviewEvent
 * 
 * Registers the Simpleview Event custom post type and its taxonomies.
 * 
 * @package ModularitySimpleviewEvents\PostType
 */
class SimpleviewEvent
{
    public function __construct()
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerTaxonomies']);
    }

    /**
     * Register the Simpleview Event custom post type
     * 
     * @return void
     */
    public function registerPostType(): void
    {
        $slug = get_field('slug', 'simpleview-events-settings') ?: 'simpleview-event';

        // Get display name from settings (for frontend: archive title, breadcrumbs)
        $displayName = get_field('display_name', 'simpleview-events-settings');
        $pluralName = !empty($displayName) ? $displayName : __('Simpleview Events', 'modularity-simpleview-events');
        $singularName = !empty($displayName) ? $displayName : __('Simpleview Event', 'modularity-simpleview-events');

        $labels = [
            // Frontend labels (use custom display name)
            'name'                  => $pluralName,
            'singular_name'         => $singularName,
            'archives'              => $pluralName,

            // Admin labels (keep fixed for plugin identity)
            'menu_name'             => __('Simpleview Events', 'modularity-simpleview-events'),
            'name_admin_bar'        => __('Simpleview Event', 'modularity-simpleview-events'),
            'add_new'               => __('Add New', 'modularity-simpleview-events'),
            'add_new_item'          => __('Add New Simpleview Event', 'modularity-simpleview-events'),
            'new_item'              => __('New Simpleview Event', 'modularity-simpleview-events'),
            'edit_item'             => __('Edit Simpleview Event', 'modularity-simpleview-events'),
            'view_item'             => __('View Simpleview Event', 'modularity-simpleview-events'),
            'all_items'             => __('All Simpleview Events', 'modularity-simpleview-events'),
            'search_items'          => __('Search Simpleview Events', 'modularity-simpleview-events'),
            'parent_item_colon'     => __('Parent Simpleview Event:', 'modularity-simpleview-events'),
            'not_found'             => __('No simpleview events found.', 'modularity-simpleview-events'),
            'not_found_in_trash'    => __('No simpleview events found in Trash.', 'modularity-simpleview-events'),
            'featured_image'        => __('Featured Image', 'modularity-simpleview-events'),
            'set_featured_image'    => __('Set featured image', 'modularity-simpleview-events'),
            'remove_featured_image' => __('Remove featured image', 'modularity-simpleview-events'),
            'use_featured_image'    => __('Use as featured image', 'modularity-simpleview-events'),
            'insert_into_item'      => __('Insert into simpleview event', 'modularity-simpleview-events'),
            'uploaded_to_this_item' => __('Uploaded to this simpleview event', 'modularity-simpleview-events'),
            'filter_items_list'     => __('Filter simpleview events list', 'modularity-simpleview-events'),
            'items_list_navigation' => __('Simpleview events list navigation', 'modularity-simpleview-events'),
            'items_list'            => __('Simpleview events list', 'modularity-simpleview-events'),
        ];

        $args = [
            'labels'             => $labels,
            'description'        => __('Events synced from Simpleview API', 'modularity-simpleview-events'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => [
                'slug' => $slug,
                'with_front' => false,
            ],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'show_in_rest'       => true,
        ];

        register_post_type('simpleview_event', $args);
    }

    /**
     * Register the taxonomy for Simpleview Events
     * 
     * Single hierarchical taxonomy where departments are top-level terms
     * and categories are child terms under their parent department.
     * 
     * @return void
     */
    public function registerTaxonomies(): void
    {
        $labels = [
            'name'                       => __('Event Categories', 'modularity-simpleview-events'),
            'singular_name'              => __('Event Category', 'modularity-simpleview-events'),
            'menu_name'                  => __('Event Categories', 'modularity-simpleview-events'),
            'all_items'                  => __('All Event Categories', 'modularity-simpleview-events'),
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
            'description'       => __('Hierarchical categories for simpleview events. Departments are top-level terms, categories are child terms.', 'modularity-simpleview-events'),
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug' => 'calendar',
                'hierarchical' => true,
                'with_front' => false,
            ],
        ];

        register_taxonomy('sv_event_category', ['simpleview_event'], $args);
    }
}
