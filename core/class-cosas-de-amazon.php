<?php
// Clase principal del plugin CosasDeAmazon

class CosasDeAmazon {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        // AJAX para herramientas de administración
        add_action('wp_ajax_cosas_amazon_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_cosas_amazon_cache_stats', array($this, 'ajax_cache_stats'));
        // Cron para actualización automática de precios
        add_action('cosas_amazon_daily_price_update', array($this, 'daily_price_update'));
        add_action('cosas_amazon_force_price_update', array($this, 'daily_price_update'));
    }

    /**
     * Obtener valores por defecto desde la configuración del plugin
     */
    private function get_default_attributes() {
        $options = get_option('cosas_amazon_options', array());
        
        return array(
            'displayStyle' => isset($options['default_style']) ? $options['default_style'] : 'horizontal',
            'showPrice' => isset($options['show_price_by_default']) ? $options['show_price_by_default'] : true,
            'showDiscount' => isset($options['show_discount_by_default']) ? $options['show_discount_by_default'] : true,
            'showDescription' => isset($options['show_description_by_default']) ? $options['show_description_by_default'] : true,
            'descriptionLength' => isset($options['default_description_length']) ? intval($options['default_description_length']) : 150,
            'color' => isset($options['default_text_color']) ? $options['default_text_color'] : '#000000',
            'fontSize' => isset($options['default_font_size']) ? $options['default_font_size'] : '16px',
            'borderStyle' => isset($options['default_border_style']) ? $options['default_border_style'] : 'solid',
            'borderColor' => isset($options['default_border_color']) ? $options['default_border_color'] : '#cccccc',
            'backgroundColor' => isset($options['default_background_color']) ? $options['default_background_color'] : '#ffffff',
            'alignment' => isset($options['default_alignment']) ? $options['default_alignment'] : 'center',
            'showButton' => isset($options['show_button_by_default']) ? $options['show_button_by_default'] : true,
            'buttonText' => isset($options['default_button_text']) ? $options['default_button_text'] : 'Ver en Amazon',
            'buttonColor' => isset($options['default_button_color']) ? $options['default_button_color'] : '#FF9900',
            'showSpecialOffer' => isset($options['show_special_offer_by_default']) ? $options['show_special_offer_by_default'] : true,
            'specialOfferColor' => isset($options['default_special_offer_color']) ? $options['default_special_offer_color'] : '#e74c3c',
            'blockSize' => isset($options['default_block_size']) ? $options['default_block_size'] : 'medium',
            'productsPerRow' => isset($options['default_products_per_row']) ? intval($options['default_products_per_row']) : 2
        );
    }

    public function init() {
        // Obtener valores por defecto desde la configuración
        $defaults = $this->get_default_attributes();
        
        // Registrar el bloque
        register_block_type(
            'cosas-amazon/producto-amazon',
            array(
                'editor_script' => 'cosas-amazon-block-editor',
                'editor_style' => 'cosas-amazon-block-editor-style',
                'style' => 'cosas-amazon-block-style',
                'render_callback' => array($this, 'render_amazon_product_block'),
                'attributes' => array(
                    'amazonUrl' => array('type' => 'string', 'default' => ''),
                    'amazonUrls' => array('type' => 'array', 'default' => array()),
                    'displayStyle' => array('type' => 'string', 'default' => $defaults['displayStyle']),
                    'blockSize' => array('type' => 'string', 'default' => $defaults['blockSize']),
                    'productData' => array('type' => 'object', 'default' => array()),
                    'productsData' => array('type' => 'array', 'default' => array()),
                    'showPrice' => array('type' => 'boolean', 'default' => $defaults['showPrice']),
                    'showDiscount' => array('type' => 'boolean', 'default' => $defaults['showDiscount']),
                    'showDescription' => array('type' => 'boolean', 'default' => $defaults['showDescription']),
                    'descriptionLength' => array('type' => 'number', 'default' => $defaults['descriptionLength']),
                    'color' => array('type' => 'string', 'default' => $defaults['color']),
                    'fontSize' => array('type' => 'string', 'default' => $defaults['fontSize']),
                    'borderStyle' => array('type' => 'string', 'default' => $defaults['borderStyle']),
                    'borderColor' => array('type' => 'string', 'default' => $defaults['borderColor']),
                    'backgroundColor' => array('type' => 'string', 'default' => $defaults['backgroundColor']),
                    'alignment' => array('type' => 'string', 'default' => $defaults['alignment']),
                    'showButton' => array('type' => 'boolean', 'default' => $defaults['showButton']),
                    'buttonText' => array('type' => 'string', 'default' => $defaults['buttonText']),
                    'buttonColor' => array('type' => 'string', 'default' => $defaults['buttonColor']),
                    'showSpecialOffer' => array('type' => 'boolean', 'default' => $defaults['showSpecialOffer']),
                    'specialOfferText' => array('type' => 'string', 'default' => ''),
                    'specialOfferColor' => array('type' => 'string', 'default' => $defaults['specialOfferColor']),
                    'multipleProductsMode' => array('type' => 'boolean', 'default' => false),
                    'productsPerRow' => array('type' => 'number', 'default' => $defaults['productsPerRow'])
                )
            )
        );
        // Registrar shortcode principal para el plugin
        add_shortcode('amazon_producto', array($this, 'shortcode_amazon_producto'));
        // Registrar shortcode para testing
        add_shortcode('cosas_amazon_test', array($this, 'shortcode_test_product'));
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'cosas-amazon-block-style',
            COSAS_AMAZON_PLUGIN_URL . 'assets/css/style.css',
            array(),
            COSAS_AMAZON_VERSION
        );
        wp_enqueue_script(
            'cosas-amazon-carousel',
            COSAS_AMAZON_PLUGIN_URL . 'assets/js/carousel.js',
            array(),
            COSAS_AMAZON_VERSION,
            true
        );
    }

    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'cosas-amazon-block-editor',
            COSAS_AMAZON_PLUGIN_URL . 'assets/js/block.js',
            array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch'),
            COSAS_AMAZON_VERSION,
            true
        );
        $plugin_options = get_option('cosas_amazon_options', array());
        wp_localize_script('cosas-amazon-block-editor', 'cosasAmazonAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cosas_amazon_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('cda/v1/'),
            'pluginUrl' => COSAS_AMAZON_PLUGIN_URL,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'defaultConfig' => array(
                'displayStyle' => isset($plugin_options['default_display_style']) ? $plugin_options['default_display_style'] : 'horizontal',
                'showPrice' => isset($plugin_options['show_price']) ? $plugin_options['show_price'] : true,
                'showDiscount' => isset($plugin_options['show_discount']) ? $plugin_options['show_discount'] : true,
                'showDescription' => isset($plugin_options['show_description']) ? $plugin_options['show_description'] : true,
                'descriptionLength' => isset($plugin_options['description_length']) ? $plugin_options['description_length'] : 150,
                'color' => isset($plugin_options['primary_color']) ? $plugin_options['primary_color'] : '#000000',
                'fontSize' => isset($plugin_options['base_font_size']) ? $plugin_options['base_font_size'] . 'px' : '16px',
                'borderColor' => isset($plugin_options['border_color']) ? $plugin_options['border_color'] : '#cccccc',
                'backgroundColor' => isset($plugin_options['background_color']) ? $plugin_options['background_color'] : '#ffffff',
                'buttonText' => isset($plugin_options['button_text']) ? $plugin_options['button_text'] : 'Ver en Amazon',
                'buttonColor' => isset($plugin_options['button_color']) ? $plugin_options['button_color'] : '#FF9900',
                'specialOfferColor' => isset($plugin_options['accent_color']) ? $plugin_options['accent_color'] : '#e74c3c',
                'showRatings' => get_theme_mod('cosas_amazon_show_ratings', true),
            )
        ));
        wp_enqueue_style(
            'cosas-amazon-block-editor-style',
            COSAS_AMAZON_PLUGIN_URL . 'assets/css/editor.css',
            array('wp-edit-blocks'),
            COSAS_AMAZON_VERSION
        );
    }

    /**
     * Función para renderizar el bloque con aplicación inteligente de configuración por defecto
     */
    public function render_amazon_product_block($attributes) {
        // Obtener configuración global del plugin
        $global_config = $this->get_default_attributes();
        
        // Aplicar configuración global solo a atributos que no han sido modificados por el usuario
        $merged_attributes = $this->merge_attributes_with_defaults($attributes, $global_config);
        
        // Extraer datos para el renderizado
        $amazon_url = isset($merged_attributes['amazonUrl']) ? $merged_attributes['amazonUrl'] : '';
        $amazon_urls = isset($merged_attributes['amazonUrls']) ? $merged_attributes['amazonUrls'] : array();
        $display_style = $merged_attributes['displayStyle'];
        $block_size = $merged_attributes['blockSize'];
        $product_data = isset($merged_attributes['productData']) ? $merged_attributes['productData'] : array();
        $products_data = isset($merged_attributes['productsData']) ? $merged_attributes['productsData'] : array();
        $multiple_products_mode = isset($merged_attributes['multipleProductsMode']) ? $merged_attributes['multipleProductsMode'] : false;
        
        // NUEVA LÓGICA: Obtener datos del producto automáticamente si no están disponibles
        if (empty($product_data) && !empty($amazon_url)) {
            // Intentar obtener datos del producto usando la función helper
            if (function_exists('cosas_amazon_get_product_data')) {
                $product_data = cosas_amazon_get_product_data($amazon_url);
                
                // Si se obtuvieron datos, actualizar los atributos
                if (!empty($product_data)) {
                    $merged_attributes['productData'] = $product_data;
                }
            }
        }
        
        // Renderizar según el modo
        if ($multiple_products_mode && !empty($products_data)) {
            return $this->render_multiple_products($products_data, $merged_attributes);
        } else {
            return $this->render_single_product($product_data, $amazon_url, $merged_attributes);
        }
    }
    
    /**
     * Fusionar atributos del bloque con configuración por defecto de forma inteligente
     */
    private function merge_attributes_with_defaults($block_attributes, $global_defaults) {
        $merged = array();
        
        // Lista de atributos que deben aplicar configuración global si no se han modificado
        $configurable_attributes = array(
            'displayStyle', 'blockSize', 'showPrice', 'showDiscount', 'showDescription',
            'descriptionLength', 'color', 'fontSize', 'borderStyle', 'borderColor',
            'backgroundColor', 'alignment', 'showButton', 'buttonText', 'buttonColor',
            'showSpecialOffer', 'specialOfferColor', 'productsPerRow'
        );
        
        foreach ($configurable_attributes as $attr) {
            if (isset($block_attributes[$attr]) && $this->attribute_has_been_modified($block_attributes, $attr)) {
                // El usuario ha modificado este atributo, usar el valor del bloque
                $merged[$attr] = $block_attributes[$attr];
            } else {
                // Usar configuración global por defecto
                $merged[$attr] = isset($global_defaults[$attr]) ? $global_defaults[$attr] : $block_attributes[$attr];
            }
        }
        
        // Mantener atributos que no son configurables globalmente
        $non_configurable = array('amazonUrl', 'amazonUrls', 'productData', 'productsData', 'multipleProductsMode', 'specialOfferText');
        foreach ($non_configurable as $attr) {
            if (isset($block_attributes[$attr])) {
                $merged[$attr] = $block_attributes[$attr];
            }
        }
        
        return $merged;
    }
    
    /**
     * Determinar si un atributo ha sido modificado por el usuario
     * Compara con los valores por defecto actuales del plugin
     */
    private function attribute_has_been_modified($attributes, $attribute_name) {
        if (!isset($attributes[$attribute_name])) {
            return false;
        }
        
        // Obtener los valores por defecto actuales del plugin
        $current_defaults = $this->get_default_attributes();
        
        // Si no hay valor por defecto definido, considerar que no ha sido modificado
        if (!isset($current_defaults[$attribute_name])) {
            return false;
        }
        
        // Comparar el valor del atributo con el valor por defecto actual
        return $attributes[$attribute_name] !== $current_defaults[$attribute_name];
    }
    
    /**
     * Renderizar un solo producto
     */
    private function render_single_product($product_data, $amazon_url, $attributes) {
        // Normalizar product_data - puede venir como array u objeto
        $product_data = $this->normalize_product_data($product_data);
        
        if (empty($product_data) || !is_array($product_data)) {
            return '<div class="cosas-amazon-error">No hay datos del producto disponibles.</div>';
        }
        
        // Estilos del wrapper con alineación (sincronizar con JS)
        $wrapper_styles = 'display: flex; width: 100%; ';
        switch ($attributes['alignment']) {
            case 'left':
                $wrapper_styles .= 'justify-content: flex-start;';
                break;
            case 'right':
                $wrapper_styles .= 'justify-content: flex-end;';
                break;
            case 'center':
            default:
                $wrapper_styles .= 'justify-content: center;';
                break;
        }
        
        // Generar HTML usando los atributos fusionados
        $alignment_class = 'cosas-amazon-align-' . esc_attr($attributes['alignment']);
        
        // Crear el wrapper con alineación
        $html = '<div style="' . esc_attr($wrapper_styles) . '">';
        
        // Crear el contenedor del producto
        $html .= '<div class="cosas-amazon-product cosas-amazon-' . esc_attr($attributes['displayStyle']) . ' cosas-amazon-size-' . esc_attr($attributes['blockSize']) . ' ' . $alignment_class . '" style="';
        if ($attributes['borderStyle'] !== 'none') {
            $html .= 'border: 1px ' . esc_attr($attributes['borderStyle']) . ' ' . esc_attr($attributes['borderColor']) . '; ';
        }
        $html .= 'background-color: ' . esc_attr($attributes['backgroundColor']) . '; ';
        $html .= 'color: ' . esc_attr($attributes['color']) . '; ';
        $html .= 'font-size: ' . esc_attr($attributes['fontSize']) . '; ';
        $html .= 'text-align: ' . esc_attr($attributes['alignment']) . ' !important;';
        $html .= '">';
        
        // Estructura según el estilo
        if ($attributes['displayStyle'] === 'horizontal') {
            $html .= '<div class="cosas-amazon-image">';
            if (!empty($product_data['image'])) {
                $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
            }
            $html .= '</div>';
            
            $html .= '<div class="cosas-amazon-content">';
            // Añadir etiqueta de oferta especial al inicio del contenido
            $html .= $this->render_special_offer_tag($product_data, $attributes);
            $html .= $this->render_product_content($product_data, $attributes, $amazon_url);
            $html .= '</div>';
            
        } else {
            // Vertical y otros estilos
            if (!empty($product_data['image'])) {
                $html .= '<div class="cosas-amazon-image">';
                $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
                $html .= '</div>';
            }
            
            // Añadir etiqueta de oferta especial entre imagen y título
            $html .= $this->render_special_offer_tag($product_data, $attributes);
            $html .= $this->render_product_content($product_data, $attributes, $amazon_url);
        }
        
        $html .= '</div>'; // Cerrar contenedor del producto
        $html .= '</div>'; // Cerrar wrapper con alineación
        
        return $html;
    }
    
    /**
     * Renderizar el contenido del producto (sin imagen)
     */
    private function render_product_content($product_data, $attributes, $amazon_url) {
        $html = '';
        
        // Título del producto
        if (!empty($product_data['title'])) {
            $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
        }
        
        // Valoraciones del producto
        if (CosasAmazonHelpers::are_ratings_enabled() && (!empty($product_data['rating']) || !empty($product_data['reviewCount']))) {
            $html .= '<div class="cosas-amazon-rating">';
            
            // Estrellas
            if (!empty($product_data['rating'])) {
                $html .= CosasAmazonHelpers::generate_rating_stars($product_data['rating']);
                $html .= '<span class="cosas-amazon-rating-number">' . esc_html($product_data['rating']) . '</span>';
            }
            
            // Número de reseñas
            if (!empty($product_data['reviewCount'])) {
                $html .= '<span class="cosas-amazon-review-count">' . CosasAmazonHelpers::format_review_count($product_data['reviewCount']) . '</span>';
            }
            
            $html .= '</div>';
        }
        
        // Precio del producto
        if ($attributes['showPrice'] && !empty($product_data['price'])) {
            $html .= '<div class="cosas-amazon-pricing">';
            
            // Precio actual
            $html .= '<span class="cosas-amazon-price">' . esc_html($product_data['price']) . '</span>';
            
            // Precio original si existe
            if (!empty($product_data['originalPrice'])) {
                $html .= '<span class="cosas-amazon-original-price">' . esc_html($product_data['originalPrice']) . '</span>';
            }
            
            // Descuento si existe
            if ($attributes['showDiscount'] && !empty($product_data['discount'])) {
                $html .= '<span class="cosas-amazon-discount">-' . esc_html($product_data['discount']) . '%</span>';
            }
            
            $html .= '</div>';
        }
        
        // Descripción del producto
        if ($attributes['showDescription'] && !empty($product_data['description'])) {
            $description = $product_data['description'];
            if (strlen($description) > $attributes['descriptionLength']) {
                $description = substr($description, 0, $attributes['descriptionLength']) . '...';
            }
            $html .= '<div class="cosas-amazon-description">';
            $html .= '<p>' . esc_html($description) . '</p>';
            $html .= '</div>';
        }

        // Botón de compra
        if ($attributes['showButton'] && !empty($amazon_url)) {
            $html .= '<div class="cosas-amazon-button">';
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
            $html .= esc_html($attributes['buttonText']);
            $html .= '</a>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Renderizar múltiples productos
     */
    private function render_multiple_products($products_data, $attributes) {
        if (empty($products_data) || !is_array($products_data)) {
            return '<div class="cosas-amazon-error">No hay datos de productos disponibles.</div>';
        }
        
        $products_per_row = $attributes['productsPerRow'];
        
        $html = '<div class="cosas-amazon-products-grid" style="display: grid; grid-template-columns: repeat(' . $products_per_row . ', 1fr); gap: 20px; margin: 20px 0;">';
        
        foreach ($products_data as $index => $product_data) {
            // Normalizar los datos del producto
            $product_data = $this->normalize_product_data($product_data);
            
            if (!empty($product_data) && is_array($product_data)) {
                // Construir el array de URLs igual que en JavaScript: [amazonUrl, ...amazonUrls]
                $amazon_url = '';
                if ($index === 0) {
                    // Primer producto usa amazonUrl
                    $amazon_url = isset($attributes['amazonUrl']) ? $attributes['amazonUrl'] : '';
                } else {
                    // Productos adicionales usan amazonUrls[index-1]
                    $amazon_urls = isset($attributes['amazonUrls']) ? $attributes['amazonUrls'] : array();
                    $amazon_url = isset($amazon_urls[$index - 1]) ? $amazon_urls[$index - 1] : '';
                }
                
                // Debug logging para ayudar con el desarrollo
                if (function_exists('cosas_amazon_log')) {
                    cosas_amazon_log("Renderizando producto $index con URL: $amazon_url");
                }
                
                $html .= $this->render_single_product($product_data, $amazon_url, $attributes);
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Normalizar datos del producto - convierte objetos y JSON a arrays
     */
    private function normalize_product_data($product_data) {
        // Si es un string JSON, decodificar
        if (is_string($product_data)) {
            $decoded = json_decode($product_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            return null;
        }
        
        // Si es un objeto, convertir a array
        if (is_object($product_data)) {
            return (array) $product_data;
        }
        
        // Si ya es array, devolverlo tal como está
        if (is_array($product_data)) {
            return $product_data;
        }
        
        return null;
    }
    
    /**
     * Generar HTML para etiqueta de oferta especial
     */
    private function render_special_offer_tag($product_data, $attributes) {
        if (!$attributes['showSpecialOffer']) {
            return '';
        }
        
        $special_offer_text = '';
        
        // Prioridad: specialOfferText > product_data['specialOffer'] > descuento > texto por defecto
        if (!empty($attributes['specialOfferText'])) {
            $special_offer_text = $attributes['specialOfferText'];
        } elseif (!empty($product_data['specialOffer'])) {
            $special_offer_text = $product_data['specialOffer'];
        } elseif (!empty($product_data['discount'])) {
            $special_offer_text = 'Oferta ' . $product_data['discount'] . '%';
        } else {
            $special_offer_text = 'Oferta';
        }
        
        if (!empty($special_offer_text)) {
            return '<div class="cosas-amazon-special-offer">' .
                   '<span style="background-color: ' . esc_attr($attributes['specialOfferColor']) . ';">' . esc_html($special_offer_text) . '</span>' .
                   '</div>';
        }
        
        return '';
    }
}
