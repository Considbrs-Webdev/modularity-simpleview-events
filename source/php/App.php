<?php

namespace ModularitySimpleviewEvents;

use ModularitySimpleviewEvents\Admin\ArchiveRegistryTable;
use ModularitySimpleviewEvents\Admin\Settings;
use ModularitySimpleviewEvents\Cron\SyncScheduler;
use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;
use ModularitySimpleviewEvents\Taxonomy\DynamicTaxonomyManager;
use ModularitySimpleviewEvents\PostStatus\ArchivedPostStatus;
use ModularitySimpleviewEvents\ApplyDecorator\ApplySimpleviewEventData;
use ModularitySimpleviewEvents\Helper\CacheBust;

/**
 * Class App
 * 
 * @package ModularitySimpleviewEvents
 */
class App
{
    /**
     * Memoized dynamic post types list for current request.
     *
     * @var string[]|null
     */
    private ?array $registeredSimpleviewPostTypes = null;

    public function __construct()
    {
        new Settings();
        new ArchiveRegistryTable();

        new SyncScheduler();

        add_action('init', [$this, 'registerArchivedPostStatus'], 10);
        add_action('init', [$this, 'registerDynamicPostTypes'], 0);
        add_action('init', [$this, 'registerDynamicTaxonomies'], 1);
        add_action('init', [$this, 'maybeFlushRewriteRules'], 99);
        add_filter('body_class', [$this, 'addSimpleviewBodyClass'], 10, 1);
        add_filter('Municipio/DecoratePostObject', [$this, 'decoratePostObject'], 10, 1);

        add_filter('Municipio/viewPaths', [$this, 'addViewPaths'], 999);
        add_filter('/Modularity/externalViewPath', [$this, 'addPostsModuleViewPath']);
        add_filter('ComponentLibrary/ViewPaths', [$this, 'addComponentLibraryViewPaths'], 999);

        add_action('rest_request_before_callbacks', [$this, 'exposePostTypeFromRestAttributes'], 10, 3);

        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);

