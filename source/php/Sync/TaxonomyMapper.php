<?php

namespace ModularitySimpleviewEvents\Sync;

/**
 * Class TaxonomyMapper
 * 
 * Maps Simpleview API data to WordPress taxonomy.
 * Extracts departments (top-level terms) and categories (child terms) from API response.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class TaxonomyMapper
{
    private const TAXONOMY = 'sv_event_category';

    /**
     * Extract and sync departments from API data as top-level terms
     * 
     * This is a placeholder method. The actual extraction logic
     * will be implemented once the API data structure is provided.
     * 
     * @param array $events Array of event data from API
     * @return array Array of department term IDs mapped by department identifier
     */
    public function syncDepartments(array $events): array
    {
        $departments = [];

        // TODO: Extract unique departments from events once API structure is known
        // Example structure (to be updated):
        // foreach ($events as $event) {
        //     if (isset($event['department'])) {
        //         $deptId = $event['department']['id'];
        //         $deptName = $event['department']['name'];
        //         
        //         if (!isset($departments[$deptId])) {
        //             $termId = $this->createOrUpdateTerm(
        //                 self::TAXONOMY,
        //                 $deptName,
        //                 ['simpleview_id' => $deptId],
        //                 0 // Top-level term (parent = 0)
        //             );
        //             $departments[$deptId] = $termId;
        //         }
        //     }
        // }

        return $departments;
    }

    /**
     * Extract and sync categories from API data as child terms under departments
     * 
     * This is a placeholder method. The actual extraction logic
     * will be implemented once the API data structure is provided.
     * 
     * @param array $events Array of event data from API
     * @param array $departments Array of department term IDs mapped by department identifier
     * @return array Array of category term IDs mapped by category identifier
     */
    public function syncCategories(array $events, array $departments): array
    {
        $categories = [];

        // TODO: Extract unique categories from events once API structure is known
        // Categories should be linked to their parent departments
        // Example structure (to be updated):
        // foreach ($events as $event) {
        //     if (isset($event['category'])) {
        //         $catId = $event['category']['id'];
        //         $catName = $event['category']['name'];
        //         $deptId = $event['department']['id'];
        //         
        //         if (!isset($categories[$catId])) {
        //             $parentTermId = $departments[$deptId] ?? 0;
        //             $termId = $this->createOrUpdateTerm(
        //                 self::TAXONOMY,
        //                 $catName,
        //                 ['simpleview_id' => $catId],
        //                 $parentTermId // Child term under department
        //             );
        //             $categories[$catId] = $termId;
        //         }
        //     }
        // }

        return $categories;
    }

    /**
     * Create or update a taxonomy term in sv_event_category
     * 
     * @param string $taxonomy Taxonomy slug (should be sv_event_category)
     * @param string $name Term name
     * @param array $meta Meta fields to set (e.g., simpleview_id)
     * @param int $parent Parent term ID (0 for top-level/department terms)
     * @return int Term ID
     */
    private function createOrUpdateTerm(string $taxonomy, string $name, array $meta = [], int $parent = 0): int
    {
        // Check if term exists by simpleview_id meta
        $simpleviewId = $meta['simpleview_id'] ?? null;
        $existingTerm = null;

        if ($simpleviewId) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'meta_query' => [
                    [
                        'key' => 'simpleview_id',
                        'value' => $simpleviewId,
                        'compare' => '=',
                    ],
                ],
            ]);

            if (!is_wp_error($terms) && !empty($terms)) {
                $existingTerm = $terms[0];
            }
        }

        // If not found by meta, try by name and parent (to avoid duplicates with same name but different parents)
        if (!$existingTerm) {
            $args = [
                'taxonomy' => $taxonomy,
                'name' => $name,
                'hide_empty' => false,
            ];
            
            // If parent is specified, also check parent to ensure we get the right term
            if ($parent > 0) {
                $args['parent'] = $parent;
            } else {
                // For top-level terms, explicitly check parent is 0
                $args['parent'] = 0;
            }
            
            $terms = get_terms($args);

            if (!is_wp_error($terms) && !empty($terms)) {
                $existingTerm = $terms[0];
            }
        }

        if ($existingTerm) {
            // Update existing term
            $termId = $existingTerm->term_id;
            wp_update_term($termId, $taxonomy, [
                'name' => $name,
                'parent' => $parent,
            ]);
        } else {
            // Create new term
            $result = wp_insert_term($name, $taxonomy, [
                'parent' => $parent,
            ]);

            if (is_wp_error($result)) {
                error_log('Failed to create term: ' . $result->get_error_message());
                return 0;
            }

            $termId = $result['term_id'];
        }

        // Set meta fields
        foreach ($meta as $key => $value) {
            update_term_meta($termId, $key, $value);
        }

        return $termId;
    }
}
