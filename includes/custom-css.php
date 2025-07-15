<?php
/**
 * Generador de CSS dinámico para Cosas de Amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonCustomCSS {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_custom_styles'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_custom_styles'));
    }
    
    /**
     * Encolar estilos personalizados
     */
    public function enqueue_custom_styles() {
        $custom_css = $this->generate_custom_css();
        
        if (!empty($custom_css)) {
            wp_add_inline_style('cosas-amazon-block-style', $custom_css);
        }
    }
    
    /**
     * Generar CSS personalizado basado en las opciones
     */
    public function generate_custom_css() {
        $options = get_option('cosas_amazon_options');
        $css = '';
        
        // Variables CSS para facilitar la personalización
        $css .= ':root {';
        
        // Colores
        if (isset($options['primary_color'])) {
            $css .= '--cosas-amazon-primary: ' . sanitize_hex_color($options['primary_color']) . ';';
        }
        if (isset($options['secondary_color'])) {
            $css .= '--cosas-amazon-secondary: ' . sanitize_hex_color($options['secondary_color']) . ';';
        }
        if (isset($options['accent_color'])) {
            $css .= '--cosas-amazon-accent: ' . sanitize_hex_color($options['accent_color']) . ';';
        }
        if (isset($options['text_color'])) {
            $css .= '--cosas-amazon-text: ' . sanitize_hex_color($options['text_color']) . ';';
        }
        if (isset($options['background_color'])) {
            $css .= '--cosas-amazon-bg: ' . sanitize_hex_color($options['background_color']) . ';';
        }
        
        // Tipografía
        if (isset($options['title_size'])) {
            $css .= '--cosas-amazon-title-size: ' . intval($options['title_size']) . 'px;';
        }
        if (isset($options['text_size'])) {
            $css .= '--cosas-amazon-text-size: ' . intval($options['text_size']) . 'px;';
        }
        if (isset($options['price_size'])) {
            $css .= '--cosas-amazon-price-size: ' . intval($options['price_size']) . 'px;';
        }
        
        // Espaciado
        if (isset($options['card_padding'])) {
            $css .= '--cosas-amazon-padding: ' . intval($options['card_padding']) . 'px;';
        }
        if (isset($options['card_margin'])) {
            $css .= '--cosas-amazon-margin: ' . intval($options['card_margin']) . 'px;';
        }
        if (isset($options['border_radius'])) {
            $css .= '--cosas-amazon-radius: ' . intval($options['border_radius']) . 'px;';
        }
        if (isset($options['image_size'])) {
            $css .= '--cosas-amazon-image-size: ' . intval($options['image_size']) . 'px;';
        }
        
        // Efectos
        $animation_speed = isset($options['animation_speed']) ? $options['animation_speed'] : 'normal';
        $speed_values = array(
            'slow' => '0.5s',
            'normal' => '0.3s',
            'fast' => '0.15s'
        );
        $css .= '--cosas-amazon-animation-speed: ' . $speed_values[$animation_speed] . ';';
        
        $css .= '}';
        
        // Aplicar estilos a los elementos
        $css .= $this->generate_element_styles($options);
        
        return $css;
    }
    
    /**
     * Generar estilos específicos para elementos
     */
    private function generate_element_styles($options) {
        $css = '';
        
        // Estilos base de las tarjetas
        $css .= '.wp-block-cosas-amazon-producto-amazon {';
        $css .= 'background-color: var(--cosas-amazon-bg, #ffffff);';
        $css .= 'color: var(--cosas-amazon-text, #333333);';
        $css .= 'padding: var(--cosas-amazon-padding, 15px);';
        $css .= 'margin: var(--cosas-amazon-margin, 10px) 0;';
        $css .= 'border-radius: var(--cosas-amazon-radius, 8px);';
        $css .= 'transition: all var(--cosas-amazon-animation-speed, 0.3s) ease;';
        $css .= '}';
        
        // Títulos
        $css .= '.wp-block-cosas-amazon-producto-amazon .amazon-product-title {';
        $css .= 'font-size: var(--cosas-amazon-title-size, 18px);';
        $css .= 'color: var(--cosas-amazon-text, #333333);';
        $css .= '}';
        
        // Texto
        $css .= '.wp-block-cosas-amazon-producto-amazon .amazon-product-description {';
        $css .= 'font-size: var(--cosas-amazon-text-size, 14px);';
        $css .= 'color: var(--cosas-amazon-secondary, #666666);';
        $css .= '}';
        
        // Precios
        $css .= '.wp-block-cosas-amazon-producto-amazon .amazon-product-price {';
        $css .= 'font-size: var(--cosas-amazon-price-size, 16px);';
        $css .= 'color: var(--cosas-amazon-accent, #ff9900);';
        $css .= 'font-weight: bold;';
        $css .= '}';
        
        // Botones
        $css .= '.wp-block-cosas-amazon-producto-amazon .amazon-product-button {';
        $css .= 'background-color: var(--cosas-amazon-primary, #e47911);';
        $css .= 'color: #ffffff;';
        $css .= 'border-radius: var(--cosas-amazon-radius, 8px);';
        $css .= 'transition: all var(--cosas-amazon-animation-speed, 0.3s) ease;';
        $css .= '}';
        
        $css .= '.wp-block-cosas-amazon-producto-amazon .amazon-product-button:hover {';
        $css .= 'background-color: var(--cosas-amazon-secondary, #232f3e);';
        $css .= '}';
        
        // Imágenes
        $css .= '.wp-block-cosas-amazon-producto-amazon .amazon-product-image img {';
        $css .= 'max-width: var(--cosas-amazon-image-size, 150px);';
        $css .= 'border-radius: var(--cosas-amazon-radius, 8px);';
        $css .= '}';
        
        // Familia de fuente
        if (isset($options['font_family']) && $options['font_family'] !== 'default') {
            $font_families = array(
                'system' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                'arial' => 'Arial, sans-serif',
                'helvetica' => 'Helvetica, Arial, sans-serif',
                'roboto' => '"Roboto", sans-serif',
                'open-sans' => '"Open Sans", sans-serif'
            );
            
            if (isset($font_families[$options['font_family']])) {
                $css .= '.wp-block-cosas-amazon-producto-amazon {';
                $css .= 'font-family: ' . $font_families[$options['font_family']] . ';';
                $css .= '}';
            }
        }
        
        // Efectos hover
        if (isset($options['hover_effect'])) {
            switch ($options['hover_effect']) {
                case 'scale':
                    $css .= '.wp-block-cosas-amazon-producto-amazon:hover {';
                    $css .= 'transform: scale(1.02);';
                    $css .= '}';
                    break;
                    
                case 'lift':
                    $css .= '.wp-block-cosas-amazon-producto-amazon:hover {';
                    $css .= 'transform: translateY(-5px);';
                    $css .= '}';
                    break;
                    
                case 'glow':
                    $css .= '.wp-block-cosas-amazon-producto-amazon:hover {';
                    $css .= 'box-shadow: 0 0 20px rgba(228, 121, 17, 0.3);';
                    $css .= '}';
                    break;
            }
        }
        
        // Estilos de sombra
        if (isset($options['shadow_style'])) {
            $shadows = array(
                'light' => '0 2px 4px rgba(0,0,0,0.1)',
                'medium' => '0 4px 8px rgba(0,0,0,0.15)',
                'strong' => '0 8px 16px rgba(0,0,0,0.2)'
            );
            
            if (isset($shadows[$options['shadow_style']])) {
                $css .= '.wp-block-cosas-amazon-producto-amazon {';
                $css .= 'box-shadow: ' . $shadows[$options['shadow_style']] . ';';
                $css .= '}';
            }
        }
        
        // Gradientes
        if (isset($options['gradient_enable']) && $options['gradient_enable']) {
            $css .= '.wp-block-cosas-amazon-producto-amazon {';
            $css .= 'background: linear-gradient(135deg, var(--cosas-amazon-bg, #ffffff) 0%, rgba(228, 121, 17, 0.05) 100%);';
            $css .= '}';
        }
        
        return $css;
    }
}

// Inicializar la clase
new CosasAmazonCustomCSS();
