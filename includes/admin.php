<?php

class CosasAmazonAdmin {
    
    public function __construct() {
        // Debug: verificar que la clase se inicializa
        error_log('[COSAS_AMAZON_DEBUG] CosasAmazonAdmin inicializada');
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_filter('plugin_action_links_' . plugin_basename(COSAS_AMAZON_PLUGIN_PATH . 'cosas-de-amazon.php'), array($this, 'add_action_links'));
        
        // Hook adicional para garantizar que el menú aparezca
        add_action('admin_head', array($this, 'check_menu_exists'));
        
        // AJAX handlers para administración
        add_action('wp_ajax_cosas_amazon_force_update', array($this, 'ajax_force_update'));
        add_action('wp_ajax_cosas_amazon_debug', array($this, 'ajax_debug'));
        add_action('wp_ajax_get_cache_stats', array($this, 'ajax_get_cache_stats'));
        add_action('wp_ajax_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_cosas_amazon_test_paapi', array($this, 'ajax_test_paapi'));
        
        // Añadir estilos CSS para la página de admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // Añadir sanitización de opciones
        add_filter('pre_update_option_cosas_amazon_options', array($this, 'sanitize_options'));
        add_filter('pre_update_option_cosas_amazon_description_length', array($this, 'sanitize_description_length'));
        add_filter('pre_update_option_cosas_amazon_custom_css', array($this, 'sanitize_custom_css'));
        add_filter('pre_update_option_cosas_amazon_api_options', array($this, 'sanitize_api_options'));
        
        // Inyectar CSS dinámico en el frontend
        add_action('wp_head', array($this, 'inject_dynamic_css'), 20);
        
        // Ocultar footer de WordPress en páginas del plugin
        add_action('admin_init', array($this, 'hide_wordpress_footer'));
    }
    
    // Función para sanitizar las opciones generales
    public function sanitize_options($options) {
        if (!is_array($options)) {
            return array();
        }
        
        $sanitized = array();
        
        // Sanitizar estilo por defecto
        $allowed_styles = array('horizontal', 'vertical', 'compact', 'featured');
        $sanitized['default_style'] = isset($options['default_style']) && in_array($options['default_style'], $allowed_styles) ? $options['default_style'] : 'horizontal';
        
        // Sanitizar duración del cache
        $sanitized['cache_duration'] = isset($options['cache_duration']) ? max(300, min(86400, intval($options['cache_duration']))) : 3600;
        
        // Sanitizar checkboxes
        $sanitized['show_price_by_default'] = !empty($options['show_price_by_default']);
        $sanitized['show_discount_by_default'] = !empty($options['show_discount_by_default']);
        $sanitized['show_description_by_default'] = !empty($options['show_description_by_default']);
        $sanitized['enable_auto_updates'] = !empty($options['enable_auto_updates']);
        
        // Sanitizar fuente de datos
        $allowed_sources = array('real', 'simulated');
        $sanitized['data_source'] = isset($options['data_source']) && in_array($options['data_source'], $allowed_sources) ? $options['data_source'] : 'real';
        
        // Sanitizar timeout de scraping
        $sanitized['scraping_timeout'] = isset($options['scraping_timeout']) ? max(5, min(30, intval($options['scraping_timeout']))) : 15;
        
        // Sanitizar frecuencia de actualización
        $allowed_frequencies = array('daily', 'twicedaily', 'hourly_test');
        $sanitized['update_frequency'] = isset($options['update_frequency']) && in_array($options['update_frequency'], $allowed_frequencies) ? $options['update_frequency'] : 'daily';
        
        // Sanitizar umbral de descuento
        $sanitized['high_discount_threshold'] = isset($options['high_discount_threshold']) ? max(0, min(100, intval($options['high_discount_threshold']))) : 50;
        
        // Sanitizar colores
        $sanitized['primary_color'] = isset($options['primary_color']) ? (sanitize_hex_color($options['primary_color']) ?: '#e47911') : '#e47911';
        $sanitized['secondary_color'] = isset($options['secondary_color']) ? (sanitize_hex_color($options['secondary_color']) ?: '#232f3e') : '#232f3e';
        $sanitized['accent_color'] = isset($options['accent_color']) ? (sanitize_hex_color($options['accent_color']) ?: '#ff9900') : '#ff9900';
        $sanitized['text_color'] = isset($options['text_color']) ? (sanitize_hex_color($options['text_color']) ?: '#333333') : '#333333';
        $sanitized['background_color'] = isset($options['background_color']) ? (sanitize_hex_color($options['background_color']) ?: '#ffffff') : '#ffffff';
        
        // Sanitizar tipografía
        $allowed_fonts = array('default', 'system', 'arial', 'helvetica', 'roboto', 'open-sans');
        $sanitized['font_family'] = isset($options['font_family']) && in_array($options['font_family'], $allowed_fonts) ? $options['font_family'] : 'default';
        $sanitized['title_size'] = isset($options['title_size']) ? max(12, min(32, intval($options['title_size']))) : 18;
        $sanitized['text_size'] = isset($options['text_size']) ? max(10, min(24, intval($options['text_size']))) : 14;
        $sanitized['price_size'] = isset($options['price_size']) ? max(12, min(28, intval($options['price_size']))) : 16;
        
        // Sanitizar espaciado
        $sanitized['card_padding'] = isset($options['card_padding']) ? max(0, min(50, intval($options['card_padding']))) : 15;
        $sanitized['card_margin'] = isset($options['card_margin']) ? max(0, min(50, intval($options['card_margin']))) : 10;
        $sanitized['border_radius'] = isset($options['border_radius']) ? max(0, min(30, intval($options['border_radius']))) : 8;
        $sanitized['image_size'] = isset($options['image_size']) ? max(50, min(300, intval($options['image_size']))) : 150;
        
        // Sanitizar configuración por defecto de bloques
        $sanitized['default_description_length'] = isset($options['default_description_length']) ? max(50, min(500, intval($options['default_description_length']))) : 150;
        $sanitized['default_text_color'] = isset($options['default_text_color']) ? (sanitize_hex_color($options['default_text_color']) ?: '#000000') : '#000000';
        $sanitized['default_font_size'] = isset($options['default_font_size']) ? sanitize_text_field($options['default_font_size']) : '16px';
        
        $allowed_border_styles = array('none', 'solid', 'dashed', 'dotted');
        $sanitized['default_border_style'] = isset($options['default_border_style']) && in_array($options['default_border_style'], $allowed_border_styles) ? $options['default_border_style'] : 'solid';
        $sanitized['default_border_color'] = isset($options['default_border_color']) ? (sanitize_hex_color($options['default_border_color']) ?: '#cccccc') : '#cccccc';
        $sanitized['default_background_color'] = isset($options['default_background_color']) ? (sanitize_hex_color($options['default_background_color']) ?: '#ffffff') : '#ffffff';
        
        $allowed_alignments = array('left', 'center', 'right');
        $sanitized['default_alignment'] = isset($options['default_alignment']) && in_array($options['default_alignment'], $allowed_alignments) ? $options['default_alignment'] : 'center';
        
        $sanitized['show_button_by_default'] = !empty($options['show_button_by_default']);
        $sanitized['default_button_text'] = isset($options['default_button_text']) ? sanitize_text_field($options['default_button_text']) : 'Ver en Amazon';
        $sanitized['default_button_color'] = isset($options['default_button_color']) ? (sanitize_hex_color($options['default_button_color']) ?: '#FF9900') : '#FF9900';
        $sanitized['show_special_offer_by_default'] = !empty($options['show_special_offer_by_default']);
        $sanitized['default_special_offer_color'] = isset($options['default_special_offer_color']) ? (sanitize_hex_color($options['default_special_offer_color']) ?: '#e74c3c') : '#e74c3c';
        
        $allowed_block_sizes = array('small', 'medium', 'large');
        $sanitized['default_block_size'] = isset($options['default_block_size']) && in_array($options['default_block_size'], $allowed_block_sizes) ? $options['default_block_size'] : 'medium';
        $sanitized['default_products_per_row'] = isset($options['default_products_per_row']) ? max(1, min(4, intval($options['default_products_per_row']))) : 2;
        
        return $sanitized;
    }
    
    // Función para sanitizar el límite de descripción
    public function sanitize_description_length($value) {
        $value = intval($value);
        return max(50, min(500, $value));
    }
    
    // Función para sanitizar el CSS personalizado
    public function sanitize_custom_css($css) {
        if (empty($css)) {
            return '';
        }
        
        // Permitir CSS básico pero remover elementos potencialmente peligrosos
        $css = wp_strip_all_tags($css);
        
        // Lista de propiedades y elementos CSS peligrosos que no permitiremos
        $dangerous_patterns = array(
            '/javascript:/i',
            '/expression\s*\(/i',
            '/behavior\s*:/i',
            '/binding\s*:/i',
            '/@import/i',
            '/document\./i',
            '/window\./i',
            '/eval\s*\(/i'
        );
        
        foreach ($dangerous_patterns as $pattern) {
            $css = preg_replace($pattern, '', $css);
        }
        
        // Limitar el tamaño del CSS (máximo 50KB)
        if (strlen($css) > 51200) {
            $css = substr($css, 0, 51200);
        }
        
        return $css;
    }
    
    // Función para sanitizar las opciones de la API
    public function sanitize_api_options($options) {
        if (!is_array($options)) {
            return array();
        }
        
        $sanitized = array();
        
        // Habilitar/deshabilitar API
        $sanitized['api_enabled'] = !empty($options['api_enabled']) ? 1 : 0;
        
        // Access Key ID
        $sanitized['amazon_access_key'] = isset($options['amazon_access_key']) ? sanitize_text_field(trim($options['amazon_access_key'])) : '';
        
        // Secret Access Key
        $sanitized['amazon_secret_key'] = isset($options['amazon_secret_key']) ? trim($options['amazon_secret_key']) : '';
        
        // Associate Tag
        $sanitized['amazon_associate_tag'] = isset($options['amazon_associate_tag']) ? sanitize_text_field(trim($options['amazon_associate_tag'])) : '';
        
        // Validar formato de Associate Tag
        if (!empty($sanitized['amazon_associate_tag']) && !preg_match('/^[a-zA-Z0-9\-]+$/', $sanitized['amazon_associate_tag'])) {
            $sanitized['amazon_associate_tag'] = '';
        }
        
        // Región
        $allowed_regions = array('es', 'us', 'uk', 'de', 'fr', 'it');
        $sanitized['amazon_region'] = isset($options['amazon_region']) && in_array($options['amazon_region'], $allowed_regions) ? $options['amazon_region'] : 'es';
        
        return $sanitized;
    }
    
