<?php

/**
 * Plugin Name:       Modularity Simpleview Events
 * Plugin URI:        https://github.com/helsingborg-stad/modularity-simpleview-events
 * Description:       A Simpleview event integration plugin for managing events from Simpleview API.
 * Version: 1.0.0
 * Author:            Starter
 * Author URI:        https://github.com/helsingborg-stad
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       modularity-simpleview-events
 * Domain Path:       /languages
 */

// Protect against direct file access
if (! defined('WPINC')) {
    die;
}

define('MODULARITYSIMPLEVIEWEVENTS_PATH', plugin_dir_path(__FILE__));
define('MODULARITYSIMPLEVIEWEVENTS_URL', plugins_url('', __FILE__));
define('MODULARITYSIMPLEVIEWEVENTS_MODULE_VIEW_PATH', MODULARITYSIMPLEVIEWEVENTS_PATH . 'source/php/Module/views');
define('MODULARITYSIMPLEVIEWEVENTS_MODULE_PATH', MODULARITYSIMPLEVIEWEVENTS_PATH . 'source/php/Module/');

// Load text domain early (before acf/init) so ACF field labels translate
add_action('plugins_loaded', function () {
    load_plugin_textdomain('modularity-simpleview-events', false, plugin_basename(dirname(__FILE__)) . '/languages');
});

// Autoload from plugin
if (file_exists(MODULARITYSIMPLEVIEWEVENTS_PATH . 'vendor/autoload.php')) {
    require_once MODULARITYSIMPLEVIEWEVENTS_PATH . 'vendor/autoload.php';
}

// ACF auto import and export
add_action('acf/init', function () {
    if (!class_exists('\AcfExportManager\AcfExportManager')) {
        return;
    }

    $acfExportManager = new \AcfExportManager\AcfExportManager();
    $acfExportManager->setTextdomain('modularity-simpleview-events');
    $acfExportManager->setExportFolder(MODULARITYSIMPLEVIEWEVENTS_PATH . 'source/php/AcfFields/');
    $acfExportManager->autoExport(array(
        'general-settings' => 'group_simpleview_events_general_settings',
        'sv-events-module' => 'group_sv_events_module',
    ));
    $acfExportManager->import();
});

add_filter('/Modularity/externalViewPath', function (array $arr): array {
    $arr['mod-sv-events'] = MODULARITYSIMPLEVIEWEVENTS_MODULE_VIEW_PATH;

    return $arr;
}, 10, 3);

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, function () {
    // Load autoloader first    
    if (file_exists(MODULARITYSIMPLEVIEWEVENTS_PATH . 'vendor/autoload.php')) {
        require_once MODULARITYSIMPLEVIEWEVENTS_PATH . 'vendor/autoload.php';
    }

    $options = get_option('modularity-options', []);
    if (!is_array($options)) {
        $options = [];
    }
    if (!isset($options['enabled-modules']) || !is_array($options['enabled-modules'])) {
        $options['enabled-modules'] = [];
    }
    if (!in_array('mod-sv-events', $options['enabled-modules'], true)) {
        $options['enabled-modules'][] = 'mod-sv-events';
        update_option('modularity-options', $options);
    }

    // Dynamic post types are created during sync, so we just flush rewrite rules
    flush_rewrite_rules();
});

// Flush rewrite rules on plugin deactivation
register_deactivation_hook(__FILE__, function () {
    // Load autoloader first
    if (file_exists(MODULARITYSIMPLEVIEWEVENTS_PATH . 'vendor/autoload.php')) {
        require_once MODULARITYSIMPLEVIEWEVENTS_PATH . 'vendor/autoload.php';
    }

    // Clear cron events
    if (class_exists('ModularitySimpleviewEvents\Cron\SyncScheduler')) {
        $scheduler = new ModularitySimpleviewEvents\Cron\SyncScheduler();
        $scheduler->unschedule();
    }

    flush_rewrite_rules();
});

// Start application
if (class_exists('ModularitySimpleviewEvents\App')) {
    new ModularitySimpleviewEvents\App();
}

// Register WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('simpleview-events', 'ModularitySimpleviewEvents\Cli\SimpleviewCommand');
}
