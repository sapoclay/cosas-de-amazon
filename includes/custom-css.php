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
        // Usar prioridad alta para ejecutar después de que se registren/encolen los estilos base
        add_action('wp_enqueue_scripts', array($this, 'enqueue_custom_styles'), 99);
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_custom_styles'), 99);
    }
    
    /**
     * Encolar estilos personalizados
     */
    public function enqueue_custom_styles() {
        $custom_css = $this->generate_custom_css();
        if (empty($custom_css)) {
            return;
        }

        // Frontend: adjuntar después del CSS base del plugin
        if (!is_admin()) {
            // Asegurar que el handle base esté encolado antes de añadir inline
            if (!wp_style_is('cosas-amazon-block-style', 'enqueued')) {
                wp_enqueue_style('cosas-amazon-block-style');
            }
            wp_add_inline_style('cosas-amazon-block-style', $custom_css);
        }

        // Editor (Gutenberg): adjuntar al CSS del editor del bloque
        if (is_admin()) {
            if (!wp_style_is('cosas-amazon-block-editor-style', 'enqueued')) {
                wp_enqueue_style('cosas-amazon-block-editor-style');
            }
            wp_add_inline_style('cosas-amazon-block-editor-style', $custom_css);
        }
    }
    
    /**
     * Generar CSS personalizado basado en las opciones
     */
    public function generate_custom_css() {
        $options = get_option('cosas_amazon_options', array());
        $css = '';

        // Mapear opciones reales del plugin a variables locales
        $text_color       = isset($options['default_text_color']) ? sanitize_hex_color($options['default_text_color']) : '#000000';
        $bg_color         = isset($options['default_background_color']) ? sanitize_hex_color($options['default_background_color']) : '#ffffff';
        $border_color     = isset($options['default_border_color']) ? sanitize_hex_color($options['default_border_color']) : '#cccccc';
        $button_color     = isset($options['default_button_color']) ? sanitize_hex_color($options['default_button_color']) : '#FF9900';
        $offer_color      = isset($options['default_special_offer_color']) ? sanitize_hex_color($options['default_special_offer_color']) : '#e74c3c';
        // Usamos el color por defecto de precio del bloque si no hay preferencia global
        $price_color      = '#B12704';
        $original_color   = '#999999';
        $discount_bg      = isset($options['accent_color']) ? sanitize_hex_color($options['accent_color']) : '#d93025';

        // Definir variables a nivel root y en el wrapper del editor
        $css .= ':root, .editor-styles-wrapper {';
        $css .= '--cda-text: ' . $text_color . ';';
        $css .= '--cda-bg: ' . $bg_color . ';';
        $css .= '--cda-border: ' . $border_color . ';';
        $css .= '--cda-button: ' . $button_color . ';';
        $css .= '--cda-offer: ' . $offer_color . ';';
        $css .= '--cda-price: ' . $price_color . ';';
        $css .= '--cda-original: ' . $original_color . ';';
        $css .= '--cda-discount-bg: ' . $discount_bg . ';';
        $css .= '}';

        // Estilos para las clases actuales del bloque (frontend y editor)
        $selectors_prefix = '.editor-styles-wrapper ';
        $both = function($sel) use ($selectors_prefix) {
            return $sel . ', ' . $selectors_prefix . $sel;
        };

        // Contenedor de tarjeta
        $css .= $both('.cosas-amazon-product') . '{'
              . 'background-color: var(--cda-bg);'
              . 'color: var(--cda-text);'
              . '}';

        // Título enlazado
        $css .= $both('.cosas-amazon-title a') . '{'
              . 'color: var(--cda-text);'
              . 'text-decoration: none;'
              . '}';

        // Precio actual
        $css .= $both('.cosas-amazon-price') . '{'
              . 'color: var(--cda-price);'
              . '}';

        // Precio original
        $css .= $both('.cosas-amazon-original-price') . '{'
              . 'color: var(--cda-original);'
              . '}';

        // Descuento
        $css .= $both('.cosas-amazon-discount') . '{'
              . 'background-color: var(--cda-discount-bg);'
              . 'color: #fff;'
              . '}';

        // Botón
        $css .= $both('.cosas-amazon-btn') . '{'
              . 'background-color: var(--cda-button);'
              . 'color: #fff;'
              . '}';

        // Etiqueta de oferta
        $css .= $both('.cosas-amazon-special-offer span') . '{'
              . 'background-color: var(--cda-offer);'
              . 'color: #fff;'
              . '}';

        return $css;
    }
    
    /**
     * Generar estilos específicos para elementos
     */
    private function generate_element_styles($options) {
        // Mantener método por compatibilidad; ahora las reglas se generan en generate_custom_css()
        return '';
    }
}

// Inicializar la clase
new CosasAmazonCustomCSS();
