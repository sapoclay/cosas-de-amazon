<?php
/**
 * Instalación y desinstalación del plugin Cosas de Amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función de activación del plugin (interna)
 */
function cosas_amazon_activate_internal() {
    // Crear opciones por defecto
    $default_options = array(
        'default_style' => 'horizontal',
        'cache_duration' => HOUR_IN_SECONDS,
        'show_price_by_default' => true,
        'show_discount_by_default' => true,
        'show_description_by_default' => true,
        'auto_update_enabled' => true,
        'update_frequency' => 'daily',
        'data_source' => 'real',
        'scraping_timeout' => 15,
        'version' => COSAS_AMAZON_VERSION
    );
    
    if (!get_option('cosas_amazon_options')) {
        add_option('cosas_amazon_options', $default_options);
    }
    
    // Establecer límite de descripción por defecto si no existe
    if (!get_option('cosas_amazon_description_length')) {
        add_option('cosas_amazon_description_length', 150);
    }
    
    // Crear tabla de logs si es necesario (opcional)
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cosas_amazon_logs';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        url varchar(500) NOT NULL,
        action varchar(50) NOT NULL,
        data text,
        PRIMARY KEY  (id),
        KEY url_index (url),
        KEY time_index (time),
        KEY action_index (action)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Programar cron job para actualización diaria
    if (!wp_next_scheduled('cosas_amazon_daily_price_update')) {
        wp_schedule_event(time(), 'daily', 'cosas_amazon_daily_price_update');
    }
    
    // Crear mapeo de URLs vacío
    add_option('cosas_amazon_url_mapping', array());
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Log de activación
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Cosas de Amazon] Plugin activado correctamente con cron job programado');
    }
}

/**
 * Función de desactivación del plugin (interna)
 */
function cosas_amazon_deactivate_internal() {
    // Cancelar cron jobs programados
    $timestamp = wp_next_scheduled('cosas_amazon_daily_price_update');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'cosas_amazon_daily_price_update');
    }
    
    // Cancelar cualquier actualización individual pendiente
    wp_clear_scheduled_hook('cosas_amazon_single_update');
    
    // Limpiar transients
    global $wpdb;
    
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cosas_amazon_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cosas_amazon_%'");
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Log de desactivación
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Cosas de Amazon] Plugin desactivado - Cron jobs cancelados');
    }
}

/**
 * Función de desinstalación del plugin
 */
function cosas_amazon_uninstall() {
    // Cancelar todos los cron jobs
    wp_clear_scheduled_hook('cosas_amazon_daily_price_update');
    wp_clear_scheduled_hook('cosas_amazon_single_update');
    
    // Eliminar opciones
    delete_option('cosas_amazon_options');
    delete_option('cosas_amazon_url_mapping');
    delete_option('cosas_amazon_last_update');
    
    // Eliminar transients
    global $wpdb;
    
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cosas_amazon_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cosas_amazon_%'");
    
    // Eliminar tabla de logs (opcional)
    $table_name = $wpdb->prefix . 'cosas_amazon_logs';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Log de desinstalación
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Cosas de Amazon] Plugin desinstalado completamente - Todos los datos eliminados');
    }
}

// Los hooks de activación/desactivación se registran en el archivo principal
// register_uninstall_hook se registra aquí para la función de desinstalación
register_uninstall_hook(COSAS_AMAZON_PLUGIN_PATH . 'cosas-de-amazon.php', 'cosas_amazon_uninstall');
