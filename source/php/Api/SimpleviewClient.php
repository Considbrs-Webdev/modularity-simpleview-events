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
     * Fetch events from Simpleview API
     * 
     * This is a placeholder method. The actual endpoint and data structure
     * will be implemented once the API structure is provided.
     * 
     * @param array $params Optional query parameters
     * @return array|WP_Error Array of event data or WP_Error on failure
     */
    public function fetchEvents(array $params = []): array|\WP_Error
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return new \WP_Error(
                'missing_credentials',
                __('API credentials are not configured', 'modularity-simpleview-events')
            );
        }

        // TODO: Replace with actual API endpoint once structure is known
        $endpoint = $this->baseUrl . '/events';

        // Add query parameters
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
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

        // TODO: Return actual event data structure once API structure is known
        // For now, return the raw data
        return $data ?? [];
    }

    /**
     * Test API connection
     * 
     * @return bool|WP_Error True if connection successful, WP_Error otherwise
     */
    public function testConnection(): bool|\WP_Error
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return new \WP_Error(
                'missing_credentials',
                __('API credentials are not configured', 'modularity-simpleview-events')
            );
        }

        // Try a simple request to verify credentials
        $result = $this->fetchEvents(['limit' => 1]);

        if (is_wp_error($result)) {
            return $result;
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
     * Check if credentials are configured
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }
}