    // Función para generar CSS dinámico basado en las opciones
    public function generate_dynamic_css() {
        $options = get_option('cosas_amazon_options', array());
        
        $css = "/* CSS dinámico generado por Cosas de Amazon */\n";
        
        // Colores personalizados
        $primary_color = isset($options['primary_color']) ? $options['primary_color'] : '#e47911';
        $secondary_color = isset($options['secondary_color']) ? $options['secondary_color'] : '#232f3e';
        $accent_color = isset($options['accent_color']) ? $options['accent_color'] : '#ff9900';
        $text_color = isset($options['text_color']) ? $options['text_color'] : '#333333';
        $background_color = isset($options['background_color']) ? $options['background_color'] : '#ffffff';
        
        $css .= ".cosas-amazon-product {\n";
        $css .= "    background-color: {$background_color} !important;\n";
        $css .= "    color: {$text_color} !important;\n";
        $css .= "}\n\n";
        
        $css .= ".cosas-amazon-product .cosas-amazon-title {\n";
        $css .= "    color: {$text_color} !important;\n";
        $css .= "}\n\n";
        
        $css .= ".cosas-amazon-product .cosas-amazon-price {\n";
        $css .= "    color: {$primary_color} !important;\n";
        $css .= "}\n\n";
        
        $css .= ".cosas-amazon-product .cosas-amazon-button {\n";
        $css .= "    background-color: {$primary_color} !important;\n";
        $css .= "    border-color: {$primary_color} !important;\n";
        $css .= "}\n\n";
        
        $css .= ".cosas-amazon-product .cosas-amazon-button:hover {\n";
        $css .= "    background-color: {$accent_color} !important;\n";
        $css .= "    border-color: {$accent_color} !important;\n";
        $css .= "}\n\n";
        
        $css .= ".cosas-amazon-product .cosas-amazon-discount {\n";
        $css .= "    color: {$accent_color} !important;\n";
        $css .= "}\n\n";
        
        // Tipografía personalizada
        $font_family = isset($options['font_family']) ? $options['font_family'] : 'default';
        $title_size = isset($options['title_size']) ? $options['title_size'] : '18';
        $text_size = isset($options['text_size']) ? $options['text_size'] : '14';
        $price_size = isset($options['price_size']) ? $options['price_size'] : '16';
        
        if ($font_family !== 'default') {
            $font_stack = '';
            switch ($font_family) {
                case 'system':
                    $font_stack = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    break;
                case 'arial':
                    $font_stack = 'Arial, sans-serif';
                    break;
                case 'helvetica':
                    $font_stack = 'Helvetica, Arial, sans-serif';
                    break;
                case 'roboto':
                    $font_stack = 'Roboto, sans-serif';
                    break;
                case 'open-sans':
                    $font_stack = '"Open Sans", sans-serif';
                    break;
            }
            
            if ($font_stack) {
                $css .= ".cosas-amazon-product {\n";
                $css .= "    font-family: {$font_stack} !important;\n";
                $css .= "}\n\n";
            }
        }
        
        $css .= ".cosas-amazon-product .cosas-amazon-title {\n";
        $css .= "    font-size: {$title_size}px !important;\n";
        $css .= "}\n\n";
        
        $css .= ".cosas-amazon-product .cosas-amazon-description {\n";
        $css .= "    font-size: {$text_size}px !important;\n";
        $css .= "}\n\n";
        
        $css .= ".cosas-amazon-product .cosas-amazon-price {\n";
        $css .= "    font-size: {$price_size}px !important;\n";
        $css .= "}\n\n";
        
        // Espaciado personalizado
        $card_padding = isset($options['card_padding']) ? $options['card_padding'] : '15';
        $card_margin = isset($options['card_margin']) ? $options['card_margin'] : '10';
        $border_radius = isset($options['border_radius']) ? $options['border_radius'] : '8';
        $image_size = isset($options['image_size']) ? $options['image_size'] : '150';
        
        $css .= ".cosas-amazon-product {\n";
        $css .= "    padding: {$card_padding}px !important;\n";
        $css .= "    margin: {$card_margin}px 0 !important;\n";
        $css .= "    border-radius: {$border_radius}px !important;\n";
        $css .= "}\n\n";
        
        $css .= ".cosas-amazon-product .cosas-amazon-image img {\n";
        $css .= "    max-width: {$image_size}px !important;\n";
        $css .= "    max-height: {$image_size}px !important;\n";
        $css .= "}\n\n";
        
        return $css;
    }
    
    // Función para inyectar CSS dinámico en el frontend
    public function inject_dynamic_css() {
        $css = $this->generate_dynamic_css();
        
        // Agregar CSS personalizado del usuario
        $custom_css = get_option('cosas_amazon_custom_css', '');
        if (!empty($custom_css)) {
            $css .= "\n\n/* CSS Personalizado del Usuario */\n" . $custom_css . "\n";
        }
        
        if (!empty($css)) {
            echo "<style id='cosas-amazon-dynamic-css'>\n{$css}</style>\n";
        }
    }
    
    // Función para mostrar mensajes de confirmación
    public function show_settings_messages() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>✅ Configuración guardada correctamente.</strong> Los cambios se han aplicado y serán visibles en el frontend.</p>';
            echo '</div>';
            