        new TypesenseSearchIntegration();
    }

    /**
     * Enqueue front-end styles (card tokens, meta layout).
     */
    public function enqueueStyles(): void
    {
        $styleFile = CacheBust::name('css/modularity-simpleview-events.css');

        if ($styleFile) {
            wp_enqueue_style(
                'modularity-simpleview-events',
                MODULARITYSIMPLEVIEWEVENTS_URL . '/assets/dist/' . $styleFile,
                [],
                null
            );
        }
    }

    /**
     * Add plugin view paths to Municipio for custom templates
     *
     * @param array $paths The existing view paths
     * @return array The modified view paths
     */
    public function addViewPaths(array $paths): array
    {
        if ($this->isSimpleviewEventsContext() || is_search()) {
            $paths[] = MODULARITYSIMPLEVIEWEVENTS_PATH . 'views';
        }

        return $paths;
    }

    public function addComponentLibraryViewPaths(array $paths): array
    {

        if (!$this->isSimpleviewEventsContext()) {
            return $paths;
        }

        $ourPath = rtrim(MODULARITYSIMPLEVIEWEVENTS_PATH . 'views', DIRECTORY_SEPARATOR);
        if (is_dir($ourPath)) {
            array_unshift($paths, $ourPath . DIRECTORY_SEPARATOR);
        }
        return $paths;
    }

    /**
     * Add our view path to the Posts module view paths
     *
     * @param array $externalViewPaths Module post_type => view path mapping
     * @return array Modified mapping
     */
    public function addPostsModuleViewPath(array $externalViewPaths): array
    {
        if ($this->isSimpleviewEventsContext()) {
            $postsModuleViewPath = defined('MODULARITY_PATH')
                ? MODULARITY_PATH . 'source/php/Module/Posts/views'
                : '';

            $externalViewPaths['mod-posts'] = [
                $postsModuleViewPath,
                MODULARITYSIMPLEVIEWEVENTS_PATH . 'views',
            ];
        }

        return $externalViewPaths;
    }

    /**
     * Add body class on Simpleview calendar archive pages for easier styling.
     *
     * @param array $classes Existing body classes
     * @return array Modified body classes
     */
    public function addSimpleviewBodyClass(array $classes): array
    {
        $postTypes = $this->getRegisteredSimpleviewPostTypes();
        if (!empty($postTypes) && (is_post_type_archive($postTypes) || is_singular($postTypes))) {
            $classes[] = 'archive-simpleview';
        }

        return $classes;
    }

    /**
     * Register the archived post status
     * 
     * @return void
     */
    public function registerArchivedPostStatus(): void
    {
        $archivedStatus = new ArchivedPostStatus();
        $archivedStatus->register();
    }

    /**
     * Register dynamic post types that were created during sync
     * 
     * @return void
     */
    public function registerDynamicPostTypes(): void
    {
        $postTypeManager = new DynamicPostTypeManager();
        $optionKey = 'simpleview_events_registered_post_types';
        $registered = $postTypeManager->getRegisteredPostTypes();

        if (empty($registered)) {
            wp_cache_delete($optionKey, 'options');
            $registered = $postTypeManager->getRegisteredPostTypes();

            if (empty($registered)) {
                $registered = $this->discoverPostTypesFromDatabase();
                if (!empty($registered)) {
                    update_option($optionKey, $registered);
                }
            }
        }

        foreach ($registered as $postTypeSlug => $info) {
            $postTypeManager->registerPostTypeForMediaChannel(
                $info['name'] ?? '',
                (string) ($info['id'] ?? ''),
                false
            );
        }
    }

    /**
     * Register dynamic taxonomies that were created during sync
     * 
     * @return void
     */
    public function registerDynamicTaxonomies(): void
    {
        $taxonomyManager = new DynamicTaxonomyManager();
        $postTypeManager = new DynamicPostTypeManager();
        $registered = $postTypeManager->getRegisteredPostTypes();

        foreach ($registered as $postTypeSlug => $info) {
            $taxonomyManager->registerCategoryTaxonomyForPostType(
                $postTypeSlug,
                $info['name'] ?? ''
            );
        }
    }

    /**
     * Flush rewrite rules after sync registers a new dynamic post type.
     *
     * Runs on init after post types and taxonomies are registered.
     *
     * @return void
     */
    public function maybeFlushRewriteRules(): void
    {
        if (!get_option(DynamicPostTypeManager::FLUSH_REWRITE_RULES_OPTION)) {
            return;
        }

        DynamicPostTypeManager::flushRewriteRulesIfNeeded();
    }

    /**
     * Discover post types from existing posts in database
     * 
     * @return array Array of post type data in same format as option
     */
    private function discoverPostTypesFromDatabase(): array
    {
        global $wpdb;

        // Find all post types that start with 'sv_' and have posts
        $postTypes = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_type FROM {$wpdb->posts} 
            WHERE post_type LIKE %s 
            AND post_status != 'trash'
            LIMIT 20",
            'sv_%'
        ));

        if (empty($postTypes)) {
            return [];
        }

        $discovered = [];

        foreach ($postTypes as $postTypeSlug) {
            $postId = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND pm.meta_key = 'simpleview_id'
                AND p.post_status != 'trash'
                LIMIT 1",
                $postTypeSlug
            ));

            if ($postId) {
                $mediaChannelName = get_post_meta($postId, 'simpleview_media_channel_name', true);
                $mediaChannelId = get_post_meta($postId, 'simpleview_media_channel_id', true);

                if (empty($mediaChannelName)) {
                    $mediaChannelName = str_replace('sv_', '', $postTypeSlug);
                    $mediaChannelName = str_replace('_', ' ', $mediaChannelName);
                    $mediaChannelName = ucwords($mediaChannelName);
                }

                $discovered[$postTypeSlug] = DynamicPostTypeManager::createRegistryEntry(
                    $mediaChannelName,
                    $mediaChannelId ?: 'discovered'
                );
            }
        }

        return $discovered;
    }

    /**
     * Get registered dynamic post type slugs for Simpleview events.
     *
     * @return string[]
     */
    private function getRegisteredSimpleviewPostTypes(): array
    {
        if ($this->registeredSimpleviewPostTypes !== null) {
            return $this->registeredSimpleviewPostTypes;
        }

        $postTypeManager = new DynamicPostTypeManager();
        $registered = $postTypeManager->getRegisteredPostTypes();

        if (empty($registered)) {
            wp_cache_delete('simpleview_events_registered_post_types', 'options');
            $registered = $postTypeManager->getRegisteredPostTypes();

            if (empty($registered)) {
                $registered = $this->discoverPostTypesFromDatabase();
            }
        }

        $this->registeredSimpleviewPostTypes = array_values(array_filter(array_keys($registered), 'is_string'));

        return $this->registeredSimpleviewPostTypes;
    }

    /**
     * Determine whether the current request should use plugin templates.
     * Safe to call before the main query is set up (e.g. in ComponentLibrary/ViewPaths).
     *
     * Applies to:
     * - Single pages for any dynamic sv_* post type
     * - Archive pages for any dynamic sv_* post type
     * - Category taxonomy archives for any dynamic sv_* post type (sv_*_category)
     */
    private function isSimpleviewEventsContext(): bool
    {
        $postTypes = $this->getRegisteredSimpleviewPostTypes();

        if (empty($postTypes)) {
            return false;
        }

        // --- Early-safe: no use of get_query_var() or main query ---
        $postTypeFromRequest = $this->getPostTypeFromRequestForViewPaths();
        if ($postTypeFromRequest !== null && in_array($postTypeFromRequest, $postTypes, true)) {
            return true;
        }

        if (!empty($_SERVER['REQUEST_URI'])) {
            foreach ($postTypes as $pt) {
                if (strpos($_SERVER['REQUEST_URI'], $pt) !== false) {
                    return true;
                }
            }
        }

        // --- Only use query when it is safe ---
        if (!did_action('wp') && (empty($GLOBALS['wp_query']) || !is_object($GLOBALS['wp_query']))) {
            return false;
        }

        $queriedPostType = get_query_var('post_type');
        if (is_string($queriedPostType) && in_array($queriedPostType, $postTypes, true)) {
            return true;
        }
        if (is_array($queriedPostType) && !empty(array_intersect($queriedPostType, $postTypes))) {
            return true;
        }

        if (is_singular($postTypes) || is_post_type_archive($postTypes)) {
            return true;
        }

        $taxonomies = array_map(
            static fn(string $postType): string => $postType . '_category',
            $postTypes
        );

        return !empty($taxonomies) && is_tax($taxonomies);
    }

    /**
     * Get post type from request (GET/POST/attributes JSON) for view-path context.
     * Safe to call before the main query is set up.
     *
     * @return string|null Post type slug or null
     */
    private function getPostTypeFromRequestForViewPaths(): ?string
    {
        if (!empty($_GET['postType']) && is_string($_GET['postType'])) {
            return $_GET['postType'];
        }
        if (!empty($_POST['postType']) && is_string($_POST['postType'])) {
            return $_POST['postType'];
        }
        $raw = $_GET['attributes'] ?? $_POST['attributes'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded['postType']) && is_string($decoded['postType'])) {
                return $decoded['postType'];
            }
        }
        return null;
    }

    /**
     * Set $_GET['postType'] from REST request attributes so view-path logic sees it.
     * Runs before the posts-list/render callback.
     *
     * @param mixed $response
     * @param array $handler
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public function exposePostTypeFromRestAttributes($response, $handler, $request)
    {
        if (!$request instanceof \WP_REST_Request) {
            return $response;
        }
        $route = $request->get_route();
        if ($route === null || strpos($route, 'posts-list/render') === false) {
            return $response;
        }
        $attrs = $request->get_param('attributes');
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true);
        }
        if (is_array($attrs) && !empty($attrs['postType'])) {
            $_GET['postType'] = $attrs['postType'];
        }
        return $response;
    }


    /**
     * Decorate post object with Simpleview event data
     * 
     * @param object $postObject The post object to decorate
     * @return object The decorated post object
     */
    public function decoratePostObject($postObject): object
    {
        if (!is_object($postObject) || !method_exists($postObject, 'getPostType') || !method_exists($postObject, 'getId')) {
            return $postObject;
        }

        $postType = $postObject->getPostType();
        $dynamicPostTypes = $this->getRegisteredSimpleviewPostTypes();

        if (empty($dynamicPostTypes) || !in_array($postType, $dynamicPostTypes, true)) {
            return $postObject;
        }

        $postId = $postObject->getId();
        $wpPost = get_post($postId);

        if (!$wpPost || $wpPost->post_type !== $postType) {
            return $postObject;
        }

        $decoratedPost = (new ApplySimpleviewEventData())->apply($wpPost);

        // PostObjectInterface supports dynamic properties via __get/__set
        if (isset($decoratedPost->simpleviewEventData)) {
            $postObject->simpleviewEventData = $decoratedPost->simpleviewEventData;
        }

        return $postObject;
    }
}
