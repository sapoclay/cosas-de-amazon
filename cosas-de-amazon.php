<?php
/**
 * Plugin Name: Cosas de Amazon
 * Plugin URI: https://entreunosyceros.com
 * Description: Plugin para mostrar productos de Amazon usando enlaces cortos con diferentes estilos de tarjetas. Versión mejorada con limitaciones progresivas completas, sincronización editor-frontend, soporte integral para múltiples productos y CSS personalizado.
 * Version: 2.12.0
 * Author: entreunosyceros
 * License: GPL v2 or later
 * Text Domain: cosas-de-amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('COSAS_AMAZON_VERSION', '2.12.0');
define('COSAS_AMAZON_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COSAS_AMAZON_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Activar modo debug del plugin solo si está habilitado explícitamente en configuración de emergencia
// Nota: se eliminó la activación automática cuando WP_DEBUG está activo para evitar ruido en debug.log.
if (!defined('COSAS_AMAZON_DEBUG')) {
    $emergency_config = get_option('cosas_amazon_emergency_config', []);
    $debug_enabled = !empty($emergency_config['debug_mode']);
    define('COSAS_AMAZON_DEBUG', $debug_enabled);
}

// Cargar traducciones del plugin
add_action('plugins_loaded', function() {
    load_plugin_textdomain('cosas-de-amazon', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Incluir archivos necesarios y core modular
require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/helpers.php';
require_once COSAS_AMAZON_PLUGIN_PATH . 'core/class-cosas-de-amazon.php';

// Cargar clase de Amazon PA-API
if (file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/class-amazon-paapi.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/class-amazon-paapi.php';
}

// Cargar sistema de estadísticas en todos los contextos (frontend y admin)
if (file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/stats.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/stats.php';
}

// Cargar clase admin siempre (necesaria para verificaciones)
if (file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/admin.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/admin.php';
}

// Cargar otros archivos adicionales solo en admin si existen
if (is_admin()) {
    $additional_admin_files = [
        'includes/install.php',
        'includes/security.php',
        'includes/comparator.php',
        'includes/customizer.php',
        'includes/custom-css.php',
        'includes/price-alerts.php',
        'includes/frontend-images-fix.php'
    ];
    
    foreach ($additional_admin_files as $admin_file) {
        if (file_exists(COSAS_AMAZON_PLUGIN_PATH . $admin_file)) {
            require_once COSAS_AMAZON_PLUGIN_PATH . $admin_file;
        }
    }
}

// Cargar core REST endpoints si existe
if (file_exists(COSAS_AMAZON_PLUGIN_PATH . 'core/rest-endpoints.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'core/rest-endpoints.php';
}

// Detectar servidor y aplicar configuraciones específicas
function cosas_amazon_detect_server_config() {
    $is_litespeed = stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'litespeed') !== false;
    
    if ($is_litespeed) {
        // Configuración específica para LiteSpeed
        add_action('wp_enqueue_scripts', 'cosas_amazon_litespeed_assets', 5);
        add_action('admin_enqueue_scripts', 'cosas_amazon_litespeed_assets', 5);
        
        // Headers anti-cache para LiteSpeed
        add_action('send_headers', 'cosas_amazon_litespeed_headers');
    }
}
add_action('init', 'cosas_amazon_detect_server_config', 1);

// Configuración de assets para LiteSpeed
function cosas_amazon_litespeed_assets() {
    $asset_version = get_option('cosas_amazon_asset_version', COSAS_AMAZON_VERSION);
    
    // Forzar recarga con timestamp si es necesario
    $emergency_config = get_option('cosas_amazon_emergency_config', []);
    if (!empty($emergency_config['debug_mode'])) {
        $asset_version = time();
    }
    
    // Registrar assets con versión actualizada
    wp_register_style(
        'cosas-amazon-litespeed-fix',
        COSAS_AMAZON_PLUGIN_URL . 'assets/css/style.css',
        [],
        $asset_version,
        'all'
    );
    
    wp_register_script(
        'cosas-amazon-litespeed-fix',
        COSAS_AMAZON_PLUGIN_URL . 'assets/js/frontend.js',
        ['jquery'],
        $asset_version,
        true
    );
}

// Headers específicos para LiteSpeed
function cosas_amazon_litespeed_headers() {
    if (is_admin() || strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-content/plugins/cosas-de-amazon') !== false) {
        header('Cache-Control: no-cache, no-store, must-revalidate', true);
        header('Pragma: no-cache', true);
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT', true);
    }
}

// ==========================================
// REGISTRO DEL CRON PARA ACTUALIZACIÓN DE PRECIOS
// Debe registrarse ANTES del hook 'init' para que esté disponible cuando WordPress procese crons
// ==========================================
function cosas_amazon_handle_daily_price_update($args = array()) {
    // Asegurar que la clase esté cargada
    if (!class_exists('CosasDeAmazon')) {
        require_once COSAS_AMAZON_PLUGIN_PATH . 'core/class-cosas-de-amazon.php';
    }
    
    try {
        $args = is_array($args) ? $args : array();
        $stats = CosasDeAmazon::run_bulk_price_refresh($args);
        update_option('cosas_amazon_last_update', $stats);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CosasDeAmazon][cron] Actualización de precios completada: ' . json_encode($stats));
        }
    } catch (\Throwable $e) {
        error_log('[CosasDeAmazon][cron] Error en actualización de precios: ' . $e->getMessage());
    }
}
// Registrar el hook ANTES de 'init' para asegurar que esté disponible para el cron
add_action('cosas_amazon_daily_price_update', 'cosas_amazon_handle_daily_price_update', 10, 1);
add_action('cosas_amazon_force_price_update', 'cosas_amazon_handle_daily_price_update', 10, 1);

// Inicializar el plugin
function cosas_amazon_init() {
    new CosasDeAmazon();
}
add_action('init', 'cosas_amazon_init');

// SOLUCIÓN ALTERNATIVA: Registrar el bloque directamente
add_action('init', function() {
    if (class_exists('CosasDeAmazon')) {
        $instance = new CosasDeAmazon();
        
        // Forzar registro del bloque
        if (method_exists($instance, 'init')) {
            $instance->init();
        }
    }
}, 999); // Prioridad alta para ejecutar después de otros plugins

// Inicialización única del admin para evitar duplicados de menú
add_action('plugins_loaded', function() {
    if (is_admin() && class_exists('CosasAmazonAdmin')) {
        if (!isset($GLOBALS['cosas_amazon_admin_instance'])) {
            $GLOBALS['cosas_amazon_admin_instance'] = new CosasAmazonAdmin();
            error_log('[COSAS_AMAZON_DEBUG] Admin instance creada en plugins_loaded');
        }
    }
}, 1);

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
    // Verificar requisitos mínimos
    if (!function_exists('curl_init')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('El plugin "Cosas de Amazon" requiere la extensión cURL de PHP.');
    }
    
    // Crear tabla de caché si no existe
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
    
    // Crear opciones por defecto si no existen
    $default_options = array(
        'data_source' => 'real',
        'cache_duration' => 3600,
        'timeout' => 15,
        'show_ratings' => true,
        'default_style' => 'horizontal',
        'show_price' => true,
        'show_discount' => true,
        'show_description' => true,
        'primary_color' => '#e47911',
        'secondary_color' => '#232f3e',
        'accent_color' => '#ff9900',
        'show_button_by_default' => true,
        'default_button_text' => 'Ver en Amazon',
        'enable_cache' => true,
        'track_clicks' => true,
        'button_style' => 'modern',
        'open_in_new_tab' => true,
        'enable_fallback_images' => true,
        'show_price_by_default' => true,
        'show_discount_by_default' => true,
        'show_description_by_default' => true,
        'default_description_length' => 150,
        'default_text_color' => '#000000',
        'default_font_size' => '16px',
        'default_border_style' => 'solid',
        'default_border_color' => '#cccccc',
        'default_background_color' => '#ffffff',
        'default_alignment' => 'center',
        'default_button_color' => '#FF9900',
        'show_special_offer_by_default' => true,
        'default_special_offer_color' => '#e74c3c',
        'default_block_size' => 'medium',
        'default_products_per_row' => 2
    );
    
    $existing_options = get_option('cosas_amazon_options', array());
    $merged_options = array_merge($default_options, $existing_options);
    // Ajuste: ocultar placeholders en frontend por defecto
    if (!isset($merged_options['hide_placeholder_on_frontend'])) {
        $merged_options['hide_placeholder_on_frontend'] = true;
    }
    update_option('cosas_amazon_options', $merged_options);
    
    // Configuración específica para producción
    $server_type = stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'litespeed') !== false ? 'litespeed' : 'other';
    $production_config = array(
        'activation_date' => current_time('mysql'),
        'server_type' => $server_type,
        'asset_version' => time(),
        'force_button_display' => true,
        'production_mode' => !WP_DEBUG
    );
    update_option('cosas_amazon_production_config', $production_config);
    
    // Generar nueva versión de assets para evitar cache
    update_option('cosas_amazon_asset_version', time());
    
    // Limpiar caché anterior si existe
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cosas_amazon_product_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cosas_amazon_product_%'");
    
    // Programar evento cron para actualización diaria
    if (!wp_next_scheduled('cosas_amazon_daily_price_update')) {
        wp_schedule_event(time(), 'daily', 'cosas_amazon_daily_price_update');
    }
    
    // Establecer notificación de activación
    set_transient('cosas_amazon_activation_notice', true, 60);
    
    // Forzar inicialización de la clase admin para verificar menús
    if (class_exists('CosasAmazonAdmin')) {
        $admin_instance = new CosasAmazonAdmin();
        $GLOBALS['cosas_amazon_admin_instance'] = $admin_instance;
        error_log('[COSAS_AMAZON_DEBUG] Admin instance creada durante activación');
        
        // Forzar que los hooks de admin_menu se ejecuten inmediatamente
        if (method_exists($admin_instance, 'add_admin_menu')) {
            $admin_instance->add_admin_menu();
            error_log('[COSAS_AMAZON_DEBUG] Menús admin forzados durante activación');
        }
    }
    
    // Limpiar cache de opciones para forzar recarga
    wp_cache_delete('cosas_amazon_options', 'options');

    // Restaurar contenido que se hubiera ocultado al desactivar
    if (function_exists('cosas_amazon_restore_blocks_on_activate')) {
        cosas_amazon_restore_blocks_on_activate();
    }
    
    error_log('[COSAS_AMAZON_DEBUG] Plugin activado completamente');
}

function cosas_amazon_deactivate() {
    wp_clear_scheduled_hook('cosas_amazon_daily_price_update');
    wp_clear_scheduled_hook('cosas_amazon_force_price_update');

    // Ocultar bloques/shortcodes de Cosas de Amazon en el contenido
    if (function_exists('cosas_amazon_disable_blocks_on_deactivate')) {
        cosas_amazon_disable_blocks_on_deactivate();
    }
}

// Hooks de activación y desactivación
register_activation_hook(__FILE__, 'cosas_amazon_activate');
register_deactivation_hook(__FILE__, 'cosas_amazon_deactivate');

// Función para mostrar notificación después de activar
function cosas_amazon_first_activation_notice() {
    if (get_transient('cosas_amazon_activation_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <h3>🎉 ¡Cosas de Amazon activado!</h3>
            <p>El plugin se ha activado correctamente.</p>
            <p><strong>Próximo paso:</strong> Ve a <a href="<?php echo admin_url('admin.php?page=cosas-amazon-main'); ?>"><strong>Cosas de Amazon</strong></a> en el menú de administración para configurar el plugin.</p>
            <p><small><strong>💡 Tip:</strong> En la página de configuración encontrarás herramientas de diagnóstico y verificación si necesitas solucionar algún problema.</small></p>
        </div>
        <?php
        delete_transient('cosas_amazon_activation_notice');
    }
}

/**
 * Forzar carga de CSS inline para máxima compatibilidad
 */
