<?php

namespace ModularitySimpleviewEvents;

use ModularitySimpleviewEvents\PostType\SimpleviewEvent;
use ModularitySimpleviewEvents\Admin\Settings;
use ModularitySimpleviewEvents\Cron\SyncScheduler;

/**
 * Class App
 * 
 * Main application bootstrap class.
 * Initialize your plugin components here.
 * 
 * @package ModularitySimpleviewEvents
 */
class App
{
    public function __construct()
    {
        // Initialize settings page
        new Settings();

        // Initialize custom post type
        new SimpleviewEvent();

        // Initialize cron scheduler
        new SyncScheduler();

        // Fix taxonomy archive queries - set post type for sv_event_category taxonomy
        add_action('pre_get_posts', [$this, 'setPostTypeForTaxonomyArchive']);

        // Fix the tax_query operator for our hierarchical taxonomy
        add_action('pre_get_posts', [$this, 'fixTaxQueryOperator'], 5);
        
        // Fix tax_query structure when filtering on taxonomy archives
        add_action('pre_get_posts', [$this, 'fixTaxQueryForFiltering'], 6);

        // Enable taxonomy filtering on taxonomy archive pages
        add_filter('Municipio/Archive/getTaxonomyFilters/taxonomies', [$this, 'enableTaxonomyFilteringOnTaxonomyArchives'], 10, 2);
        add_filter('get_terms', [$this, 'filterTermsToChildrenOnly'], 10, 4);
    }

    /**
     * Set the correct post type for sv_event_category taxonomy archives.
     * 
     * WordPress doesn't automatically associate taxonomy archives with their
     * registered post type, so we need to explicitly set it.
     * 
     * @param \WP_Query $query The WP_Query instance
     * @return void
     */
    public function setPostTypeForTaxonomyArchive(\WP_Query $query): void
    {
        // Only modify main frontend queries for our taxonomy
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Check if this is a sv_event_category taxonomy archive
        if (!$query->is_tax('sv_event_category')) {
            return;
        }

        // Set the post type to our custom post type
        $query->set('post_type', 'simpleview_event');
    }

    /**
     * Fix the tax_query operator for sv_event_category taxonomy.
     * 
     * Municipio's ApplyTaxQuery doesn't recognize our taxonomy as hierarchical,
     * so it uses 'AND' operator which requires posts to have ALL terms.
     * We need 'IN' operator to match posts with ANY of the terms.
     *
     * @param \WP_Query $query
     * @return void
     */
    public function fixTaxQueryOperator(\WP_Query $query): void
    {
        if (is_admin()) {
            return;
        }

        $taxQuery = $query->get('tax_query');
        if (empty($taxQuery) || !is_array($taxQuery)) {
            return;
        }

        $modified = false;
        foreach ($taxQuery as $key => $clause) {
            if (!is_array($clause)) {
                continue;
            }

            // Check if this is our taxonomy with AND operator
            if (
                isset($clause['taxonomy']) &&
                $clause['taxonomy'] === 'sv_event_category' &&
                isset($clause['operator']) &&
                $clause['operator'] === 'AND'
            ) {
                $taxQuery[$key]['operator'] = 'IN';
                $modified = true;
            }
        }

        if ($modified) {
            $query->set('tax_query', $taxQuery);
        }
    }

