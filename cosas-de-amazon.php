<?php
/**
 * Plugin Name: Cosas de Amazon
 * Plugin URI: https://entreunosyceros.com
 * Description: Plugin para mostrar productos de Amazon usando enlaces cortos con diferentes estilos de tarjetas. Versión mejorada con extracción avanzada de imágenes y CSS forzado. Incluye soporte completo para carousel con elementos centrados.
 * Version: 2.2.0
 * Author: entreunosyceros
 * License: GPL v2 or later
 * Text Domain: cosas-de-amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('COSAS_AMAZON_VERSION', '2.2.0');
define('COSAS_AMAZON_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COSAS_AMAZON_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Incluir archivos necesarios y core modular
require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/helpers.php';
require_once COSAS_AMAZON_PLUGIN_PATH . 'core/class-cosas-de-amazon.php';

// Cargar clase de Amazon PA-API
if (file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/class-amazon-paapi.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/class-amazon-paapi.php';
}

// Cargar archivos adicionales solo si existen
if (is_admin() && file_exists(COSAS_AMAZON_PLUGIN_PATH . 'includes/admin.php')) {
    require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/admin.php';
}

// Cargar otros archivos adicionales solo en admin si existen
if (is_admin()) {
    $additional_admin_files = [
        'includes/install.php',
        'includes/security.php',
        'includes/stats.php',
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
        'accent_color' => '#ff9900'
    );
    
    $existing_options = get_option('cosas_amazon_options', array());
    $merged_options = array_merge($default_options, $existing_options);
    update_option('cosas_amazon_options', $merged_options);
    
    // Limpiar caché anterior si existe
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cosas_amazon_product_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cosas_amazon_product_%'");
    
    // Programar evento cron para actualización diaria
    if (!wp_next_scheduled('cosas_amazon_daily_price_update')) {
        wp_schedule_event(time(), 'daily', 'cosas_amazon_daily_price_update');
    }
    
    // Establecer notificación de activación
    set_transient('cosas_amazon_activation_notice', true, 60);
}

function cosas_amazon_deactivate() {
    wp_clear_scheduled_hook('cosas_amazon_daily_price_update');
    wp_clear_scheduled_hook('cosas_amazon_force_price_update');
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
            <p>El plugin se ha activado correctamente. Si tienes problemas con el scraping:</p>
            <ul>
                <li><strong>Emergencia:</strong> <a href="<?php echo admin_url('options-general.php?page=cosas-amazon-emergency'); ?>">Configuración de emergencia</a></li>
                <li><strong>Diagnóstico:</strong> <a href="<?php echo admin_url('options-general.php?page=cosas-amazon-diagnostic'); ?>">Herramientas de diagnóstico</a></li>
                <li><strong>Configuración:</strong> <a href="<?php echo admin_url('options-general.php?page=cosas-amazon-options'); ?>">Opciones generales</a></li>
            </ul>
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
    $body = $request->get_json_params();
    $url_from_body = isset($body['url']) ? $body['url'] : '';
    $url_from_param = $request->get_param('url');
    
    $url = !empty($url_from_body) ? $url_from_body : $url_from_param;
    $url = esc_url_raw($url);
    
    if (empty($url)) {
        return new WP_Error('no_url', 'No URL provided', array('status' => 400));
    }

    if (!class_exists('CosasAmazonHelpers')) {
        require_once COSAS_AMAZON_PLUGIN_PATH . 'includes/helpers.php';
    }
    
    $is_amazon = CosasAmazonHelpers::is_amazon_url($url);
    if (!$is_amazon) {
        return new WP_Error('invalid_url', 'URL is not a valid Amazon URL', array('status' => 400));
    }

    // Forzar obtener datos reales
    $force_refresh = isset($body['force_refresh']) ? $body['force_refresh'] : false;
    $product_data = CosasAmazonHelpers::get_product_data($url, $force_refresh);
    
    if (!$product_data || empty($product_data['title'])) {
        return new WP_Error('not_found', 'No se pudieron obtener datos del producto', array('status' => 404));
    }

    return rest_ensure_response($product_data);
}
