<?php
/*
Plugin Name: WP Realtime Sports Engine
Description: Realtime sports scores and odds engine
Version: 1.0.0
Author: Mean Un
*/

if (!defined('ABSPATH')) exit;

define('WRSE_PATH', plugin_dir_path(__FILE__));
define('WRSE_URL', plugin_dir_url(__FILE__));
define('WRSE_VERSION', '1.0.0');

require_once WRSE_PATH . 'includes/db.php';
require_once WRSE_PATH . 'includes/cron.php';
require_once WRSE_PATH . 'includes/rest-api.php';
require_once WRSE_PATH . 'public/shortcodes.php';
require_once WRSE_PATH . 'admin/settings.php';
require_once WRSE_PATH . 'includes/crawler.php';
require_once WRSE_PATH . 'includes/odds-tracker.php';

register_activation_hook(__FILE__, 'wrse_activate');
register_deactivation_hook(__FILE__, 'wrse_deactivate');
require_once WRSE_PATH . 'public/header-shortcode.php';

function wrse_register_assets() {
    wp_register_style('wrse-header', WRSE_URL . 'assets/css/wrse-header.css', [], WRSE_VERSION);
    wp_register_script('wrse-header', WRSE_URL . 'assets/js/wrse-header.js', [], WRSE_VERSION, true);
    wp_register_style('wrse-frontend', WRSE_URL . 'assets/css/wrse-frontend.css', [], WRSE_VERSION);
    wp_register_script('wrse-frontend', WRSE_URL . 'assets/js/wrse-frontend.js', ['jquery'], WRSE_VERSION, true);
}
add_action('wp_enqueue_scripts', 'wrse_register_assets');

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('wrse-header');
    wp_enqueue_script('wrse-header');
});
