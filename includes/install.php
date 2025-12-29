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
 * Buscar posts que contengan bloques o shortcodes de Cosas de Amazon.
 */
function cosas_amazon_find_posts_with_blocks() {
    global $wpdb;
    $like_block = '%<!-- wp:cosas-amazon/producto-amazon%';
    $like_shortcode1 = '%[amazon_producto%';
    $like_shortcode2 = '%[cosas-amazon%';
    $sql = $wpdb->prepare(
        "SELECT ID, post_content FROM {$wpdb->posts} " .
        "WHERE post_type NOT IN ('revision','nav_menu_item') " .
        "AND post_status IN ('publish','future','draft','pending','private') " .
        "AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)",
        $like_block, $like_shortcode1, $like_shortcode2
    );
    return $wpdb->get_results($sql, ARRAY_A);
}

/**
 * Reemplazar bloques/shortcodes por un marcador oculto y guardar backup.
 */
function cosas_amazon_disable_blocks_on_deactivate() {
    $posts = cosas_amazon_find_posts_with_blocks();
    if (empty($posts)) { return; }

    foreach ($posts as $row) {
        $post_id = intval($row['ID']);
        $content = $row['post_content'];

        // Evitar sobrescribir un backup existente
        if (!metadata_exists('post', $post_id, '_cosas_amazon_backup')) {
            update_post_meta($post_id, '_cosas_amazon_backup', $content);
        }

        $new_content = cosas_amazon_strip_blocks_from_content($content);
        if ($new_content !== $content) {
            wp_update_post(array('ID' => $post_id, 'post_content' => $new_content));
        }
    }
}

/**
 * Restaurar contenido original desde el backup guardado en activación.
 */
function cosas_amazon_restore_blocks_on_activate() {
    global $wpdb;
    $post_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
        '_cosas_amazon_backup'
    ));
    if (empty($post_ids)) { return; }

    foreach ($post_ids as $post_id) {
        $backup = get_post_meta($post_id, '_cosas_amazon_backup', true);
        if (!empty($backup)) {
            wp_update_post(array('ID' => intval($post_id), 'post_content' => $backup));
        }
        delete_post_meta($post_id, '_cosas_amazon_backup');
    }
}

/**
 * Limpiar marcadores de contenido en desinstalación (por si quedaron).
 */
function cosas_amazon_cleanup_disabled_placeholders() {
    global $wpdb;
    $like_placeholder = '%<!-- COSAS_AMAZON_DISABLED -->%';
    $sql = $wpdb->prepare(
        "SELECT ID, post_content FROM {$wpdb->posts} " .
        "WHERE post_type NOT IN ('revision','nav_menu_item') " .
        "AND post_status IN ('publish','future','draft','pending','private') " .
        "AND post_content LIKE %s",
        $like_placeholder
    );
    $posts = $wpdb->get_results($sql, ARRAY_A);
    foreach ($posts as $row) {
        $new = str_replace('<!-- COSAS_AMAZON_DISABLED -->', '', $row['post_content']);
        if ($new !== $row['post_content']) {
            wp_update_post(array('ID' => intval($row['ID']), 'post_content' => $new));
        }
    }
}

/**
 * Quitar bloques y shortcodes, dejando solo un marcador HTML invisible.
 */
function cosas_amazon_strip_blocks_from_content($content) {
    // Bloques Gutenberg del plugin
    $content = preg_replace(
        '/<!--\s*wp:cosas-amazon\/producto-amazon.*?<!--\s*\/wp:cosas-amazon\/producto-amazon\s*-->/is',
        '<!-- COSAS_AMAZON_DISABLED -->',
        $content
    );

    // Shortcodes [amazon_producto] y [cosas-amazon]
    $content = preg_replace('/\[(?:amazon_producto|cosas-amazon)[^\]]*\]/i', '<!-- COSAS_AMAZON_DISABLED -->', $content);

    return $content;
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
    
        // Limpiar placeholders y respaldos en contenido si existieran
        cosas_amazon_cleanup_disabled_placeholders();
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_cosas_amazon_backup'));
    
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
