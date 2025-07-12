<?php
/**
 * Sistema de personalización visual para Cosas de Amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonCustomizer {
    
    public function __construct() {
        add_action('customize_register', array($this, 'register_customizer_settings'));
        add_action('wp_head', array($this, 'output_custom_styles'));
        add_action('wp_ajax_save_visual_preset', array($this, 'save_visual_preset'));
        add_action('wp_ajax_load_visual_preset', array($this, 'load_visual_preset'));
    }
    
    /**
     * Registrar configuraciones del customizer
     */
    public function register_customizer_settings($wp_customize) {
        // Panel principal
        $wp_customize->add_panel('cosas_amazon_panel', array(
            'title' => 'Cosas de Amazon',
            'description' => 'Personaliza la apariencia de los productos de Amazon',
            'priority' => 160
        ));
        
        // Sección de colores
        $wp_customize->add_section('cosas_amazon_colors', array(
            'title' => 'Colores',
            'panel' => 'cosas_amazon_panel'
        ));
        
        // Color principal
        $wp_customize->add_setting('cosas_amazon_primary_color', array(
            'default' => '#ff9500',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'cosas_amazon_primary_color', array(
            'label' => 'Color principal',
            'section' => 'cosas_amazon_colors'
        )));
        
        // Color secundario
        $wp_customize->add_setting('cosas_amazon_secondary_color', array(
            'default' => '#ff7b00',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'cosas_amazon_secondary_color', array(
            'label' => 'Color secundario',
            'section' => 'cosas_amazon_colors'
        )));
        
        // Color de texto
        $wp_customize->add_setting('cosas_amazon_text_color', array(
            'default' => '#333333',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'cosas_amazon_text_color', array(
            'label' => 'Color de texto',
            'section' => 'cosas_amazon_colors'
        )));
        
        // Color de fondo
        $wp_customize->add_setting('cosas_amazon_bg_color', array(
            'default' => '#ffffff',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'cosas_amazon_bg_color', array(
            'label' => 'Color de fondo',
            'section' => 'cosas_amazon_colors'
        )));
        
        // Sección de tipografía
        $wp_customize->add_section('cosas_amazon_typography', array(
            'title' => 'Tipografía',
            'panel' => 'cosas_amazon_panel'
        ));
        
        // Fuente del título
        $wp_customize->add_setting('cosas_amazon_title_font', array(
            'default' => 'inherit',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        $wp_customize->add_control('cosas_amazon_title_font', array(
            'label' => 'Fuente del título',
            'section' => 'cosas_amazon_typography',
            'type' => 'select',
            'choices' => array(
                'inherit' => 'Heredar del tema',
                'Arial, sans-serif' => 'Arial',
                'Georgia, serif' => 'Georgia',
                'Times, serif' => 'Times',
                'Helvetica, sans-serif' => 'Helvetica',
                'Roboto, sans-serif' => 'Roboto',
                'Open Sans, sans-serif' => 'Open Sans'
            )
        ));
        
        // Tamaño del título
        $wp_customize->add_setting('cosas_amazon_title_size', array(
            'default' => '18',
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('cosas_amazon_title_size', array(
            'label' => 'Tamaño del título (px)',
            'section' => 'cosas_amazon_typography',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 12,
                'max' => 36,
                'step' => 1
            )
        ));
        
        // Sección de espaciado
        $wp_customize->add_section('cosas_amazon_spacing', array(
            'title' => 'Espaciado',
            'panel' => 'cosas_amazon_panel'
        ));
        
        // Padding interno
        $wp_customize->add_setting('cosas_amazon_padding', array(
            'default' => '20',
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('cosas_amazon_padding', array(
            'label' => 'Espaciado interno (px)',
            'section' => 'cosas_amazon_spacing',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 0,
                'max' => 50,
                'step' => 5
            )
        ));
        
        // Margen externo
        $wp_customize->add_setting('cosas_amazon_margin', array(
            'default' => '20',
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('cosas_amazon_margin', array(
            'label' => 'Margen externo (px)',
            'section' => 'cosas_amazon_spacing',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 0,
                'max' => 50,
                'step' => 5
            )
        ));
        
        // Radio de bordes
        $wp_customize->add_setting('cosas_amazon_border_radius', array(
            'default' => '8',
            'sanitize_callback' => 'absint'
        ));
        
        $wp_customize->add_control('cosas_amazon_border_radius', array(
            'label' => 'Radio de bordes (px)',
            'section' => 'cosas_amazon_spacing',
            'type' => 'number',
            'input_attrs' => array(
                'min' => 0,
                'max' => 20,
                'step' => 1
            )
        ));
        
        // Sección de efectos
        $wp_customize->add_section('cosas_amazon_effects', array(
            'title' => 'Efectos',
            'panel' => 'cosas_amazon_panel'
        ));
        
        // Sombra
        $wp_customize->add_setting('cosas_amazon_shadow', array(
            'default' => true,
            'sanitize_callback' => 'wp_validate_boolean'
        ));
        
        $wp_customize->add_control('cosas_amazon_shadow', array(
            'label' => 'Mostrar sombra',
            'section' => 'cosas_amazon_effects',
            'type' => 'checkbox'
        ));
        
        // Hover effect
        $wp_customize->add_setting('cosas_amazon_hover_effect', array(
            'default' => true,
            'sanitize_callback' => 'wp_validate_boolean'
        ));
        
        $wp_customize->add_control('cosas_amazon_hover_effect', array(
            'label' => 'Efecto hover',
            'section' => 'cosas_amazon_effects',
            'type' => 'checkbox'
        ));
        
        // Animaciones
        $wp_customize->add_setting('cosas_amazon_animations', array(
            'default' => true,
            'sanitize_callback' => 'wp_validate_boolean'
        ));
        
        $wp_customize->add_control('cosas_amazon_animations', array(
            'label' => 'Habilitar animaciones',
            'section' => 'cosas_amazon_effects',
            'type' => 'checkbox'
        ));
        
        // Sección de contenido
        $wp_customize->add_section('cosas_amazon_content', array(
            'title' => 'Contenido',
            'panel' => 'cosas_amazon_panel'
        ));
        
        // Mostrar valoraciones
        $wp_customize->add_setting('cosas_amazon_show_ratings', array(
            'default' => true,
            'sanitize_callback' => 'wp_validate_boolean'
        ));
        
        $wp_customize->add_control('cosas_amazon_show_ratings', array(
            'label' => 'Mostrar valoraciones (estrellas)',
            'description' => 'Muestra las valoraciones y número de reseñas de los productos',
            'section' => 'cosas_amazon_content',
            'type' => 'checkbox'
        ));
    }
    
    /**
     * Generar CSS personalizado
     */
    public function output_custom_styles() {
        $primary_color = get_theme_mod('cosas_amazon_primary_color', '#ff9500');
        $secondary_color = get_theme_mod('cosas_amazon_secondary_color', '#ff7b00');
        $text_color = get_theme_mod('cosas_amazon_text_color', '#333333');
        $bg_color = get_theme_mod('cosas_amazon_bg_color', '#ffffff');
        $title_font = get_theme_mod('cosas_amazon_title_font', 'inherit');
        $title_size = get_theme_mod('cosas_amazon_title_size', 18);
        $padding = get_theme_mod('cosas_amazon_padding', 20);
        $margin = get_theme_mod('cosas_amazon_margin', 20);
        $border_radius = get_theme_mod('cosas_amazon_border_radius', 8);
        $shadow = get_theme_mod('cosas_amazon_shadow', true);
        $hover_effect = get_theme_mod('cosas_amazon_hover_effect', true);
        $animations = get_theme_mod('cosas_amazon_animations', true);
        $show_ratings = get_theme_mod('cosas_amazon_show_ratings', true);
        
        $css = "<style id='cosas-amazon-custom-styles'>";
        
        // Estilos base
        $css .= "
        .cosas-amazon-product {
            background-color: {$bg_color} !important;
            color: {$text_color} !important;
            padding: {$padding}px !important;
            margin: {$margin}px 0 !important;
            border-radius: {$border_radius}px !important;
        }
        
        .cosas-amazon-title {
            color: {$text_color} !important;
            font-family: {$title_font} !important;
            font-size: {$title_size}px !important;
        }
        
        .cosas-amazon-btn {
            background: linear-gradient(135deg, {$primary_color} 0%, {$secondary_color} 100%) !important;
            border-radius: {$border_radius}px !important;
        }
        
        .cosas-amazon-btn:hover {
            background: linear-gradient(135deg, {$secondary_color} 0%, {$primary_color} 100%) !important;
        }
        
        .cosas-amazon-discount {
            background: {$primary_color} !important;
        }
        
        .cosas-amazon-special-offer {
            background: {$bg_color} !important;
        }
        
        .cosas-amazon-special-offer span {
            background: {$primary_color};
        }
        ";
        
        // Sombra
        if ($shadow) {
            $css .= "
            .cosas-amazon-product {
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
            }
            ";
        } else {
            $css .= "
            .cosas-amazon-product {
                box-shadow: none !important;
            }
            ";
        }
        
        // Efecto hover
        if ($hover_effect) {
            $css .= "
            .cosas-amazon-product:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15) !important;
            }
            ";
        } else {
            $css .= "
            .cosas-amazon-product:hover {
                transform: none !important;
            }
            ";
        }
        
        // Animaciones
        if ($animations) {
            $css .= "
            .cosas-amazon-product {
                transition: all 0.2s ease !important;
            }
            
            .cosas-amazon-btn {
                transition: all 0.2s ease !important;
            }
            ";
        } else {
            $css .= "
            .cosas-amazon-product,
            .cosas-amazon-btn {
                transition: none !important;
            }
            ";
        }
        
        // Control de visibilidad de valoraciones
        if (!$show_ratings) {
            $css .= "
            .cosas-amazon-rating {
                display: none !important;
            }
            ";
        }
        
        $css .= "</style>";
        
        echo $css;
    }
    
    /**
     * Guardar preset visual
     */
    public function save_visual_preset() {
        check_ajax_referer('visual_preset_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        $preset_name = sanitize_text_field($_POST['preset_name']);
        $preset_data = array(
            'primary_color' => sanitize_hex_color($_POST['primary_color']),
            'secondary_color' => sanitize_hex_color($_POST['secondary_color']),
            'text_color' => sanitize_hex_color($_POST['text_color']),
            'bg_color' => sanitize_hex_color($_POST['bg_color']),
            'title_font' => sanitize_text_field($_POST['title_font']),
            'title_size' => absint($_POST['title_size']),
            'padding' => absint($_POST['padding']),
            'margin' => absint($_POST['margin']),
            'border_radius' => absint($_POST['border_radius']),
            'shadow' => wp_validate_boolean($_POST['shadow']),
            'hover_effect' => wp_validate_boolean($_POST['hover_effect']),
            'animations' => wp_validate_boolean($_POST['animations']),
            'show_ratings' => wp_validate_boolean($_POST['show_ratings'])
        );
        
        $presets = get_option('cosas_amazon_visual_presets', array());
        $presets[$preset_name] = $preset_data;
        
        update_option('cosas_amazon_visual_presets', $presets);
        
        wp_send_json_success(array('message' => 'Preset guardado correctamente'));
    }
    
    /**
     * Cargar preset visual
     */
    public function load_visual_preset() {
        check_ajax_referer('visual_preset_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        $preset_name = sanitize_text_field($_POST['preset_name']);
        $presets = get_option('cosas_amazon_visual_presets', array());
        
        if (!isset($presets[$preset_name])) {
            wp_send_json_error('Preset no encontrado');
            return;
        }
        
        wp_send_json_success($presets[$preset_name]);
    }
    
    /**
     * Obtener presets predefinidos
     */
    public static function get_default_presets() {
        return array(
            'default' => array(
                'name' => 'Por defecto',
                'primary_color' => '#ff9500',
                'secondary_color' => '#ff7b00',
                'text_color' => '#333333',
                'bg_color' => '#ffffff',
                'title_font' => 'inherit',
                'title_size' => 18,
                'padding' => 20,
                'margin' => 20,
                'border_radius' => 8,
                'shadow' => true,
                'hover_effect' => true,
                'animations' => true,
                'show_ratings' => true
            ),
            'minimal' => array(
                'name' => 'Minimalista',
                'primary_color' => '#000000',
                'secondary_color' => '#333333',
                'text_color' => '#333333',
                'bg_color' => '#ffffff',
                'title_font' => 'Helvetica, sans-serif',
                'title_size' => 16,
                'padding' => 15,
                'margin' => 15,
                'border_radius' => 0,
                'shadow' => false,
                'hover_effect' => false,
                'animations' => false,
                'show_ratings' => true
            ),
            'colorful' => array(
                'name' => 'Colorido',
                'primary_color' => '#e74c3c',
                'secondary_color' => '#c0392b',
                'text_color' => '#2c3e50',
                'bg_color' => '#ecf0f1',
                'title_font' => 'Roboto, sans-serif',
                'title_size' => 20,
                'padding' => 25,
                'margin' => 25,
                'border_radius' => 15,
                'shadow' => true,
                'hover_effect' => true,
                'animations' => true,
                'show_ratings' => true
            ),
            'elegant' => array(
                'name' => 'Elegante',
                'primary_color' => '#8e44ad',
                'secondary_color' => '#9b59b6',
                'text_color' => '#2c3e50',
                'bg_color' => '#ffffff',
                'title_font' => 'Georgia, serif',
                'title_size' => 19,
                'padding' => 30,
                'margin' => 20,
                'border_radius' => 12,
                'shadow' => true,
                'hover_effect' => true,
                'animations' => true,
                'show_ratings' => true
            )
        );
    }
}

// Nota: La inicialización se hace en el archivo principal cosas-de-amazon.php
