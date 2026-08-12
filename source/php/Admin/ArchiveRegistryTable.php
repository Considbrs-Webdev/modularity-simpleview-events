<?php

namespace ModularitySimpleviewEvents\Admin;

use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;

/**
 * Renders and manages the event archive registry on the settings page.
 */
class ArchiveRegistryTable
{
    private const PAGE_SLUG = 'simpleview-events-settings';

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_post_simpleview_events_remove_archive', [$this, 'handleRemoveArchive']);
        add_action('wp_ajax_simpleview_events_toggle_keep_archive', [$this, 'ajaxToggleKeepArchive']);
        add_action('admin_notices', [$this, 'renderAdminNotices']);
    }

    /**
     * @param string $hook
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if (!$this->isSettingsHook($hook)) {
            return;
        }

        $stylePath = MODULARITYSIMPLEVIEWEVENTS_PATH . 'assets/css/admin-settings.css';

        wp_enqueue_style(
            'modularity-simpleview-events-admin-settings',
            MODULARITYSIMPLEVIEWEVENTS_URL . '/assets/css/admin-settings.css',
            [],
            file_exists($stylePath) ? (string) filemtime($stylePath) : '1.0.0'
        );

        add_action('admin_footer', [$this, 'renderTableSection']);
    }

    /**
     * @return void
     */
    public function renderTableSection(): void
    {
        $postTypeManager = new DynamicPostTypeManager();
        $registered = $postTypeManager->getRegisteredPostTypes();

        uasort($registered, static function (array $a, array $b): int {
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $toggleNonce = wp_create_nonce('simpleview_events_toggle_keep_archive');
        ?>
        <div id="simpleview-events-archive-registry" class="simpleview-events-archive-registry">
            <h2><?php esc_html_e('Event archives', 'modularity-simpleview-events'); ?></h2>
            <p class="description">
                <?php esc_html_e('Archives discovered from Simpleview stay registered by default, even when they have no events in the current sync. Turn off "Keep when empty" and run sync to remove unused archives automatically, or remove an archive manually below.', 'modularity-simpleview-events'); ?>
            </p>

            <?php if (empty($registered)) : ?>
                <p class="description"><?php esc_html_e('No event archives have been registered yet. Run a sync to discover media channels.', 'modularity-simpleview-events'); ?></p>
            <?php else : ?>
                <table class="widefat striped simpleview-events-archive-table">
                    <thead>
                        <tr>
                            <th scope="col" class="col-archive"><?php esc_html_e('Archive', 'modularity-simpleview-events'); ?></th>
                            <th scope="col" class="col-id"><?php esc_html_e('Channel ID', 'modularity-simpleview-events'); ?></th>
                            <th scope="col" class="col-date"><?php esc_html_e('First seen', 'modularity-simpleview-events'); ?></th>
                            <th scope="col" class="col-date"><?php esc_html_e('Last in API', 'modularity-simpleview-events'); ?></th>
                            <th scope="col" class="col-posts"><?php esc_html_e('Posts', 'modularity-simpleview-events'); ?></th>
                            <th scope="col" class="col-keep"><?php esc_html_e('Keep when empty', 'modularity-simpleview-events'); ?></th>
                            <th scope="col" class="col-actions"><?php esc_html_e('Actions', 'modularity-simpleview-events'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registered as $postTypeSlug => $info) : ?>
                            <?php
                            $postCount = $postTypeManager->getPostCount($postTypeSlug);
                            $keepWhenEmpty = !empty($info['keep_when_empty']);
                            $removeUrl = wp_nonce_url(
                                admin_url('admin-post.php?action=simpleview_events_remove_archive&post_type=' . rawurlencode($postTypeSlug)),
                                'simpleview_events_remove_archive'
                            );
                            ?>
                            <tr data-post-type="<?php echo esc_attr($postTypeSlug); ?>">
                                <td class="col-archive">
                                    <span class="simpleview-events-archive-registry__name"><?php echo esc_html($info['name'] ?? $postTypeSlug); ?></span>
                                    <code class="simpleview-events-archive-registry__slug"><?php echo esc_html($postTypeSlug); ?></code>
                                </td>
                                <td class="col-id"><?php echo esc_html((string) ($info['id'] ?? '')); ?></td>
                                <td class="col-date"><?php echo $this->formatDateCell($info['first_seen_at'] ?? null); ?></td>
                                <td class="col-date"><?php echo $this->formatDateCell($info['last_seen_in_api_at'] ?? null); ?></td>
                                <td class="col-posts"><?php echo esc_html(number_format_i18n($postCount)); ?></td>
                                <td class="col-keep">
                                    <label class="simpleview-events-archive-registry__keep-label">
                                        <input
                                            type="checkbox"
                                            class="simpleview-events-keep-toggle"
                                            data-post-type="<?php echo esc_attr($postTypeSlug); ?>"
                                            <?php checked($keepWhenEmpty); ?>
                                        >
                                        <span><?php esc_html_e('Keep archive', 'modularity-simpleview-events'); ?></span>
                                    </label>
                                </td>
                                <td class="col-actions">
                                    <a
                                        class="button button-link-delete simpleview-events-remove-archive"
                                        href="<?php echo esc_url($removeUrl); ?>"
                                        data-post-type="<?php echo esc_attr($postTypeSlug); ?>"
                                        data-post-count="<?php echo esc_attr((string) $postCount); ?>"
                                        data-archive-name="<?php echo esc_attr((string) ($info['name'] ?? $postTypeSlug)); ?>"
                                    >
                                        <?php esc_html_e('Remove', 'modularity-simpleview-events'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
            (function() {
                var registry = document.getElementById('simpleview-events-archive-registry');
                if (!registry) {
                    return;
                }

                var wrap = document.querySelector('#postbox-container-1, .acf-settings-wrap, .wrap');
                var acfFields = document.querySelector('.acf-fields');

                if (acfFields && acfFields.parentNode) {
                    acfFields.parentNode.insertBefore(registry, acfFields.nextSibling);
                } else if (wrap) {
                    wrap.appendChild(registry);
                }

                var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
                var toggleNonce = <?php echo wp_json_encode($toggleNonce); ?>;

                registry.addEventListener('change', function(event) {
                    var target = event.target;
                    if (!target.classList.contains('simpleview-events-keep-toggle')) {
                        return;
                    }

                    var postType = target.getAttribute('data-post-type');
                    var keep = target.checked ? '1' : '0';
                    var previous = !target.checked;

                    target.disabled = true;

                    var formData = new FormData();
                    formData.append('action', 'simpleview_events_toggle_keep_archive');
                    formData.append('nonce', toggleNonce);
                    formData.append('post_type', postType);
                    formData.append('keep', keep);

                    fetch(ajaxUrl, { method: 'POST', body: formData })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (!data.success) {
                                target.checked = previous;
                                alert(data.data?.message || <?php echo wp_json_encode(__('Could not update archive setting.', 'modularity-simpleview-events')); ?>);
                            }
                        })
                        .catch(function() {
                            target.checked = previous;
                            alert(<?php echo wp_json_encode(__('Could not update archive setting.', 'modularity-simpleview-events')); ?>);
                        })
                        .finally(function() {
                            target.disabled = false;
                        });
                });

                registry.addEventListener('click', function(event) {
                    var target = event.target;
                    if (!target.classList.contains('simpleview-events-remove-archive')) {
                        return;
                    }

                    event.preventDefault();

                    var archiveName = target.getAttribute('data-archive-name') || target.getAttribute('data-post-type');
                    var postCount = parseInt(target.getAttribute('data-post-count') || '0', 10);
                    var message = <?php echo wp_json_encode(__('Remove "%s" from the archive registry? The admin menu and public archive will disappear on the next page load. Posts and categories are not deleted.', 'modularity-simpleview-events')); ?>;
                    message = message.replace('%s', archiveName);

                    if (postCount > 0) {
                        message += '\n\n' + <?php echo wp_json_encode(__('Warning: this archive still has posts in the database.', 'modularity-simpleview-events')); ?>;
                    }

                    if (window.confirm(message)) {
                        window.location.href = target.href;
                    }
                });
            })();
        </script>
        <?php
    }

    /**
     * @return void
     */
    public function ajaxToggleKeepArchive(): void
    {
        check_ajax_referer('simpleview_events_toggle_keep_archive', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'modularity-simpleview-events')], 403);
        }

        $postTypeSlug = sanitize_key((string) ($_POST['post_type'] ?? ''));
        $keep = !empty($_POST['keep']) && $_POST['keep'] !== '0';

        if ($postTypeSlug === '' || strpos($postTypeSlug, 'sv_') !== 0) {
            wp_send_json_error(['message' => __('Invalid archive.', 'modularity-simpleview-events')], 400);
        }

        $postTypeManager = new DynamicPostTypeManager();

        if (!$postTypeManager->setKeepWhenEmpty($postTypeSlug, $keep)) {
            wp_send_json_error(['message' => __('Archive not found.', 'modularity-simpleview-events')], 404);
        }

        wp_send_json_success([
            'message' => $keep
                ? __('Archive will be kept even when empty.', 'modularity-simpleview-events')
                : __('Archive will be removed on the next sync if it is not in the API response.', 'modularity-simpleview-events'),
        ]);
    }

    /**
     * @return void
     */
    public function handleRemoveArchive(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'modularity-simpleview-events'));
        }

        check_admin_referer('simpleview_events_remove_archive');

        $postTypeSlug = sanitize_key((string) ($_GET['post_type'] ?? ''));
        $redirectUrl = admin_url('options-general.php?page=' . self::PAGE_SLUG);

        if ($postTypeSlug === '' || strpos($postTypeSlug, 'sv_') !== 0) {
            wp_safe_redirect(add_query_arg('simpleview_archive_error', 'invalid', $redirectUrl));
            exit;
        }

        $postTypeManager = new DynamicPostTypeManager();

        if (!$postTypeManager->unregisterPostType($postTypeSlug)) {
            wp_safe_redirect(add_query_arg('simpleview_archive_error', 'missing', $redirectUrl));
            exit;
        }

        wp_safe_redirect(add_query_arg('simpleview_archive_removed', rawurlencode($postTypeSlug), $redirectUrl));
        exit;
    }

    /**
     * @return void
     */
    public function renderAdminNotices(): void
    {
        if (!$this->isSettingsPage()) {
            return;
        }

        if (!empty($_GET['simpleview_archive_removed'])) {
            $postTypeSlug = sanitize_key((string) wp_unslash($_GET['simpleview_archive_removed']));
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    printf(
                        /* translators: %s: post type slug */
                        esc_html__('Removed archive "%s" from the registry.', 'modularity-simpleview-events'),
                        esc_html($postTypeSlug)
                    );
                    ?>
                </p>
            </div>
            <?php
        }

        if (!empty($_GET['simpleview_archive_error'])) {
            $error = sanitize_key((string) wp_unslash($_GET['simpleview_archive_error']));
            $message = $error === 'missing'
                ? __('Archive could not be removed because it was not found.', 'modularity-simpleview-events')
                : __('Archive could not be removed.', 'modularity-simpleview-events');
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
        }
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function formatDateCell(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '<span class="simpleview-events-archive-registry__empty">&mdash;</span>';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return esc_html($value);
        }

        return sprintf(
            '<span class="simpleview-events-archive-registry__date">%1$s</span><span class="simpleview-events-archive-registry__time">%2$s</span>',
            esc_html(wp_date(get_option('date_format'), $timestamp)),
            esc_html(wp_date(get_option('time_format'), $timestamp))
        );
    }

    /**
     * @param string $hook
     * @return bool
     */
    private function isSettingsHook(string $hook): bool
    {
        return str_starts_with($hook, 'settings_page_' . self::PAGE_SLUG);
    }

    /**
     * @return bool
     */
    private function isSettingsPage(): bool
    {
        if (isset($_GET['page']) && is_string($_GET['page'])) {
            return str_starts_with($_GET['page'], self::PAGE_SLUG);
        }

        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();

        return $screen && str_starts_with($screen->id, 'settings_page_' . self::PAGE_SLUG);
    }
}
