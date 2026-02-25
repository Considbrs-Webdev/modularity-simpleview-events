<?php

namespace ModularitySimpleviewEvents\Admin;

/**
 * Class Settings
 * 
 * Registers the ACF options page for Simpleview Events settings.
 * 
 * @package ModularitySimpleviewEvents\Admin
 */
class Settings
{
    public function __construct()
    {
        add_action('acf/init', [$this, 'registerOptionsPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_action('wp_ajax_simpleview_events_manual_sync', [$this, 'ajaxManualSync']);
        add_action('wp_ajax_simpleview_events_test_connection', [$this, 'ajaxTestConnection']);
    }

    /**
     * Register ACF options page for Simpleview Events
     * 
     * @return void
     */
    public function registerOptionsPage(): void
    {
        if (function_exists('acf_add_options_sub_page')) {
            acf_add_options_sub_page([
                'page_title'  => __('Simpleview Events Settings', 'modularity-simpleview-events'),
                'menu_title'  => __('Simpleview Events', 'modularity-simpleview-events'),
                'menu_slug'   => 'simpleview-events-settings',
                'parent_slug' => 'options-general.php',
                'post_id'     => 'simpleview-events-settings',
                'capability'  => 'manage_options',
            ]);
        }
    }

    /**
     * AJAX handler for manual sync trigger
     * 
     * @return void
     */
    public function ajaxManualSync(): void
    {
        check_ajax_referer('simpleview_events_manual_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'modularity-simpleview-events')], 403);
        }

        $synchronizer = new \ModularitySimpleviewEvents\Sync\EventSynchronizer();
        $result = $synchronizer->sync();

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        } else {
            $message = sprintf(
                __('Sync completed: %d created, %d updated, %d archived, %d restored, %d pruned', 'modularity-simpleview-events'),
                $result['created'] ?? 0,
                $result['updated'] ?? 0,
                $result['archived'] ?? 0,
                $result['restored'] ?? 0,
                $result['pruned'] ?? 0
            );

            if (!empty($result['warnings'] ?? [])) {
                $message .= '. ' . sprintf(__('Warnings: %d', 'modularity-simpleview-events'), count($result['warnings']));
            }

            if (!empty($result['errors'] ?? [])) {
                $message .= '. ' . sprintf(__('Errors: %d', 'modularity-simpleview-events'), count($result['errors']));
            }

            wp_send_json_success([
                'message' => $message,
                'data' => $result,
            ]);
        }
    }

    /**
     * AJAX handler for testing API connection.
     * Returns media channel summary so the admin can verify configuration.
     * 
     * @return void
     */
    public function ajaxTestConnection(): void
    {
        check_ajax_referer('simpleview_events_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'modularity-simpleview-events')], 403);
        }

        $client = new \ModularitySimpleviewEvents\Api\SimpleviewClient();
        $result = $client->testConnection();

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        wp_send_json_success($result);
    }

    /**
     * Enqueue admin scripts for the settings page
     * 
     * @param string $hook The current admin page hook
     * @return void
     */
    public function enqueueAdminScripts(string $hook): void
    {
        if ($hook !== 'settings_page_simpleview-events-settings') {
            return;
        }

        add_action('admin_footer', [$this, 'printAdminScripts']);
    }

    /**
     * Print the admin JavaScript in admin footer
     * 
     * @return void
     */
    public function printAdminScripts(): void
    {
        $syncNonce = wp_create_nonce('simpleview_events_manual_sync');
        $testNonce = wp_create_nonce('simpleview_events_test_connection');
        $ajaxUrl = admin_url('admin-ajax.php');
?>
        <script>
            (function() {
                var ajaxUrl = <?php echo json_encode($ajaxUrl); ?>;
                var syncNonce = <?php echo json_encode($syncNonce); ?>;
                var testNonce = <?php echo json_encode($testNonce); ?>;

                function initManualSync() {
                    var syncButton = document.getElementById('simpleview-events-manual-sync');
                    if (!syncButton) return;

                    syncButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        var button = this;
                        var originalText = button.textContent;
                        button.disabled = true;
                        button.textContent = '<?php echo esc_js(__('Syncing...', 'modularity-simpleview-events')); ?>';

                        var formData = new FormData();
                        formData.append('action', 'simpleview_events_manual_sync');
                        formData.append('nonce', syncNonce);

                        fetch(ajaxUrl, { method: 'POST', body: formData })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success) {
                                    var msg = data.data?.message || '<?php echo esc_js(__('Sync completed', 'modularity-simpleview-events')); ?>';
                                    var stats = data.data?.data;
                                    if (stats) {
                                        var d = [];
                                        if (stats.created > 0) d.push(stats.created + ' created');
                                        if (stats.updated > 0) d.push(stats.updated + ' updated');
                                        if (stats.archived > 0) d.push(stats.archived + ' archived');
                                        if (stats.restored > 0) d.push(stats.restored + ' restored');
                                        if (stats.pruned > 0) d.push(stats.pruned + ' pruned');
                                        if (d.length > 0) msg += '\n\n' + d.join(', ');
                                        if (stats.warnings && stats.warnings.length > 0) msg += '\n\nWarnings:\n' + stats.warnings.join('\n');
                                        if (stats.errors && stats.errors.length > 0) msg += '\n\nErrors: ' + stats.errors.length;
                                    }
                                    alert(msg);
                                } else {
                                    alert('<?php echo esc_js(__('Sync failed:', 'modularity-simpleview-events')); ?> ' + (data.data?.message || 'Unknown error'));
                                }
                            })
                            .catch(function(err) {
                                alert('<?php echo esc_js(__('Error during sync:', 'modularity-simpleview-events')); ?> ' + err.message);
                            })
                            .finally(function() {
                                button.disabled = false;
                                button.textContent = originalText;
                            });
                    });
                }

                function initTestConnection() {
                    var testButton = document.getElementById('simpleview-events-test-connection');
                    if (!testButton) return;

                    var resultDiv = document.getElementById('simpleview-events-test-result');

                    testButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        var button = this;
                        var originalText = button.textContent;
                        button.disabled = true;
                        button.textContent = '<?php echo esc_js(__('Testing...', 'modularity-simpleview-events')); ?>';
                        if (resultDiv) resultDiv.innerHTML = '';

                        var formData = new FormData();
                        formData.append('action', 'simpleview_events_test_connection');
                        formData.append('nonce', testNonce);

                        fetch(ajaxUrl, { method: 'POST', body: formData })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (!resultDiv) return;

                                if (data.success) {
                                    var d = data.data;
                                    var html = '<div class="notice notice-success inline" style="margin:8px 0;padding:8px 12px;">';
                                    html += '<p><strong><?php echo esc_js(__('Connection successful!', 'modularity-simpleview-events')); ?></strong></p>';
                                    html += '<p><?php echo esc_js(__('Total products:', 'modularity-simpleview-events')); ?> ' + d.product_count + '</p>';

                                    if (d.media_channels && d.media_channels.length > 0) {
                                        html += '<p><strong><?php echo esc_js(__('Media channels (WEBSITECONTENT):', 'modularity-simpleview-events')); ?></strong></p>';
                                        html += '<table class="widefat striped" style="max-width:500px;">';
                                        html += '<thead><tr><th>ID</th><th><?php echo esc_js(__('Name', 'modularity-simpleview-events')); ?></th><th><?php echo esc_js(__('Products', 'modularity-simpleview-events')); ?></th></tr></thead><tbody>';
                                        d.media_channels.forEach(function(mc) {
                                            html += '<tr><td>' + mc.id + '</td><td><strong>' + mc.name + '</strong></td><td>' + mc.products + '</td></tr>';
                                        });
                                        html += '</tbody></table>';
                                    } else {
                                        html += '<p><?php echo esc_js(__('No WEBSITECONTENT media channels found.', 'modularity-simpleview-events')); ?></p>';
                                    }
                                    html += '</div>';
                                    resultDiv.innerHTML = html;
                                } else {
                                    resultDiv.innerHTML = '<div class="notice notice-error inline" style="margin:8px 0;padding:8px 12px;"><p>' + (data.data?.message || 'Unknown error') + '</p></div>';
                                }
                            })
                            .catch(function(err) {
                                if (resultDiv) {
                                    resultDiv.innerHTML = '<div class="notice notice-error inline" style="margin:8px 0;padding:8px 12px;"><p>' + err.message + '</p></div>';
                                }
                            })
                            .finally(function() {
                                button.disabled = false;
                                button.textContent = originalText;
                            });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        initManualSync();
                        initTestConnection();
                    });
                } else {
                    initManualSync();
                    initTestConnection();
                }
            })();
        </script>
<?php
    }
}
