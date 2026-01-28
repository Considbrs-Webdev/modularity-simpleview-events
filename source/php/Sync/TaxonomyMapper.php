<?php

namespace ModularitySimpleviewEvents\Sync;

/**
 * Class TaxonomyMapper
 * 
 * Maps Simpleview API data to WordPress taxonomy.
 * Extracts categories from products within a specific mediaChannel.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class TaxonomyMapper
{
    /**
     * Extract and sync categories from products within a mediaChannel
     * 
     * Extracts categories from categoryList.category.categorySubType1List.categorySubType1
     * 
     * @param array $products Array of product data from API (filtered by mediaChannel)
     * @param string $taxonomySlug The taxonomy slug to sync to
     * @param string $postTypeSlug The post type slug (for validation)
     * @return array Array of category term IDs mapped by Simpleview category ID
     */
    public function syncCategories(array $products, string $taxonomySlug, string $postTypeSlug): array
    {
        $categories = [];
        $uniqueCategories = [];

        // Extract unique categories from all products
        foreach ($products as $product) {
            $categoryData = $this->extractCategoriesFromProduct($product);

            foreach ($categoryData as $category) {
                $categoryId = $category['id'] ?? '';
                $categoryName = $category['name'] ?? '';

                if (!empty($categoryId) && !empty($categoryName)) {
                    // Use category ID as key to ensure uniqueness
                    if (!isset($uniqueCategories[$categoryId])) {
                        $uniqueCategories[$categoryId] = $categoryName;
                    }
                }
            }
        }

        // Create/update terms for each unique category
        foreach ($uniqueCategories as $categoryId => $categoryName) {
            $termId = $this->createOrUpdateTerm(
                $taxonomySlug,
                $categoryName,
                ['simpleview_id' => $categoryId],
                0 // Categories are flat (no hierarchy)
            );

            if ($termId > 0) {
                $categories[$categoryId] = $termId;
            }
        }

        return $categories;
    }

    /**
     * Extract categories from a single product
     * 
     * Handles the structure: categoryList.category.categorySubType1List.categorySubType1
     * Can be single object or array
     * 
     * @param array $product Single product data from API
     * @return array Array of category data with 'id' and 'name' keys
     */
    public function extractCategoriesFromProduct(array $product): array
    {
        $categories = [];

        // Navigate: categoryList -> category -> categorySubType1List -> categorySubType1
        if (!isset($product['categoryList']['category'])) {
            return $categories;
        }

        $category = $product['categoryList']['category'];

        // Handle both single object and array
        if (isset($category['categorySubType1List']['categorySubType1'])) {
            $subType1 = $category['categorySubType1List']['categorySubType1'];

            // Handle both single object and array
            if (isset($subType1[0])) {
                // Array of categorySubType1
                foreach ($subType1 as $subType) {
                    if (isset($subType['@id']) && isset($subType['name'])) {
                        $categories[] = [
                            'id' => (string) $subType['@id'],
                            'name' => $subType['name'],
                        ];
                    }
                }
            } else {
                // Single categorySubType1 object
                if (isset($subType1['@id']) && isset($subType1['name'])) {
                    $categories[] = [
                        'id' => (string) $subType1['@id'],
                        'name' => $subType1['name'],
                    ];
                }
            }
        }

        return $categories;
    }

    /**
     * Create or update a taxonomy term
     * 
     * @param string $taxonomy Taxonomy slug
     * @param string $name Term name
     * @param array $meta Meta fields to set (e.g., simpleview_id)
     * @param int $parent Parent term ID (0 for flat categories)
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
