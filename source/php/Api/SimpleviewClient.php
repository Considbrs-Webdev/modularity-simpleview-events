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

    /**
     *  Static parameters for the Simpleview API (without LicenseKey which is set at runtime)
     * 
     * @var array
     */
    private static array $baseParameters = [
        'op' => 'GetProductList',
        'LanguageId' => 'sv',
        'DBOwnerIdList' => '143',
        'CountryId' => 'SE',
        'DistributionChannelId' => '192',
        'json' => 'on',
    ];

    /**
     * Constructor
     * 
     * @param string|null $baseUrl API base URL (defaults to settings)
     * @param string|null $apiKey API key (defaults to settings)
     * @param int $timeout Request timeout in seconds
     */
    public function __construct(?string $baseUrl = null, ?string $apiKey = null, int $timeout = 30)
    {
        $this->baseUrl = $baseUrl ?? get_field('api_base_url', 'simpleview-events-settings') ?? '';
        $this->apiKey = $apiKey ?? get_field('api_key', 'simpleview-events-settings') ?? '';
        $this->timeout = $timeout;
    }

    /**
     * Get API parameters with LicenseKey set at runtime
     * 
     * @return array
     */
    private function getParameters(): array
    {
        $parameters = self::$baseParameters;
        $parameters['LicenseKey'] = $this->apiKey;
        return $parameters;
    }

    /**
     * Check if mock mode is enabled
     * 
     * @return bool
     */
    private function isMockMode(): bool
    {
        return (bool) get_field('use_mock_data', 'simpleview-events-settings');
    }

    /**
     * Fetch events from local mock JSON file
     * 
     * @return array|WP_Error Array of event data or WP_Error on failure
     */
    private function fetchFromMockFile(): array|\WP_Error
    {
        $mockFile = MODULARITYSIMPLEVIEWEVENTS_PATH . 'simpleview.json';

        if (!file_exists($mockFile)) {
            return new \WP_Error(
                'mock_not_found',
                __('Mock data file not found at: ', 'modularity-simpleview-events') . $mockFile
            );
        }

        $jsonContent = file_get_contents($mockFile);

        if ($jsonContent === false) {
            return new \WP_Error(
                'mock_read_error',
                __('Failed to read mock data file', 'modularity-simpleview-events')
            );
        }

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'json_error',
                __('Failed to parse mock data: ', 'modularity-simpleview-events') . json_last_error_msg()
            );
        }

        return $data ?? [];
    }

    /**
     * Fetch events from Simpleview API or mock file
     * 
     * @param array $params Optional query parameters
     * @return array|WP_Error Array of event data or WP_Error on failure
     */
    public function fetchEvents(array $params = []): array|\WP_Error
    {
        // Check for mock mode first
        if ($this->isMockMode()) {
            return $this->fetchFromMockFile();
        }

        // Validate API credentials for live mode
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return new \WP_Error(
                'missing_credentials',
                __('API credentials are not configured', 'modularity-simpleview-events')
            );
        }

        $endpoint = $this->baseUrl;

        // Merge default parameters with provided params
        $queryParams = array_merge($this->getParameters(), $params);

        // Add query parameters
        if (!empty($queryParams)) {
            $endpoint .= '?' . http_build_query($queryParams);
        }

        $response = wp_remote_get($endpoint, [
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
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
                sprintf(__('API request failed with status %d: %s', 'modularity-simpleview-events'), $statusCode, $message)
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
     * Test API connection
     * 
     * @return bool|WP_Error True if connection successful, WP_Error otherwise
     */
    public function testConnection(): bool|\WP_Error
    {
        // Mock mode always succeeds if file exists
        if ($this->isMockMode()) {
            $mockFile = MODULARITYSIMPLEVIEWEVENTS_PATH . 'simpleview.json';
            if (!file_exists($mockFile)) {
                return new \WP_Error(
                    'mock_not_found',
                    __('Mock data file not found', 'modularity-simpleview-events')
                );
            }
            return true;
        }

        // Validate API credentials for live mode
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return new \WP_Error(
                'missing_credentials',
                __('API credentials are not configured', 'modularity-simpleview-events')
            );
        }

        // Try a simple request to verify credentials
        $result = $this->fetchEvents($this->getParameters());

        if (is_wp_error($result)) {
            return new \WP_Error(
                'api_error',
                __('API request failed: ' . $result->get_error_message(), 'modularity-simpleview-events')
            );
        }

        if (empty($result)) {
            return new \WP_Error(
                'api_error',
                __('API returned empty response', 'modularity-simpleview-events')
            );
        }

        return true;
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
     * Check if credentials are configured or mock mode is enabled
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        // Mock mode doesn't need credentials
        if ($this->isMockMode()) {
            return true;
        }

        if (!empty($this->baseUrl) && !empty($this->apiKey)) {
            return true;
        }

        return false;
    }
}
