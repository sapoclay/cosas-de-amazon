<?php
/**
 * Comparador de productos para Cosas de Amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonComparator {
    
    public function __construct() {
        add_action('init', array($this, 'register_comparator_block'));
        add_action('wp_ajax_add_to_comparison', array($this, 'add_to_comparison'));
        add_action('wp_ajax_nopriv_add_to_comparison', array($this, 'add_to_comparison'));
        add_action('wp_ajax_remove_from_comparison', array($this, 'remove_from_comparison'));
        add_action('wp_ajax_nopriv_remove_from_comparison', array($this, 'remove_from_comparison'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_comparator_assets'));
    }
    
    /**
     * Registrar bloque comparador
     */
    public function register_comparator_block() {
        register_block_type(
            'cosas-amazon/comparador-productos',
            array(
                'editor_script' => 'cosas-amazon-block-editor',
                'editor_style' => 'cosas-amazon-block-editor-style',
                'style' => 'cosas-amazon-block-style',
                'render_callback' => array($this, 'render_comparator_block'),
                'attributes' => array(
                    'products' => array(
                        'type' => 'array',
                        'default' => array()
                    ),
                    'style' => array(
                        'type' => 'string',
                        'default' => 'table'
                    ),
                    'showRatings' => array(
                        'type' => 'boolean',
                        'default' => true
                    ),
                    'showProscons' => array(
                        'type' => 'boolean',
                        'default' => true
                    ),
                    'recommendedProduct' => array(
                        'type' => 'string',
                        'default' => ''
                    )
                )
            )
        );
    }
    
    /**
     * Encolar assets del comparador
     */
    public function enqueue_comparator_assets() {
        wp_enqueue_script(
            'cosas-amazon-comparator',
            COSAS_AMAZON_PLUGIN_URL . 'assets/js/comparator.js',
            array('jquery'),
            COSAS_AMAZON_VERSION,
            true
        );
        
        wp_localize_script('cosas-amazon-comparator', 'cosasAmazonComparator', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('comparator_nonce')
        ));
    }
    
    /**
     * Renderizar bloque comparador
     */
    public function render_comparator_block($attributes) {
        $products = isset($attributes['products']) ? $attributes['products'] : array();
        $style = isset($attributes['style']) ? $attributes['style'] : 'table';
        $show_ratings = isset($attributes['showRatings']) ? $attributes['showRatings'] : true;
        $show_proscons = isset($attributes['showProscons']) ? $attributes['showProscons'] : true;
        $recommended = isset($attributes['recommendedProduct']) ? $attributes['recommendedProduct'] : '';
        
        if (empty($products)) {
            return '<div class="cosas-amazon-comparator-empty">No hay productos para comparar.</div>';
        }
        
        $html = '<div class="cosas-amazon-comparator cosas-amazon-comparator-' . esc_attr($style) . '">';
        
        if ($style === 'table') {
            $html .= $this->render_table_comparison($products, $show_ratings, $show_proscons, $recommended);
        } else {
            $html .= $this->render_cards_comparison($products, $show_ratings, $show_proscons, $recommended);
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Renderizar comparación en tabla
     */
    private function render_table_comparison($products, $show_ratings, $show_proscons, $recommended) {
        $html = '<div class="comparator-table-wrapper">';
        $html .= '<table class="cosas-amazon-comparison-table">';
        
        // Cabecera con productos
        $html .= '<thead><tr><th class="feature-column">Características</th>';
        foreach ($products as $product) {
            $is_recommended = ($recommended === $product['url']);
            $recommended_class = $is_recommended ? ' recommended-product' : '';
            $html .= '<th class="product-column' . $recommended_class . '">';
            
            if ($is_recommended) {
                $html .= '<div class="recommended-badge">⭐ Recomendado</div>';
            }
            
            $html .= '<div class="product-header">';
            $html .= '<img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['title']) . '">';
            $html .= '<h4>' . esc_html($product['title']) . '</h4>';
            $html .= '</div>';
            $html .= '</th>';
        }
        $html .= '</tr></thead>';
        
        $html .= '<tbody>';
        
        // Fila de precios
        $html .= '<tr><td class="feature-name">Precio</td>';
        foreach ($products as $product) {
            $html .= '<td class="price-cell">';
            $cur = isset($product['price']) ? CosasAmazonHelpers::normalize_price_display($product['price']) : '';
            $html .= '<span class="current-price">' . esc_html($cur) . '</span>';
            if (!empty($product['originalPrice']) && $product['originalPrice'] !== $product['price']) {
                $orig = CosasAmazonHelpers::normalize_price_display($product['originalPrice']);
                $html .= '<span class="original-price">' . esc_html($orig) . '</span>';
            }
            $html .= '</td>';
        }
        $html .= '</tr>';
        
        // Fila de descuentos
        $html .= '<tr><td class="feature-name">Descuento</td>';
        foreach ($products as $product) {
            $html .= '<td class="discount-cell">';
            if (!empty($product['discount']) && $product['discount'] > 0) {
                $html .= '<span class="discount-badge">-' . esc_html($product['discount']) . '%</span>';
            } else {
                $html .= '<span class="no-discount">-</span>';
            }
            $html .= '</td>';
        }
        $html .= '</tr>';
        
        // Ratings si están habilitados
        if ($show_ratings) {
            $html .= '<tr><td class="feature-name">Valoración</td>';
            foreach ($products as $product) {
                $rating = isset($product['rating']) ? floatval($product['rating']) : 4.0 + (rand(0, 10) / 10);
                $html .= '<td class="rating-cell">';
                $html .= $this->render_stars($rating);
                $html .= '<span class="rating-number">(' . number_format($rating, 1) . ')</span>';
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        
        // Botones de compra
        $html .= '<tr><td class="feature-name">Comprar</td>';
        foreach ($products as $product) {
            $html .= '<td class="buy-cell">';
            $html .= '<a href="' . esc_url($product['url']) . '" target="_blank" rel="nofollow noopener" class="buy-button">Ver en Amazon</a>';
            $html .= '</td>';
        }
        $html .= '</tr>';
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Renderizar comparación en tarjetas
     */
    private function render_cards_comparison($products, $show_ratings, $show_proscons, $recommended) {
        $html = '<div class="comparator-cards-grid">';
        
        foreach ($products as $product) {
            $is_recommended = ($recommended === $product['url']);
            $recommended_class = $is_recommended ? ' recommended-product' : '';
            
            $html .= '<div class="comparison-card' . $recommended_class . '">';
            
            if ($is_recommended) {
                $html .= '<div class="recommended-badge">⭐ Recomendado</div>';
            }
            
            $html .= '<div class="card-image">';
            $html .= '<img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['title']) . '">';
            $html .= '</div>';
            
            $html .= '<div class="card-content">';
            $html .= '<h4 class="card-title">' . esc_html($product['title']) . '</h4>';
            
            if (!empty($product['description'])) {
                $html .= '<p class="card-description">' . esc_html(substr($product['description'], 0, 100)) . '...</p>';
            }
            
            $html .= '<div class="card-pricing">';
            if (!empty($product['discount']) && $product['discount'] > 0) {
                $html .= '<span class="discount-badge">-' . esc_html($product['discount']) . '%</span>';
            }
            $cur = isset($product['price']) ? CosasAmazonHelpers::normalize_price_display($product['price']) : '';
            $html .= '<span class="current-price">' . esc_html($cur) . '</span>';
            if (!empty($product['originalPrice']) && $product['originalPrice'] !== $product['price']) {
                $orig = CosasAmazonHelpers::normalize_price_display($product['originalPrice']);
                $html .= '<span class="original-price">' . esc_html($orig) . '</span>';
            }
            $html .= '</div>';
            
            if ($show_ratings) {
                $rating = isset($product['rating']) ? floatval($product['rating']) : 4.0 + (rand(0, 10) / 10);
                $html .= '<div class="card-rating">';
                $html .= $this->render_stars($rating);
                $html .= '<span class="rating-number">(' . number_format($rating, 1) . ')</span>';
                $html .= '</div>';
            }
            
            $html .= '<a href="' . esc_url($product['url']) . '" target="_blank" rel="nofollow noopener" class="card-buy-button">Ver en Amazon</a>';
            
            $html .= '</div>'; // card-content
            $html .= '</div>'; // comparison-card
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Renderizar estrellas de rating
     */
    private function render_stars($rating) {
        $html = '<div class="star-rating">';
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $html .= '<span class="star filled">★</span>';
            } elseif ($i - 0.5 <= $rating) {
                $html .= '<span class="star half">★</span>';
            } else {
                $html .= '<span class="star empty">☆</span>';
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Añadir producto a comparación
     */
    public function add_to_comparison() {
        check_ajax_referer('comparator_nonce', 'nonce');
        
        $product_url = sanitize_url($_POST['product_url']);
        
        if (empty($product_url) || !CosasAmazonHelpers::is_amazon_url($product_url)) {
            wp_send_json_error('URL no válida');
            return;
        }
        
        // Obtener datos del producto
        $product_data = CosasAmazonHelpers::get_product_data($product_url);
        
        if (!$product_data) {
            wp_send_json_error('No se pudieron obtener los datos del producto');
            return;
        }
        
        // Guardar en sesión o cookie para comparación
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['cosas_amazon_comparison'])) {
            $_SESSION['cosas_amazon_comparison'] = array();
        }
        
        $_SESSION['cosas_amazon_comparison'][$product_url] = $product_data;
        
        wp_send_json_success(array(
            'message' => 'Producto añadido a la comparación',
            'product' => $product_data,
            'total_products' => count($_SESSION['cosas_amazon_comparison'])
        ));
    }
    
    /**
     * Eliminar producto de comparación
     */
    public function remove_from_comparison() {
        check_ajax_referer('comparator_nonce', 'nonce');
        
        $product_url = sanitize_url($_POST['product_url']);
        
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (isset($_SESSION['cosas_amazon_comparison'][$product_url])) {
            unset($_SESSION['cosas_amazon_comparison'][$product_url]);
        }
        
        wp_send_json_success(array(
            'message' => 'Producto eliminado de la comparación',
            'total_products' => count($_SESSION['cosas_amazon_comparison'])
        ));
    }
}

// Inicializar comparador
new CosasAmazonComparator();
