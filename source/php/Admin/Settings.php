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
        add_action('wp_ajax_simpleview_events_sanitize_slug', [$this, 'ajaxSanitizeSlug']);
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

        // Trigger sync
        if (class_exists('ModularitySimpleviewEvents\Sync\EventSynchronizer')) {
            $synchronizer = new \ModularitySimpleviewEvents\Sync\EventSynchronizer();
            $result = $synchronizer->sync();

            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                ]);
            } else {
                // Build success message with statistics
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
        } else {
            wp_send_json_error([
                'message' => __('Synchronizer class not found', 'modularity-simpleview-events'),
            ]);
        }
    }

    /**
     * AJAX handler to sanitize slug using WordPress sanitize_title()
     * 
     * @return void
     */
    public function ajaxSanitizeSlug(): void
    {
        check_ajax_referer('simpleview_events_sanitize_slug', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';

        // Use WordPress's sanitize_title for proper slug generation
        $slug = sanitize_title($title);

        wp_send_json_success(['slug' => $slug]);
    }

    /**
     * Enqueue admin scripts for the settings page
     * 
     * @param string $hook The current admin page hook
     * @return void
     */
    public function enqueueAdminScripts(string $hook): void
    {
        // Only on our settings page
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
        $slugNonce = wp_create_nonce('simpleview_events_sanitize_slug');
        $ajaxUrl = admin_url('admin-ajax.php');
?>
        <script>
            (function() {
                var ajaxUrl = <?php echo json_encode($ajaxUrl); ?>;
                var syncNonce = <?php echo json_encode($syncNonce); ?>;
                var slugNonce = <?php echo json_encode($slugNonce); ?>;
                var debounceTimer;

                // Auto-slug generation
                function initAutoSlug() {
                    var displayNameField = document.querySelector('input[name="acf[field_simpleview_events_display_name]"]');
                    var slugField = document.querySelector('input[name="acf[field_simpleview_events_slug]"]');

                    if (!displayNameField || !slugField) return;

                    var slugManuallyEdited = slugField.value.length > 0;

                    // Mark as manually edited if user types in slug field
                    slugField.addEventListener('input', function() {
                        slugManuallyEdited = true;
                    });

                    // Auto-generate slug from display name using WordPress sanitize_title
                    displayNameField.addEventListener('input', function() {
                        if (slugManuallyEdited && slugField.value.length > 0) return;

                        var title = displayNameField.value;
                        if (!title) {
                            slugField.value = '';
                            return;
                        }

                        // Debounce to avoid too many AJAX calls
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(function() {
                            var formData = new FormData();
                            formData.append('action', 'simpleview_events_sanitize_slug');
                            formData.append('nonce', slugNonce);
                            formData.append('title', title);

                            fetch(ajaxUrl, {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(function(response) {
                                    return response.json();
                                })
                                .then(function(data) {
                                    if (data.success && data.data.slug) {
                                        slugField.value = data.data.slug;
                                        slugManuallyEdited = false;
                                    }
                                })
                                .catch(function(error) {
                                    console.error('Error sanitizing slug:', error);
                                });
                        }, 300);
                    });
                }

                // Manual sync button handler
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

                        fetch(ajaxUrl, {
                                method: 'POST',
                                body: formData
                            })
                            .then(function(response) {
                                return response.json();
                            })
                            .then(function(data) {
                                if (data.success) {
                                    var message = data.data?.message || '<?php echo esc_js(__('Sync completed successfully', 'modularity-simpleview-events')); ?>';
                                    
                                    // Show detailed statistics if available
                                    if (data.data?.data) {
                                        var stats = data.data.data;
                                        var details = [];
                                        
                                        if (stats.created > 0) details.push(stats.created + ' created');
                                        if (stats.updated > 0) details.push(stats.updated + ' updated');
                                        if (stats.archived > 0) details.push(stats.archived + ' archived');
                                        if (stats.restored > 0) details.push(stats.restored + ' restored');
                                        if (stats.pruned > 0) details.push(stats.pruned + ' pruned');
                                        
                                        if (details.length > 0) {
                                            message += '\n\n' + details.join(', ');
                                        }
                                        
                                        if (stats.warnings && stats.warnings.length > 0) {
                                            message += '\n\nWarnings:\n' + stats.warnings.join('\n');
                                        }
                                        
                                        if (stats.errors && stats.errors.length > 0) {
                                            message += '\n\nErrors: ' + stats.errors.length;
                                        }
                                    }
                                    
                                    alert(message);
                                } else {
                                    alert('<?php echo esc_js(__('Sync failed:', 'modularity-simpleview-events')); ?> ' + (data.data?.message || 'Unknown error'));
                                }
                            })
                            .catch(function(error) {
                                alert('<?php echo esc_js(__('Error during sync:', 'modularity-simpleview-events')); ?> ' + error.message);
                            })
                            .finally(function() {
                                button.disabled = false;
                                button.textContent = originalText;
                            });
                    });
                }

                // Initialize when DOM is ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        initAutoSlug();
                        initManualSync();
                    });
                } else {
                    initAutoSlug();
                    initManualSync();
                }
            })();
        </script>
<?php
    }
}