    /**
     * Fix tax_query structure when filtering on taxonomy archives.
     * 
     * When filtering on a taxonomy archive, Municipio combines the archive term
     * and filtered terms in a single clause with operator "IN", which matches
     * posts with ANY term. We need to split them into separate clauses so posts
     * must match BOTH the archive term AND the filtered term.
     *
     * @param \WP_Query $query
     * @return void
     */
    public function fixTaxQueryForFiltering(\WP_Query $query): void
    {
        if (is_admin()) {
            return;
        }

        // Only process on taxonomy archive pages
        if (!is_tax('sv_event_category')) {
            return;
        }

        $taxQuery = $query->get('tax_query');
        if (empty($taxQuery) || !is_array($taxQuery)) {
            return;
        }

        // Get current archive term
        $currentTerm = get_queried_object();
        if (!($currentTerm instanceof \WP_Term) || $currentTerm->taxonomy !== 'sv_event_category') {
            return;
        }

        // Check if there are filter parameters for our taxonomy
        $filterParam = 'archive_sv_event_category';
        $hasFilterParams = !empty($_GET[$filterParam]) && is_array($_GET[$filterParam]) && !empty(array_filter($_GET[$filterParam]));

        if (!$hasFilterParams) {
            return;
        }

        // Find the sv_event_category clause
        foreach ($taxQuery as $key => $clause) {
            if (!is_array($clause)) {
                continue;
            }

            if (
                isset($clause['taxonomy']) &&
                $clause['taxonomy'] === 'sv_event_category' &&
                isset($clause['terms']) &&
                is_array($clause['terms']) &&
                count($clause['terms']) > 1
            ) {
                // Check if current term is in the terms array
                $currentTermInTerms = in_array($currentTerm->term_id, $clause['terms'], true);
                
                if ($currentTermInTerms) {
                    // Split into two clauses: one for archive term, one for filtered terms
                    $filteredTerms = array_filter($clause['terms'], fn($termId) => $termId !== $currentTerm->term_id);
                    
                    if (!empty($filteredTerms)) {
                        // Remove the original clause
                        unset($taxQuery[$key]);
                        
                        // Add two separate clauses with AND relation
                        $taxQuery[] = [
                            'taxonomy' => 'sv_event_category',
                            'field' => 'term_id',
                            'terms' => [$currentTerm->term_id],
                            'operator' => 'IN',
                        ];
                        
                        $taxQuery[] = [
                            'taxonomy' => 'sv_event_category',
                            'field' => 'term_id',
                            'terms' => array_values($filteredTerms),
                            'operator' => 'IN',
                        ];
                        
                        // Ensure relation is AND
                        if (!isset($taxQuery['relation']) || $taxQuery['relation'] !== 'AND') {
                            $taxQuery['relation'] = 'AND';
                        }
                        
                        // Re-index array keys (keep relation first)
                        $relation = $taxQuery['relation'] ?? 'AND';
                        unset($taxQuery['relation']);
                        $taxQuery = array_merge(['relation' => $relation], array_values($taxQuery));
                        
                        $query->set('tax_query', $taxQuery);
                    }
                }
                
                break;
            }
        }
    }

    /**
     * Enable taxonomy filtering on taxonomy archive pages.
     * 
     * Re-adds the current taxonomy to the enabled filters list so that
     * child terms can be filtered on taxonomy archive pages.
     * 
     * @param array $taxonomies The list of taxonomy names
     * @param string|null $currentTaxonomy The current taxonomy being viewed
     * @return array
     */
    public function enableTaxonomyFilteringOnTaxonomyArchives(array $taxonomies, ?string $currentTaxonomy): array
    {
        // Only process on sv_event_category taxonomy archives
        if ($currentTaxonomy !== 'sv_event_category') {
            return $taxonomies;
        }

        // Re-add the current taxonomy to enable filtering by child terms
        if (!in_array('sv_event_category', $taxonomies, true)) {
            $taxonomies[] = 'sv_event_category';
        }

        return $taxonomies;
    }

    /**
     * Filter terms to only show child terms of the current term on taxonomy archives.
     * 
     * When building taxonomy filters on a taxonomy archive page, only show
     * child terms of the current term, not all terms in the taxonomy.
     * 
     * @param array|\WP_Error $terms The terms array
     * @param array $taxonomies The taxonomies being queried
     * @param array $args The get_terms arguments
     * @param \WP_Term_Query $term_query The term query object
     * @return array|\WP_Error
     */
    public function filterTermsToChildrenOnly($terms, array $taxonomies, array $args, \WP_Term_Query $term_query)
    {
        // Return early if terms is an error
        if (is_wp_error($terms)) {
            return $terms;
        }

        // Only process on frontend, not admin
        if (is_admin()) {
            return $terms;
        }

        // Only process if we're querying sv_event_category
        if (!in_array('sv_event_category', $taxonomies, true)) {
            return $terms;
        }

        // Only process on taxonomy archive pages
        if (!is_tax('sv_event_category')) {
            return $terms;
        }

        // Get the current term
        $currentTerm = get_queried_object();
        if (!($currentTerm instanceof \WP_Term) || $currentTerm->taxonomy !== 'sv_event_category') {
            return $terms;
        }

        // If current term has no parent (it's a top-level term), filter to only show its children
        if ($currentTerm->parent === 0) {
            // Filter terms to only include direct children of the current term
            $filteredTerms = array_filter($terms, function($term) use ($currentTerm) {
                if (!($term instanceof \WP_Term)) {
                    return false;
                }
                // Only include direct children (parent matches current term ID)
                return $term->parent === $currentTerm->term_id;
            });

            return array_values($filteredTerms);
        }

        // If current term is a child, we could show siblings, but for now just return all
        // (This could be customized based on requirements)
        return $terms;
    }
}
