<?php

namespace ModularitySimpleviewEvents\Sync;

/**
 * Class ApiResponseValidator
 * 
 * Validates API responses before sync proceeds to prevent data corruption
 * from invalid or incomplete API responses.
 * 
 * @package ModularitySimpleviewEvents\Sync
 */
class ApiResponseValidator
{
    private const OPTION_KEY_LAST_PRODUCT_COUNT = 'simpleview_events_last_product_count';
    private const DRASTIC_DROP_THRESHOLD = 0.5; // 50% drop triggers warning

    /**
     * Validate API response
     * 
     * @param array $response The API response to validate
     * @return array Validation result with 'valid' boolean and 'warnings' array
     */
    public function validate(array $response): array
    {
        $result = [
            'valid' => true,
            'warnings' => [],
        ];

        // Hard fail checks - abort sync if these fail
        if (empty($response)) {
            return [
                'valid' => false,
                'warnings' => [__('API response is empty', 'modularity-simpleview-events')],
            ];
        }

        if (!isset($response['productList']['product'])) {
            return [
                'valid' => false,
                'warnings' => [__('API response missing productList.product structure', 'modularity-simpleview-events')],
            ];
        }

        // Extract products to count
        $productData = $response['productList']['product'];
        $productCount = 0;

        if (isset($productData[0])) {
            // Array of products
            $productCount = count($productData);
        } else {
            // Single product object
            $productCount = 1;
        }

        if ($productCount === 0) {
            return [
                'valid' => false,
                'warnings' => [__('API response contains zero products', 'modularity-simpleview-events')],
            ];
        }

        // Soft warning checks - proceed but warn
        $lastProductCount = $this->getLastSyncProductCount();
        
        if ($lastProductCount > 0) {
            $dropPercentage = ($lastProductCount - $productCount) / $lastProductCount;
            
            if ($dropPercentage > self::DRASTIC_DROP_THRESHOLD) {
                $result['warnings'][] = sprintf(
                    __('Product count dropped significantly: %d products (was %d). This may indicate an API issue, but sync will proceed.', 'modularity-simpleview-events'),
                    $productCount,
                    $lastProductCount
                );
            }
        }

        // Save current count for next validation
        update_option(self::OPTION_KEY_LAST_PRODUCT_COUNT, $productCount);

        return $result;
    }

    /**
     * Get the product count from the last successful sync
     * 
     * @return int Product count from last sync, or 0 if not available
     */
    public function getLastSyncProductCount(): int
    {
        return (int) get_option(self::OPTION_KEY_LAST_PRODUCT_COUNT, 0);
    }
}
