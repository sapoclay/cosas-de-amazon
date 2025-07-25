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
        // Obtener versión de assets para cache busting
        $asset_version = get_option('cosas_amazon_asset_version', COSAS_AMAZON_VERSION);
        
        // En producción, usar versión con timestamp para evitar cache
        $production_config = get_option('cosas_amazon_production_config', []);
        if (!empty($production_config['production_mode']) || !WP_DEBUG) {
            $asset_version = COSAS_AMAZON_VERSION . '-' . time();
        }
        
        wp_enqueue_style(
            'cosas-amazon-block-style',
            COSAS_AMAZON_PLUGIN_URL . 'assets/css/style.css',
            array(),
            $asset_version
        );
        
        wp_enqueue_script(
            'cosas-amazon-frontend',
            COSAS_AMAZON_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            $asset_version,
            true
        );
        
        wp_enqueue_script(
            'cosas-amazon-carousel',
            COSAS_AMAZON_PLUGIN_URL . 'assets/js/carousel.js',
            array('jquery'),
            $asset_version,
            true
        );
        
        // Pasar configuración al JavaScript
        wp_localize_script('cosas-amazon-frontend', 'cosasAmazonConfig', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cosas_amazon_nonce'),
            'trackClicks' => get_option('cosas_amazon_options')['track_clicks'] ?? true,
            'forceButtons' => !empty($production_config['force_button_display'])
        ));
    }

    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'cosas-amazon-block-editor',
            COSAS_AMAZON_PLUGIN_URL . 'assets/js/block.js',
            array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch'),
            COSAS_AMAZON_VERSION . '-' . time(), // Forzar recarga JS también
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
        
        // Cargar CSS del editor con alta prioridad
        wp_enqueue_style(
            'cosas-amazon-block-editor-style',
            COSAS_AMAZON_PLUGIN_URL . 'assets/css/editor.css',
            array('wp-edit-blocks', 'wp-block-editor', 'wp-block-library'),
            COSAS_AMAZON_VERSION . '-' . time(), // Forzar recarga con timestamp
            'all'
        );
        
        // Inyectar CSS personalizado del usuario en el editor
        $this->inject_custom_css_in_editor();
    }
    
    /**
     * Función para inyectar CSS personalizado en el editor de bloques
     */
    private function inject_custom_css_in_editor() {
        $custom_css = get_option('cosas_amazon_custom_css', '');
        if (!empty($custom_css)) {
            wp_add_inline_style(
                'cosas-amazon-block-editor-style',
                "/* CSS Personalizado del Usuario en Editor */\n" . $custom_css
            );
        }
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
        $products_per_row = isset($merged_attributes['productsPerRow']) ? $merged_attributes['productsPerRow'] : 2;
        
        // LIMITACIONES PROGRESIVAS PARA ESTILOS HORIZONTAL, COMPACTA, VERTICAL Y MINIMAL
        if ($display_style === 'horizontal' || $display_style === 'compact' || $display_style === 'vertical' || $display_style === 'minimal') {
            // Determinar límites según el estilo y tamaño
            if ($display_style === 'horizontal') {
                switch($block_size) {
                    case 'xlarge':
                    case 'large':
                        $max_products = 2;
                        $max_urls = 1; // 1 principal + 1 adicional = 2 total
                        break;
                    case 'medium':
                        $max_products = 3;
                        $max_urls = 2; // 1 principal + 2 adicionales = 3 total
                        break;
                    case 'small':
                        $max_products = 4;
                        $max_urls = 3; // 1 principal + 3 adicionales = 4 total
                        break;
                    default:
                        $max_products = 4; // Nunca 5 para horizontal
                        $max_urls = 3;
                        break;
                }
            } elseif ($display_style === 'compact' || $display_style === 'vertical') {
                // COMPACTA Y VERTICAL tienen limitaciones progresivas
                switch($block_size) {
                    case 'xlarge':
                    case 'large':
                        $max_products = 2;
                        $max_urls = 1; // 1 principal + 1 adicional = 2 total
                        break;
                    case 'medium':
                    case 'small':
                        $max_products = 3;
                        $max_urls = 2; // 1 principal + 2 adicionales = 3 total
                        break;
                    default:
                        $max_products = 3; // Máximo 3 para compacta/vertical por defecto
                        $max_urls = 2;
                        break;
                }
            } elseif ($display_style === 'minimal') {
                // MINIMAL tiene limitaciones específicas: siempre 3 productos máximo
                switch($block_size) {
                    case 'xlarge':
                    case 'large':
                        $max_products = 3; // Para minimal: xlarge/large = 3 productos máximo
                        $max_urls = 2; // 1 principal + 2 adicionales = 3 total
                        break;
                    case 'medium':
                    case 'small':
                        $max_products = 3; // Para minimal: medium/small = 3 productos máximo
                        $max_urls = 2; // 1 principal + 2 adicionales = 3 total
                        break;
                    default:
                        $max_products = 3; // Máximo 3 para minimal siempre
                        $max_urls = 2;
                        break;
                }
            }
            
            // Limitar URLs adicionales según el tamaño
            if (is_array($amazon_urls) && count($amazon_urls) > $max_urls) {
                $amazon_urls = array_slice($amazon_urls, 0, $max_urls);
                $merged_attributes['amazonUrls'] = $amazon_urls;
                error_log(strtoupper($block_size) . ' ' . strtoupper($display_style) . ': Limitando URLs adicionales a ' . $max_urls . ' máximo (' . ($max_urls + 1) . ' productos total)');
            }
            
            // Limitar productos por fila según el tamaño
            if ($products_per_row > $max_products) {
                $products_per_row = $max_products;
                $merged_attributes['productsPerRow'] = $max_products;
                error_log(strtoupper($block_size) . ' ' . strtoupper($display_style) . ': Limitando productos por fila a ' . $max_products . ' máximo');
            }
        }
        
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
        if ($display_style === 'carousel') {
            // Para carousel, siempre renderizar como carousel independientemente del número de productos
            $urls_array = array();
            if (!empty($amazon_url)) {
                $urls_array[] = $amazon_url;
            }
            if (!empty($amazon_urls)) {
                $urls_array = array_merge($urls_array, $amazon_urls);
            }
            
            // Si no hay datos de productos, intentar obtenerlos
            if (empty($products_data)) {
                $products_data = array();
                foreach ($urls_array as $url) {
                    if (function_exists('cosas_amazon_get_product_data')) {
                        $product_data_single = cosas_amazon_get_product_data($url);
                        if (!empty($product_data_single)) {
                            $products_data[] = $product_data_single;
                        }
                    }
                }
            }
            
            // Renderizar siempre como carousel
            return $this->render_carousel($urls_array, $products_data, $merged_attributes);
        } elseif ($display_style === 'table') {
            // Para tabla, siempre usar múltiples productos
            $urls_array = array();
            if (!empty($amazon_url)) {
                $urls_array[] = $amazon_url;
            }
            if (!empty($amazon_urls)) {
                $urls_array = array_merge($urls_array, $amazon_urls);
            }
            
            // Si no hay datos de productos, intentar obtenerlos
            if (empty($products_data)) {
                $products_data = array();
                foreach ($urls_array as $url) {
                    if (function_exists('cosas_amazon_get_product_data')) {
                        $product_data_single = cosas_amazon_get_product_data($url);
                        if (!empty($product_data_single)) {
                            $products_data[] = $product_data_single;
                        }
                    }
                }
            }
            
            // Renderizar como tabla
            return $this->render_table($urls_array, $products_data, $merged_attributes);
        } elseif ($multiple_products_mode && !empty($products_data)) {
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
        
        // Verificar configuración de producción
        $production_config = get_option('cosas_amazon_production_config', []);
        $force_button_display = !empty($production_config['force_button_display']);
        
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
            
            // CONFIGURACIÓN ESPECÍFICA PARA PRODUCCIÓN: Forzar botón si está habilitado
            if ($attr === 'showButton' && $force_button_display) {
                $merged[$attr] = true;
            }
            if ($attr === 'buttonText' && ($force_button_display || empty($merged[$attr]))) {
                $merged[$attr] = isset($global_defaults[$attr]) ? $global_defaults[$attr] : 'Ver en Amazon';
            }
        }
        
        // GARANTÍA DE BOTÓN EN PRODUCCIÓN: Asegurar que showButton esté siempre true en producción
        $options = get_option('cosas_amazon_options', []);
        $default_show_button = isset($options['show_button_by_default']) ? $options['show_button_by_default'] : true;
        
        // Si no está definido o está vacío, forzar a true
        if (!isset($merged['showButton']) || $merged['showButton'] === false || $merged['showButton'] === null) {
            $merged['showButton'] = $default_show_button;
        }
        
        // Si no hay texto de botón, asegurar que tenga uno
        if (!isset($merged['buttonText']) || empty($merged['buttonText'])) {
            $merged['buttonText'] = isset($options['default_button_text']) ? $options['default_button_text'] : 'Ver en Amazon';
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
            // Para horizontal + small/medium/large/xlarge, usar estructura especial con contenedor de contenido
            if (in_array($attributes['blockSize'], array('small', 'medium', 'large', 'xlarge'))) {
                // Imagen del producto (columna izquierda)
                $html .= '<div class="cosas-amazon-image">';
                if (!empty($product_data['image'])) {
                    $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
                }
                $html .= '</div>';
                
                // Contenedor de contenido (columna derecha)
                $html .= '<div class="cosas-amazon-content">';
                $html .= $this->render_product_content($product_data, $attributes, $amazon_url);
                $html .= '</div>';
            } else {
                // Estructura original para otros tamaños horizontales
                $html .= '<div class="cosas-amazon-image">';
                if (!empty($product_data['image'])) {
                    $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
                }
                $html .= '</div>';
                
                $html .= '<div class="cosas-amazon-content">';
                // Añadir etiqueta de oferta especial al inicio del contenido
                $html .= $this->render_special_offer_tag($product_data, $attributes);
                $html .= $this->render_product_content($product_data, $attributes, $amazon_url, false);
                $html .= '</div>';
            }
            
        } elseif ($attributes['displayStyle'] === 'compact') {
            // Para estilo compacto: título arriba, luego main-content con imagen a la izquierda y contenido a la derecha
            
            // Título del producto en la parte superior
            if (!empty($product_data['title'])) {
                $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
            }
            
            // Contenedor principal con imagen a la izquierda y contenido a la derecha
            $html .= '<div class="cosas-amazon-main-content">';
            
            // Imagen a la izquierda
            $html .= '<div class="cosas-amazon-image">';
            if (!empty($product_data['image'])) {
                $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
            }
            $html .= '</div>';
            
            // Contenido a la derecha
            $html .= '<div class="cosas-amazon-content">';
            
            // Añadir etiqueta de oferta especial
            $html .= $this->render_special_offer_tag($product_data, $attributes);
            
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
                
                // Descuento si existe
                if ($attributes['showDiscount'] && !empty($product_data['discount'])) {
                    $html .= '<span class="cosas-amazon-discount">-' . esc_html($product_data['discount']) . '%</span>';
                }
                
                // Precio actual
                $html .= '<span class="cosas-amazon-price">' . esc_html($product_data['price']) . '</span>';
                
                // Precio original si existe
                if (!empty($product_data['originalPrice'])) {
                    $html .= '<span class="cosas-amazon-original-price">' . esc_html($product_data['originalPrice']) . '</span>';
                }
                
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
            
            $html .= '</div>'; // Cerrar contenido
            $html .= '</div>'; // Cerrar main-content
            
        } elseif ($attributes['displayStyle'] === 'featured') {
            // Para estilo destacado: imagen arriba, etiqueta, título, rating derecha, descripción centro, precios centro, botón centro
            
            // Contenedor para agrupar elementos excepto el botón
            $html .= '<div class="cosas-amazon-content-wrapper">';
            
            // Imagen en la parte superior
            if (!empty($product_data['image'])) {
                $html .= '<div class="cosas-amazon-image">';
                $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
                $html .= '</div>';
            }
            
            // Etiqueta de oferta especial debajo de la imagen
            $html .= $this->render_special_offer_tag($product_data, $attributes);
            
            // Título del producto centrado
            if (!empty($product_data['title'])) {
                $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
            }
            
            // Rating alineado a la derecha
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
            
            // Descripción centrada
            if ($attributes['showDescription'] && !empty($product_data['description'])) {
                $description = $product_data['description'];
                if (strlen($description) > $attributes['descriptionLength']) {
                    $description = substr($description, 0, $attributes['descriptionLength']) . '...';
                }
                $html .= '<div class="cosas-amazon-description">';
                $html .= '<p>' . esc_html($description) . '</p>';
                $html .= '</div>';
            }
            
            // Precios centrados
            if ($attributes['showPrice'] && !empty($product_data['price'])) {
                $html .= '<div class="cosas-amazon-pricing">';
                
                // Descuento si existe
                if ($attributes['showDiscount'] && !empty($product_data['discount'])) {
                    $html .= '<span class="cosas-amazon-discount">-' . esc_html($product_data['discount']) . '%</span>';
                }
                
                // Precio actual
                $html .= '<span class="cosas-amazon-price">' . esc_html($product_data['price']) . '</span>';
                
                // Precio original si existe
                if (!empty($product_data['originalPrice'])) {
                    $html .= '<span class="cosas-amazon-original-price">' . esc_html($product_data['originalPrice']) . '</span>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>'; // Cerrar content-wrapper
            
            // Botón centrado en la parte inferior
            if ($attributes['showButton'] && !empty($amazon_url)) {
                $html .= '<div class="cosas-amazon-button">';
                $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
                $html .= esc_html($attributes['buttonText']);
                $html .= '</a>';
                $html .= '</div>';
            }
            
        } elseif ($attributes['displayStyle'] === 'minimal') {
            // Para estilo minimal: título arriba, imagen izquierda, precio, descuento/precio anterior, etiqueta, botón abajo
            
            // Título del producto en la parte superior
            if (!empty($product_data['title'])) {
                $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
            }
            
            // Contenedor principal con imagen a la izquierda
            $html .= '<div class="cosas-amazon-main-content">';
            
            // Imagen a la izquierda
            if (!empty($product_data['image'])) {
                $html .= '<div class="cosas-amazon-image">';
                $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
                $html .= '</div>';
            }
            
            // Contenido a la derecha
            $html .= '<div class="cosas-amazon-content">';
            
            // Precio del producto
            if ($attributes['showPrice'] && !empty($product_data['price'])) {
                $html .= '<div class="cosas-amazon-price">' . esc_html($product_data['price']) . '</div>';
            }
            
            // Línea de descuento y precio anterior
            if (($attributes['showDiscount'] && !empty($product_data['discount'])) || !empty($product_data['originalPrice'])) {
                $html .= '<div class="cosas-amazon-pricing-line">';
                
                // Descuento si existe
                if ($attributes['showDiscount'] && !empty($product_data['discount'])) {
                    $html .= '<span class="cosas-amazon-discount">-' . esc_html($product_data['discount']) . '%</span>';
                }
                
                // Precio original si existe
                if (!empty($product_data['originalPrice'])) {
                    $html .= '<span class="cosas-amazon-original-price">' . esc_html($product_data['originalPrice']) . '</span>';
                }
                
                $html .= '</div>';
            }
            
            // Etiqueta de oferta especial
            $html .= $this->render_special_offer_tag($product_data, $attributes);
            
            // Rating opcional
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
            
            // Botón en la parte inferior
            if ($attributes['showButton'] && !empty($amazon_url)) {
                $html .= '<div class="cosas-amazon-button">';
                $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
                $html .= esc_html($attributes['buttonText']);
                $html .= '</a>';
                $html .= '</div>';
            }
            
            $html .= '</div>'; // Cerrar contenido
            $html .= '</div>'; // Cerrar main-content
            
        } elseif ($attributes['displayStyle'] === 'carousel') {
            // Para carousel individual (cuando solo hay un producto)
            // Aplicar wrapper de alineación igual que otros estilos
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
            
            $alignment_class = 'cosas-amazon-align-' . esc_attr($attributes['alignment']);
            $html = '<div style="' . esc_attr($wrapper_styles) . '">';
            $html .= '<div class="cosas-amazon-carousel cosas-amazon-size-' . esc_attr($attributes['blockSize']) . ' ' . $alignment_class . '">';
            $html .= $this->render_carousel_item($product_data, $amazon_url, $attributes);
            $html .= '</div>'; // Cerrar carousel
            $html .= '</div>'; // Cerrar wrapper
            
            return $html;
            
        } else {
            // Vertical y otros estilos
            if (!empty($product_data['image'])) {
                $html .= '<div class="cosas-amazon-image">';
                $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
                $html .= '</div>';
            }
            
            // Añadir etiqueta de oferta especial entre imagen y título
            $html .= $this->render_special_offer_tag($product_data, $attributes);
            $html .= $this->render_product_content($product_data, $attributes, $amazon_url, false);
        }
        
        $html .= '</div>'; // Cerrar contenedor del producto
        $html .= '</div>'; // Cerrar wrapper con alineación
        
        return $html;
    }
    
    /**
     * Renderizar el contenido del producto (sin imagen)
     */
    private function render_product_content($product_data, $attributes, $amazon_url, $include_special_offer_tag = true) {
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
        
        // Etiqueta de oferta especial (DENTRO del contenido solo si no se ha incluido antes)
        if ($include_special_offer_tag) {
            $html .= $this->render_special_offer_tag($product_data, $attributes);
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
     * Renderizar carousel de productos
     */
    private function render_carousel($urls_array, $products_data, $attributes) {
        if (empty($urls_array)) {
            return '<div class="cosas-amazon-error">No hay URLs disponibles para el carousel.</div>';
        }
        
        // Estilos del wrapper con alineación (igual que otros estilos)
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
        
        // Crear el wrapper con alineación
        $html = '<div style="' . esc_attr($wrapper_styles) . '">';
        
        // Crear el contenedor del carousel con clase de alineación
        $alignment_class = 'cosas-amazon-align-' . esc_attr($attributes['alignment']);
        $html .= '<div class="cosas-amazon-carousel cosas-amazon-size-' . esc_attr($attributes['blockSize']) . ' ' . $alignment_class . '">';
        
        foreach ($urls_array as $index => $url) {
            $product_data = null;
            
            // Obtener datos del producto del array products_data o por índice
            if (!empty($products_data) && is_array($products_data)) {
                $product_data = isset($products_data[$index]) ? $products_data[$index] : null;
            }
            
            // Normalizar datos del producto
            $product_data = $this->normalize_product_data($product_data);
            
            if (!empty($product_data) && is_array($product_data)) {
                $html .= $this->render_carousel_item($product_data, $url, $attributes);
            } else {
                // Si no hay datos del producto, mostrar un placeholder
                $html .= $this->render_carousel_placeholder($url, $attributes);
            }
        }
        
        $html .= '</div>'; // Cerrar carousel
        $html .= '</div>'; // Cerrar wrapper
        
        return $html;
    }
    
    /**
     * Renderizar placeholder para carousel cuando no hay datos
     */
    private function render_carousel_placeholder($amazon_url, $attributes) {
        $html = '<div class="cosas-amazon-carousel-item">';
        $html .= '<div class="cosas-amazon-content">';
        
        // Imagen placeholder
        $html .= '<div class="cosas-amazon-image">';
        $html .= '<div style="width: 100%; height: 120px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666; font-size: 12px;">Sin imagen</div>';
        $html .= '</div>';
        
        // Título placeholder
        $html .= '<h3 class="cosas-amazon-title">Producto de Amazon</h3>';
        
        // Mensaje para obtener datos
        $html .= '<p style="font-size: 11px; color: #666; text-align: center; margin: 10px 0;">Use el botón "Obtener Múltiples Productos" para cargar los datos</p>';
        
        // Botón
        if ($attributes['showButton'] && !empty($amazon_url)) {
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
            $html .= esc_html($attributes['buttonText']);
            $html .= '</a>';
        }
        
        $html .= '</div>'; // Cerrar contenido
        $html .= '</div>'; // Cerrar item
        
        return $html;
    }
    
    /**
     * Renderizar un item individual del carousel
     */
    private function render_carousel_item($product_data, $amazon_url, $attributes) {
        $html = '<div class="cosas-amazon-carousel-item">';
        
        // Contenido del item
        $html .= '<div class="cosas-amazon-content">';
        
        // 1. Imagen en la parte superior
        if (!empty($product_data['image'])) {
            $html .= '<div class="cosas-amazon-image">';
            $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title']) . '" />';
            $html .= '</div>';
        }
        
        // 2. Etiqueta de oferta especial
        $html .= $this->render_special_offer_tag($product_data, $attributes);
        
        // 3. Título del producto
        if (!empty($product_data['title'])) {
            $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
        }
        
        // 4. Rating (estrellas, valoración y total de valoraciones)
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
        
        // 5. Precio del producto
        if ($attributes['showPrice'] && !empty($product_data['price'])) {
            $html .= '<div class="cosas-amazon-price">' . esc_html($product_data['price']) . '</div>';
        }
        
        // 6. Descuento (si existe)
        if ($attributes['showDiscount'] && !empty($product_data['discount'])) {
            $html .= '<div class="cosas-amazon-discount">-' . esc_html($product_data['discount']) . '%</div>';
        }
        
        // 7. Precio original (si existe)
        if (!empty($product_data['originalPrice'])) {
            $html .= '<div class="cosas-amazon-original-price">' . esc_html($product_data['originalPrice']) . '</div>';
        }
        
        // 8. Botón Ver en Amazon
        if ($attributes['showButton'] && !empty($amazon_url)) {
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
            $html .= esc_html($attributes['buttonText']);
            $html .= '</a>';
        }
        
        $html .= '</div>'; // Cerrar contenido
        $html .= '</div>'; // Cerrar item
        
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
        
        // FORZAR productsPerRow = 2 para horizontal + small/medium/large/xlarge (reglas originales)
        if (isset($attributes['displayStyle']) && $attributes['displayStyle'] === 'horizontal' && 
            isset($attributes['blockSize']) && in_array($attributes['blockSize'], ['small', 'medium', 'large', 'xlarge'])) {
            $products_per_row = 2;
        }
        
        // NUEVAS LIMITACIONES PROGRESIVAS PARA VERTICAL, COMPACTA Y MINIMAL
        if (isset($attributes['displayStyle']) && isset($attributes['blockSize'])) {
            $display_style = $attributes['displayStyle'];
            $block_size = $attributes['blockSize'];
            
            if ($display_style === 'compact' || $display_style === 'vertical') {
                // Compacta y Vertical: xlarge/large=2, medium/small=3
                switch($block_size) {
                    case 'xlarge':
                    case 'large':
                        $products_per_row = 2;
                        break;
                    case 'medium':
                    case 'small':
                        $products_per_row = 3;
                        break;
                }
            } elseif ($display_style === 'minimal') {
                // Minimal: siempre 3 productos máximo para todos los tamaños
                $products_per_row = 3;
            }
        }
        
        // Agregar clase específica para el número de columnas
        $grid_class = 'cosas-amazon-grid-' . $products_per_row . '-cols';
        
        $html = '<div class="cosas-amazon-multiple-products ' . $grid_class . '" style="display: grid !important; grid-template-columns: repeat(' . $products_per_row . ', 1fr) !important; gap: 20px !important; margin: 20px 0;">';
        
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
     * Renderizar tabla comparativa de productos
     */
    private function render_table($urls_array, $products_data, $attributes) {
        $html = '<div class="cosas-amazon-table-container cosas-amazon-size-' . esc_attr($attributes['blockSize']) . '">';
        $html .= '<table class="cosas-amazon-table">';
        
        // Encabezados de la tabla
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>Imagen</th>';
        $html .= '<th>Producto</th>';
        $html .= '<th>Valoración</th>';
        if ($attributes['showPrice']) {
            $html .= '<th>Precio</th>';
        }
        if ($attributes['showDiscount']) {
            $html .= '<th>Descuento</th>';
        }
        if ($attributes['showButton']) {
            $html .= '<th>Acción</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';
        
        // Cuerpo de la tabla
        $html .= '<tbody>';
        
        foreach ($urls_array as $index => $url) {
            $product_data = isset($products_data[$index]) ? $products_data[$index] : null;
            
            if (!empty($product_data)) {
                $html .= $this->render_table_row($product_data, $url, $attributes);
            } else {
                $html .= $this->render_table_row_placeholder($url, $attributes);
            }
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Renderizar una fila de la tabla con datos del producto
     */
    private function render_table_row($product_data, $amazon_url, $attributes) {
        $product_data = $this->normalize_product_data($product_data);
        
        if (!$product_data) {
            return $this->render_table_row_placeholder($amazon_url, $attributes);
        }
        
        $html = '<tr>';
        
        // Imagen
        $html .= '<td class="cosas-amazon-table-image">';
        if (!empty($product_data['image'])) {
            $html .= '<img src="' . esc_url($product_data['image']) . '" alt="' . esc_attr($product_data['title'] ?? 'Producto de Amazon') . '">';
        } else {
            $html .= '<div class="cosas-amazon-no-image">Sin imagen</div>';
        }
        $html .= '</td>';
        
        // Título del producto
        $html .= '<td class="cosas-amazon-table-title">';
        if (!empty($product_data['title'])) {
            $html .= '<h4>' . esc_html($product_data['title']) . '</h4>';
        } else {
            $html .= '<h4>Producto de Amazon</h4>';
        }
        
        // Descripción si está habilitada
        if ($attributes['showDescription'] && !empty($product_data['description'])) {
            $html .= '<p class="cosas-amazon-table-description">' . esc_html(wp_trim_words($product_data['description'], 15)) . '</p>';
        }
        $html .= '</td>';
        
        // Valoración
        $html .= '<td class="cosas-amazon-table-rating">';
        if (!empty($product_data['rating'])) {
            $html .= '<div class="cosas-amazon-rating">';
            $html .= CosasAmazonHelpers::generate_rating_stars($product_data['rating']);
            $html .= '<span class="cosas-amazon-rating-number">' . esc_html($product_data['rating']) . '</span>';
            if (!empty($product_data['review_count'])) {
                $html .= '<span class="cosas-amazon-review-count">(' . esc_html($product_data['review_count']) . ')</span>';
            }
            $html .= '</div>';
        } else {
            $html .= '<span class="cosas-amazon-no-rating">Sin valoración</span>';
        }
        $html .= '</td>';
        
        // Precio
        if ($attributes['showPrice']) {
            $html .= '<td class="cosas-amazon-table-price">';
            if (!empty($product_data['price'])) {
                $html .= '<span class="cosas-amazon-price">' . esc_html($product_data['price']) . '</span>';
            } else {
                $html .= '<span class="cosas-amazon-no-price">N/A</span>';
            }
            $html .= '</td>';
        }
        
        // Descuento
        if ($attributes['showDiscount']) {
            $html .= '<td class="cosas-amazon-table-discount">';
            if (!empty($product_data['discount']) && $product_data['discount'] > 0) {
                $html .= '<span class="cosas-amazon-discount">-' . esc_html($product_data['discount']) . '%</span>';
                if (!empty($product_data['original_price'])) {
                    $html .= '<span class="cosas-amazon-original-price">' . esc_html($product_data['original_price']) . '</span>';
                }
            } else {
                $html .= '<span class="cosas-amazon-no-discount">Sin descuento</span>';
            }
            $html .= '</td>';
        }
        
        // Botón
        if ($attributes['showButton']) {
            $html .= '<td class="cosas-amazon-table-button">';
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="noopener noreferrer" class="cosas-amazon-btn">';
            $html .= esc_html($attributes['buttonText'] ?? 'Ver en Amazon');
            $html .= '</a>';
            $html .= '</td>';
        }
        
        $html .= '</tr>';
        
        return $html;
    }
    
    /**
     * Renderizar una fila de la tabla como placeholder
     */
    private function render_table_row_placeholder($amazon_url, $attributes) {
        $html = '<tr class="cosas-amazon-table-placeholder">';
        
        // Imagen placeholder
        $html .= '<td class="cosas-amazon-table-image">';
        $html .= '<div class="cosas-amazon-placeholder-image">📦</div>';
        $html .= '</td>';
        
        // Título placeholder
        $html .= '<td class="cosas-amazon-table-title">';
        $html .= '<h4>Producto de Amazon</h4>';
        $html .= '<p class="cosas-amazon-loading">Cargando datos...</p>';
        $html .= '</td>';
        
        // Valoración placeholder
        $html .= '<td class="cosas-amazon-table-rating">';
        $html .= '<span class="cosas-amazon-loading">Cargando...</span>';
        $html .= '</td>';
        
        // Precio placeholder
        if ($attributes['showPrice']) {
            $html .= '<td class="cosas-amazon-table-price">';
            $html .= '<span class="cosas-amazon-loading">Cargando...</span>';
            $html .= '</td>';
        }
        
        // Descuento placeholder
        if ($attributes['showDiscount']) {
            $html .= '<td class="cosas-amazon-table-discount">';
            $html .= '<span class="cosas-amazon-loading">Cargando...</span>';
            $html .= '</td>';
        }
        
        // Botón
        if ($attributes['showButton']) {
            $html .= '<td class="cosas-amazon-table-button">';
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="noopener noreferrer" class="cosas-amazon-btn">';
            $html .= esc_html($attributes['buttonText'] ?? 'Ver en Amazon');
            $html .= '</a>';
            $html .= '</td>';
        }
        
        $html .= '</tr>';
        
        return $html;
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
    
    /**
     * Función para el shortcode [amazon_producto]
     */
    public function shortcode_amazon_producto($atts) {
        // Atributos por defecto del shortcode
        $attributes = shortcode_atts(array(
            'url' => '',
            'style' => 'horizontal',
            'size' => 'medium',
            'show_button' => true,
            'button_text' => 'Ver en Amazon',
            'show_price' => true,
            'show_discount' => true,
            'show_description' => true
        ), $atts, 'amazon_producto');
        
        // Convertir a formato del bloque
        $block_attributes = array(
            'amazonUrl' => $attributes['url'],
            'displayStyle' => $attributes['style'],
            'blockSize' => $attributes['size'],
            'showButton' => filter_var($attributes['show_button'], FILTER_VALIDATE_BOOLEAN),
            'buttonText' => $attributes['button_text'],
            'showPrice' => filter_var($attributes['show_price'], FILTER_VALIDATE_BOOLEAN),
            'showDiscount' => filter_var($attributes['show_discount'], FILTER_VALIDATE_BOOLEAN),
            'showDescription' => filter_var($attributes['show_description'], FILTER_VALIDATE_BOOLEAN)
        );
        
        // Usar la misma función de renderizado que el bloque
        return $this->render_amazon_product_block($block_attributes);
    }
}
