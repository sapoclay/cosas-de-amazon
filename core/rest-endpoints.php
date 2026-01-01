<?php
/**
 * REST API Endpoints para Cosas de Amazon
 * Maneja las peticiones del editor de bloques
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrar endpoints REST
 */
add_action('rest_api_init', function() {
    // Endpoint para obtener datos de un producto
    register_rest_route('cda/v1', '/fetch-product-data', array(
        'methods' => 'POST',
        'callback' => 'cosas_amazon_rest_fetch_product_data',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
        'args' => array(
            'url' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'validate_callback' => function($value) {
                    return !empty($value) && filter_var($value, FILTER_VALIDATE_URL);
                }
            ),
            'force_refresh' => array(
                'required' => false,
                'type' => 'boolean',
                'default' => false,
            ),
        ),
    ));
    
    // Endpoint para limpiar caché de un producto
    register_rest_route('cda/v1', '/clear-cache', array(
        'methods' => 'POST',
        'callback' => 'cosas_amazon_rest_clear_cache',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => array(
            'asin' => array(
                'required' => false,
                'type' => 'string',
            ),
            'all' => array(
                'required' => false,
                'type' => 'boolean',
                'default' => false,
            ),
        ),
    ));
    
    // Endpoint para obtener estadísticas de caché
    register_rest_route('cda/v1', '/cache-stats', array(
        'methods' => 'GET',
        'callback' => 'cosas_amazon_rest_cache_stats',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ));
});

/**
 * Callback para obtener datos de un producto
 */
function cosas_amazon_rest_fetch_product_data($request) {
    $url = $request->get_param('url');
    $force_refresh = $request->get_param('force_refresh') ?? false;
    
    if (empty($url)) {
        return new WP_Error(
            'missing_url',
            'Se requiere una URL de producto de Amazon',
            array('status' => 400)
        );
    }
    
    // Verificar que es una URL de Amazon
    if (class_exists('CosasAmazonHelpers') && !CosasAmazonHelpers::is_amazon_url($url)) {
        return new WP_Error(
            'invalid_url',
            'La URL proporcionada no es una URL válida de Amazon',
            array('status' => 400)
        );
    }
    
    // Obtener datos del producto
    if (function_exists('cosas_amazon_get_product_data')) {
        $product_data = cosas_amazon_get_product_data($url, $force_refresh);
    } elseif (class_exists('CosasAmazonHelpers')) {
        $product_data = CosasAmazonHelpers::get_product_data($url, $force_refresh);
    } else {
        return new WP_Error(
            'helper_not_found',
            'El sistema de obtención de datos no está disponible',
            array('status' => 500)
        );
    }
    
    if (!$product_data || empty($product_data)) {
        return new WP_Error(
            'no_data',
            'No se pudieron obtener los datos del producto. Amazon puede estar bloqueando las peticiones.',
            array('status' => 404)
        );
    }
    
    // Asegurar que los datos tienen el formato correcto
    $response_data = array(
        'asin' => isset($product_data['asin']) ? $product_data['asin'] : '',
        'url' => isset($product_data['url']) ? $product_data['url'] : $url,
        'title' => isset($product_data['title']) ? $product_data['title'] : '',
        'price' => isset($product_data['price']) ? $product_data['price'] : '',
        'originalPrice' => isset($product_data['originalPrice']) ? $product_data['originalPrice'] : '',
        'discount' => isset($product_data['discount']) ? $product_data['discount'] : '',
        'image' => isset($product_data['image']) ? $product_data['image'] : '',
        'description' => isset($product_data['description']) ? $product_data['description'] : '',
        'rating' => isset($product_data['rating']) ? $product_data['rating'] : '',
        'reviewCount' => isset($product_data['reviewCount']) ? $product_data['reviewCount'] : '',
        'specialOffer' => isset($product_data['specialOffer']) ? $product_data['specialOffer'] : '',
        'is_fallback' => isset($product_data['is_fallback']) ? $product_data['is_fallback'] : false,
    );
    
    return rest_ensure_response($response_data);
}

/**
 * Callback para limpiar caché
 */
function cosas_amazon_rest_clear_cache($request) {
    $asin = $request->get_param('asin');
    $clear_all = $request->get_param('all');
    
    global $wpdb;
    $cleared = 0;
    
    if ($clear_all) {
        // Limpiar todos los transients del plugin
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cosas_amazon_product_%' OR option_name LIKE '_transient_timeout_cosas_amazon_product_%'"
        );
        $cleared = $wpdb->rows_affected;
        
        // También limpiar la tabla de caché personalizada si existe
        $table_name = $wpdb->prefix . 'cosas_amazon_cache';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $wpdb->query("TRUNCATE TABLE $table_name");
        }
    } elseif (!empty($asin)) {
        // Limpiar solo el transient específico
        if (class_exists('CosasAmazonHelpers')) {
            CosasAmazonHelpers::clear_product_cache($asin);
        } else {
            delete_transient('cosas_amazon_product_' . $asin);
        }
        $cleared = 1;
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'cleared' => $cleared,
        'message' => $clear_all ? 'Toda la caché ha sido limpiada' : "Caché limpiada para ASIN: $asin"
    ));
}

/**
 * Callback para obtener estadísticas de caché
 */
function cosas_amazon_rest_cache_stats($request) {
    global $wpdb;
    
    // Contar transients de productos
    $transient_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_cosas_amazon_product_%' AND option_name NOT LIKE '_transient_timeout_%'"
    );
    
    // Obtener estadísticas de la tabla personalizada si existe
    $table_name = $wpdb->prefix . 'cosas_amazon_cache';
    $table_count = 0;
    $table_stats = array();
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $table_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $table_stats = array(
            'total_entries' => intval($table_count),
            'last_updated' => $wpdb->get_var("SELECT MAX(updated_at) FROM $table_name"),
        );
    }
    
    return rest_ensure_response(array(
        'transients' => intval($transient_count),
        'table' => $table_stats,
        'total' => intval($transient_count) + intval($table_count),
    ));
}
