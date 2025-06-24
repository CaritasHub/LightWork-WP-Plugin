<?php
/**
 * Plugin Name: LightWork WP Plugin
 * Description: Gestione dei Custom Post Types integrata con ACF e REST API.
 * Version: 0.3.3
 * Author: LightWork
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once __DIR__ . '/includes/class-template-editor.php';
require_once __DIR__ . '/includes/class-acf-system.php';
require_once __DIR__ . '/includes/class-cpt-system.php';
require_once __DIR__ . '/includes/class-lightwork-wp-plugin.php';

function lightwork_wp_plugin_main() {
    return LightWork_WP_Plugin::instance();
}

lightwork_wp_plugin_main();

register_activation_hook( __FILE__, [ 'LightWork_WP_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'LightWork_WP_Plugin', 'deactivate' ] );