function cosas_amazon_force_css_inline() {
    if (is_singular() || is_home() || is_front_page()) {
        $css_path = COSAS_AMAZON_PLUGIN_PATH . 'assets/css/style.css';
        if (file_exists($css_path)) {
            $css_content = file_get_contents($css_path);
            if ($css_content) {
                echo "<style id=\"cosas-amazon-forced-inline\">\n";
                echo "/* Cosas de Amazon - CSS Forzado Inline v2.1.0 */\n";
                echo $css_content;
                echo "\n</style>\n";
            }
        }
    }
}

// Agregar con alta prioridad para que aparezca en el head
add_action("wp_head", "cosas_amazon_force_css_inline", 1);

// Mostrar notificación después de activar
add_action('admin_notices', 'cosas_amazon_first_activation_notice');

// ============================================
// REGISTRO DE ENDPOINTS REST
// ============================================
add_action('rest_api_init', function () {
    register_rest_route('cda/v1', '/fetch-product-data', array(
        'methods' => 'POST',
        'callback' => 'cda_fetch_product_data_callback',
        'permission_callback' => function () {
            return is_user_logged_in() && current_user_can('edit_posts');
        },
        'args' => array(
            'url' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ),
        ),
    ));
    
    register_rest_route('cda/v1', '/test', array(
        'methods' => 'GET',
        'callback' => 'cda_test_callback',
        'permission_callback' => '__return_true'
    ));
});