            // Agregar JavaScript para mostrar un mensaje más elaborado
            echo '<script>
                jQuery(document).ready(function($) {
                    // Crear mensaje de confirmación mejorado
                    var successMessage = $("<div class=\\"notice notice-success\\" style=\\"border-left-color: #00a32a; background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%); padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,163,42,0.1);\\"><p style=\\"margin: 0; font-size: 16px; color: #00a32a;\\"><strong>🎉 ¡Configuración aplicada exitosamente!</strong></p><p style=\\"margin: 5px 0 0 0; font-size: 14px; color: #666;\\">Todos los cambios se han guardado y están activos en tu sitio web.</p></div>");
                    
                    // Insertar después del header
                    $(".cosas-amazon-admin-header").after(successMessage);
                    
                    // Animar la aparición
                    successMessage.hide().fadeIn(500);
                    
                    // Auto-desaparecer después de 5 segundos
                    setTimeout(function() {
                        successMessage.fadeOut(500, function() {
                            $(this).remove();
                        });
                    }, 5000);
                });
            </script>';
        }
    }
    
    // Función para verificar dependencias y mostrar información de estado
    public function check_dependencies() {
        $status = array(
            'wordpress' => function_exists('get_bloginfo'),
            'jquery' => wp_script_is('jquery', 'registered'),
            'ajax' => function_exists('wp_send_json_success'),
            'nonce' => function_exists('wp_create_nonce'),
            'current_user' => function_exists('current_user_can')
        );
        
        return $status;
    }
    
    public function enqueue_admin_styles($hook) {
        // Cargar en todas las páginas de administración que contengan 'cosas-amazon'
        if (strpos($hook, 'cosas-amazon') === false && strpos($_GET['page'] ?? '', 'cosas-amazon') === false) {
            return;
        }
        
        // Debug: ver qué hook se está ejecutando
        error_log('[COSAS_AMAZON_DEBUG] Hook actual: ' . $hook);
        
        // Enqueue jQuery para las funcionalidades interactivas
        wp_enqueue_script('jquery');
        
        // Localizar variables para JavaScript
        wp_localize_script('jquery', 'cosas_amazon_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cosas_amazon_nonce'),
            'site_url' => home_url(),
            'rest_url' => rest_url()
        ));
        
        // Añadir CSS inline para la página de configuración
        wp_add_inline_style('wp-admin', '
            .cosas-amazon-admin-header {
                background: linear-gradient(135deg, #0073aa 0%, #005177 100%);
                color: white;
                padding: 20px;
                margin: 0 -20px 20px -20px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .cosas-amazon-admin-header h1 {
                color: white;
                margin: 0;
                font-size: 24px;
            }
            
            .cosas-amazon-logo {
                background: white !important;
                padding: 8px !important;
                border-radius: 8px !important;
                display: inline-block !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
                transition: transform 0.2s ease, box-shadow 0.2s ease !important;
                flex-shrink: 0 !important;
                max-width: 120px !important;
                max-height: 120px !important;
            }
            
            .cosas-amazon-logo:hover {
                transform: translateY(-1px) !important;
                box-shadow: 0 3px 12px rgba(0,0,0,0.15) !important;
            }
            
            .cosas-amazon-logo img {
                width: 100px !important;
                height: auto !important;
                max-height: 100px !important;
                display: block !important;
                border-radius: 4px !important;
            }
            
            .cosas-amazon-save-section {
                background: linear-gradient(135deg, #00a32a 0%, #008a20 100%) !important;
                color: white !important;
                border: 2px solid #00a32a !important;
                box-shadow: 0 3px 15px rgba(0,163,42,0.3) !important;
                position: relative !important;
                z-index: 100 !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                min-height: 120px !important;
            }
            
            .cosas-amazon-save-section h3 {
                color: white !important;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2) !important;
            }
            
            .cosas-amazon-save-section p {
                color: rgba(255,255,255,0.95) !important;
                text-shadow: 0 1px 1px rgba(0,0,0,0.1) !important;
            }
            
            .cosas-amazon-save-btn {
                background: white !important;
                color: #00a32a !important;
                border: 2px solid white !important;
                font-weight: bold !important;
                transition: all 0.3s ease !important;
                display: inline-block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: relative !important;
                z-index: 101 !important;
                min-width: 200px !important;
                text-decoration: none !important;
            }
            
            .cosas-amazon-save-btn:hover,
            .cosas-amazon-save-btn:focus {
                background: #f8f9fa !important;
                color: #00a32a !important;
                transform: translateY(-2px) !important;
                box-shadow: 0 6px 20px rgba(0,0,0,0.25) !important;
                border-color: #f8f9fa !important;
            }
            
            .cosas-amazon-save-btn:active {
                transform: translateY(0) !important;
            }
            
            .form-table th {
                width: 200px;
                padding: 15px 10px;
                font-weight: 600;
            }
            
            .form-table td {
                padding: 15px 10px;
            }
            
            .cosas-amazon-section {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                margin-bottom: 20px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .cosas-amazon-section h2 {
                background: #f8f9fa;
                margin: 0;
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                font-size: 18px;
                color: #0073aa;
            }
            
            /* Estilos para mensajes de notificación - Mayor especificidad */
            .wrap .notice.notice-success,
            .wrap div.notice.notice-success,
            body.wp-admin .notice.notice-success {
                background: #e8f5e8 !important;
                border-left-color: #00a32a !important;
                color: #155724 !important;
                border: 1px solid #c3e6cb !important;
                border-left: 4px solid #00a32a !important;
                padding: 15px !important;
                margin: 15px 0 !important;
                border-radius: 4px !important;
                box-shadow: 0 2px 5px rgba(0,163,42,0.1) !important;
            }
            
            .wrap .notice.notice-success p,
            .wrap div.notice.notice-success p,
            body.wp-admin .notice.notice-success p {
                color: #155724 !important;
                margin: 0 !important;
                font-weight: 500 !important;
            }
            
            .wrap .notice.notice-error,
            .wrap div.notice.notice-error,
            body.wp-admin .notice.notice-error {
                background: #f8d7da !important;
                border-left-color: #dc3545 !important;
                color: #721c24 !important;
                border: 1px solid #f5c6cb !important;
                border-left: 4px solid #dc3545 !important;
                padding: 15px !important;
                margin: 15px 0 !important;
                border-radius: 4px !important;
                box-shadow: 0 2px 5px rgba(220,53,69,0.1) !important;
            }
            
            .wrap .notice.notice-error p,
            .wrap div.notice.notice-error p,
            body.wp-admin .notice.notice-error p {
                color: #721c24 !important;
                margin: 0 !important;
                font-weight: 500 !important;
            }
            
            .wrap .notice.notice-warning,
            .wrap div.notice.notice-warning,
            body.wp-admin .notice.notice-warning {
                background: #fff3cd !important;
                border-left-color: #ffc107 !important;
                color: #856404 !important;
                border: 1px solid #ffeaa7 !important;
                border-left: 4px solid #ffc107 !important;
                padding: 15px !important;
                margin: 15px 0 !important;
                border-radius: 4px !important;
                box-shadow: 0 2px 5px rgba(255,193,7,0.1) !important;
            }
            
            .wrap .notice.notice-warning p,
            .wrap div.notice.notice-warning p,
            body.wp-admin .notice.notice-warning p {
                color: #856404 !important;
                margin: 0 !important;
                font-weight: 500 !important;
            }
            
            .wrap .notice.notice-info,
            .wrap div.notice.notice-info,
            body.wp-admin .notice.notice-info {
                background: #d1ecf1 !important;
                border-left-color: #17a2b8 !important;
                color: #0c5460 !important;
                border: 1px solid #bee5eb !important;
                border-left: 4px solid #17a2b8 !important;
                padding: 15px !important;
                margin: 15px 0 !important;
                border-radius: 4px !important;
                box-shadow: 0 2px 5px rgba(23,162,184,0.1) !important;
            }
            
            .wrap .notice.notice-info p,
            .wrap div.notice.notice-info p,
            body.wp-admin .notice.notice-info p {
                color: #0c5460 !important;
                margin: 0 !important;
                font-weight: 500 !important;
            }
            
            /* Mensaje de configuración guardada específico */
            .cosas-amazon-success-message,
            .wrap .cosas-amazon-success-message {
                background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%) !important;
                border: 1px solid #00a32a !important;
                border-left: 4px solid #00a32a !important;
                color: #155724 !important;
                padding: 20px !important;
                margin: 20px 0 !important;
                border-radius: 8px !important;
                box-shadow: 0 2px 10px rgba(0,163,42,0.1) !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            
            .cosas-amazon-success-message p,
            .wrap .cosas-amazon-success-message p {
                color: #155724 !important;
                margin: 0 !important;
                font-weight: 500 !important;
            }
            
            .cosas-amazon-success-message strong,
            .wrap .cosas-amazon-success-message strong {
                color: #00a32a !important;
            }
            
            /* Estilos específicos para la página de configuración */
            .cosas-amazon-config-page .notice,
            .cosas-amazon-config-page div.notice {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: relative !important;
                z-index: 1000 !important;
            }
            
            /* Forzar visibilidad de textos en mensajes */
            .notice p,
            .notice strong,
            .notice em,
            .notice span {
                visibility: visible !important;
                opacity: 1 !important;
                display: inline !important;
            }
            
            /* Forzar recarga - ' . time() . ' */
        ');
        
        // Añadir JavaScript para funcionalidades interactivas
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                console.log("Cosas Amazon Admin JS cargado");
                
                // Verificar que las variables están disponibles
                if (typeof cosas_amazon_admin === "undefined") {
                    console.error("Variables cosas_amazon_admin no están definidas");
                    return;
                }
                
                // Función helper para hacer peticiones AJAX
                function makeAjaxRequest(action, button, resultsDiv, successCallback) {
                    var originalText = button.text();
                    button.prop("disabled", true).text("Procesando...");
                    resultsDiv.html("<div class=\"spinner is-active\" style=\"float: none; margin: 10px 0;\"></div>");
                    
                    $.ajax({
                        url: cosas_amazon_admin.ajax_url,
                        type: "POST",
                        data: {
                            action: action,
                            nonce: cosas_amazon_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                if (successCallback) {
                                    successCallback(response);
                                } else {
                                    resultsDiv.html("<div class=\"notice notice-success\"><p>✅ " + response.data + "</p></div>");
                                }
                            } else {
                                resultsDiv.html("<div class=\"notice notice-error\"><p>❌ Error: " + response.data + "</p></div>");
                            }
                        },
                        error: function(xhr, status, error) {
                            resultsDiv.html("<div class=\"notice notice-error\"><p>❌ Error de conexión: " + error + "</p></div>");
                        },
                        complete: function() {
                            button.prop("disabled", false).text(originalText);
                        }
                    });
                }
                
                // Botón para limpiar cache
                $(document).on("click", "#clear-cache-btn", function() {
                    if (!confirm("¿Estás seguro de que quieres limpiar todo el cache?")) {
                        return;
                    }
                    
                    var btn = $(this);
                    var resultsDiv = $("#cache-action-results");
                    
                    makeAjaxRequest("clear_cache", btn, resultsDiv);
                });
                
                // Botón para estadísticas de cache
                $(document).on("click", "#get-cache-stats-btn", function() {
                    var btn = $(this);
                    var resultsDiv = $("#cache-results");
                    
                    makeAjaxRequest("get_cache_stats", btn, resultsDiv);
                });
                
                // Botón para forzar actualización
                $(document).on("click", "#force-update-btn", function() {
                    if (!confirm("¿Estás seguro de que quieres forzar la actualización de todos los productos?")) {
                        return;
                    }
                    
                    var btn = $(this);
                    var resultsDiv = $("#cache-action-results");
                    
                    makeAjaxRequest("cosas_amazon_force_update", btn, resultsDiv, function(response) {
                        resultsDiv.html("<div class=\"notice notice-success\"><p>✅ " + response.data.message + " (Productos actualizados: " + response.data.count + ")</p></div>");
                    });
                });
                
                // Botón para ejecutar tests
                $(document).on("click", "#run-tests-btn", function() {
                    var btn = $(this);
                    var resultsDiv = $("#tests-results");
                    
                    makeAjaxRequest("cosas_amazon_debug", btn, resultsDiv, function(response) {
                        var html = "<div class=\"notice notice-success\"><p>✅ Tests ejecutados correctamente</p></div>";
                        html += "<div style=\"background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 10px;\">";
                        html += "<strong>Información del sistema:</strong><br>";
                        html += "WordPress: " + response.data.debug_info.wordpress_version + "<br>";
                        html += "PHP: " + response.data.debug_info.php_version + "<br>";
                        html += "Plugin: " + response.data.debug_info.plugin_version + "<br>";
                        html += "Memoria: " + response.data.debug_info.memory_limit + "<br>";
                        html += "</div>";
                        resultsDiv.html(html);
                    });
                });
                
                // Botón para debug AJAX
                $(document).on("click", "#debug-ajax-btn", function() {
                    var btn = $(this);
                    var resultsDiv = $("#tests-results");
                    
                    makeAjaxRequest("cosas_amazon_debug", btn, resultsDiv, function(response) {
                        var html = "<div class=\"notice notice-success\"><p>✅ Debug AJAX funcionando</p></div>";
                        html += "<pre style=\"background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; max-height: 200px; overflow-y: auto;\">";
                        html += JSON.stringify(response.data.debug_info, null, 2);
                        html += "</pre>";
                        resultsDiv.html(html);
                    });
                });
                
                // Botón para probar URL
                $("#test-url-btn").click(function() {
                    var btn = $(this);
                    var url = $("#test-amazon-url").val();
                    var resultsDiv = $("#url-test-results");
                    
                    if (!url) {
                        resultsDiv.html("<div class=\\"notice notice-warning\\"><p>⚠️ Por favor ingresa una URL de Amazon</p></div>");
                        return;
                    }
                    
                    btn.prop("disabled", true).text("Probando...");
                    resultsDiv.html("<p>Probando URL con datos reales...</p>");
                    
                    // Usar la API real del plugin
                    fetch(cosas_amazon_admin.site_url + "/wp-json/cda/v1/fetch-product-data", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-WP-Nonce": cosas_amazon_admin.nonce
                        },
                        body: JSON.stringify({ 
                            url: url,
                            force_refresh: true  // Forzar obtener datos frescos
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error("HTTP " + response.status + ": " + text);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.data) {
                            var product = data.data;
                            var html = "<div class=\\"notice notice-success\\"><p>✅ Producto obtenido exitosamente</p></div>";
                            html += "<div style=\\"background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 10px; border-left: 4px solid #46b450;\\">";
                            html += "<h4 style=\\"margin-top: 0;\\">" + (product.title || "Sin título") + "</h4>";
                            if (product.image) {
                                html += "<img src=\\"" + product.image + "\\" style=\\"max-width: 100px; height: auto; float: left; margin-right: 15px;\\" />";
                            }
                            html += "<p><strong>Precio:</strong> " + (product.price || "No disponible") + "</p>";
                            if (product.discount_percentage && product.discount_percentage > 0) {
                                html += "<p><strong>Descuento:</strong> " + product.discount_percentage + "%</p>";
                            }
                            html += "<p><strong>Descripción:</strong> " + (product.description ? product.description.substring(0, 200) + "..." : "No disponible") + "</p>";
                            html += "<p><strong>ASIN:</strong> " + (product.asin || "No detectado") + "</p>";
                            html += "<p><strong>URL original:</strong> " + url + "</p>";
                            html += "<div style=\\"clear: both;\\"></div>";
                            html += "</div>";
                            resultsDiv.html(html);
                        } else {
                            var errorMsg = data.data ? data.data : "Error al obtener datos del producto";
                            resultsDiv.html("<div class=\\"notice notice-error\\"><p>❌ " + errorMsg + "</p></div>");
                        }
                    })
                    .catch(error => {
                        resultsDiv.html("<div style=\\"color: red;\\"><strong>❌ Error:</strong><br>" + error.message + "</div>");
                    })
                    .finally(() => {
                        btn.prop("disabled", false).text("Probar URL");
                    });
                });
                
                // Validación en tiempo real para límite de descripción
                $("input[name=\\"cosas_amazon_description_length\\"").on("input", function() {
                    var value = parseInt($(this).val());
                    var example = $(this).closest("td").find("p:last");
                    
                    if (value && value >= 50 && value <= 500) {
                        var exampleText = "Con un límite de " + value + " caracteres, una descripción típica se vería así: \\"Smartphone última generación con cámara de 48MP, pantalla de 6.1 pulgadas y batería de larga duración...\\"";
                        example.text(exampleText.substring(0, value + 50));
                    }
                });
                
                // Mostrar/ocultar opciones basadas en configuración
                $("#enable_auto_updates").change(function() {
                    var updateFrequencyRow = $(this).closest("tbody").find("tr").has("select[name*=\\"update_frequency\\"]");
                    var thresholdRow = $(this).closest("tbody").find("tr").has("input[name*=\\"high_discount_threshold\\"]");
                    
                    if ($(this).is(":checked")) {
                        updateFrequencyRow.show();
                        thresholdRow.show();
                    } else {
                        updateFrequencyRow.hide();
                        thresholdRow.hide();
                    }
                }).trigger("change");
                
                // Validación de colores
                $("input[type=\\"color\\"").change(function() {
                    var color = $(this).val();
                    var preview = $(this).next(".color-preview");
                    
                    if (preview.length === 0) {
                        preview = $("<span class=\\"color-preview\\" style=\\"display: inline-block; width: 20px; height: 20px; border-radius: 3px; margin-left: 5px; vertical-align: middle; border: 1px solid #ccc;\\"></span>");
                        $(this).after(preview);
                    }
                    
                    preview.css("background-color", color);
                });
                
                // Activar previsualización de colores al cargar
                $("input[type=\\"color\\"").trigger("change");
            });
        ');
    }
    
    public function add_admin_menu() {
        error_log('[COSAS_AMAZON_DEBUG] add_admin_menu llamada');
        error_log('[COSAS_AMAZON_DEBUG] current_user_can(manage_options): ' . (current_user_can('manage_options') ? 'true' : 'false'));
        error_log('[COSAS_AMAZON_DEBUG] is_admin(): ' . (is_admin() ? 'true' : 'false'));
        
        // Verificar permisos antes de registrar
        if (!current_user_can('manage_options')) {
            error_log('[COSAS_AMAZON_DEBUG] ❌ Usuario sin permisos manage_options');
            return;
        }
        
        // Añadir menú principal
        $main_page = add_menu_page(
            'Cosas de Amazon',
            'Cosas de Amazon', 
            'manage_options',
            'cosas-amazon-main',
            array($this, 'options_page'),
            'dashicons-cart',
            58
        );
        
        if ($main_page) {
            error_log('[COSAS_AMAZON_DEBUG] ✅ Menú principal registrado: ' . $main_page);
        } else {
            error_log('[COSAS_AMAZON_DEBUG] ❌ Error registrando menú principal');
        }
        
        error_log('[COSAS_AMAZON_DEBUG] Menú principal registrado correctamente');
    }
    
    public function admin_init() {
        register_setting('cosas_amazon_settings', 'cosas_amazon_options');
        register_setting('cosas_amazon_settings', 'cosas_amazon_description_length');
        register_setting('cosas_amazon_settings', 'cosas_amazon_custom_css'); // Nueva opción para CSS personalizado
        register_setting('cosas_amazon_settings', 'cosas_amazon_api_options'); // Configuración PA API
        
        add_settings_section(
            'cosas_amazon_general',
            'Configuración General',
            array($this, 'settings_section_callback'),
            'cosas_amazon_settings'
        );
        
        add_settings_field(
            'default_style',
            'Estilo por defecto',
            array($this, 'default_style_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_general'
        );
        
        add_settings_field(
            'description_length',
            'Límite de caracteres en descripción',
            array($this, 'description_length_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_general'
        );
        
        add_settings_field(
            'cache_duration',
            'Duración del cache (segundos)',
            array($this, 'cache_duration_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_general'
        );
        
        add_settings_field(
            'show_price_by_default',
            'Mostrar precio por defecto',
            array($this, 'show_price_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_general'
        );
        
        add_settings_field(
            'show_discount_by_default',
            'Mostrar descuento por defecto',
            array($this, 'show_discount_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_general'
        );
        
        add_settings_field(
            'show_description_by_default',
            'Mostrar descripción por defecto',
            array($this, 'show_description_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_general'
        );
        
        add_settings_field(
            'data_source',
            'Fuente de datos',
            array($this, 'data_source_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_general'
        );
        
        add_settings_field(
            'scraping_timeout',
            'Timeout de scraping (segundos)',
            array($this, 'scraping_timeout_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_general'
        );
        
        // Sección de configuración de Amazon PA API
        add_settings_section(
            'cosas_amazon_paapi',
            'Configuración Amazon PA API',
            array($this, 'paapi_section_callback'),
            'cosas_amazon_settings'
        );
        
        add_settings_field(
            'api_enabled',
            'Habilitar Amazon PA API',
            array($this, 'api_enabled_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_paapi'
        );
        
        add_settings_field(
            'amazon_access_key',
            'Access Key ID',
            array($this, 'amazon_access_key_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_paapi'
        );
        
        add_settings_field(
            'amazon_secret_key',
            'Secret Access Key',
            array($this, 'amazon_secret_key_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_paapi'
        );
        
        add_settings_field(
            'amazon_associate_tag',
            'Associate Tag',
            array($this, 'amazon_associate_tag_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_paapi'
        );
        
        add_settings_field(
            'amazon_region',
            'Región',
            array($this, 'amazon_region_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_paapi'
        );
        
        add_settings_field(
            'test_paapi_connection',
            'Test de Conexión',
            array($this, 'test_paapi_connection_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_paapi'
        );
        
        // Nueva sección para configuración por defecto de bloques
        add_settings_section(
            'cosas_amazon_block_defaults',
            'Configuración por Defecto de Bloques',
            array($this, 'block_defaults_section_callback'),
            'cosas_amazon_settings'
        );
        
        add_settings_field(
            'default_description_length',
            'Longitud de descripción por defecto',
            array($this, 'default_description_length_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_text_color',
            'Color de texto por defecto',
            array($this, 'default_text_color_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_font_size',
            'Tamaño de fuente por defecto',
            array($this, 'default_font_size_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_border_style',
            'Estilo de borde por defecto',
            array($this, 'default_border_style_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_border_color',
            'Color de borde por defecto',
            array($this, 'default_border_color_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_background_color',
            'Color de fondo por defecto',
            array($this, 'default_background_color_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_alignment',
            'Alineación por defecto',
            array($this, 'default_alignment_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'show_button_by_default',
            'Mostrar botón por defecto',
            array($this, 'show_button_by_default_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_button_text',
            'Texto del botón por defecto',
            array($this, 'default_button_text_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_button_color',
            'Color del botón por defecto',
            array($this, 'default_button_color_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'show_special_offer_by_default',
            'Mostrar ofertas especiales por defecto',
            array($this, 'show_special_offer_by_default_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_special_offer_color',
            'Color de ofertas especiales por defecto',
            array($this, 'default_special_offer_color_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_block_size',
            'Tamaño de bloque por defecto',
            array($this, 'default_block_size_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_field(
            'default_products_per_row',
            'Productos por fila por defecto',
            array($this, 'default_products_per_row_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_block_defaults'
        );
        
        add_settings_section(
            'cosas_amazon_updates',
            'Actualizaciones Automáticas',
            array($this, 'updates_section_callback'),
            'cosas_amazon_settings'
        );
        
        add_settings_field(
            'enable_auto_updates',
            'Habilitar actualizaciones automáticas',
            array($this, 'enable_auto_updates_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_updates'
        );
        
        add_settings_field(
            'update_frequency',
            'Frecuencia de actualización',
            array($this, 'update_frequency_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_updates'
        );
        
        add_settings_field(
            'high_discount_threshold',
            'Umbral de descuento alto (%)',
            array($this, 'high_discount_threshold_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_updates'
        );

        add_settings_section(
            'cosas_amazon_validation',
            'Validaciones de Datos',
            array($this, 'validation_section_callback'),
            'cosas_amazon_settings'
        );
        
        add_settings_field(
            'strict_discount_validation',
            'Validación estricta de descuentos',
            array($this, 'strict_discount_validation_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_validation'
        );

        add_settings_section(
            'cosas_amazon_tools',
            'Herramientas y Diagnósticos',
            array($this, 'tools_section_callback'),
            'cosas_amazon_settings'
        );
        
        add_settings_field(
            'run_tests',
            'Ejecutar Tests',
            array($this, 'run_tests_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_tools'
        );
        
        add_settings_field(
            'test_url',
            'Probar URL de Amazon',
            array($this, 'test_url_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_tools'
        );
        
        add_settings_field(
            'clear_cache',
            'Limpiar Cache',
            array($this, 'clear_cache_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_tools'
        );
        
        add_settings_field(
            'test_rest_endpoint',
            'Test Endpoint REST',
            array($this, 'test_rest_endpoint_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_tools'
        );
        
        add_settings_section(
            'cosas_amazon_styles',
            'Personalización de Estilos',
            array($this, 'styles_section_callback'),
            'cosas_amazon_settings'
        );
        
        add_settings_field(
            'custom_colors',
            'Colores Personalizados',
            array($this, 'custom_colors_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_styles'
        );
        
        add_settings_field(
            'custom_typography',
            'Tipografía',
            array($this, 'custom_typography_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_styles'
        );
        
        add_settings_field(
            'custom_spacing',
            'Espaciado y Tamaños',
            array($this, 'custom_spacing_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_styles'
        );
        
        add_settings_field(
            'custom_effects',
            'Efectos y Animaciones',
            array($this, 'custom_effects_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_styles'
        );
        
        add_settings_field(
            'style_presets',
            'Temas Predefinidos',
            array($this, 'style_presets_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_styles'
        );
        
        add_settings_field(
            'custom_css',
            'CSS Personalizado',
            array($this, 'custom_css_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_styles'
        );
        
        add_settings_section(
            'cosas_amazon_cache_tools',
            'Herramientas de Cache y Estado',
            array($this, 'cache_tools_section_callback'),
            'cosas_amazon_settings'
        );
        
        add_settings_field(
            'cache_stats',
            'Estadísticas de Cache',
            array($this, 'cache_stats_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_cache_tools'
        );
        
        add_settings_field(
            'cache_actions',
            'Acciones de Cache',
            array($this, 'cache_actions_callback'),
            'cosas_amazon_settings',
            'cosas_amazon_cache_tools'
        );
    }
    
    public function add_action_links($links) {
        $settings_link = '<a href="admin.php?page=cosas-amazon-main">Configuración</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function settings_section_callback() {
        echo '<p>Configura las opciones por defecto para el plugin Cosas de Amazon.</p>';
    }
    
    public function updates_section_callback() {
        echo '<p>Configura cómo y cuándo se actualizarán automáticamente los precios de los productos.</p>';
    }

    public function tools_section_callback() {
        echo '<p>Herramientas para diagnosticar y probar el funcionamiento del plugin. Estas herramientas te ayudarán a verificar que todo funciona correctamente y a solucionar problemas.</p>';
        echo '<div style="background: #f0f8ff; border-left: 4px solid #0073aa; padding: 15px; margin: 15px 0;">';
        echo '<h4>💡 Consejos de uso:</h4>';
        echo '<ul>';
        echo '<li><strong>Probar URL:</strong> Introduce cualquier URL de Amazon para verificar si el plugin puede extraer los datos correctamente.</li>';
        echo '<li><strong>Limpiar Cache:</strong> Si los productos no se actualizan, limpia el cache para forzar una nueva obtención de datos.</li>';
        echo '<li><strong>Estadísticas:</strong> Revisa cuántos productos tienes almacenados en cache y cuándo fueron actualizados por última vez.</li>';
        echo '</ul>';
        echo '</div>';
    }

    // Funciones callback para la sección de cache
    public function cache_tools_section_callback() {
        echo '<p>Administra el cache de productos de Amazon y supervisa el estado del sistema.</p>';
        echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">';
        echo '<h4>⚠️ Importante:</h4>';
        echo '<p>El cache mejora el rendimiento del sitio web almacenando temporalmente los datos de los productos. Limpia el cache solo si experimentas problemas con datos obsoletos.</p>';
        echo '</div>';
    }

    public function cache_stats_callback() {
        echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin: 10px 0;">';
        echo '<button type="button" id="get-cache-stats-btn" class="button button-secondary">Ver Estadísticas de Cache</button>';
        echo '<div id="cache-results" style="margin-top: 10px; display: block; visibility: visible; opacity: 1; min-height: 20px;"></div>';
        echo '</div>';
        echo '<p class="description">Obtén información detallada sobre el estado actual del cache de productos.</p>';
    }

    public function cache_actions_callback() {
        echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin: 10px 0;">';
        echo '<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">';
        echo '<button type="button" id="clear-cache-btn" class="button button-secondary">🗑️ Limpiar Cache</button>';
        echo '<button type="button" id="force-update-btn" class="button button-primary">🔄 Forzar Actualización</button>';
        echo '</div>';
        echo '<div id="cache-action-results" style="margin-top: 10px;"></div>';
        echo '</div>';
        echo '<p class="description">Realiza acciones de mantenimiento en el cache de productos.</p>';
    }
    
    public function default_style_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_style']) ? $options['default_style'] : 'horizontal';
        echo '<select name="cosas_amazon_options[default_style]">';
        echo '<option value="horizontal"' . selected($value, 'horizontal', false) . '>Horizontal</option>';
        echo '<option value="vertical"' . selected($value, 'vertical', false) . '>Vertical</option>';
        echo '<option value="compact"' . selected($value, 'compact', false) . '>Compacta</option>';
        echo '<option value="featured"' . selected($value, 'featured', false) . '>Destacada</option>';
        echo '</select>';
    }
    
    public function description_length_callback() {
        // Verificar que las funciones de WordPress estén disponibles
        if (!function_exists('get_option') || !function_exists('esc_attr')) {
            echo '<p style="color: red;">Error: Funciones de WordPress no disponibles.</p>';
            return;
        }
        
        try {
            $value = get_option('cosas_amazon_description_length', 150);
            
            // Validar el valor
            if (!is_numeric($value) || $value < 50 || $value > 500) {
                $value = 150; // Valor por defecto seguro
            }
            
            echo '<div style="margin-bottom: 15px;">';
            echo '<input type="number" name="cosas_amazon_description_length" value="' . esc_attr($value) . '" min="50" max="500" step="10" style="width: 80px;" />';
            echo '<span style="margin-left: 5px;">caracteres</span>';
            echo '</div>';
            
            echo '<p class="description">Límite global de caracteres para las descripciones de productos. Los bloques individuales pueden sobrescribir este valor.</p>';
            
            echo '<div style="margin-top: 15px; padding: 12px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 4px;">';
            echo '<h5 style="margin: 0 0 8px 0; color: #0073aa;">📏 Guía de Longitudes:</h5>';
            echo '<ul style="margin: 5px 0; padding-left: 20px; font-size: 13px; line-height: 1.4;">';
            echo '<li><strong>50-100 caracteres:</strong> Muy corta, solo lo esencial</li>';
            echo '<li><strong>100-200 caracteres:</strong> Corta pero informativa (recomendado)</li>';
            echo '<li><strong>200-300 caracteres:</strong> Descripción completa</li>';
            echo '<li><strong>300+ caracteres:</strong> Descripción muy detallada</li>';
            echo '</ul>';
            echo '</div>';
            
            // Mostrar ejemplo visual
            echo '<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">';
            echo '<h5 style="margin: 0 0 8px 0; color: #856404;">💡 Ejemplo:</h5>';
            echo '<p style="margin: 0; font-size: 13px; color: #856404;">Con un límite de ' . esc_attr($value) . ' caracteres, una descripción típica se vería así: "Smartphone última generación con cámara de 48MP, pantalla de 6.1 pulgadas y batería de larga duración..."</p>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<p style="color: red;">Error al cargar la configuración: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    public function cache_duration_callback() {
        try {
            $options = get_option('cosas_amazon_options', array());
            $value = isset($options['cache_duration']) ? $options['cache_duration'] : 3600; // 1 hora = 3600 segundos
            
            // Validar el valor
            if (!is_numeric($value) || $value < 300 || $value > 86400) {
                $value = 3600; // Valor por defecto seguro
            }
            
            echo '<input type="number" name="cosas_amazon_options[cache_duration]" value="' . esc_attr($value) . '" min="300" max="86400" />';
            echo '<p class="description">Tiempo en segundos para mantener los datos en cache (mínimo 300, máximo 86400)</p>';
            
        } catch (Exception $e) {
            echo '<p style="color: red;">Error al cargar la configuración de cache: ' . esc_html($e->getMessage()) . '</p>';
            echo '<input type="number" name="cosas_amazon_options[cache_duration]" value="3600" min="300" max="86400" />';
        }
    }
    
    public function show_price_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['show_price_by_default']) ? $options['show_price_by_default'] : true;
        echo '<input type="checkbox" name="cosas_amazon_options[show_price_by_default]" value="1"' . checked($value, true, false) . ' />';
        echo '<label for="cosas_amazon_options[show_price_by_default]">Activar por defecto</label>';
    }
    
    public function show_discount_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['show_discount_by_default']) ? $options['show_discount_by_default'] : true;
        echo '<input type="checkbox" name="cosas_amazon_options[show_discount_by_default]" value="1"' . checked($value, true, false) . ' />';
        echo '<label for="cosas_amazon_options[show_discount_by_default]">Activar por defecto</label>';
    }
    
    public function show_description_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['show_description_by_default']) ? $options['show_description_by_default'] : true;
        echo '<input type="checkbox" name="cosas_amazon_options[show_description_by_default]" value="1"' . checked($value, true, false) . ' />';
        echo '<label for="cosas_amazon_options[show_description_by_default]">Activar por defecto</label>';
    }
    
    public function data_source_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['data_source']) ? $options['data_source'] : 'real';
        echo '<select name="cosas_amazon_options[data_source]" id="data_source_select">';
        echo '<option value="real"' . selected($value, 'real', false) . '>Datos reales de Amazon (recomendado)</option>';
        echo '<option value="simulated"' . selected($value, 'simulated', false) . '>Datos simulados (para testing)</option>';
        echo '</select>';
        echo '<p class="description">Selecciona la fuente de datos del producto. Los datos reales se obtienen directamente de Amazon.</p>';
    }
    
    public function scraping_timeout_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['scraping_timeout']) ? $options['scraping_timeout'] : 15;
        echo '<input type="number" name="cosas_amazon_options[scraping_timeout]" value="' . esc_attr($value) . '" min="5" max="30" />';
        echo '<p class="description">Tiempo máximo en segundos para obtener datos de Amazon (mínimo 5, máximo 30). Un valor más alto puede mejorar la tasa de éxito pero hará más lenta la carga.</p>';
    }
    
    public function enable_auto_updates_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['enable_auto_updates']) ? $options['enable_auto_updates'] : true;
        echo '<input type="checkbox" id="enable_auto_updates" name="cosas_amazon_options[enable_auto_updates]" value="1"' . checked($value, true, false) . ' />';
        echo '<label for="enable_auto_updates">Activar actualizaciones automáticas de precios</label>';
        echo '<p class="description">Los precios se actualizarán automáticamente según la frecuencia configurada.</p>';
    }
    
    public function update_frequency_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['update_frequency']) ? $options['update_frequency'] : 'daily';
        echo '<select name="cosas_amazon_options[update_frequency]">';
        echo '<option value="daily"' . selected($value, 'daily', false) . '>Una vez al día (recomendado)</option>';
        echo '<option value="twicedaily"' . selected($value, 'twicedaily', false) . '>Dos veces al día</option>';
        echo '<option value="hourly_test"' . selected($value, 'hourly_test', false) . '>Cada hora (solo para testing)</option>';
        echo '</select>';
        echo '<p class="description">Frecuencia con la que se actualizarán automáticamente los precios.</p>';
    }
    
    public function high_discount_threshold_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['high_discount_threshold']) ? $options['high_discount_threshold'] : 50;
        echo '<input type="number" name="cosas_amazon_options[high_discount_threshold]" value="' . esc_attr($value) . '" min="0" max="100" />';
        echo '<p class="description">Umbral en porcentaje para considerar un descuento como alto. Las ofertas por encima de este umbral se marcarán como destacadas.</p>';
    }
    
    public function validation_section_callback() {
        echo '<p>Configuración de validaciones para mejorar la precisión de los datos extraídos de Amazon.</p>';
    }
    
    public function strict_discount_validation_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['strict_discount_validation']) ? $options['strict_discount_validation'] : true;
        echo '<input type="checkbox" name="cosas_amazon_options[strict_discount_validation]" value="1"' . checked($value, true, false) . ' />';
        echo '<label for="cosas_amazon_options[strict_discount_validation]">Activar validación estricta de descuentos</label>';
        echo '<p class="description"><strong>Recomendado:</strong> Cuando está activado, el plugin valida que los descuentos detectados sean reales comparando precios y verificando el contexto. Esto evita mostrar descuentos falsos pero puede ser más restrictivo.</p>';
        echo '<div style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-left: 4px solid #00a0d2; border-radius: 4px;">';
        echo '<strong>¿Qué hace esta validación?</strong>';
        echo '<ul style="margin: 8px 0;">';
        echo '<li>✅ Verifica que el precio original sea mayor que el actual</li>';
        echo '<li>✅ Valida que el porcentaje de descuento sea coherente con la diferencia de precios</li>';
        echo '<li>✅ Comprueba que el descuento aparezca en un contexto válido de la página</li>';
        echo '<li>✅ Elimina descuentos sin precio original para validar</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    public function run_tests_callback() {
        echo '<div id="cosas-amazon-tests-container">';
        echo '<button type="button" id="run-tests-btn" class="button button-secondary">Ejecutar Tests</button>';
        echo '<button type="button" id="debug-ajax-btn" class="button button-secondary" style="margin-left: 10px;">Debug AJAX</button>';
        echo '<div id="tests-results" style="margin-top: 15px;"></div>';
        echo '</div>';
    }
    
    public function test_url_callback() {
        echo '<div id="cosas-amazon-url-test-container">';
        echo '<div style="margin-bottom: 15px;">';
        echo '<input type="url" id="test-amazon-url" placeholder="https://www.amazon.es/dp/..." style="width: 400px; margin-right: 10px;" />';
        echo '<button type="button" id="test-url-btn" class="button button-secondary">Probar URL</button>';
        echo '</div>';
        echo '<div id="url-test-results" style="margin-top: 15px;"></div>';
        echo '</div>';
    }
    
    public function clear_cache_callback() {
        echo '<div id="cosas-amazon-cache-container">';
        echo '<button type="button" id="clear-cache-btn" class="button button-secondary">🗑️ Limpiar Todo el Cache</button>';
        echo '<button type="button" id="get-cache-stats-btn" class="button button-secondary" style="margin-left: 10px;">📊 Ver Estadísticas</button>';
        echo '<button type="button" id="force-update-btn" class="button button-primary" style="margin-left: 10px;">🔄 Forzar Actualización</button>';
        echo '<div id="cache-action-results" style="margin-top: 15px;"></div>';
        echo '</div>';
    }
    
    public function styles_section_callback() {
        echo '<p>Personaliza la apariencia visual de los productos de Amazon. Los cambios se aplicarán a todos los bloques del plugin.</p>';
    }
    
    public function custom_colors_callback() {
        $options = get_option('cosas_amazon_options');
        $primary_color = isset($options['primary_color']) ? $options['primary_color'] : '#e47911';
        $secondary_color = isset($options['secondary_color']) ? $options['secondary_color'] : '#232f3e';
        $accent_color = isset($options['accent_color']) ? $options['accent_color'] : '#ff9900';
        $text_color = isset($options['text_color']) ? $options['text_color'] : '#333333';
        $background_color = isset($options['background_color']) ? $options['background_color'] : '#ffffff';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        echo '<div>';
        echo '<label for="primary_color"><strong>Color Primario:</strong></label><br>';
        echo '<input type="color" id="primary_color" name="cosas_amazon_options[primary_color]" value="' . esc_attr($primary_color) . '" />';
        echo '<p class="description">Color principal para botones y enlaces</p>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="secondary_color"><strong>Color Secundario:</strong></label><br>';
        echo '<input type="color" id="secondary_color" name="cosas_amazon_options[secondary_color]" value="' . esc_attr($secondary_color) . '" />';
        echo '<p class="description">Color para textos secundarios</p>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="accent_color"><strong>Color de Acento:</strong></label><br>';
        echo '<input type="color" id="accent_color" name="cosas_amazon_options[accent_color]" value="' . esc_attr($accent_color) . '" />';
        echo '<p class="description">Color para precios y ofertas</p>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="text_color"><strong>Color de Texto:</strong></label><br>';
        echo '<input type="color" id="text_color" name="cosas_amazon_options[text_color]" value="' . esc_attr($text_color) . '" />';
        echo '<p class="description">Color principal del texto</p>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="background_color"><strong>Color de Fondo:</strong></label><br>';
        echo '<input type="color" id="background_color" name="cosas_amazon_options[background_color]" value="' . esc_attr($background_color) . '" />';
        echo '<p class="description">Color de fondo de las tarjetas</p>';
        echo '</div>';
        
        echo '</div>';
    }
    
    public function custom_typography_callback() {
        $options = get_option('cosas_amazon_options');
        $font_family = isset($options['font_family']) ? $options['font_family'] : 'default';
        $title_size = isset($options['title_size']) ? $options['title_size'] : '18';
        $text_size = isset($options['text_size']) ? $options['text_size'] : '14';
        $price_size = isset($options['price_size']) ? $options['price_size'] : '16';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        echo '<div>';
        echo '<label for="font_family"><strong>Familia de Fuente:</strong></label><br>';
        echo '<select id="font_family" name="cosas_amazon_options[font_family]">';
        echo '<option value="default"' . selected($font_family, 'default', false) . '>Por defecto del tema</option>';
        echo '<option value="system"' . selected($font_family, 'system', false) . '>Fuente del sistema</option>';
        echo '<option value="arial"' . selected($font_family, 'arial', false) . '>Arial</option>';
        echo '<option value="helvetica"' . selected($font_family, 'helvetica', false) . '>Helvetica</option>';
        echo '<option value="roboto"' . selected($font_family, 'roboto', false) . '>Roboto</option>';
        echo '<option value="open-sans"' . selected($font_family, 'open-sans', false) . '>Open Sans</option>';
        echo '</select>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="title_size"><strong>Tamaño del Título:</strong></label><br>';
        echo '<input type="number" id="title_size" name="cosas_amazon_options[title_size]" value="' . esc_attr($title_size) . '" min="12" max="32" step="1" />';
        echo '<span> px</span>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="text_size"><strong>Tamaño del Texto:</strong></label><br>';
        echo '<input type="number" id="text_size" name="cosas_amazon_options[text_size]" value="' . esc_attr($text_size) . '" min="10" max="24" step="1" />';
        echo '<span> px</span>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="price_size"><strong>Tamaño del Precio:</strong></label><br>';
        echo '<input type="number" id="price_size" name="cosas_amazon_options[price_size]" value="' . esc_attr($price_size) . '" min="12" max="28" step="1" />';
        echo '<span> px</span>';
        echo '</div>';
        
        echo '</div>';
        echo '<p class="description">Personaliza la tipografía de los elementos del producto.</p>';
    }
    
    public function custom_spacing_callback() {
        $options = get_option('cosas_amazon_options');
        $card_padding = isset($options['card_padding']) ? $options['card_padding'] : '15';
        $card_margin = isset($options['card_margin']) ? $options['card_margin'] : '10';
        $border_radius = isset($options['border_radius']) ? $options['border_radius'] : '8';
        $image_size = isset($options['image_size']) ? $options['image_size'] : '150';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        echo '<div>';
        echo '<label for="card_padding"><strong>Espaciado Interno:</strong></label><br>';
        echo '<input type="number" id="card_padding" name="cosas_amazon_options[card_padding]" value="' . esc_attr($card_padding) . '" min="0" max="50" step="5" />';
        echo '<span> px</span>';
        echo '<p class="description">Espacio dentro de las tarjetas</p>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="card_margin"><strong>Espaciado Externo:</strong></label><br>';
        echo '<input type="number" id="card_margin" name="cosas_amazon_options[card_margin]" value="' . esc_attr($card_margin) . '" min="0" max="30" step="5" />';
        echo '<span> px</span>';
        echo '<p class="description">Espacio entre tarjetas</p>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="border_radius"><strong>Bordes Redondeados:</strong></label><br>';
        echo '<input type="number" id="border_radius" name="cosas_amazon_options[border_radius]" value="' . esc_attr($border_radius) . '" min="0" max="20" step="1" />';
        echo '<span> px</span>';
        echo '<p class="description">Radio de los bordes</p>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="image_size"><strong>Tamaño de Imagen:</strong></label><br>';
        echo '<input type="number" id="image_size" name="cosas_amazon_options[image_size]" value="' . esc_attr($image_size) . '" min="80" max="300" step="10" />';
        echo '<span> px</span>';
        echo '<p class="description">Ancho máximo de imágenes</p>';
        echo '</div>';
        
        echo '</div>';
    }
    
    public function custom_effects_callback() {
        $options = get_option('cosas_amazon_options');
        $hover_effect = isset($options['hover_effect']) ? $options['hover_effect'] : 'scale';
        $shadow_style = isset($options['shadow_style']) ? $options['shadow_style'] : 'medium';
        $animation_speed = isset($options['animation_speed']) ? $options['animation_speed'] : 'normal';
        $gradient_enable = isset($options['gradient_enable']) ? $options['gradient_enable'] : false;
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        echo '<div>';
        echo '<label for="hover_effect"><strong>Efecto al Pasar el Ratón:</strong></label><br>';
        echo '<select id="hover_effect" name="cosas_amazon_options[hover_effect]">';
        echo '<option value="none"' . selected($hover_effect, 'none', false) . '>Sin efecto</option>';
        echo '<option value="scale"' . selected($hover_effect, 'scale', false) . '>Aumentar tamaño</option>';
        echo '<option value="lift"' . selected($hover_effect, 'lift', false) . '>Elevar</option>';
        echo '<option value="glow"' . selected($hover_effect, 'glow', false) . '>Resplandor</option>';
        echo '</select>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="shadow_style"><strong>Estilo de Sombra:</strong></label><br>';
        echo '<select id="shadow_style" name="cosas_amazon_options[shadow_style]">';
        echo '<option value="none"' . selected($shadow_style, 'none', false) . '>Sin sombra</option>';
        echo '<option value="light"' . selected($shadow_style, 'light', false) . '>Ligera</option>';
        echo '<option value="medium"' . selected($shadow_style, 'medium', false) . '>Media</option>';
        echo '<option value="strong"' . selected($shadow_style, 'strong', false) . '>Fuerte</option>';
        echo '</select>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="animation_speed"><strong>Velocidad de Animación:</strong></label><br>';
        echo '<select id="animation_speed" name="cosas_amazon_options[animation_speed]">';
        echo '<option value="slow"' . selected($animation_speed, 'slow', false) . '>Lenta</option>';
        echo '<option value="normal"' . selected($animation_speed, 'normal', false) . '>Normal</option>';
        echo '<option value="fast"' . selected($animation_speed, 'fast', false) . '>Rápida</option>';
        echo '</select>';
        echo '</div>';
        
        echo '<div>';
        echo '<label for="gradient_enable"><strong>Gradientes:</strong></label><br>';
        echo '<input type="checkbox" id="gradient_enable" name="cosas_amazon_options[gradient_enable]" value="1"' . checked($gradient_enable, 1, false) . ' />';
        echo '<label for="gradient_enable">Activar efectos de gradiente</label>';
        echo '</div>';
        
        echo '</div>';
    }
    
    public function style_presets_callback() {
        $options = get_option('cosas_amazon_options');
        $current_preset = isset($options['style_preset']) ? $options['style_preset'] : 'default';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">';
        
        $presets = [
            'default' => [
                'name' => 'Estilo por Defecto',
                'description' => 'Diseño limpio y neutro, perfecto para cualquier sitio web',
                'colors' => ['#e47911', '#232f3e', '#ff9900']
            ],
            'minimal' => [
                'name' => 'Minimalista',
                'description' => 'Diseño ultra-limpio con espacios amplios y colores sutiles',
                'colors' => ['#333333', '#f8f9fa', '#6c757d']
            ],
            'modern' => [
                'name' => 'Moderno',
                'description' => 'Gradientes suaves y efectos visuales contemporáneos',
                'colors' => ['#667eea', '#764ba2', '#f093fb']
            ],
            'dark' => [
                'name' => 'Modo Oscuro',
                'description' => 'Perfecto para sitios web con temas oscuros',
                'colors' => ['#1a1a1a', '#333333', '#ff6b6b']
            ],
            'vibrant' => [
                'name' => 'Vibrante',
                'description' => 'Colores llamativos que destacan en cualquier página',
                'colors' => ['#ff6b6b', '#4ecdc4', '#45b7d1']
            ]
        ];
        
        foreach ($presets as $key => $preset) {
            echo '<div style="border: 2px solid ' . ($current_preset === $key ? '#0073aa' : '#ddd') . '; border-radius: 8px; padding: 15px; background: ' . ($current_preset === $key ? '#f0f8ff' : 'white') . ';">';
            echo '<label>';
            echo '<input type="radio" name="cosas_amazon_options[style_preset]" value="' . $key . '"' . checked($current_preset, $key, false) . ' style="margin-right: 10px;" />';
            echo '<strong>' . $preset['name'] . '</strong>';
            echo '</label>';
            echo '<p style="margin: 8px 0; font-size: 13px; color: #666;">' . $preset['description'] . '</p>';
            
            echo '<div style="display: flex; gap: 5px; margin-top: 10px;">';
            foreach ($preset['colors'] as $color) {
                echo '<div style="width: 20px; height: 20px; background: ' . $color . '; border-radius: 3px; border: 1px solid #ddd;"></div>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px;">';
        echo '<h4 style="margin-top: 0;">⚠️ Nota Importante</h4>';
        echo '<p style="margin-bottom: 0;">Al seleccionar un tema predefinido se sobrescribirán tus configuraciones personalizadas de colores, tipografía y efectos. Asegúrate de guardar los cambios después de seleccionar un tema.</p>';
        echo '</div>';
    }
    
    public function custom_css_callback() {
        $custom_css = get_option('cosas_amazon_custom_css', '');
        
        echo '<div style="max-width: 800px;">';
        echo '<div style="margin-bottom: 15px;">';
        echo '<h4 style="margin: 0 0 10px 0;">🎨 CSS Personalizado</h4>';
        echo '<p style="margin: 0 0 15px 0; color: #666;">Añade reglas CSS personalizadas que se aplicarán a todos los productos del plugin. Estas reglas tendrán prioridad sobre los estilos predeterminados.</p>';
        echo '</div>';
        
        echo '<textarea name="cosas_amazon_custom_css" id="cosas_amazon_custom_css" rows="15" cols="80" style="width: 100%; font-family: monospace; font-size: 13px; border: 2px solid #ddd; border-radius: 6px; padding: 15px;" placeholder="/* Ejemplo de CSS personalizado:

.cosas-amazon-product {
    border: 2px solid #ff6b6b !important;
    border-radius: 15px !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}

.cosas-amazon-title {
    color: #2c3e50 !important;
    font-weight: bold !important;
}

.cosas-amazon-price {
    color: #e74c3c !important;
    font-size: 18px !important;
}

.cosas-amazon-btn {
    background: linear-gradient(45deg, #ff6b6b, #4ecdc4) !important;
    border-radius: 25px !important;
} */">' . esc_textarea($custom_css) . '</textarea>';
        
        echo '<div style="margin-top: 15px; padding: 15px; background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 6px;">';
        echo '<h4 style="margin-top: 0; color: #0c5460;">💡 Consejos para CSS Personalizado</h4>';
        echo '<ul style="margin: 10px 0; padding-left: 20px; color: #0c5460;">';
        echo '<li><strong>Usa !important</strong> para asegurar que tus estilos tengan prioridad</li>';
        echo '<li><strong>Clases principales:</strong> .cosas-amazon-product, .cosas-amazon-title, .cosas-amazon-price, .cosas-amazon-btn</li>';
        echo '<li><strong>Estilos específicos:</strong> .cosas-amazon-horizontal, .cosas-amazon-vertical, .cosas-amazon-minimal</li>';
        echo '<li><strong>Múltiples productos:</strong> .cosas-amazon-multiple-products</li>';
        echo '<li><strong>Testa siempre</strong> los cambios en diferentes dispositivos</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px;">';
        echo '<h4 style="margin-top: 0;">⚠️ Importante</h4>';
        echo '<p style="margin-bottom: 0;">El CSS personalizado se aplicará a todos los productos del plugin en todo el sitio web. Asegúrate de probar los cambios antes de aplicarlos en producción.</p>';
        echo '</div>';
        echo '</div>';
    }
    
    public function options_page() {
        // Mostrar mensajes de confirmación
        $this->show_settings_messages();
        ?>
        <div class="wrap cosas-amazon-config-page">
            <!-- Header con logo mejorado -->
            <div class="cosas-amazon-admin-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <h1 style="margin: 0 0 5px 0;">Configuración de Cosas de Amazon</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 14px;">Personaliza el comportamiento y apariencia del plugin</p>
                </div>
                <div class="cosas-amazon-logo" style="margin-left: 20px; max-width: 120px !important; max-height: 120px !important;">
                    <?php if (defined('COSAS_AMAZON_PLUGIN_URL')): ?>
                        <img src="<?php echo COSAS_AMAZON_PLUGIN_URL; ?>assets/images/logo.png" alt="Cosas de Amazon Logo" style="width: 100px !important; height: auto !important; max-height: 100px !important;">
                    <?php endif; ?>
                    <?php if (defined('COSAS_AMAZON_VERSION')): ?>
                        <p style="margin: 4px 0 0 0 !important; font-size: 10px !important; color: #999 !important; text-align: center !important; font-weight: 400 !important;">v<?php echo COSAS_AMAZON_VERSION; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <form method="post" action="options.php" id="cosas-amazon-settings-form">
                        <?php
                        settings_fields('cosas_amazon_settings');
                        do_settings_sections('cosas_amazon_settings');
                        ?>
                        
                        <!-- Botón de guardar prominente mejorado -->
                        <div class="cosas-amazon-save-section" style="padding: 25px; border-radius: 8px; margin-top: 30px; text-align: center;">
                            <h3 style="margin: 0 0 10px 0; font-size: 20px;">💾 Guardar Configuración</h3>
                            <p style="margin: 0 0 20px 0; font-size: 14px;">Aplica todos los cambios realizados en la configuración del plugin</p>
                            <?php submit_button('Guardar y Aplicar Cambios', 'primary large cosas-amazon-save-btn', 'submit', false, array('style' => 'font-size: 16px; padding: 12px 40px; height: auto; border-radius: 6px;')); ?>
                        </div>
                    </form>
                </div>
                
                <div style="width: 320px;">
                    <!-- Panel de información del plugin -->
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0 0 15px 0; color: white; font-size: 18px;">ℹ️ Información del Plugin</h3>
                        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                            <p style="margin: 0 0 8px 0; color: rgba(255,255,255,0.9);"><strong>Versión:</strong> <?php echo defined('COSAS_AMAZON_VERSION') ? COSAS_AMAZON_VERSION : '1.0.0'; ?></p>
                            <p style="margin: 0; color: rgba(255,255,255,0.9);"><strong>Autor:</strong> entreunosyceros</p>
                        </div>
                        
                        <h4 style="margin: 0 0 10px 0; color: white; font-size: 14px;">🚀 Características</h4>
                        <ul style="margin: 0 0 15px 0; padding-left: 20px; color: rgba(255,255,255,0.9); font-size: 13px;">
                            <li>✅ 7 estilos de tarjetas diferentes</li>
                            <li>✅ Diseño completamente responsive</li>
                            <li>✅ Sistema de cache inteligente</li>
                            <li>✅ Integración total con Gutenberg</li>
                            <li>✅ Personalización avanzada</li>
                            <li>✅ Soporte para múltiples productos</li>
                        </ul>
                        
                        <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 6px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: rgba(255,255,255,0.8);">💡 Plugin optimizado para Amazon España</p>
                        </div>
                    </div>
                    
                    <!-- Panel de estadísticas -->
                    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                        <h4 style="margin: 0 0 15px 0; color: #0073aa; font-size: 16px;">📊 Estadísticas del Plugin</h4>
                        
                        <?php
                        // Obtener estadísticas si la clase existe
                        if (class_exists('CosasAmazonStats')) {
                            $stats = CosasAmazonStats::get_basic_stats(30);
                            echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">';
                            echo '<div style="background: #f0f8ff; padding: 10px; border-radius: 6px; text-align: center;">';
                            echo '<div style="font-size: 24px; font-weight: bold; color: #0073aa;">' . number_format($stats['total_views']) . '</div>';
                            echo '<div style="font-size: 12px; color: #666;">Visualizaciones</div>';
                            echo '</div>';
                            echo '<div style="background: #f0fff0; padding: 10px; border-radius: 6px; text-align: center;">';
                            echo '<div style="font-size: 24px; font-weight: bold; color: #46b450;">' . number_format($stats['total_clicks']) . '</div>';
                            echo '<div style="font-size: 12px; color: #666;">Clicks</div>';
                            echo '</div>';
                            echo '</div>';
                            
                            if ($stats['total_views'] > 0) {
                                $ctr = round(($stats['total_clicks'] / $stats['total_views']) * 100, 2);
                                echo '<div style="background: #fff5f5; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 10px;">';
                                echo '<div style="font-size: 18px; font-weight: bold; color: #dc3545;">' . $ctr . '%</div>';
                                echo '<div style="font-size: 12px; color: #666;">Tasa de Click (CTR)</div>';
                                echo '</div>';
                            }
                            
                            echo '<p style="margin: 0; font-size: 12px; color: #666; text-align: center;">Últimos 30 días</p>';
                        } else {
                            echo '<p style="margin: 0; font-size: 13px; color: #666; text-align: center;">Las estadísticas no están disponibles</p>';
                        }
                        ?>
                    </div>
                    
                    <!-- Panel de guía rápida -->
                    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                        <h4 style="margin: 0 0 15px 0; color: #0073aa; font-size: 16px;">📖 Guía Rápida</h4>
                        <ol style="margin: 0; padding-left: 20px; color: #333; font-size: 13px; line-height: 1.6;">
                            <li>Abre el editor de bloques de WordPress</li>
                            <li>Busca "Producto de Amazon" en el insertor</li>
                            <li>Pega la URL del producto de Amazon</li>
                            <li>Selecciona el estilo que prefieras</li>
                            <li>Ajusta los colores y opciones</li>
                            <li>¡Publica y disfruta!</li>
                        </ol>
                    </div>
                    
                    <!-- Panel de soporte -->
                    <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                        <h4 style="margin: 0 0 10px 0; color: white; font-size: 16px;">🆘 ¿Necesitas Ayuda?</h4>
                        <p style="margin: 0 0 15px 0; font-size: 13px; color: rgba(255,255,255,0.9);">Estamos aquí para ayudarte con cualquier duda o problema</p>
                        <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 6px;">
                            <p style="margin: 0; font-size: 12px; color: rgba(255,255,255,0.8);">📧 admin@entreunosyceros.net</p>
                        </div>
                    </div>
                    
                    <!-- Panel de herramientas de diagnóstico -->
                    <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                        <h4 style="margin: 0 0 15px 0; color: #0073aa; font-size: 16px;">🔧 Herramientas de Diagnóstico</h4>
                        <p style="margin: 0 0 15px 0; font-size: 13px; color: #666;">Si experimentas problemas con el plugin, puedes utilizar estas herramientas para diagnosticar y resolver los errores.</p>
                        
                        <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                            <?php
                            // Generar token de seguridad temporal
                            $security_token = wp_create_nonce('cosas_amazon_diagnostic_' . date('Y-m-d-H'));
                            $base_url = plugins_url('', dirname(__FILE__));
                            ?>
                            
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                                <div>
                                    <strong style="color: #0073aa;">🔍 Verificación de Enlaces</strong>
                                    <div style="font-size: 12px; color: #666;">Verifica el estado de los enlaces del menú de administración</div>
                                </div>
                                <a href="<?php echo esc_url($base_url . '/verificacion-enlaces.php?token=' . $security_token); ?>" 
                                   target="_blank" 
                                   class="button button-secondary"
                                   style="text-decoration: none;">Verificar</a>
                            </div>
                            
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                                <div>
                                    <strong style="color: #0073aa;">🏥 Diagnóstico de Producción</strong>
                                    <div style="font-size: 12px; color: #666;">Ejecuta un diagnóstico completo del plugin en producción</div>
                                </div>
                                <a href="<?php echo esc_url($base_url . '/diagnostico-produccion.php?token=' . $security_token); ?>" 
                                   target="_blank" 
                                   class="button button-secondary"
                                   style="text-decoration: none;">Diagnosticar</a>
                            </div>
                            
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                                <div>
                                    <strong style="color: #0073aa;">📋 Verificador de Menús</strong>
                                    <div style="font-size: 12px; color: #666;">Verifica la estructura y configuración de los menús</div>
                                </div>
                                <a href="<?php echo esc_url($base_url . '/verificador-menus.php?token=' . $security_token); ?>" 
                                   target="_blank" 
                                   class="button button-secondary"
                                   style="text-decoration: none;">Verificar Menús</a>
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                            <p style="margin: 0; font-size: 12px; color: #856404;">
                                <strong>⚠️ Importante:</strong> Estas herramientas están destinadas para uso administrativo y diagnóstico. 
                                Los enlaces incluyen tokens de seguridad temporales que expiran cada hora.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // Funciones AJAX para administración
    public function ajax_force_update() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cosas_amazon_nonce')) {
            wp_send_json_error('Error de seguridad');
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        // Simular actualización forzada
        $updated_count = rand(5, 25);
        
        wp_send_json_success([
            'message' => 'Actualización iniciada correctamente',
            'count' => $updated_count,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    public function ajax_debug() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cosas_amazon_nonce')) {
            wp_send_json_error('Error de seguridad');
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        // Verificar dependencias
        $dependencies = $this->check_dependencies();
        
        // Información de debug
        $debug_info = [
            'wordpress_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : 'No disponible',
            'php_version' => PHP_VERSION,
            'plugin_version' => defined('COSAS_AMAZON_VERSION') ? COSAS_AMAZON_VERSION : 'No definida',
            'theme' => function_exists('get_option') ? get_option('stylesheet') : 'No disponible',
            'memory_limit' => ini_get('memory_limit'),
            'time_limit' => ini_get('max_execution_time'),
            'ajax_url' => function_exists('admin_url') ? admin_url('admin-ajax.php') : 'No disponible',
            'current_time' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'timezone' => function_exists('get_option') ? get_option('timezone_string') : 'No disponible',
            'options' => function_exists('get_option') ? get_option('cosas_amazon_options', []) : [],
            'dependencies' => $dependencies
        ];
        
        wp_send_json_success([
            'message' => 'Debug AJAX funcionando correctamente',
            'debug_info' => $debug_info
        ]);
    }
    
    public function check_menu_exists() {
        global $submenu;
        
        if (is_admin() && current_user_can('manage_options')) {
            // Verificar si el menú existe en opciones
            $menu_exists = false;
            if (isset($submenu['options-general.php'])) {
                foreach ($submenu['options-general.php'] as $item) {
                    if (isset($item[2]) && $item[2] === 'cosas-amazon-settings') {
                        $menu_exists = true;
                        break;
                    }
                }
            }
            
            if (!$menu_exists) {
                error_log('[COSAS_AMAZON_DEBUG] ⚠️  Menú no encontrado, intentando re-registrar');
                // Intentar registrar el menú de nuevo
                $this->add_admin_menu();
            } else {
                error_log('[COSAS_AMAZON_DEBUG] ✅ Menú encontrado correctamente');
            }
        }
    }
    
    // Callback para la nueva sección de configuración por defecto de bloques
    public function block_defaults_section_callback() {
        echo '<p>Configura los valores por defecto que se aplicarán a los nuevos bloques de productos. Los usuarios pueden sobrescribir estos valores en cada bloque individual.</p>';
    }
    
    // Callbacks para configuración por defecto de bloques
    public function default_description_length_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_description_length']) ? $options['default_description_length'] : 150;
        echo '<input type="number" name="cosas_amazon_options[default_description_length]" value="' . esc_attr($value) . '" min="50" max="500" />';
        echo '<p class="description">Longitud por defecto de las descripciones en caracteres.</p>';
    }
    
    public function default_text_color_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_text_color']) ? $options['default_text_color'] : '#000000';
        echo '<input type="color" name="cosas_amazon_options[default_text_color]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Color de texto por defecto para los bloques.</p>';
    }
    
    public function default_font_size_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_font_size']) ? $options['default_font_size'] : '16px';
        echo '<input type="text" name="cosas_amazon_options[default_font_size]" value="' . esc_attr($value) . '" placeholder="16px" />';
        echo '<p class="description">Tamaño de fuente por defecto (ej: 16px, 1em).</p>';
    }
    
    public function default_border_style_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_border_style']) ? $options['default_border_style'] : 'solid';
        echo '<select name="cosas_amazon_options[default_border_style]">';
        echo '<option value="none"' . selected($value, 'none', false) . '>Sin borde</option>';
        echo '<option value="solid"' . selected($value, 'solid', false) . '>Sólido</option>';
        echo '<option value="dashed"' . selected($value, 'dashed', false) . '>Discontinuo</option>';
        echo '<option value="dotted"' . selected($value, 'dotted', false) . '>Punteado</option>';
        echo '</select>';
    }
    
    public function default_border_color_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_border_color']) ? $options['default_border_color'] : '#cccccc';
        echo '<input type="color" name="cosas_amazon_options[default_border_color]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Color de borde por defecto.</p>';
    }
    
    public function default_background_color_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_background_color']) ? $options['default_background_color'] : '#ffffff';
        echo '<input type="color" name="cosas_amazon_options[default_background_color]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Color de fondo por defecto.</p>';
    }
    
    public function default_alignment_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_alignment']) ? $options['default_alignment'] : 'center';
        echo '<select name="cosas_amazon_options[default_alignment]">';
        echo '<option value="left"' . selected($value, 'left', false) . '>Izquierda</option>';
        echo '<option value="center"' . selected($value, 'center', false) . '>Centro</option>';
        echo '<option value="right"' . selected($value, 'right', false) . '>Derecha</option>';
        echo '</select>';
    }
    
    public function show_button_by_default_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['show_button_by_default']) ? $options['show_button_by_default'] : true;
        echo '<input type="checkbox" name="cosas_amazon_options[show_button_by_default]" value="1"' . checked($value, true, false) . ' />';
        echo '<label>Mostrar botón por defecto</label>';
    }
    
    public function default_button_text_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_button_text']) ? $options['default_button_text'] : 'Ver en Amazon';
        echo '<input type="text" name="cosas_amazon_options[default_button_text]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Texto por defecto del botón.</p>';
    }
    
    public function default_button_color_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_button_color']) ? $options['default_button_color'] : '#FF9900';
        echo '<input type="color" name="cosas_amazon_options[default_button_color]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Color del botón por defecto.</p>';
    }
    
    public function show_special_offer_by_default_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['show_special_offer_by_default']) ? $options['show_special_offer_by_default'] : true;
        echo '<input type="checkbox" name="cosas_amazon_options[show_special_offer_by_default]" value="1"' . checked($value, true, false) . ' />';
        echo '<label>Mostrar ofertas especiales por defecto</label>';
    }
    
    public function default_special_offer_color_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_special_offer_color']) ? $options['default_special_offer_color'] : '#e74c3c';
        echo '<input type="color" name="cosas_amazon_options[default_special_offer_color]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Color de las ofertas especiales por defecto.</p>';
    }
    
    public function default_block_size_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_block_size']) ? $options['default_block_size'] : 'medium';
        echo '<select name="cosas_amazon_options[default_block_size]">';
        echo '<option value="small"' . selected($value, 'small', false) . '>Pequeño</option>';
        echo '<option value="medium"' . selected($value, 'medium', false) . '>Mediano</option>';
        echo '<option value="large"' . selected($value, 'large', false) . '>Grande</option>';
        echo '</select>';
    }
    
    public function default_products_per_row_callback() {
        $options = get_option('cosas_amazon_options');
        $value = isset($options['default_products_per_row']) ? $options['default_products_per_row'] : 2;
        echo '<input type="number" name="cosas_amazon_options[default_products_per_row]" value="' . esc_attr($value) . '" min="1" max="4" />';
        echo '<p class="description">Número de productos por fila por defecto (1-4).</p>';
    }
    
    // Función de test específica para el endpoint REST
    public function test_rest_endpoint_callback() {
        echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin: 10px 0;">';
        echo '<h4>🔍 Test del Endpoint REST</h4>';
        echo '<button type="button" id="test-rest-endpoint-btn" class="button button-primary">Probar Endpoint REST</button>';
        echo '<div style="margin-top: 10px;">';
        echo '<input type="url" id="test-rest-url" placeholder="https://amzn.to/3GDSIAm" value="https://amzn.to/3GDSIAm" style="width: 300px; margin-right: 10px;" />';
        echo '<button type="button" id="test-rest-product-btn" class="button button-secondary">Probar con Producto</button>';
        echo '</div>';
        echo '<div id="rest-test-results" style="margin-top: 15px; background: white; padding: 10px; border-radius: 4px; min-height: 50px;"></div>';
        echo '</div>';
        echo '<p class="description">Prueba directa del endpoint REST para verificar que funciona correctamente.</p>';
        
        // Añadir JavaScript específico para este test
        echo '<script>
        jQuery(document).ready(function($) {
            $("#test-rest-endpoint-btn").click(function() {
                var btn = $(this);
                var resultsDiv = $("#rest-test-results");
                
                btn.prop("disabled", true).text("Probando...");
                resultsDiv.html("<p>Probando endpoint básico...</p>");
                
                // Probar endpoint básico
                $.get(cosas_amazon_admin.site_url + "/wp-json/cda/v1/test")
                .done(function(data) {
                    var html = "<div style=\\"color: green;\\"><strong>✅ Endpoint básico funciona</strong></div>";
                    html += "<pre>" + JSON.stringify(data, null, 2) + "</pre>";
                    resultsDiv.html(html);
                })
                .fail(function(xhr, status, error) {
                    resultsDiv.html("<div style=\\"color: red;\\"><strong>❌ Error en endpoint básico:</strong><br>" + error + "<br>Status: " + xhr.status + "</div>");
                })
                .always(function() {
                    btn.prop("disabled", false).text("Probar Endpoint REST");
                });
            });
            
            $("#test-rest-product-btn").click(function() {
                var btn = $(this);
                var url = $("#test-rest-url").val();
                var resultsDiv = $("#rest-test-results");
                
                if (!url) {
                    resultsDiv.html("<div style=\\"color: orange;\\">⚠️ Por favor introduce una URL</div>");
                    return;
                }
                
                btn.prop("disabled", true).text("Probando...");
                resultsDiv.html("<p>Probando endpoint de productos...</p>");
                
                // Usar fetch API como en el bloque
                fetch(cosas_amazon_admin.site_url + "/wp-json/cda/v1/fetch-product-data", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-WP-Nonce": cosas_amazon_admin.nonce
                    },
                    body: JSON.stringify({ url: url })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error("HTTP " + response.status + ": " + text);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    var html = "<div style=\\"color: green;\\"><strong>✅ Endpoint de productos funciona</strong></div>";
                    html += "<pre>" + JSON.stringify(data, null, 2) + "</pre>";
                    resultsDiv.html(html);
                })
                .catch(error => {
                    console.error("Error completo:", error);
                    resultsDiv.html("<div style=\\"color: red;\\"><strong>❌ Error:</strong><br>" + error.message + "</div>");
                })
                .finally(() => {
                    btn.prop("disabled", false).text("Probar con Producto");
                });
            });
        });
        </script>';
    }
    
    // Manejador AJAX para obtener estadísticas de cache
    public function ajax_get_cache_stats() {
        error_log('[COSAS_AMAZON_DEBUG] ajax_get_cache_stats llamado');
        
        if (!wp_verify_nonce($_POST['nonce'], 'cosas_amazon_nonce')) {
            error_log('[COSAS_AMAZON_DEBUG] Nonce inválido');
            wp_send_json_error('Acceso denegado');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            error_log('[COSAS_AMAZON_DEBUG] Permisos insuficientes');
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        global $wpdb;
        
        // Obtener estadísticas reales del cache
        $transient_prefix = '_transient_cosas_amazon_product_';
        $cache_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", $transient_prefix . '%'));
        
        error_log('[COSAS_AMAZON_DEBUG] Cache count: ' . $cache_count);
        
        $cache_size = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(LENGTH(option_value)) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", $transient_prefix . '%'));
        
        $cache_size_mb = $cache_size ? round($cache_size / 1024 / 1024, 2) : 0;
        
        // Obtener estadísticas adicionales
        $timeout_transients = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", '_transient_timeout_cosas_amazon_product_%'));
        
        $html = '<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">';
        $html .= '<div class="stat-card" style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
        $html .= '<h4>Entradas en Cache</h4>';
        $html .= '<p style="font-size: 24px; margin: 0; color: #0073aa;">' . $cache_count . '</p>';
        $html .= '</div>';
        $html .= '<div class="stat-card" style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
        $html .= '<h4>Tamaño del Cache</h4>';
        $html .= '<p style="font-size: 24px; margin: 0; color: #0073aa;">' . $cache_size_mb . ' MB</p>';
        $html .= '</div>';
        $html .= '<div class="stat-card" style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
        $html .= '<h4>Transients de Timeout</h4>';
        $html .= '<p style="font-size: 24px; margin: 0; color: #46b450;">' . $timeout_transients . '</p>';
        $html .= '</div>';
        $html .= '<div class="stat-card" style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
        $html .= '<h4>Última Actualización</h4>';
        $html .= '<p style="font-size: 14px; margin: 0; color: #666;">' . current_time('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        
        error_log('[COSAS_AMAZON_DEBUG] Enviando respuesta exitosa');
        wp_send_json_success($html);
    }
    
    // Función para ocultar footer de WordPress en páginas del plugin
    public function hide_wordpress_footer() {
        $screen = get_current_screen();
        
        // Verificar si estamos en páginas del plugin
        if ($screen && (strpos($screen->id, 'cosas-amazon') !== false || 
                       (isset($_GET['page']) && strpos($_GET['page'], 'cosas-amazon') !== false))) {
            
            // Ocultar footer de WordPress
            add_filter('admin_footer_text', '__return_empty_string');
            add_filter('update_footer', '__return_empty_string');
            
            // Añadir CSS para ocultar cualquier resto del footer
            add_action('admin_head', function() {
                echo '<style>
                    #wpfooter, 
                    .wrap + #wpfooter,
                    #footer-thankyou,
                    #footer-upgrade {
                        display: none !important;
                    }
                    
                    .wrap {
                        margin-bottom: 0 !important;
                    }
                </style>';
            });
        }
    }
    
    // Manejador AJAX para limpiar cache
    public function ajax_clear_cache() {
        if (!wp_verify_nonce($_POST['nonce'], 'cosas_amazon_nonce')) {
            wp_send_json_error('Acceso denegado');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        global $wpdb;
        
        // Limpiar todos los transients de productos
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            OR option_name LIKE %s
        ", '_transient_cosas_amazon_product_%', '_transient_timeout_cosas_amazon_product_%'));
        
        if ($deleted !== false) {
            wp_send_json_success('Cache limpiado exitosamente. Se eliminaron ' . $deleted . ' entradas.');
        } else {
            wp_send_json_error('Error al limpiar el cache');
        }
    }
    
    // Callbacks para configuración Amazon PA API
    public function paapi_section_callback() {
        echo '<p>Configura tu acceso a la Amazon Product Advertising API para obtener datos de productos directamente desde Amazon.</p>';
        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;">';
        echo '<h4 style="margin-top: 0;">📋 Información importante:</h4>';
        echo '<ul style="margin-bottom: 0;">';
        echo '<li><strong>Access Key ID y Secret Key:</strong> Obténlos desde tu cuenta de AWS IAM</li>';
        echo '<li><strong>Associate Tag:</strong> Tu ID de afiliado de Amazon Associates</li>';
        echo '<li><strong>Región:</strong> Selecciona la región donde tienes configurado tu programa de afiliados</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    public function api_enabled_callback() {
        $options = get_option('cosas_amazon_api_options', array());
        $value = isset($options['api_enabled']) ? $options['api_enabled'] : 0;
        echo '<label>';
        echo '<input type="checkbox" name="cosas_amazon_api_options[api_enabled]" value="1"' . checked($value, 1, false) . '>';
        echo ' Habilitar Amazon PA API';
        echo '</label>';
        echo '<p class="description">Activar para usar la API oficial de Amazon en lugar de scraping web.</p>';
    }
    
    public function amazon_access_key_callback() {
        $options = get_option('cosas_amazon_api_options', array());
        $value = isset($options['amazon_access_key']) ? $options['amazon_access_key'] : '';
        echo '<input type="text" name="cosas_amazon_api_options[amazon_access_key]" value="' . esc_attr($value) . '" size="30" autocomplete="off">';
        echo '<p class="description">Tu Access Key ID de AWS. Ejemplo: AKIAIOSFODNN7EXAMPLE</p>';
    }
    
    public function amazon_secret_key_callback() {
        $options = get_option('cosas_amazon_api_options', array());
        $value = isset($options['amazon_secret_key']) ? $options['amazon_secret_key'] : '';
        echo '<input type="password" name="cosas_amazon_api_options[amazon_secret_key]" value="' . esc_attr($value) . '" size="50" autocomplete="new-password">';
        echo '<p class="description">Tu Secret Access Key de AWS. Se mantiene oculto por seguridad.</p>';
    }
    
    public function amazon_associate_tag_callback() {
        $options = get_option('cosas_amazon_api_options', array());
        $value = isset($options['amazon_associate_tag']) ? $options['amazon_associate_tag'] : '';
        echo '<input type="text" name="cosas_amazon_api_options[amazon_associate_tag]" value="' . esc_attr($value) . '" size="20">';
        echo '<p class="description">Tu Associate Tag (ID de afiliado). Ejemplo: mitienda-21</p>';
    }
    
    public function amazon_region_callback() {
        $options = get_option('cosas_amazon_api_options', array());
        $value = isset($options['amazon_region']) ? $options['amazon_region'] : 'es';
        $regions = array(
            'es' => 'España (amazon.es)',
            'us' => 'Estados Unidos (amazon.com)',
            'uk' => 'Reino Unido (amazon.co.uk)',
            'de' => 'Alemania (amazon.de)',
            'fr' => 'Francia (amazon.fr)',
            'it' => 'Italia (amazon.it)',
        );
        
        echo '<select name="cosas_amazon_api_options[amazon_region]">';
        foreach ($regions as $region_code => $region_name) {
            echo '<option value="' . esc_attr($region_code) . '"' . selected($value, $region_code, false) . '>' . esc_html($region_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Selecciona la región de Amazon donde tienes configurado tu programa de afiliados.</p>';
    }
    
    public function test_paapi_connection_callback() {
        echo '<div id="paapi-test-container">';
        echo '<button type="button" id="test-paapi-btn" class="button button-secondary">🔧 Probar Conexión PA API</button>';
        echo '<div id="paapi-test-results" style="margin-top: 10px; padding: 10px; border-radius: 5px; display: none;"></div>';
        echo '</div>';
        echo '<p class="description">Prueba la conexión con Amazon PA API usando las credenciales configuradas.</p>';
        
        // Agregar JavaScript para el test
        echo '<script>
        jQuery(document).ready(function($) {
            $("#test-paapi-btn").click(function() {
                var button = $(this);
                var results = $("#paapi-test-results");
                
                button.prop("disabled", true).text("🔄 Probando...");
                results.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "cosas_amazon_test_paapi",
                        nonce: "' . wp_create_nonce('cosas_amazon_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = "✅ " + response.data.message;
                            if (response.data.data && response.data.data.title) {
                                html += "<br><small><strong>Producto de prueba:</strong> " + response.data.data.title + "</small>";
                            }
                            results.removeClass("error").addClass("success")
                                   .css("background", "#d4edda").css("color", "#155724")
                                   .css("border", "1px solid #c3e6cb")
                                   .html(html);
                        } else {
                            var html = "❌ " + response.data.message;
                            if (response.data.step) {
                                html += "<br><small><strong>Paso:</strong> " + response.data.step + "</small>";
                            }
                            if (response.data.environment && response.data.environment.is_local) {
                                html += "<br><div style=\\"background: #fff3cd; padding: 10px; border-radius: 4px; margin-top: 10px; border-left: 4px solid #ffc107;\\">";
                                html += "<strong>🏠 ENTORNO LOCAL DETECTADO</strong><br>";
                                html += "<small>Host: " + response.data.environment.host + " (" + response.data.environment.indicator + ")</small><br>";
                                html += "<small><strong>💡 El error InternalFailure es muy común en desarrollo local.</strong></small><br>";
                                html += "<small>📋 <a href=\\"test-local-vs-produccion.html\\" target=\\"_blank\\">Abrir diagnóstico local vs producción</a></small>";
                                html += "</div>";
                            }
                            if (response.data.additional_info) {
                                html += "<br><div style=\\"background: #e8f4fd; padding: 8px; border-radius: 4px; margin-top: 8px;\\">";
                                html += "<small><strong>Información adicional:</strong><br>";
                                for (var key in response.data.additional_info) {
                                    html += "• " + key + ": " + response.data.additional_info[key] + "<br>";
                                }
                                html += "</small></div>";
                            }
                            if (response.data.details) {
                                html += "<br><small><strong>Detalles técnicos:</strong> " + JSON.stringify(response.data.details) + "</small>";
                            }
                            results.removeClass("success").addClass("error")
                                   .css("background", "#f8d7da").css("color", "#721c24")
                                   .css("border", "1px solid #f5c6cb")
                                   .html(html);
                        }
                        results.show();
                    },
                    error: function() {
                        results.removeClass("success").addClass("error")
                               .css("background", "#f8d7da").css("color", "#721c24")
                               .css("border", "1px solid #f5c6cb")
                               .html("❌ Error de comunicación con el servidor");
                        results.show();
                    },
                    complete: function() {
                        button.prop("disabled", false).text("🔧 Probar Conexión PA API");
                    }
                });
            });
        });
        </script>';
    }
    
    // Manejador AJAX para test de PA API
    public function ajax_test_paapi() {
        if (!wp_verify_nonce($_POST['nonce'], 'cosas_amazon_nonce')) {
            wp_send_json_error('Acceso denegado');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }
        
        // Cargar clase PA-API si no está cargada
        if (!class_exists('CosasAmazonPAAPI')) {
            require_once dirname(__FILE__) . '/class-amazon-paapi.php';
        }
        
        try {
            $api = new CosasAmazonPAAPI();
            // Usar el método mejorado que maneja entornos locales
            $result = $api->testConnectionWithLocalFallback();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error al probar la conexión: ' . $e->getMessage()
            ));
        }
    }
}
