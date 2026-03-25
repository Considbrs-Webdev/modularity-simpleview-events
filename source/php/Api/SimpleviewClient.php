<?php

namespace ModularitySimpleviewEvents\Api;

/**
 * Class SimpleviewClient
 * 
 * HTTP client for interacting with the Simpleview API.
 * 
 * @package ModularitySimpleviewEvents\Api
 */
class SimpleviewClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    private const SETTINGS_POST_ID = 'simpleview-events-settings';

    /**
     * Constructor
     * 
     * @param string|null $baseUrl API base URL (defaults to settings)
     * @param string|null $apiKey API key (defaults to settings)
     * @param int $timeout Request timeout in seconds
     */
    public function __construct(?string $baseUrl = null, ?string $apiKey = null, int $timeout = 30)
    {
        $this->baseUrl = $baseUrl ?? get_field('api_base_url', self::SETTINGS_POST_ID) ?? '';
        $this->apiKey = $apiKey ?? get_field('api_key', self::SETTINGS_POST_ID) ?? '';
        $this->timeout = $timeout;
    }

    /**
     * Build query parameters from ACF settings + structural defaults.
     * 
     * @return array
     */
    private function getParameters(): array
    {
        $parameters = [
            'op'    => 'GetProductList',
            'json'  => 'on',
        ];

        $configurable = [
            'language_id'              => 'LanguageId',
            'db_owner_id_list'         => 'DBOwnerIdList',
            'country_id'               => 'CountryId',
            'distribution_channel_id'  => 'DistributionChannelId',
        ];

        foreach ($configurable as $acfField => $apiParam) {
            $value = get_field($acfField, self::SETTINGS_POST_ID);
            if (is_string($value) && $value !== '') {
                $parameters[$apiParam] = $value;
            }
        }

        $parameters['CategoryIdList'] = '';
        $parameters['LicenceKey'] = $this->apiKey;

        return $parameters;
    }

    /**
     * Fetch events from Simpleview API
     * 
     * @param array $params Optional extra query parameters
     * @return array|\WP_Error Array of event data or WP_Error on failure
     */
    public function fetchEvents(array $params = []): array|\WP_Error
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return new \WP_Error(
                'missing_credentials',
                __('API credentials are not configured', 'modularity-simpleview-events')
            );
        }

        $endpoint = $this->baseUrl;
        $queryParams = array_merge($this->getParameters(), $params);

        if (!empty($queryParams)) {
            $endpoint .= '?' . http_build_query($queryParams);
        }

        $response = wp_remote_get($endpoint, [
            'timeout' => $this->timeout,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            $message = wp_remote_retrieve_response_message($response);
            return new \WP_Error(
                'api_error',
                sprintf(
                    /* translators: 1: HTTP status code, 2: Response message text. */
                    __('API request failed with status %1$d: %2$s', 'modularity-simpleview-events'),
                    $statusCode,
                    $message
                )
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'json_error',
                __('Failed to parse API response', 'modularity-simpleview-events')
            );
        }

        return $data ?? [];
    }

    /**
     * Test API connection and return a summary of what the API returns.
     * 
     * @return array|\WP_Error Summary array on success, WP_Error on failure
     */
    public function testConnection(): array|\WP_Error
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return new \WP_Error(
                'missing_credentials',
                __('API credentials are not configured. Please fill in Base URL and API Key.', 'modularity-simpleview-events')
            );
        }

        $result = $this->fetchEvents();

        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result)) {
            return new \WP_Error(
                'api_error',
                __('API returned empty response', 'modularity-simpleview-events')
            );
        }

        if (!isset($result['productList']['product'])) {
            return new \WP_Error(
                'api_error',
                __('API response missing productList.product structure', 'modularity-simpleview-events')
            );
        }

        $productData = $result['productList']['product'];
        $products = isset($productData[0]) ? $productData : [$productData];
        $productCount = count($products);

        $mediaChannels = [];
        foreach ($products as $product) {
            $mcData = $product['mediaChannelList']['mediaChannel'] ?? null;
            if ($mcData === null) {
                continue;
            }

            $channels = isset($mcData['@id']) ? [$mcData] : (is_array($mcData) ? $mcData : []);
            foreach ($channels as $channel) {
                if (($channel['typeId'] ?? '') !== 'WEBSITECONTENT') {
                    continue;
                }
                $id = (string) ($channel['@id'] ?? '');
                $name = $channel['name'] ?? '';
                if ($id === '' || $name === '') {
                    continue;
                }
                if (!isset($mediaChannels[$id])) {
                    $mediaChannels[$id] = ['id' => $id, 'name' => $name, 'products' => 0];
                }
                $mediaChannels[$id]['products']++;
            }
        }

        return [
            'product_count' => $productCount,
            'media_channels' => array_values($mediaChannels),
        ];
    }

    /**
     * Get the configured base URL
     * 
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Check if credentials are configured
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }
}