function cda_test_callback($request) {
    return rest_ensure_response(array(
        'status' => 'ok',
        'message' => 'Cosas de Amazon REST API funcionando',
        'timestamp' => current_time('mysql'),
        'user_can_edit_posts' => current_user_can('edit_posts')
    ));
}

function cda_fetch_product_data_callback($request) {
    error_log('[COSAS_AMAZON_DEBUG] === ENDPOINT REST LLAMADO ===');
    
    $body = $request->get_json_params();
    $url_from_body = isset($body['url']) ? $body['url'] : '';
    $url_from_param = $request->get_param('url');
    
    $url = !empty($url_from_body) ? $url_from_body : $url_from_param;
    $url = esc_url_raw($url);
    
    error_log('[COSAS_AMAZON_DEBUG] URL recibida: ' . $url);
    
    if (empty($url)) {
        error_log('[COSAS_AMAZON_DEBUG] Error: URL vacía');
        return new WP_Error('no_url', 'No URL provided', array('status' => 400));
    }

    if (!class_exists('CosasAmazonHelpers')) {
        require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/helpers.php';
    }
    
    $is_amazon = CosasAmazonHelpers::is_amazon_url($url);
    error_log('[COSAS_AMAZON_DEBUG] ¿Es URL de Amazon?: ' . ($is_amazon ? 'SÍ' : 'NO'));
    
    if (!$is_amazon) {
        error_log('[COSAS_AMAZON_DEBUG] Error: URL no es de Amazon');
        return new WP_Error('invalid_url', 'URL is not a valid Amazon URL', array('status' => 400));
    }

    // Forzar obtener datos reales
    $force_refresh = isset($body['force_refresh']) ? $body['force_refresh'] : false;
    
    error_log('[COSAS_AMAZON_DEBUG] Llamando a get_product_data con force_refresh=' . ($force_refresh ? 'true' : 'false'));
    
    $product_data = CosasAmazonHelpers::get_product_data($url, $force_refresh);
    
    error_log('[COSAS_AMAZON_DEBUG] Datos obtenidos: ' . print_r($product_data, true));
    
    if (!$product_data || empty($product_data['title'])) {
        error_log('[COSAS_AMAZON_DEBUG] Error: No se pudieron obtener datos o título vacío');
        return new WP_Error('not_found', 'No se pudieron obtener datos del producto', array('status' => 404));
    }

    error_log('[COSAS_AMAZON_DEBUG] Retornando datos exitosamente');
    error_log('[COSAS_AMAZON_DEBUG] === FIN ENDPOINT REST ===');
    return rest_ensure_response($product_data);
}
