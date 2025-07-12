<?php
/**
 * Plugin Name: Cosas de Amazon
 * Plugin URI: https://entreunosyceros.com
 * Description: Plugin para mostrar productos de Amazon usando enlaces cortos con diferentes estilos de tarjetas.
 * Version: 1.3.0
 * Author: entreunosyceros
 * License: GPL v2 or later
 * Text Domain: cosas-de-amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función de log para debugging
 */
function cosas_amazon_log($message) {
    // Logs habilitados temporalmente para debug
    $log_message = '[AMAZON_DEBUG] ' . date('Y-m-d H:i:s') . ' - ' . $message;
    error_log($log_message);
}

// Definir constantes del plugin
define('COSAS_AMAZON_VERSION', '1.3.0');
define('COSAS_AMAZON_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COSAS_AMAZON_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Incluir archivos necesarios y core modular
require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/helpers.php';
require_once COSAS_AMAZON_PLUGIN_PATH . 'core/class-cosas-de-amazon.php';
require_once COSAS_AMAZON_PLUGIN_PATH . 'core/rest-endpoints.php';

// Inicializar el plugin
function cosas_amazon_init() {
    new CosasDeAmazon();
}
add_action('init', 'cosas_amazon_init');

// Cargar archivos adicionales solo si existen
if (is_admin() && file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/admin.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/admin.php';
}

// Cargar archivos de customización solo si existen
if (file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/customizer.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/customizer.php';
}

if (file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/custom-css.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/custom-css.php';
}

// Cargar estadísticas
if (file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/stats.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/stats.php';
}

// Inicializar clases si existen (solo una vez)
if (is_admin() && class_exists('CosasAmazonAdmin')) {
    new CosasAmazonAdmin();
}

if (class_exists('CosasAmazonCustomizer')) {
    new CosasAmazonCustomizer();
}

if (class_exists('CosasAmazonCustomCSS')) {
    new CosasAmazonCustomCSS();
}

if (class_exists('CosasAmazonStats')) {
    new CosasAmazonStats();
}

// Funciones de activación y desactivación
function cosas_amazon_activate() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cosas_amazon_cache';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        url varchar(500) NOT NULL,
        product_data longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        hits int DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY url (url)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Programar evento cron para actualización diaria
    if (!wp_next_scheduled('cosas_amazon_daily_price_update')) {
        wp_schedule_event(time(), 'daily', 'cosas_amazon_daily_price_update');
    }
}

function cosas_amazon_deactivate() {
    wp_clear_scheduled_hook('cosas_amazon_daily_price_update');
    wp_clear_scheduled_hook('cosas_amazon_force_price_update');
}

// Hooks de activación y desactivación
register_activation_hook(__FILE__, 'cosas_amazon_activate');
register_deactivation_hook(__FILE__, 'cosas_amazon_deactivate');

// Inicializar el plugin
cosas_amazon_init();
