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

// Load text domain
add_action('init', function () {
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
    ));
    $acfExportManager->import();
});

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, function () {
    // Load autoloader first    
    if (file_exists(MODULARITYSIMPLEVIEWEVENTS_PATH . 'vendor/autoload.php')) {
        require_once MODULARITYSIMPLEVIEWEVENTS_PATH . 'vendor/autoload.php';
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
