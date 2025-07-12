<?php
// Endpoints REST del plugin Cosas de Amazon
add_action('rest_api_init', function () {
    register_rest_route('cda/v1', '/fetch-product-data', array(
        'methods' => 'POST',
        'callback' => 'cda_fetch_product_data_callback',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));
    
    // Endpoint de test simple
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
    // Añadir logging para debug
    error_log('[COSAS_AMAZON_DEBUG] === Endpoint fetch-product-data called ===');
    
    // Verificar que tenemos los datos de la petición
    $body = $request->get_json_params();
    $url_from_body = isset($body['url']) ? $body['url'] : '';
    $url_from_param = $request->get_param('url');
    
    error_log('[COSAS_AMAZON_DEBUG] URL from body: ' . $url_from_body);
    error_log('[COSAS_AMAZON_DEBUG] URL from param: ' . $url_from_param);
    
    // Usar la URL que esté disponible
    $url = !empty($url_from_body) ? $url_from_body : $url_from_param;
    $url = esc_url_raw($url);
    
    error_log('[COSAS_AMAZON_DEBUG] Final URL: ' . $url);
    
    if (empty($url)) {
        error_log('[COSAS_AMAZON_DEBUG] No URL provided - returning error');
        return new WP_Error('no_url', 'No URL provided', array('status' => 400));
    }

    // Validar que sea una URL de Amazon
    if (!class_exists('CosasAmazonHelpers')) {
        require_once dirname(__DIR__) . '/includes/helpers.php';
        error_log('[COSAS_AMAZON_DEBUG] Loaded helpers.php');
    }
    
    $is_amazon = CosasAmazonHelpers::is_amazon_url($url);
    error_log('[COSAS_AMAZON_DEBUG] Is Amazon URL: ' . ($is_amazon ? 'YES' : 'NO'));
    
    if (!$is_amazon) {
        error_log('[COSAS_AMAZON_DEBUG] URL rejected as invalid Amazon URL');
        return new WP_Error('invalid_url', 'URL is not a valid Amazon URL', array('status' => 400));
    }

    // Obtener datos del producto (scraping/caché)
    error_log('[COSAS_AMAZON_DEBUG] Calling get_product_data...');
    $product_data = CosasAmazonHelpers::get_product_data($url);
    error_log('[COSAS_AMAZON_DEBUG] Product data result: ' . print_r($product_data, true));
    
    if (!$product_data || empty($product_data['title'])) {
        error_log('[COSAS_AMAZON_DEBUG] No product data or empty title - returning error');
        return new WP_Error('not_found', 'No se pudieron obtener datos del producto', array('status' => 404));
    }

    error_log('[COSAS_AMAZON_DEBUG] === Returning product data successfully ===');
    // Respuesta exitosa
    return rest_ensure_response($product_data);
}
