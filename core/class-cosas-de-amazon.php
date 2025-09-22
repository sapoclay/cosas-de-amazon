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
    add_action('cosas_amazon_daily_price_update', array($this, 'daily_price_update'), 10, 1);
    add_action('cosas_amazon_force_price_update', array($this, 'daily_price_update'), 10, 1);
    }

    /**
     * Manejador del cron diario y del trigger de fuerza.
     * Recolecta URLs de productos y fuerza la actualización de caché de precios/descuentos.
     */
    public function daily_price_update($args = array()) {
        try {
            $args = is_array($args) ? $args : array();
            $stats = self::run_bulk_price_refresh($args);
            update_option('cosas_amazon_last_update', $stats);
        } catch (\Throwable $e) {
            error_log('[CosasDeAmazon][daily_price_update] ' . $e->getMessage());
        }
    }

    /**
     * Ejecuta una actualización en lote de precios/descuentos.
     * args:
     * - limit: número máximo de URLs a procesar (por defecto 50)
     * - sleep: segundos de espera entre peticiones (por defecto 1)
     * - source_urls: array de URLs a procesar (opcional)
     * Devuelve array con estadísticas.
     */
    public static function run_bulk_price_refresh($args = array()) {
        if (!class_exists('CosasAmazonHelpers')) {
            require_once dirname(__FILE__) . '/../includes/helpers.php';
        }

        $defaults = array(
            'limit' => 50,
            'sleep' => 1,
            'source_urls' => null,
        );
        $args = wp_parse_args($args, $defaults);

        $start = microtime(true);
        $urls = array();
        if (is_array($args['source_urls'])) {
            $urls = $args['source_urls'];
        } else {
            $urls = self::collect_product_urls();
        }
        $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));

        $limit = intval($args['limit']);
        if ($limit > 0 && count($urls) > $limit) {
            $urls = array_slice($urls, 0, $limit);
        }

        $stats = array(
            'started_at' => current_time('mysql'),
            'total_urls' => count($urls),
            'processed' => 0,
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
        );

        foreach ($urls as $url) {
            $stats['processed']++;
            try {
                if (!CosasAmazonHelpers::is_amazon_url($url)) {
                    $stats['skipped']++;
                    continue;
                }
                $data = CosasAmazonHelpers::get_product_data($url, true);
                if (is_array($data) && !empty($data)) {
                    self::upsert_cache_row($url, $data);
                    $stats['success']++;
                } else {
                    $stats['errors']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                error_log('[CosasDeAmazon][bulk_refresh] ' . $e->getMessage());
            }
            if ($args['sleep'] > 0) {
                sleep(intval($args['sleep']));
            }
        }

        $stats['finished_at'] = current_time('mysql');
        $stats['duration_sec'] = round(microtime(true) - $start, 2);
        return $stats;
    }

    /** Recolecta URLs de varias fuentes: opción, tabla y contenido de posts. */
    private static function collect_product_urls() {
        $urls = array();
        // 1) Opción persistida
        $urls = array_merge($urls, self::collect_urls_from_option());
        // 2) Tabla propia si existe
        $urls = array_merge($urls, self::collect_urls_from_db());
        // 3) Escaneo ligero de contenido
        $urls = array_merge($urls, self::collect_urls_from_posts());
        return $urls;
    }

    private static function collect_urls_from_option() {
        $out = array();
        $opt = get_option('cosas_amazon_url_mapping', array());
        if (is_array($opt)) {
            foreach ($opt as $k => $v) {
                if (is_string($k) && filter_var($k, FILTER_VALIDATE_URL)) { $out[] = $k; }
                if (is_string($v) && filter_var($v, FILTER_VALIDATE_URL)) { $out[] = $v; }
                if (is_array($v)) {
                    foreach ($v as $u) { if (is_string($u) && filter_var($u, FILTER_VALIDATE_URL)) { $out[] = $u; } }
                }
            }
        }
        return $out;
    }

    private static function collect_urls_from_db() {
        global $wpdb;
        $out = array();
        $table = $wpdb->prefix . 'cosas_amazon_cache';
        // Verificar existencia de la tabla con supresión de errores para evitar 500
        $prev = $wpdb->suppress_errors();
        $wpdb->suppress_errors(true);
        try {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists === $table) {
                $rows = $wpdb->get_col("SELECT url FROM {$table} ORDER BY updated_at DESC LIMIT 500");
                if (is_array($rows)) { $out = array_merge($out, $rows); }
            }
        } catch (\Throwable $e) {
            // Silenciar cualquier fallo de conexión/consulta y continuar sin DB
        } finally {
            $wpdb->suppress_errors($prev);
        }
        return $out;
    }

    private static function collect_urls_from_posts() {
        $out = array();
        // Buscar en tipos públicos
        $post_types = get_post_types(array('public' => true), 'names');
        if (empty($post_types)) { return $out; }
        $q = new \WP_Query(array(
            'post_type' => $post_types,
            'posts_per_page' => 300,
            'post_status' => array('publish', 'future', 'draft', 'pending'),
            'fields' => 'ids'
        ));
        if ($q->have_posts()) {
            foreach ($q->posts as $pid) {
                $content = get_post_field('post_content', $pid);
                if (!is_string($content) || $content === '') { continue; }
                // 3a) Bloques Gutenberg
                if (function_exists('has_blocks') && has_blocks($content) && function_exists('parse_blocks')) {
                    $blocks = parse_blocks($content);
                    foreach ($blocks as $b) {
                        if (!is_array($b) || empty($b['blockName'])) { continue; }
                        if ($b['blockName'] === 'cosas-amazon/producto-amazon' && !empty($b['attrs']) && is_array($b['attrs'])) {
                            $attrs = $b['attrs'];
                            if (!empty($attrs['amazonUrl']) && is_string($attrs['amazonUrl'])) { $out[] = $attrs['amazonUrl']; }
                            if (!empty($attrs['amazonUrls']) && is_array($attrs['amazonUrls'])) {
                                foreach ($attrs['amazonUrls'] as $u) { if (is_string($u)) { $out[] = $u; } }
                            }
                        }
                    }
                }
                // 3b) Shortcodes [amazon_producto url="..."] o [cosas-amazon]
                if (strpos($content, '[amazon_producto') !== false || strpos($content, '[cosas-amazon') !== false) {
                    // Extraer url="..." (permite comillas simples o dobles)
                    if (preg_match_all('/\[(?:amazon_producto|cosas-amazon)[^\]]*url\s*=\s*([\"\'])(.*?)\\1/si', $content, $m)) {
                        foreach ($m[2] as $u) {
                            // Puede contener múltiples URLs separadas por coma
                            $parts = array_map('trim', explode(',', $u));
                            foreach ($parts as $pu) { if ($pu !== '') { $out[] = $pu; } }
                        }
                    }
                }
            }
        }
        wp_reset_postdata();
        return $out;
    }

    /** Inserta/actualiza fila en la tabla propia para histórico/observabilidad. */
    private static function upsert_cache_row($url, $product_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cosas_amazon_cache';
        // Suprimir errores para evitar 500 si no hay conexión o falta la tabla
        $prev = $wpdb->suppress_errors();
        $wpdb->suppress_errors(true);
        try {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists !== $table) { return; }
            $json = wp_json_encode($product_data);
            if ($json === null) { $json = json_encode($product_data); }
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (url, product_data, created_at, updated_at, hits) VALUES (%s, %s, NOW(), NOW(), 0)
                     ON DUPLICATE KEY UPDATE product_data = VALUES(product_data), updated_at = NOW(), hits = hits + 1",
                    $url, $json
                )
            );
        } catch (\Throwable $e) {
            // No hacer nada: evitar que un fallo de BD tumbe el render
        } finally {
            $wpdb->suppress_errors($prev);
        }
    }

    /** Determina si deben ocultarse placeholders en frontend (por defecto sí). */
    private function should_hide_placeholder_on_frontend() {
        if (is_admin()) return false;
        $opts = get_option('cosas_amazon_options', array());
        return isset($opts['hide_placeholder_on_frontend']) ? (bool)$opts['hide_placeholder_on_frontend'] : true;
    }

    /** Heurística: ¿este product_data es un placeholder/fallback? */
    private function is_placeholder_product($pd) {
        if (!is_array($pd)) return true;
        if (!empty($pd['is_fallback'])) return true;
        $title = isset($pd['title']) ? (string)$pd['title'] : '';
        $price = isset($pd['price']) ? (string)$pd['price'] : '';
        $desc  = isset($pd['description']) ? (string)$pd['description'] : '';
        $looks_generic_title = ($title === '' || preg_match('/^Producto de Amazon/i', $title));
        $no_value_fields = ($price === '' && ($pd['originalPrice'] ?? '') === '' && ($pd['discount'] ?? '') === '' && ($pd['rating'] ?? '') === '' && ($pd['reviewCount'] ?? '') === '');
        return $looks_generic_title && $no_value_fields && $desc === '';
    }

    /**
     * Obtener valores por defecto desde la configuración del plugin
     */
    private function get_default_attributes() {
        $options = get_option('cosas_amazon_options', array());
        
        return array(
            'displayStyle' => isset($options['default_style']) ? $options['default_style'] : 'horizontal',
            'showPrice' => isset($options['show_price_by_default']) ? (bool)$options['show_price_by_default'] : true,
            'showDiscount' => isset($options['show_discount_by_default']) ? (bool)$options['show_discount_by_default'] : true,
            'showDescription' => isset($options['show_description_by_default']) ? (bool)$options['show_description_by_default'] : true,
            'descriptionLength' => isset($options['default_description_length']) ? intval($options['default_description_length']) : 150,
            // Colores por defecto alineados con el preset del plugin. Fallback a los campos legacy default_*
            'color' => isset($options['text_color']) ? $options['text_color'] : (isset($options['default_text_color']) ? $options['default_text_color'] : '#000000'),
            'fontSize' => isset($options['default_font_size']) ? $options['default_font_size'] : '16px',
            'borderStyle' => isset($options['default_border_style']) ? $options['default_border_style'] : 'solid',
            'borderColor' => isset($options['default_border_color']) ? $options['default_border_color'] : '#cccccc',
            'backgroundColor' => isset($options['background_color']) ? $options['background_color'] : (isset($options['default_background_color']) ? $options['default_background_color'] : '#ffffff'),
            'alignment' => isset($options['default_alignment']) ? $options['default_alignment'] : 'center',
            'showButton' => isset($options['show_button_by_default']) ? (bool)$options['show_button_by_default'] : true,
            'buttonText' => isset($options['default_button_text']) ? $options['default_button_text'] : 'Ver en Amazon',
            'buttonColor' => isset($options['default_button_color']) ? $options['default_button_color'] : '#FF9900',
            'showSpecialOffer' => isset($options['show_special_offer_by_default']) ? (bool)$options['show_special_offer_by_default'] : true,
            'specialOfferColor' => isset($options['default_special_offer_color']) ? $options['default_special_offer_color'] : (isset($options['accent_color']) ? $options['accent_color'] : '#e74c3c'),
            'priceColor' => isset($options['primary_color']) ? $options['primary_color'] : '#B12704',
            'discountColor' => isset($options['accent_color']) ? $options['accent_color'] : '#d93025',
            'originalPriceColor' => '#999999',
            'featuredBackgroundColor' => '',
            'featuredBackgroundGradient' => '',
            'blockSize' => isset($options['default_block_size']) ? $options['default_block_size'] : 'medium',
            'productsPerRow' => isset($options['default_products_per_row']) ? intval($options['default_products_per_row']) : 2
        );
    }

    /**
     * Determina un color de precio seguro en contraste con el fondo cuando el usuario no lo ha modificado.
     */
    private function resolve_price_color($attributes) {
        $price = isset($attributes['priceColor']) ? $attributes['priceColor'] : '#B12704';
        $bg = isset($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '#ffffff';
        $user_modified = false;
        if (isset($attributes['modifiedAttributes']) && is_array($attributes['modifiedAttributes'])) {
            $user_modified = in_array('priceColor', $attributes['modifiedAttributes'], true);
        }
        if ($user_modified) {
            return $price;
        }
        $hexPrice = $this->normalize_color_to_hex($price);
        $hexBg = $this->normalize_color_to_hex($bg);
        if ($hexPrice && $hexBg && strcasecmp($hexPrice, $hexBg) === 0) {
            return $this->get_contrasting_text_color($hexBg);
        }
        return $price;
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
        'render_callback' => array($this, 'render_amazon_product_block_safe'),
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
                    'priceColor' => array('type' => 'string', 'default' => '#B12704'),
                    'discountColor' => array('type' => 'string', 'default' => $defaults['discountColor']),
                    'originalPriceColor' => array('type' => 'string', 'default' => $defaults['originalPriceColor']),
                    'featuredBackgroundColor' => array('type' => 'string', 'default' => $defaults['featuredBackgroundColor']),
                    'featuredBackgroundGradient' => array('type' => 'string', 'default' => $defaults['featuredBackgroundGradient']),
                    'debugMode' => array('type' => 'boolean', 'default' => false),
                    'showSpecialOffer' => array('type' => 'boolean', 'default' => $defaults['showSpecialOffer']),
                    'specialOfferText' => array('type' => 'string', 'default' => ''),
                    'specialOfferColor' => array('type' => 'string', 'default' => $defaults['specialOfferColor']),
                    'multipleProductsMode' => array('type' => 'boolean', 'default' => false),
                    'productsPerRow' => array('type' => 'number', 'default' => $defaults['productsPerRow']),
                    'modifiedAttributes' => array('type' => 'array', 'default' => array())
                )
            )
        );
        // Registrar shortcode principal para el plugin
        add_shortcode('amazon_producto', array($this, 'shortcode_amazon_producto'));
    // Alias de compatibilidad con la documentación
    add_shortcode('cosas-amazon', array($this, 'shortcode_amazon_producto'));
        // Registrar shortcode para testing
        add_shortcode('cosas_amazon_test', array($this, 'shortcode_test_product'));
    }

    public function enqueue_frontend_assets() {
        // Bust de caché agresivo para asegurar que los cambios de CSS/JS se vean
        $asset_version = COSAS_AMAZON_VERSION . '-' . time();
        
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

        // Reforzar las clases de efectos en el frontend por si el tema las anula
        add_action('wp_head', array($this, 'inject_effects_reinforce_css'), 99);
    }

    /**
     * CSS inline con alta prioridad para asegurar que las clases `.cda-*` prevalezcan
     * frente a resets agresivos de algunos temas o cachés que alteran el orden.
     */
    public function inject_effects_reinforce_css() {
        if (is_admin()) return;
        $opts = get_option('cosas_amazon_options', array());
        // Permitir configurar colores del gradiente desde opciones si existen
        $grad_start = isset($opts['gradient_start']) ? trim($opts['gradient_start']) : '';
        $grad_end   = isset($opts['gradient_end']) ? trim($opts['gradient_end']) : '';

        // Helper inline para convertir HEX a RGBA con alpha
        $hex_to_rgba = function($hex, $alpha) {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
                $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
                $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
            } elseif (strlen($hex) === 6) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
            } else {
                // Fallback suave
                return 'rgba(0,0,0,' . floatval($alpha) . ')';
            }
            $alpha = max(0, min(1, floatval($alpha)));
            return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
        };

        // Usar alpha sutil para no ocultar la imagen: inicio 0.0, fin 0.10
        $grad_start_css = $grad_start !== '' ? $hex_to_rgba($grad_start, 0.0) : 'rgba(0,0,0,0.0)';
        $grad_end_css   = $grad_end   !== '' ? $hex_to_rgba($grad_end,   0.10) : 'rgba(0,0,0,0.10)';
        echo "\n<style id=\"cda-effects-reinforce\">\n";
        // Variables globales para colores de gradiente
        echo ":root{--cda-grad-start: {$grad_start_css}; --cda-grad-end: {$grad_end_css};}\n";
        echo ".cosas-amazon-product.cda-anim-slow, .cosas-amazon-carousel-item.cda-anim-slow{transition:all 300ms ease!important}\n";
        echo ".cosas-amazon-product.cda-anim-normal, .cosas-amazon-carousel-item.cda-anim-normal{transition:all 200ms ease!important}\n";
        echo ".cosas-amazon-product.cda-anim-fast, .cosas-amazon-carousel-item.cda-anim-fast{transition:all 120ms ease!important}\n";
        echo ".cosas-amazon-product.cda-hover-none:hover, .cosas-amazon-carousel-item.cda-hover-none:hover{transform:none!important}\n";
        echo ".cosas-amazon-product.cda-hover-scale:hover, .cosas-amazon-carousel-item.cda-hover-scale:hover{transform:translateZ(0) scale(1.02)!important}\n";
        echo ".cosas-amazon-product.cda-hover-lift:hover, .cosas-amazon-carousel-item.cda-hover-lift:hover{transform:translateY(-4px)!important}\n";
        echo ".cosas-amazon-product.cda-hover-glow:hover, .cosas-amazon-carousel-item.cda-hover-glow:hover{box-shadow:0 10px 28px rgba(0,0,0,.22),0 0 0 2px rgba(255,255,255,.4) inset!important}\n";
        echo ".cosas-amazon-product.cda-shadow-none, .cosas-amazon-carousel-item.cda-shadow-none{box-shadow:none!important}\n";
        echo ".cosas-amazon-product.cda-shadow-light, .cosas-amazon-carousel-item.cda-shadow-light{box-shadow:0 1px 6px rgba(0,0,0,.08)!important}\n";
        echo ".cosas-amazon-product.cda-shadow-medium, .cosas-amazon-carousel-item.cda-shadow-medium{box-shadow:0 4px 14px rgba(0,0,0,.12)!important}\n";
        echo ".cosas-amazon-product.cda-shadow-strong, .cosas-amazon-carousel-item.cda-shadow-strong{box-shadow:0 8px 24px rgba(0,0,0,.18)!important}\n";
        // Overlay con variables
        echo ".cosas-amazon-product.cda-gradient-on .cosas-amazon-image, .cosas-amazon-carousel-item.cda-gradient-on .cosas-amazon-image{position:relative!important}\n";
        echo ".cosas-amazon-product.cda-gradient-on .cosas-amazon-image::after, .cosas-amazon-carousel-item.cda-gradient-on .cosas-amazon-image::after{content:\"\";position:absolute;inset:0;pointer-events:none;background:linear-gradient(180deg, var(--cda-grad-start), var(--cda-grad-end))!important}\n";
        // Refuerzos críticos para visibilidad de precios en horizontal (evita overrides de temas/plugins)
        echo ".cosas-amazon-horizontal .cosas-amazon-pricing{display:flex!important;align-items:baseline!important;gap:8px!important;flex-wrap:wrap!important;visibility:visible!important;opacity:1!important}\n";
        echo ".cosas-amazon-horizontal .cosas-amazon-price,.cosas-amazon-horizontal .cosas-amazon-original-price,.cosas-amazon-horizontal .cosas-amazon-discount{display:inline-flex!important;visibility:visible!important;opacity:1!important}\n";
        echo "</style>\n";
    }

    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'cosas-amazon-block-editor',
            COSAS_AMAZON_PLUGIN_URL . 'assets/js/block.js',
            array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch'),
            COSAS_AMAZON_VERSION . '-' . time(), // Forzar recarga JS también
            true
        );
        // Encolar el JS del carrusel también en el editor para vista previa funcional
        wp_enqueue_script(
            'cosas-amazon-carousel',
            COSAS_AMAZON_PLUGIN_URL . 'assets/js/carousel.js',
            array('jquery'),
            COSAS_AMAZON_VERSION . '-' . time(),
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
                'displayStyle' => isset($plugin_options['default_style']) ? $plugin_options['default_style'] : 'horizontal',
                'showPrice' => isset($plugin_options['show_price_by_default']) ? (bool)$plugin_options['show_price_by_default'] : true,
                'showDiscount' => isset($plugin_options['show_discount_by_default']) ? (bool)$plugin_options['show_discount_by_default'] : true,
                'showDescription' => isset($plugin_options['show_description_by_default']) ? (bool)$plugin_options['show_description_by_default'] : true,
                'descriptionLength' => isset($plugin_options['default_description_length']) ? intval($plugin_options['default_description_length']) : 150,
                'color' => isset($plugin_options['text_color']) ? $plugin_options['text_color'] : '#000000',
                'fontSize' => isset($plugin_options['text_size']) ? (intval($plugin_options['text_size']) . 'px') : '16px',
                'borderColor' => isset($plugin_options['default_border_color']) ? $plugin_options['default_border_color'] : '#cccccc',
                'backgroundColor' => isset($plugin_options['background_color']) ? $plugin_options['background_color'] : '#ffffff',
                'buttonText' => isset($plugin_options['default_button_text']) ? $plugin_options['default_button_text'] : 'Ver en Amazon',
                'buttonColor' => isset($plugin_options['default_button_color']) ? $plugin_options['default_button_color'] : '#FF9900',
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
        // Cargar también el CSS del frontend en el editor para que coincidan los estilos visuales
        wp_enqueue_style(
            'cosas-amazon-block-style',
            COSAS_AMAZON_PLUGIN_URL . 'assets/css/style.css',
            array('cosas-amazon-block-editor-style'),
            COSAS_AMAZON_VERSION . '-' . time(),
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

        // Atributos marcados explícitamente como modificados desde el editor (tienen prioridad absoluta)
        $explicit_modified = array();
        if (isset($block_attributes['modifiedAttributes']) && is_array($block_attributes['modifiedAttributes'])) {
            foreach ($block_attributes['modifiedAttributes'] as $mAttr) {
                $explicit_modified[$mAttr] = true;
            }
        }
        
        // Verificar configuración de producción
        $production_config = get_option('cosas_amazon_production_config', []);
        $force_button_display = !empty($production_config['force_button_display']);
        
        // Lista de atributos que deben aplicar configuración global si no se han modificado
        $configurable_attributes = array(
            'displayStyle', 'blockSize', 'showPrice', 'showDiscount', 'showDescription',
            'descriptionLength', 'color', 'fontSize', 'borderStyle', 'borderColor', 'priceColor', 'discountColor', 'originalPriceColor', 'featuredBackgroundColor', 'featuredBackgroundGradient',
            'backgroundColor', 'alignment', 'showButton', 'buttonText', 'buttonColor',
            'showSpecialOffer', 'specialOfferColor', 'productsPerRow'
        );
        
        foreach ($configurable_attributes as $attr) {
            // Siempre respetar displayStyle del bloque si viene definido (aunque no esté en modifiedAttributes)
            if ($attr === 'displayStyle' && isset($block_attributes['displayStyle']) && $block_attributes['displayStyle'] !== '' && $block_attributes['displayStyle'] !== null) {
                $merged[$attr] = $block_attributes['displayStyle'];
            }
            // Para el resto, mantener la lógica de prioridad por modifiedAttributes vs preset global
            else if (isset($explicit_modified[$attr]) && isset($block_attributes[$attr])) {
                // Modificado explícitamente por el usuario en el editor: respetar valor del bloque
                $merged[$attr] = $block_attributes[$attr];
            } else {
                // No modificado explícitamente: aplicar siempre el valor global/preset actual
                $merged[$attr] = isset($global_defaults[$attr]) ? $global_defaults[$attr] : (isset($block_attributes[$attr]) ? $block_attributes[$attr] : null);
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
        // Ya no se usa para determinar overrides; se mantiene por compatibilidad.
        return isset($attributes['modifiedAttributes']) && is_array($attributes['modifiedAttributes'])
            ? in_array($attribute_name, $attributes['modifiedAttributes'], true)
            : false;
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

        // Ocultar placeholders en frontend si está habilitado
        if ($this->should_hide_placeholder_on_frontend() && $this->is_placeholder_product($product_data)) {
            return '';
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
    $effect_classes = $this->build_effect_classes();
    $html .= '<div class="cosas-amazon-product cosas-amazon-' . esc_attr($attributes['displayStyle']) . ' cosas-amazon-size-' . esc_attr($attributes['blockSize']) . ' ' . $alignment_class . (!empty($effect_classes) ? ' ' . esc_attr($effect_classes) : '') . '" style="';
        if ($attributes['borderStyle'] !== 'none') {
            $html .= 'border: 1px ' . esc_attr($attributes['borderStyle']) . ' ' . esc_attr($attributes['borderColor']) . '; ';
        }
        if ($attributes['displayStyle'] === 'featured' && !empty($attributes['featuredBackgroundGradient'])) {
            $html .= 'background: ' . esc_attr($attributes['featuredBackgroundGradient']) . '; ';
        } elseif ($attributes['displayStyle'] === 'featured' && !empty($attributes['featuredBackgroundColor'])) {
            $html .= 'background: ' . esc_attr($attributes['featuredBackgroundColor']) . '; ';
        } else {
            $html .= 'background-color: ' . esc_attr($attributes['backgroundColor']) . '; ';
        }
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
            
            // Título del producto en la parte superior (enlazado a Amazon si hay URL)
            if (!empty($product_data['title'])) {
                if (!empty($amazon_url)) {
                    $html .= '<h3 class="cosas-amazon-title"><a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer">' . esc_html($product_data['title']) . '</a></h3>';
                } else {
                    $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
                }
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
                
                // Descuento si existe (o calcular desde precios)
                if ($attributes['showDiscount']) {
                    $disc = '';
                    if (!empty($product_data['discount'])) {
                        $disc = intval(preg_replace('/[^0-9]/', '', (string)$product_data['discount']));
                    } elseif (!empty($product_data['originalPrice'])) {
                        $pct = $this->compute_discount_percent_from_prices($product_data['price'] ?? null, $product_data['originalPrice']);
                        if ($pct !== null && $pct > 0) { $disc = $pct; }
                    }
                    if ($disc !== '' && $disc > 0) {
                        $html .= '<span class="cosas-amazon-discount" style="background-color:' . esc_attr($attributes['discountColor']) . ' !important; color:#fff !important;">-' . esc_html($disc) . '%</span>';
                    }
                }
                
                // Precio actual (normalizado)
                $price_display = CosasAmazonHelpers::normalize_price_display($product_data['price']);
                if ($price_display === '' && !empty($attributes['showPrice'])) {
                    $price_display = 'Ver precio en Amazon';
                }
                $this->debug_log_price_map('compact', $product_data, $price_display, $attributes);
                if ($price_display !== '') {
                    $html .= '<span class="cosas-amazon-price" style="color:' . esc_attr($this->resolve_price_color($attributes)) . ' !important;">' . esc_html($price_display) . '</span>';
                }
                
                // Precio original si existe (normalizado)
                if (!empty($product_data['originalPrice'])) {
                    $orig_display = is_string($product_data['originalPrice']) ? CosasAmazonHelpers::normalize_price_display($product_data['originalPrice']) : '';
                    if ($orig_display !== '') {
                        $html .= '<span class="cosas-amazon-original-price" style="color:' . esc_attr($attributes['originalPriceColor']) . ';">' . esc_html($orig_display) . '</span>';
                    }
                }
                
                $html .= '</div>';
            }
            
            // Botón de compra
            if ($attributes['showButton'] && !empty($amazon_url)) {
                $html .= '<div class="cosas-amazon-button">';
                $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
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
            
            // Título del producto centrado (enlazado a Amazon si hay URL)
            if (!empty($product_data['title'])) {
                if (!empty($amazon_url)) {
                    $html .= '<h3 class="cosas-amazon-title"><a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer">' . esc_html($product_data['title']) . '</a></h3>';
                } else {
                    $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
                }
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
            
            // Descripción centrada (con fallback por ASIN y límite)
            if ($attributes['showDescription']) {
                $description = '';
                if (!empty($product_data['description'])) {
                    $description = (string) $product_data['description'];
                } elseif (!empty($product_data['asin']) && method_exists('CosasAmazonHelpers', 'get_fallback_description')) {
                    $description = (string) CosasAmazonHelpers::get_fallback_description($product_data['asin']);
                }
                if ($description !== '') {
                    if (!empty($attributes['descriptionLength']) && intval($attributes['descriptionLength']) > 0 && strlen($description) > intval($attributes['descriptionLength'])) {
                        $description = substr($description, 0, intval($attributes['descriptionLength'])) . '...';
                    }
                    $html .= '<div class="cosas-amazon-description">';
                    $html .= '<p>' . esc_html($description) . '</p>';
                    $html .= '</div>';
                }
            }
            
            // Precios centrados
            if ($attributes['showPrice'] && !empty($product_data['price'])) {
                $html .= '<div class="cosas-amazon-pricing">';
                
                // Descuento si existe (o calcular desde precios)
                if ($attributes['showDiscount']) {
                    $disc = '';
                    if (!empty($product_data['discount'])) {
                        $disc = intval(preg_replace('/[^0-9]/', '', (string)$product_data['discount']));
                    } elseif (!empty($product_data['originalPrice'])) {
                        $pct = $this->compute_discount_percent_from_prices($product_data['price'] ?? null, $product_data['originalPrice']);
                        if ($pct !== null && $pct > 0) { $disc = $pct; }
                    }
                    if ($disc !== '' && $disc > 0) {
                        $html .= '<span class="cosas-amazon-discount" style="background-color:' . esc_attr($attributes['discountColor']) . ' !important; color:#fff !important;">-' . esc_html($disc) . '%</span>';
                    }
                }
                
                // Precio actual (normalizado)
                $price_display = CosasAmazonHelpers::normalize_price_display($product_data['price']);
                if ($price_display === '' && !empty($attributes['showPrice'])) {
                    $price_display = 'Ver precio en Amazon';
                }
                $this->debug_log_price_map('featured', $product_data, $price_display, $attributes);
                if ($price_display !== '') {
                    $html .= '<span class="cosas-amazon-price" style="color:' . esc_attr($this->resolve_price_color($attributes)) . ' !important;">' . esc_html($price_display) . '</span>';
                }
                
                // Precio original si existe (normalizado)
                if (!empty($product_data['originalPrice'])) {
                    $orig_display = is_string($product_data['originalPrice']) ? CosasAmazonHelpers::normalize_price_display($product_data['originalPrice']) : '';
                    if ($orig_display !== '') {
                        $html .= '<span class="cosas-amazon-original-price" style="color:' . esc_attr($attributes['originalPriceColor']) . ';">' . esc_html($orig_display) . '</span>';
                    }
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>'; // Cerrar content-wrapper
            
            // Botón centrado en la parte inferior
            if ($attributes['showButton'] && !empty($amazon_url)) {
                $user_modified_button = false;
                if (isset($attributes['modifiedAttributes']) && is_array($attributes['modifiedAttributes'])) {
                    $user_modified_button = in_array('buttonColor', $attributes['modifiedAttributes'], true);
                }
                $btn_style = '';
                if ($user_modified_button && !empty($attributes['buttonColor'])) {
                    $text_color = $this->get_contrasting_text_color($attributes['buttonColor']);
                    $btn_style = 'background-color: ' . esc_attr($attributes['buttonColor']) . '; color: ' . esc_attr($text_color) . ';';
                } else {
                    $auto = $this->compute_featured_button_styles($attributes);
                    $btn_style = 'background: ' . esc_attr($auto['background']) . '; color: ' . esc_attr($auto['color']) . '; border: ' . esc_attr($auto['border']) . ';';
                }
                $html .= '<div class="cosas-amazon-button">';
                $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer" class="cosas-amazon-btn" style="' . $btn_style . '">';
                $html .= esc_html($attributes['buttonText']);
                $html .= '</a>';
                $html .= '</div>';
            }
            
        } elseif ($attributes['displayStyle'] === 'minimal') {
            // Para estilo minimal: título arriba, imagen izquierda, precio, descuento/precio anterior, etiqueta, botón abajo
            
            // Título del producto en la parte superior (enlazado a Amazon si hay URL)
            if (!empty($product_data['title'])) {
                if (!empty($amazon_url)) {
                    $html .= '<h3 class="cosas-amazon-title"><a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer">' . esc_html($product_data['title']) . '</a></h3>';
                } else {
                    $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
                }
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
            
            // Precio del producto (normalizado)
            if ($attributes['showPrice'] && !empty($product_data['price'])) {
                $price_display = CosasAmazonHelpers::normalize_price_display($product_data['price']);
                if ($price_display === '' && !empty($attributes['showPrice'])) {
                    $price_display = 'Ver precio en Amazon';
                }
                $this->debug_log_price_map('minimal', $product_data, $price_display, $attributes);
                if ($price_display !== '') {
                    $html .= '<div class="cosas-amazon-price" style="color:' . esc_attr($this->resolve_price_color($attributes)) . ' !important;">' . esc_html($price_display) . '</div>';
                }
            }
            
            // Línea de descuento y precio anterior
            if (($attributes['showDiscount'] && !empty($product_data['discount'])) || !empty($product_data['originalPrice'])) {
                $html .= '<div class="cosas-amazon-pricing-line">';
                
                // Descuento si existe (o calcular desde precios)
                if ($attributes['showDiscount']) {
                    $disc = '';
                    if (!empty($product_data['discount'])) {
                        $disc = intval(preg_replace('/[^0-9]/', '', (string)$product_data['discount']));
                    } elseif (!empty($product_data['originalPrice'])) {
                        $pct = $this->compute_discount_percent_from_prices($product_data['price'] ?? null, $product_data['originalPrice']);
                        if ($pct !== null && $pct > 0) { $disc = $pct; }
                    }
                    if ($disc !== '' && $disc > 0) {
                        $html .= '<span class="cosas-amazon-discount" style="background-color:' . esc_attr($attributes['discountColor']) . ' !important; color:#fff !important;">-' . esc_html($disc) . '%</span>';
                    }
                }
                
                // Precio original si existe (normalizado)
                if (!empty($product_data['originalPrice'])) {
                    $orig_display = is_string($product_data['originalPrice']) ? CosasAmazonHelpers::normalize_price_display($product_data['originalPrice']) : '';
                    if ($orig_display !== '') {
                        $html .= '<span class="cosas-amazon-original-price" style="color:' . esc_attr($attributes['originalPriceColor']) . ';">' . esc_html($orig_display) . '</span>';
                    }
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
                $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
                $html .= esc_html($attributes['buttonText']);
                $html .= '</a>';
                $html .= '</div>';
            }
            
            $html .= '</div>'; // Cerrar contenido
            $html .= '</div>'; // Cerrar main-content
            
        } elseif ($attributes['displayStyle'] === 'carousel') {
            // Carousel individual (cuando solo hay un producto) con estructura compatible con JS/CSS del carousel
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
            $html .= '<div class="cosas-amazon-products-carousel ' . $alignment_class . '">';
            $html .= '<div class="cosas-amazon-carousel-container cosas-amazon-carousel cosas-amazon-size-' . esc_attr($attributes['blockSize']) . '">';
            $html .= $this->render_carousel_item($product_data, $amazon_url, $attributes);
            $html .= '</div>'; // container
            $html .= '<div class="cosas-amazon-carousel-controls">';
            $html .= '<button type="button" class="cosas-amazon-carousel-prev" onclick="window.cosasAmazonCarousel.prev(this)">‹</button>';
            $html .= '<button type="button" class="cosas-amazon-carousel-next" onclick="window.cosasAmazonCarousel.next(this)">›</button>';
            $html .= '</div>';
            $html .= '</div>'; // wrapper productos-carousel
            $html .= '</div>'; // wrapper de alineación

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

    /** Wrapper seguro para el callback del bloque */
    public function render_amazon_product_block_safe($attributes) {
        try {
            return $this->render_amazon_product_block($attributes);
        } catch (\Throwable $e) {
            error_log('[CosasDeAmazon][render_block] ' . $e->getMessage());
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<div class="cosas-amazon-error">Error renderizando bloque: ' . esc_html($e->getMessage()) . '</div>';
            }
            return '';
        }
    }
    
    /**
     * Renderizar el contenido del producto (sin imagen)
     */
    private function render_product_content($product_data, $attributes, $amazon_url, $include_special_offer_tag = true) {
        $html = '';
        
        // Título del producto
        if (!empty($product_data['title'])) {
            if (!empty($amazon_url)) {
                $html .= '<h3 class="cosas-amazon-title"><a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer">' . esc_html($product_data['title']) . '</a></h3>';
            } else {
                $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
            }
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
        
        // Precios y descuento (mostrar si hay cualquiera de ellos válido)
        $price_display = CosasAmazonHelpers::normalize_price_display($product_data['price'] ?? '');
        // Fallback: si no hay precio disponible pero se debe mostrar el precio,
        // mostrar el placeholder para asegurar visibilidad en horizontal
        if ($price_display === '' && !empty($attributes['showPrice'])) {
            $price_display = 'Ver precio en Amazon';
        }
        $this->debug_log_price_map('content', $product_data, $price_display, $attributes);
        $orig_display = '';
        if (!empty($product_data['originalPrice']) && is_string($product_data['originalPrice'])) {
            $orig_display = CosasAmazonHelpers::normalize_price_display($product_data['originalPrice']);
        }
        $effective_discount = '';
        if ($attributes['showDiscount']) {
            if (!empty($product_data['discount'])) {
                $effective_discount = intval(preg_replace('/[^0-9]/', '', (string)$product_data['discount']));
            } elseif (!empty($product_data['originalPrice'])) {
                $pct = $this->compute_discount_percent_from_prices($product_data['price'] ?? null, $product_data['originalPrice']);
                if ($pct !== null && $pct > 0) { $effective_discount = $pct; }
            }
        }
        $has_price = ($attributes['showPrice'] && $price_display !== '');
        $has_orig  = ($attributes['showPrice'] && $orig_display !== '');
        $has_disc  = ($attributes['showDiscount'] && $effective_discount !== '' && $effective_discount > 0);
        if ($has_price || $has_orig || $has_disc) {
            $html .= '<div class="cosas-amazon-pricing">';
            if ($has_disc) {
                $html .= '<span class="cosas-amazon-discount" style="background-color:' . esc_attr($attributes['discountColor']) . ' !important; color:#fff !important;">-' . esc_html($effective_discount) . '%</span>';
            }
            if ($has_price) {
                $html .= '<span class="cosas-amazon-price" style="color:' . esc_attr($this->resolve_price_color($attributes)) . ' !important;">' . esc_html($price_display) . '</span>';
            }
            if ($has_orig) {
                $html .= '<span class="cosas-amazon-original-price" style="color:' . esc_attr($attributes['originalPriceColor']) . ';">' . esc_html($orig_display) . '</span>';
            }
            $html .= '</div>';
        }
        
        // Etiqueta de oferta especial (DENTRO del contenido solo si no se ha incluido antes)
        if ($include_special_offer_tag) {
            $html .= $this->render_special_offer_tag($product_data, $attributes);
        }
        
        // Descripción del producto (con fallback por ASIN si no viene)
        if ($attributes['showDescription']) {
            $description = '';
            if (!empty($product_data['description'])) {
                $description = (string)$product_data['description'];
            } elseif (!empty($product_data['asin']) && method_exists('CosasAmazonHelpers', 'get_fallback_description')) {
                $description = (string)CosasAmazonHelpers::get_fallback_description($product_data['asin']);
            }
            if ($description !== '') {
                if (!empty($attributes['descriptionLength']) && intval($attributes['descriptionLength']) > 0 && strlen($description) > intval($attributes['descriptionLength'])) {
                    $description = substr($description, 0, intval($attributes['descriptionLength'])) . '...';
                }
                $html .= '<div class="cosas-amazon-description">';
                $html .= '<p>' . esc_html($description) . '</p>';
                $html .= '</div>';
            }
        }

        // Botón de compra
        if ($attributes['showButton'] && !empty($amazon_url)) {
            $html .= '<div class="cosas-amazon-button">';
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
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
        
    // Wrapper de productos carousel y container de ítems
    $alignment_class = 'cosas-amazon-align-' . esc_attr($attributes['alignment']);
    $html .= '<div class="cosas-amazon-products-carousel ' . $alignment_class . '">';
    $html .= '<div class="cosas-amazon-carousel-container cosas-amazon-carousel cosas-amazon-size-' . esc_attr($attributes['blockSize']) . '">';
        
        $rendered = 0;
        foreach ($urls_array as $index => $url) {
            $product_data = null;

            // Obtener datos del producto del array products_data o por índice
            if (!empty($products_data) && is_array($products_data)) {
                $product_data = isset($products_data[$index]) ? $products_data[$index] : null;
            }

            // Si faltan datos, intentar obtenerlos directamente por URL (por ejemplo, tras añadir una URL corta nueva)
            if (empty($product_data) && function_exists('cosas_amazon_get_product_data')) {
                $fetched = cosas_amazon_get_product_data($url);
                if (!empty($fetched)) {
                    $product_data = $fetched;
                }
            }

            // Normalizar datos del producto
            $product_data = $this->normalize_product_data($product_data);

            // Saltar placeholders si la opción está activa
            if ($this->should_hide_placeholder_on_frontend() && $this->is_placeholder_product($product_data)) {
                continue;
            }
            if (!empty($product_data) && is_array($product_data)) {
                $html .= $this->render_carousel_item($product_data, $url, $attributes);
                $rendered++;
            }
        }
        
    $html .= '</div>'; // Cerrar container
    if ($rendered === 0) {
        $html .= '<div class="cosas-amazon-error">No hay productos disponibles.</div>';
    }
    // Controles prev/next
    $html .= '<div class="cosas-amazon-carousel-controls">';
    $html .= '<button type="button" class="cosas-amazon-carousel-prev" onclick="window.cosasAmazonCarousel.prev(this)">‹</button>';
    $html .= '<button type="button" class="cosas-amazon-carousel-next" onclick="window.cosasAmazonCarousel.next(this)">›</button>';
    $html .= '</div>';
    $html .= '</div>'; // Cerrar wrapper productos-carousel
    $html .= '</div>'; // Cerrar wrapper
        
        return $html;
    }
    
    /**
     * Renderizar placeholder para carousel cuando no hay datos
     */
    private function render_carousel_placeholder($amazon_url, $attributes) {
        $effect_classes = $this->build_effect_classes();
        $html = '<div class="cosas-amazon-carousel-item' . (!empty($effect_classes) ? ' ' . esc_attr($effect_classes) : '') . '">';
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
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
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
        $effect_classes = $this->build_effect_classes();
        $html = '<div class="cosas-amazon-carousel-item' . (!empty($effect_classes) ? ' ' . esc_attr($effect_classes) : '') . '">';
        
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
        
        // 3. Título del producto (enlazado a Amazon si hay URL)
        if (!empty($product_data['title'])) {
            if (!empty($amazon_url)) {
                $html .= '<h3 class="cosas-amazon-title"><a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer">' . esc_html($product_data['title']) . '</a></h3>';
            } else {
                $html .= '<h3 class="cosas-amazon-title">' . esc_html($product_data['title']) . '</h3>';
            }
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
        
        // 5. Precio del producto (normalizado a formato español con €)
            if ($attributes['showPrice'] && !empty($product_data['price'])) {
            $price_display = CosasAmazonHelpers::normalize_price_display($product_data['price']);
            if ($price_display === '' && !empty($attributes['showPrice'])) {
                $price_display = 'Ver precio en Amazon';
            }
            $this->debug_log_price_map('carousel', $product_data, $price_display, $attributes);
            if ($price_display !== '') {
                $html .= '<div class="cosas-amazon-price" style="color:' . esc_attr($this->resolve_price_color($attributes)) . ' !important;">' . esc_html($price_display) . '</div>';
            }
        }
        
        // 6. Descuento (si existe o calcular desde precios)
        if ($attributes['showDiscount']) {
            $disc = '';
            if (!empty($product_data['discount'])) {
                $disc = intval(preg_replace('/[^0-9]/', '', (string)$product_data['discount']));
            } elseif (!empty($product_data['originalPrice'])) {
                $pct = $this->compute_discount_percent_from_prices($product_data['price'] ?? null, $product_data['originalPrice']);
                if ($pct !== null && $pct > 0) { $disc = $pct; }
            }
            if ($disc !== '' && $disc > 0) {
                $html .= '<div class="cosas-amazon-discount" style="background-color:' . esc_attr($attributes['discountColor']) . ' !important; color:#fff !important;">-' . esc_html($disc) . '%</div>';
            }
        }
        
        // 7. Precio original (si existe)
        if (!empty($product_data['originalPrice'])) {
            $orig_display = is_string($product_data['originalPrice']) ? CosasAmazonHelpers::normalize_price_display($product_data['originalPrice']) : '';
            if (!empty($orig_display)) {
                $html .= '<div class="cosas-amazon-original-price" style="color:' . esc_attr($attributes['originalPriceColor']) . ';">' . esc_html($orig_display) . '</div>';
            }
        }
        
        // 8. Descripción (fallback si no viene)
        $desc = '';
        if (!empty($product_data['description'])) {
            $desc = (string)$product_data['description'];
        } else if (!empty($product_data['asin']) && method_exists('CosasAmazonHelpers', 'get_fallback_description')) {
            $desc = CosasAmazonHelpers::get_fallback_description($product_data['asin']);
        }
        if (!empty($desc)) {
            $html .= '<div class="cosas-amazon-description">' . esc_html($desc) . '</div>';
        }

        // 9. Botón Ver en Amazon
        if ($attributes['showButton'] && !empty($amazon_url)) {
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer" class="cosas-amazon-btn" style="background-color: ' . esc_attr($attributes['buttonColor']) . ';">';
            $html .= esc_html($attributes['buttonText']);
            $html .= '</a>';
        }
        
    $html .= '</div>'; // Cerrar contenido
    $html .= '</div>'; // Cerrar item
        
        return $html;
    }

    /**
     * Construye clases CSS de efectos/animaciones a partir de las opciones globales.
     */
    private function build_effect_classes() {
        $opts = get_option('cosas_amazon_options', array());
        $hover  = isset($opts['hover_effect']) ? sanitize_key($opts['hover_effect']) : 'scale';
        $shadow = isset($opts['shadow_style']) ? sanitize_key($opts['shadow_style']) : 'medium';
        $speed  = isset($opts['animation_speed']) ? sanitize_key($opts['animation_speed']) : 'normal';
        $grad   = !empty($opts['gradient_enable']);
        $classes = array();
        if ($hover && in_array($hover, array('none','scale','lift','glow'), true)) {
            $classes[] = 'cda-hover-' . $hover;
        }
        if ($shadow && in_array($shadow, array('none','light','medium','strong'), true)) {
            $classes[] = 'cda-shadow-' . $shadow;
        }
        if ($speed && in_array($speed, array('slow','normal','fast'), true)) {
            $classes[] = 'cda-anim-' . $speed;
        }
        $classes[] = $grad ? 'cda-gradient-on' : 'cda-gradient-off';
        return implode(' ', $classes);
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
                // Sanitizar precios si existen
                if (is_array($decoded)) {
                    $decoded = $this->sanitize_price_fields($decoded);
                }
                return $decoded;
            }
            return null;
        }
        
        // Si es un objeto, convertir a array
        if (is_object($product_data)) {
            $arr = (array) $product_data;
            return $this->sanitize_price_fields($arr);
        }
        
        // Si ya es array, devolverlo tal como está
        if (is_array($product_data)) {
            return $this->sanitize_price_fields($product_data);
        }
        
        return null;
    }

    /**
     * Reemplaza espacios especiales y entidades en campos de precio para evitar caracteres "?".
     */
    private function sanitize_price_fields($data) {
        if (!is_array($data)) return $data;
        foreach (array('price', 'originalPrice', 'original_price') as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = $this->normalize_price_string($data[$key]);
                // Evitar valores cero como precios válidos
                $num = preg_replace('/[^0-9.,]/', '', $data[$key]);
                // Solo limpiar el precio si realmente es numérico cero.
                // Si no hay dígitos (placeholder como "Ver precio en Amazon"), conservarlo para mostrarlo en frontend.
                if ($key === 'price') {
                    $has_digits = preg_match('/\d/u', $data[$key]) === 1;
                    $is_zero_numeric = ($num !== '' && preg_match('/^0+(?:[,.]0+)?$/', $num));
                    if ($has_digits && $is_zero_numeric) {
                        $data[$key] = '';
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Normaliza una cadena de precio: decodifica entidades, sustituye NBSP/espacios finos por espacio normal y colapsa espacios.
     */
    private function normalize_price_string($s) {
        if (!is_string($s)) return $s;
        // Decodificar entidades HTML comunes (&euro;, &nbsp;, etc.)
        $s = wp_specialchars_decode($s, ENT_QUOTES);
        // Eliminar marcas e invisibles (zero-width, joiners, BOM, LRM/RLM, FEFF)
        $invisibles = array(
            "\xE2\x80\x8B", /* ZWSP */
            "\xE2\x80\x8C", /* ZWNJ */
            "\xE2\x80\x8D", /* ZWJ */
            "\xEF\xBB\xBF", /* BOM */
            "\xE2\x81\xA0", /* WJ */
            "\xE2\x80\x8E", /* LRM */
            "\xE2\x80\x8F", /* RLM */
            "\xEF\xBB\xBF",  /* FEFF BOM duplicate for safety */
            "\xC2\xAD" /* SOFT HYPHEN */
        );
        $s = str_replace($invisibles, '', $s);
        // Eliminar Replacement Character U+FFFD si llegara a colarse
        $s = str_replace("\xEF\xBF\xBD", '', $s);
        // Reemplazar variedad amplia de espacios Unicode por espacio normal
        $specialSpaces = array(
            "\xC2\xA0", /* NBSP */
            "\xE2\x80\xAF", /* NARROW NBSP */
            "\xE2\x80\x87", /* FIGURE SPACE */
            "\xE2\x80\x88", /* PUNCTUATION SPACE */
            "\xE2\x80\x89", /* THIN SPACE */
            "\xE2\x80\x8A", /* HAIR SPACE */
            "\xE2\x80\x82", /* EN SPACE */
            "\xE2\x80\x83", /* EM SPACE */
            "\xE2\x80\x84", /* THREE-PER-EM */
            "\xE2\x80\x85", /* FOUR-PER-EM */
            "\xE2\x80\x86"  /* SIX-PER-EM */
        );
        $s = str_replace($specialSpaces, ' ', $s);
        // Colapsar espacios múltiples y recortar
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim($s);
        // Reglas específicas por moneda
        // EUR como sufijo: "123€" o "123  €" => "123 €"
        $s = preg_replace('/(\d)\s*€/u', '$1 €', $s);
        // EUR como prefijo: "€ 123" => "€123" (sin espacios tras símbolo)
        $s = preg_replace('/^€\s+/u', '€', $s);
        // USD/GBP/JPY como prefijo: eliminar espacios tras el símbolo al inicio
        $s = preg_replace('/^([$£¥])\s+(\d)/u', '$1$2', $s);
        return $s;
    }

    /**
     * Convierte una cadena de precio (con símbolos y separadores locales) a float.
     */
    private function parse_price_to_float($s) {
        if (!is_string($s) && !is_numeric($s)) return null;
        $s = (string) $s;
        $s = $this->normalize_price_string($s);
        // Quitar todo menos dígitos y separadores
        $clean = preg_replace('/[^0-9.,]/u', '', $s);
        if ($clean === '' || $clean === null) return null;
        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');
        // Determinar separador decimal como el último separador presente
        $decimalSep = null;
        if ($lastComma !== false && $lastDot !== false) {
            $decimalSep = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            // Si solo hay coma y hay dos dígitos tras ella, asumir decimal
            $decimalSep = (preg_match('/,\d{1,2}$/', $clean)) ? ',' : null;
        } elseif ($lastDot !== false) {
            // Si solo hay punto y hay dos dígitos tras él, asumir decimal
            $decimalSep = (preg_match('/\.\d{1,2}$/', $clean)) ? '.' : null;
        }
        // Eliminar miles y normalizar decimal a punto
        if ($decimalSep === ',') {
            $clean = str_replace('.', '', $clean); // puntos eran miles
            $clean = str_replace(',', '.', $clean); // coma a punto
        } elseif ($decimalSep === '.') {
            $clean = str_replace(',', '', $clean); // comas eran miles
        } else {
            // Sin separador decimal fiable: eliminar todos separadores
            $clean = str_replace([',', '.'], '', $clean);
        }
        if ($clean === '' || $clean === null) return null;
        return floatval($clean);
    }

    /**
     * Calcula descuento en % a partir de precio actual y original. Devuelve int 0-99 o null.
     */
    private function compute_discount_percent_from_prices($priceStr, $originalStr) {
        $p = $this->parse_price_to_float($priceStr);
        $o = $this->parse_price_to_float($originalStr);
        if ($p === null || $o === null || $o <= 0 || $p <= 0 || $p >= $o) return null;
        $pct = round((1 - ($p / $o)) * 100);
        if ($pct < 0) $pct = 0; if ($pct > 99) $pct = 99;
        return (int) $pct;
    }

    /**
     * Logging auxiliar: mapea precio bruto -> normalizado (+num) por contexto cuando el debug está activo.
     */
    private function debug_log_price_map($context, $product_data, $normalized, $attributes = array()) {
        if (!class_exists('CosasAmazonHelpers')) { return; }
        if (!defined('COSAS_AMAZON_DEBUG') || !COSAS_AMAZON_DEBUG) { return; }
        $raw = isset($product_data['price']) ? (string)$product_data['price'] : '';
        $asin = isset($product_data['asin']) ? (string)$product_data['asin'] : '';
        $title = isset($product_data['title']) ? (string)$product_data['title'] : '';
        $style = isset($attributes['displayStyle']) ? (string)$attributes['displayStyle'] : '';
        $num = $this->parse_price_to_float($raw);
        CosasAmazonHelpers::log_debug('PRICE_MAP [' . $context . '] style=' . $style, array(
            'asin' => $asin,
            'title' => ($title !== '' ? substr($title, 0, 80) : ''),
            'raw' => $raw,
            'normalized' => $normalized,
            'numeric' => $num,
        ));
    }

    /**
     * Determina color de texto accesible (negro/blanco) en función de un color de fondo.
     */
    private function get_contrasting_text_color($bg) {
        $hex = $this->normalize_color_to_hex($bg);
        if (!$hex) return '#ffffff';
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        // Luma relativa sRGB
        $luma = (0.2126 * ($r/255)) + (0.7152 * ($g/255)) + (0.0722 * ($b/255));
        return $luma > 0.5 ? '#000000' : '#ffffff';
    }

    /** Normaliza un color tipo #RGB/#RRGGBB a #RRGGBB si es posible */
    private function normalize_color_to_hex($c) {
        if (!is_string($c)) return null;
        $c = trim($c);
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $c, $m)) {
            $r = $m[1][0]; $g = $m[1][1]; $b = $m[1][2];
            return '#' . $r.$r . $g.$g . $b.$b;
        }
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $c)) {
            return $c;
        }
        return null;
    }

    /**
     * Calcula estilos de botón auto en estilo featured según fondo elegido (gradient/color/default).
     * Devuelve array ['background' => ..., 'color' => ..., 'border' => ...]
     */
    private function compute_featured_button_styles($attrs) {
        $default = array(
            'background' => 'rgba(255,255,255,0.2)',
            'color' => '#ffffff',
            'border' => '2px solid #ffffff'
        );
        $bg = '';
        if (!empty($attrs['featuredBackgroundGradient'])) {
            $bg = $attrs['featuredBackgroundGradient'];
        } elseif (!empty($attrs['featuredBackgroundColor'])) {
            $bg = $attrs['featuredBackgroundColor'];
        } elseif (!empty($attrs['backgroundColor'])) {
            $bg = $attrs['backgroundColor'];
        }
        // Si es gradiente, mantener botón traslúcido con borde blanco (buena legibilidad).
        if (is_string($bg) && stripos($bg, 'gradient') !== false) {
            return $default;
        }
        // Si es color sólido, ajustamos texto según contraste y usamos mismo color de fondo en sólido para el botón un poco más oscuro/claro
        $hex = $this->normalize_color_to_hex($bg);
        if ($hex) {
            $text = $this->get_contrasting_text_color($hex);
            // Bordes con opacidad para asegurar separación
            $border = $text === '#ffffff' ? '2px solid rgba(255,255,255,0.8)' : '2px solid rgba(0,0,0,0.6)';
            // Fondo: usar el color sólido, pero si el texto es oscuro, subimos la luminosidad del fondo para mantener contraste sobre el card
            return array(
                'background' => $hex,
                'color' => $text,
                'border' => $border
            );
        }
        return $default;
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
        
        $rows = 0;
        foreach ($urls_array as $index => $url) {
            $product_data = isset($products_data[$index]) ? $products_data[$index] : null;
            
            if (!empty($product_data)) {
                $pd = $this->normalize_product_data($product_data);
                if ($this->should_hide_placeholder_on_frontend() && $this->is_placeholder_product($pd)) {
                    continue;
                }
                $html .= $this->render_table_row($pd, $url, $attributes);
                $rows++;
            }
        }
        
        if ($rows === 0) {
            $html .= '<tr><td colspan="6" class="cosas-amazon-no-products">No hay productos disponibles.</td></tr>';
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
            if (!empty($amazon_url)) {
                $html .= '<h4><a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer">' . esc_html($product_data['title']) . '</a></h4>';
            } else {
                $html .= '<h4>' . esc_html($product_data['title']) . '</h4>';
            }
        } else {
            $html .= '<h4>Producto de Amazon</h4>';
        }
        
        // Descripción si está habilitada (con fallback por ASIN)
        if ($attributes['showDescription']) {
            $desc = '';
            if (!empty($product_data['description'])) {
                $desc = (string) $product_data['description'];
            } elseif (!empty($product_data['asin']) && method_exists('CosasAmazonHelpers', 'get_fallback_description')) {
                $desc = (string) CosasAmazonHelpers::get_fallback_description($product_data['asin']);
            }
            if ($desc !== '') {
                $html .= '<p class="cosas-amazon-table-description">' . esc_html(wp_trim_words($desc, 15)) . '</p>';
            }
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
                $price_display = CosasAmazonHelpers::normalize_price_display($product_data['price']);
                $this->debug_log_price_map('table', $product_data, $price_display, $attributes);
                if ($price_display !== '') {
                    $html .= '<span class="cosas-amazon-price" style="color:' . esc_attr($this->resolve_price_color($attributes)) . ' !important;">' . esc_html($price_display) . '</span>';
                } else {
                    $html .= '<span class="cosas-amazon-no-price">N/A</span>';
                }
            } else {
                $html .= '<span class="cosas-amazon-no-price">N/A</span>';
            }
            $html .= '</td>';
        }
        
        // Descuento
        if ($attributes['showDiscount']) {
            $html .= '<td class="cosas-amazon-table-discount">';
            // Determinar descuento efectivo: usar valor válido o calcular a partir de precios
            $discount_val = null;
            if (isset($product_data['discount'])) {
                $raw = (string) $product_data['discount'];
                $num = preg_replace('/[^0-9]/', '', $raw);
                if ($num !== '') {
                    $discount_val = intval($num);
                    if ($discount_val <= 0 || $discount_val >= 100) $discount_val = null;
                }
            }
            if ($discount_val === null) {
                $orig = $product_data['original_price'] ?? ($product_data['originalPrice'] ?? null);
                $discount_val = $this->compute_discount_percent_from_prices($product_data['price'] ?? null, $orig);
            }
            if ($discount_val !== null && $discount_val > 0) {
                $html .= '<span class="cosas-amazon-discount" style="background-color:' . esc_attr($attributes['discountColor']) . ' !important; color:#fff !important;">-' . esc_html($discount_val) . '%</span>';
                if (!empty($product_data['original_price'])) {
                    $html .= '<span class="cosas-amazon-original-price" style="color:' . esc_attr($attributes['originalPriceColor']) . ';">' . esc_html($product_data['original_price']) . '</span>';
                }
            } else {
                $html .= '<span class="cosas-amazon-no-discount">Sin descuento</span>';
            }
            $html .= '</td>';
        }
        
        // Botón
        if ($attributes['showButton']) {
            $html .= '<td class="cosas-amazon-table-button">';
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer" class="cosas-amazon-btn" style="background-color:' . esc_attr($attributes['buttonColor']) . ';">';
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
            $html .= '<a href="' . esc_url($amazon_url) . '" target="_blank" rel="nofollow noopener noreferrer" class="cosas-amazon-btn">';
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
        try {
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
            
            // Preparar URLs: permitir lista separada por comas para múltiples productos
            $urls = array();
            if (!empty($attributes['url'])) {
                $urls = array_map('trim', explode(',', $attributes['url']));
            }

            $amazonUrl = isset($urls[0]) ? $urls[0] : '';
            $amazonUrls = array();
            if (count($urls) > 1) {
                $amazonUrls = array_slice($urls, 1);
            }

            // Convertir a formato del bloque
            $block_attributes = array(
                'amazonUrl' => $amazonUrl,
                'amazonUrls' => $amazonUrls,
                'displayStyle' => $attributes['style'],
                'blockSize' => $attributes['size'],
                'showButton' => filter_var($attributes['show_button'], FILTER_VALIDATE_BOOLEAN),
                'buttonText' => $attributes['button_text'],
                'showPrice' => filter_var($attributes['show_price'], FILTER_VALIDATE_BOOLEAN),
                'showDiscount' => filter_var($attributes['show_discount'], FILTER_VALIDATE_BOOLEAN),
                'showDescription' => filter_var($attributes['show_description'], FILTER_VALIDATE_BOOLEAN)
            );

            // Activar modo de múltiples productos si se pasaron varias URLs o estilo tipo carousel/tabla
            if (!empty($amazonUrls) || in_array($block_attributes['displayStyle'], array('carousel', 'table'))) {
                $block_attributes['multipleProductsMode'] = true;
            }
            
            // Usar la misma función de renderizado que el bloque
            return $this->render_amazon_product_block($block_attributes);
        } catch (\Throwable $e) {
            error_log('[CosasDeAmazon][shortcode] ' . $e->getMessage());
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<div class="cosas-amazon-error">Error en shortcode: ' . esc_html($e->getMessage()) . '</div>';
            }
            return '';
        }
    }
}
