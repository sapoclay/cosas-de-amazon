<?php
/**
 * Funciones helper del plugin Cosas de Amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonHelpers {
    /** Cache local de opciones para minimizar lecturas a BD. */
    private static $options_cache = array();
    /** Cache de existencia de la tabla de caché. */
    private static $cache_table_exists = null;
    
    /** Obtener opción con caché local. */
    private static function get_option_cached($name, $default = array()) {
        if (array_key_exists($name, self::$options_cache)) {
            return self::$options_cache[$name];
        }
        $value = get_option($name, $default);
        self::$options_cache[$name] = $value;
        return $value;
    }
    
    /**
     * Extraer ASIN de una URL de Amazon
     */
    public static function extract_asin_from_url($url) {
        if (empty($url)) {
            return false;
        }
        
        // Patrones para extraer ASIN
        $patterns = array(
            '/\/dp\/([A-Z0-9]{10})/',
            '/\/product\/([A-Z0-9]{10})/',
            '/\/exec\/obidos\/ASIN\/([A-Z0-9]{10})/',
            '/\/gp\/product\/([A-Z0-9]{10})/',
            '/ASIN\/([A-Z0-9]{10})/',
            '/\/([A-Z0-9]{10})(?:\/|$|\?)/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }
    
    /**
     * Verificar si es una URL de Amazon válida
     */
    public static function is_amazon_url($url) {
        if (empty($url)) {
            return false;
        }
        
        $amazon_domains = array(
            'amazon.com', 'amazon.es', 'amazon.fr', 'amazon.de',
            'amazon.co.uk', 'amazon.it', 'amazon.ca', 'amazon.com.mx',
            'amazon.com.br', 'amazon.in', 'amazon.co.jp', 'amazon.com.au',
            'amzn.to', 'a.co'
        );
        
        $parsed_url = parse_url($url);
        if (!isset($parsed_url['host'])) {
            return false;
        }
        
        $host = strtolower($parsed_url['host']);
        $host = preg_replace('/^www\./', '', $host);
        
        return in_array($host, $amazon_domains);
    }
    
    /**
     * Formatear precio
     */
    public static function format_price($price) {
        if (empty($price)) {
            return '';
        }
        
        // Limpiar y formatear el precio
        $price = trim($price);
        $price = html_entity_decode($price, ENT_QUOTES, 'UTF-8');
        
        return $price;
    }

    /**
     * Normalizar precio para mostrar en formato español preservando exactamente los céntimos capturados.
     * Preferimos el string original (dos dígitos de céntimos) y solo como último recurso formateamos por float.
     * Resultado: "1.234,56 €" o "2.499 €" (sin céntimos para precios enteros altos)
     */
    public static function normalize_price_display($price) {
        if (empty($price)) { return ''; }
        $s = html_entity_decode((string)$price, ENT_QUOTES, 'UTF-8');
        // Eliminar invisibles y normalizar espacios
        $s = preg_replace('/[\x{FEFF}\x{200B}-\x{200D}\x{00AD}\x{200E}\x{200F}\x{2060}\x{FFFD}]/u', '', $s);
        $s = preg_replace('/[\x{00A0}\x{202F}\x{2007}\x{2002}\x{2003}\x{2004}\x{2005}\x{2006}\x{2008}\x{2009}\x{200A}\x{205F}]/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim($s);

        // Guard: si el string representa explícitamente un precio cero (0, 0,00, 0.00, con o sin €), no mostrarlo como 0,00 €
        if (preg_match('/^\s*0+(?:[,.]0+)?\s*(?:€|eur)?\s*$/iu', $s)) {
            return '';
        }

        // 1) Intento preferente: detectar enteros + separador decimal + dos dígitos y reconstruir sin alterar céntimos
        // Formato europeo con coma decimal: 1.234,56 € o 24,99 €
        if (preg_match('/(?<!\d)(\d{1,3}(?:[\.\s]\d{3})*|\d+),(\d{2})(?!\d)/u', $s, $m)) {
            $int = preg_replace('/[^0-9]/', '', $m[1]);
            // Reinsertar separadores de miles como punto
            $int_fmt = number_format((int)$int, 0, ',', '.');
            $cents = $m[2];
            // Si los céntimos son 00 y el precio es >= 100, mostrar sin céntimos
            if ($cents === '00' && (int)$int >= 100) {
                return $int_fmt . ' €';
            }
            return $int_fmt . ',' . $cents . ' €';
        }
        
        // Formato europeo entero con separador de miles: 2.499 € (sin céntimos)
        if (preg_match('/(?<!\d)(\d{1,3}(?:\.\d{3})+)(?!\d)/u', $s, $m)) {
            $int = preg_replace('/[^0-9]/', '', $m[1]);
            $int_fmt = number_format((int)$int, 0, ',', '.');
            return $int_fmt . ' €';
        }
        
        // Formato con punto decimal (ej. JSON/LD) - convertimos a coma pero preservamos dos dígitos
        if (preg_match('/(?<!\d)(\d{1,3}(?:[,\s]\d{3})*|\d+)\.(\d{2})(?!\d)/u', $s, $m)) {
            $int = preg_replace('/[^0-9]/', '', $m[1]);
            $int_fmt = number_format((int)$int, 0, ',', '.');
            $cents = $m[2];
            // Si los céntimos son 00 y el precio es >= 100, mostrar sin céntimos
            if ($cents === '00' && (int)$int >= 100) {
                return $int_fmt . ' €';
            }
            return $int_fmt . ',' . $cents . ' €';
        }
        // Si hay un solo dígito decimal, pad a dos (Amazon muestra dos)
        if (preg_match('/(?<!\d)(\d{1,3}(?:[\.\s]\d{3})*|\d+)[,\.](\d)(?!\d)/u', $s, $m)) {
            $int = preg_replace('/[^0-9]/', '', $m[1]);
            $int_fmt = number_format((int)$int, 0, ',', '.');
            $cents = $m[2] . '0';
            return $int_fmt . ',' . $cents . ' €';
        }

        // 2) Solo enteros detectados: formatear sin céntimos si >= 100, con céntimos si < 100
        if (preg_match('/\d+/', $s, $m)) {
            $int = preg_replace('/[^0-9]/', '', $m[0]);
            if ($int !== '') {
                $intval = (int)$int;
                if ($intval > 0) {
                    $int_fmt = number_format($intval, 0, ',', '.');
                    // Para precios >= 100 enteros, no mostrar céntimos
                    if ($intval >= 100) {
                        return $int_fmt . ' €';
                    }
                    return $int_fmt . ',00 €';
                }
                // Si es 0, no formatear como precio
            }
        }

        // 3) Último recurso: parseo numérico y formateo (puede redondear)
        $value = self::extract_numeric_price($s);
        if ($value > 0) {
            // Si el precio es entero y >= 100, no mostrar céntimos
            if (fmod($value, 1) == 0 && $value >= 100) {
                return number_format($value, 0, ',', '.') . ' €';
            }
            $formatted = number_format($value, 2, ',', '.');
            return $formatted . ' €';
        }

        // Asegurar espacio antes de € si hubiera números
        if (strpos($s, '€') === false && preg_match('/[0-9]/', $s)) {
            $s = $s . ' €';
        }
        $s = preg_replace('/^€\s*(.+)$/u', '$1 €', $s);
        $s = preg_replace('/(\d)\s*€/u', '$1 €', $s);
        return trim($s);
    }
    
    /**
     * Obtener datos del producto desde caché
     */
    public static function get_cached_product_data($asin) {
        $cache_key = 'cosas_amazon_product_' . $asin;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data && is_array($cached_data)) {
            self::log_debug('Datos encontrados en caché para ASIN: ' . $asin);
            // Validar caché cuando hay descuento para evitar falsos positivos por precio por unidad
            if (self::is_strict_discount_validation_enabled() && !empty($cached_data['discount'])) {
                $title = isset($cached_data['title']) ? $cached_data['title'] : '';
                $orig = isset($cached_data['originalPrice']) ? $cached_data['originalPrice'] : '';
                $price = isset($cached_data['price']) ? $cached_data['price'] : '';
                if (self::is_suspicious_unit_price_context($title, $orig, $price)) {
                    self::log_debug('❌ Caché descartada: descuento sospechoso por precio por unidad');
                    delete_transient($cache_key);
                    return false;
                }
            }
            return $cached_data;
        }
        
        return false;
    }
    
    /**
     * Guardar datos del producto en caché
     */
    public static function cache_product_data($asin, $product_data) {
        if (!$asin || !$product_data) {
            return false;
        }
        
        $cache_key = 'cosas_amazon_product_' . $asin;
        
        // Si son datos de fallback, caché muy corto (5 minutos)
        if (isset($product_data['is_fallback']) && $product_data['is_fallback']) {
            $cache_duration = 300; // 5 minutos
            self::log_debug('Datos fallback guardados con caché corto (5 min) para ASIN: ' . $asin);
        } else {
            // Datos reales: usar duración configurada (en segundos) o default
            $cache_duration = self::get_cache_duration();
            self::log_debug('Datos reales guardados en caché (' . ($cache_duration/3600) . ' horas) para ASIN: ' . $asin);
        }
        
        set_transient($cache_key, $product_data, $cache_duration);
        
        return true;
    }

    /**
     * Obtener duración del caché desde la configuración
     * @return int Duración en segundos
     */
    public static function get_cache_duration() {
        $options = self::get_option_cached('cosas_amazon_options', array());
        
        // `cache_duration` se guarda en SEGUNDOS (ver admin.php, min 300 max 86400)
        if (isset($options['cache_duration']) && intval($options['cache_duration']) > 0) {
            return max(300, min(86400, intval($options['cache_duration'])));
        }
        
        // Por defecto: 1 hora
        return 3600;
    }

    /**
     * Limpiar caché de un producto específico
     * @param string $asin ASIN del producto
     * @return bool
     */
    public static function clear_product_cache($asin) {
        if (empty($asin)) {
            return false;
        }
        
        $cache_key = 'cosas_amazon_product_' . $asin;
        delete_transient($cache_key);
        self::log_debug('Caché eliminada para ASIN: ' . $asin);
        
        return true;
    }

    /** Obtener caché persistente de la tabla propia como fallback rápido. */
    public static function get_db_cached_product_data($url) {
        global $wpdb;
        if (empty($url)) { return false; }
        $table = $wpdb->prefix . 'cosas_amazon_cache';
        $prev = $wpdb->suppress_errors();
        $wpdb->suppress_errors(true);
        try {
            if (self::$cache_table_exists === null) {
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                self::$cache_table_exists = ($exists === $table);
            }
            if (!self::$cache_table_exists) { return false; }
            $row = $wpdb->get_row($wpdb->prepare("SELECT product_data, updated_at FROM {$table} WHERE url = %s", $url), ARRAY_A);
            if (!$row || empty($row['product_data'])) { return false; }
            $data = json_decode($row['product_data'], true);
            if (!is_array($data)) { return false; }
            // Guardar timestamp de actualización para diagnóstico si se necesitara
            if (!isset($data['_cached_updated_at'])) {
                $data['_cached_updated_at'] = $row['updated_at'] ?? '';
            }
            return $data;
        } catch (\Throwable $e) {
            return false;
        } finally {
            $wpdb->suppress_errors($prev);
        }
    }

    /** Programar actualización asíncrona evitando golpear Amazon en tiempo de carga. */
    private static function maybe_schedule_async_refresh($url, $asin) {
        if (is_admin()) { return; }
        if (function_exists('wp_doing_cron') && wp_doing_cron()) { return; }
        $asin = trim((string) $asin);
        if ($asin === '') { return; }
        $lock_key = 'cosas_amazon_async_lock_' . $asin;
        if (get_transient($lock_key)) { return; }
        set_transient($lock_key, 1, 600); // Bloqueo corto para evitar duplicados
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + 60, 'cosas_amazon_async_refresh', array($url, $asin));
        }
    }

    /** Persistir en tabla propia para usar como caché persistente. */
    private static function persist_cache_row($url, $product_data) {
        if (!class_exists('CosasDeAmazon')) { return; }
        try {
            if (method_exists('CosasDeAmazon', 'upsert_cache_row')) {
                CosasDeAmazon::upsert_cache_row($url, $product_data);
            }
        } catch (\Throwable $e) {
            // Silenciar: no queremos bloquear el render si la BD falla
        }
    }
    
    /**
     * Obtener datos de fallback (datos simulados)
     */
    public static function get_fallback_data($asin, $url = '') {
        self::log_debug('Usando datos de fallback NEUTRAL para ASIN: ' . $asin);
        
        // Crear título más informativo con enlace
        $title = 'Ver producto en Amazon';
        if ($asin) {
            $title = 'Producto Amazon - ' . $asin;
        }
        
        // Descripción que explica la situación
        $description = '⚠️ No se pudieron obtener los datos automáticamente. ';
        $description .= 'Esto puede ocurrir cuando Amazon bloquea las peticiones de scraping. ';
        $description .= 'Haz clic en el enlace para ver el producto directamente en Amazon.';
        
        // Devolver un placeholder neutral sin precios/valoraciones
        return array(
            'asin' => $asin,
            'url' => $url ?: 'https://www.amazon.es/dp/' . $asin,
            'title' => $title,
            'price' => '',
            'originalPrice' => '',
            'discount' => '',
            'image' => self::get_fallback_image($asin),
            'description' => $description,
            'specialOffer' => '',
            'rating' => '',
            'reviewCount' => '',
            'is_fallback' => true,
            'amazon_blocked' => true // Flag especial para indicar bloqueo
        );
    }
    
    /**
     * Obtener datos del producto (versión simplificada)
     */
    public static function get_product_data($url, $force_refresh = false) {
        // Cache en memoria: evitar procesar la misma URL varias veces en una peticion
        $url_key = md5($url);
        if (!$force_refresh && isset(self::$product_cache[$url_key])) {
            return self::$product_cache[$url_key];
        }
        
        // Log del inicio
        self::log_debug('Iniciando get_product_data', $url);
        $cached_data = null;
        
        if (!self::is_amazon_url($url)) {
            self::log_debug('URL no es de Amazon', $url);
            return false;
        }
        
        // Intentar resolver URL corta primero
        $resolved_url = self::resolve_short_url($url);
        $final_url = $resolved_url ? $resolved_url : $url;
        
        $asin = self::extract_asin_from_url($final_url);
        if (!$asin) {
            // Si no se pudo extraer ASIN de la URL resuelta, intentar con la original
            $asin = self::extract_asin_from_url($url);
        }
        
        if (!$asin) {
            // No se pudo extraer ASIN (común cuando no se puede resolver amzn.to/a.co por restricciones del servidor)
            self::log_debug('No se pudo extraer ASIN - devolviendo placeholder mínimo', $url);
            // Devolver datos mínimos para no dejar el bloque vacío
            return array(
                'asin' => '',
                'url' => $final_url ?: $url,
                'title' => 'Producto de Amazon',
                'price' => '',
                'originalPrice' => '',
                'discount' => '',
                'image' => self::get_fallback_image(''),
                'description' => 'Consulta precio y detalles en la página del producto.',
                'specialOffer' => '',
                'rating' => '',
                'reviewCount' => ''
            );
        }
        
        self::log_debug('ASIN extraído: ' . $asin);
        
        // Si es force_refresh, limpiar el caché existente primero
        if ($force_refresh) {
            self::clear_product_cache($asin);
            self::log_debug('Force refresh: caché limpiada para ASIN: ' . $asin);
        }
        
        // Verificar caché primero (si no es refresh forzado)
        if (!$force_refresh) {
            $cached_data = self::get_cached_product_data($asin);
            if ($cached_data) {
                // Si los datos en caché son de fallback, ignorarlos y hacer scraping
                if (isset($cached_data['is_fallback']) && $cached_data['is_fallback']) {
                    self::log_debug('Datos de caché son fallback, ignorando y forzando nuevo scraping');
                    // NO retornar, continuar con scraping
                } else {
                    self::log_debug('Datos obtenidos de caché');
                    return $cached_data;
                }
            }
        }

        // Si no hay transients, intentar caché persistente en la tabla propia (stale-while-revalidate)
        if (!$force_refresh && empty($cached_data)) {
            $db_cached = self::get_db_cached_product_data($final_url);
            if (!empty($db_cached) && is_array($db_cached)) {
                self::log_debug('Usando caché persistente desde tabla para evitar scraping en tiempo de carga');
                self::cache_product_data($asin, $db_cached);
                self::maybe_schedule_async_refresh($final_url, $asin);
                return $db_cached;
            }
        }
    
        // Selección de fuente de datos:
        // - Si PA-API está activada y configurada: intentar PA-API primero.
        // - Si PA-API NO está activada (aunque existan credenciales guardadas): usar scraping directamente.
        $api_options = self::get_option_cached('cosas_amazon_api_options', array());
        $api_enabled = !empty($api_options['api_enabled']);
        $api_configured = !empty($api_options['amazon_access_key']) && !empty($api_options['amazon_secret_key']) && !empty($api_options['amazon_associate_tag']);
        $use_api = $api_enabled && $api_configured;

        $force_region_from_url = !empty($api_options['force_region_from_url']);
        $region_hint = $force_region_from_url ? self::infer_region_from_url($final_url) : null;

        $product_data = false;
        if ($use_api) {
            // Intentar primero con Amazon PA-API; si está activa y configurada.
            $product_data = self::get_product_data_from_api($asin, $region_hint);
        }

        // Si no se obtuvieron datos por API (o API desactivada), usar scraping
        if (!$product_data) {
            if (!$use_api) {
                self::log_debug('PA-API deshabilitada o no configurada; usando scraping');
                $product_data = self::get_product_data_scraping($final_url, $asin);
            } else {
                // API activa pero falló: decidir si hacemos fallback a scraping
                // Nota: `fallback_to_scraping` puede no existir en UI, por defecto SÍ.
                $fallback_enabled = isset($api_options['fallback_to_scraping']) ? (int) $api_options['fallback_to_scraping'] : 1;
                if ($fallback_enabled) {
                    self::log_debug('PA-API falló, usando scraping como fallback');
                    $product_data = self::get_product_data_scraping($final_url, $asin);
                } else {
                    self::log_debug('Fallback a scraping deshabilitado; manteniendo fallo de PA-API');
                }
            }
        }
        
        // Si todo falla, usar datos de fallback neutrales (no simulados)
        if (!$product_data) {
            $general_options = self::get_option_cached('cosas_amazon_options', array());
            $data_source = isset($general_options['data_source']) ? $general_options['data_source'] : 'real';
            
            if ($data_source === 'simulated') {
                self::log_debug('Usando datos simulados según configuración');
                $product_data = self::get_fallback_data($asin, $final_url);
            } else {
                // Último recurso: placeholder neutral con información del bloqueo
                self::log_debug('Usando placeholder neutral como último recurso (posible bloqueo de Amazon)');
                $product_data = self::get_fallback_data($asin, $final_url);
            }
        }
        
        // Guardar en caché si obtuvimos datos
        if ($product_data) {
            self::cache_product_data($asin, $product_data);
            self::persist_cache_row($final_url, $product_data);
            self::$product_cache[$url_key] = $product_data;
        }
        
        return $product_data;
    }
    
    /**
     * Obtener datos del producto usando Amazon PA-API
     */
    public static function get_product_data_from_api($asin, $region_hint = null) {
        // Cargar clase PA-API si no está cargada
        if (!class_exists('CosasAmazonPAAPI')) {
            require_once dirname(__FILE__) . '/class-amazon-paapi.php';
        }
        
        $api = new CosasAmazonPAAPI();
        
        if (!$api->isEnabled() || !$api->isConfigured()) {
            self::log_debug('Amazon PA-API no configurada o deshabilitada - continuando con scraping');
            return false;
        }
        
    self::log_debug('Intentando obtener datos con Amazon PA-API para ASIN: ' . $asin . ($region_hint ? (' en región forzada: ' . $region_hint) : ''));
        
        try {
            if (!empty($region_hint)) {
                // Forzar región detectada de la URL y evitar fallback cruzado de marketplace
                if (method_exists($api, 'getProductDataForRegion')) {
                    $api_data = $api->getProductDataForRegion($asin, $region_hint, true);
                } else {
                    // Compatibilidad por si no existe el método
                    $api_data = $api->getProductData($asin);
                }
            } else {
                $api_data = $api->getProductData($asin);
            }
            
            if ($api_data && !empty($api_data['title'])) {
                // Validación adicional: si hay región ES pero el precio aparenta USD o numéricamente es 0, desechar para forzar scraping
                $price_str = isset($api_data['price']) ? (string)$api_data['price'] : '';
                $num_val = self::extract_numeric_price($price_str);
                $is_us_currency = (strpos($price_str, '$') !== false) || stripos($price_str, 'USD') !== false;
                if (!empty($region_hint) && $region_hint === 'es' && ($is_us_currency || $num_val <= 0)) {
                    self::log_debug('Precio/moneda inconsistente para ES desde PA-API, forzando scraping. Precio=' . $price_str);
                    return false;
                }
                self::log_debug('Datos obtenidos exitosamente de Amazon PA-API');
                return $api_data;
            } else {
                self::log_debug('Amazon PA-API no devolvió datos válidos');
                return false;
            }
            
        } catch (Exception $e) {
            self::log_debug('Error en Amazon PA-API: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Inferir región de Amazon a partir del host de la URL
     */
    public static function infer_region_from_url($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return null;
        $host = strtolower($host);
        if (strpos($host, 'amazon.es') !== false) return 'es';
        if (strpos($host, 'amazon.fr') !== false) return 'fr';
        if (strpos($host, 'amazon.it') !== false) return 'it';
        if (strpos($host, 'amazon.de') !== false) return 'de';
        if (strpos($host, 'amazon.co.uk') !== false) return 'uk';
        if (strpos($host, 'amazon.com') !== false) return 'us';
        return null;
    }
    
    /**
     * Obtener datos del producto usando scraping (método original)
     */
    public static function get_product_data_scraping($url, $asin) {
        self::log_debug('Iniciando scraping para ASIN: ' . $asin);
        
        // Usar el método de scraping mejorado que maneja la descompresión
        return self::scrape_amazon_product($url, $asin);
    }

    /**
     * Continuar con el flujo original de get_product_data
     */
    private static function continue_get_product_data($url, $asin, $force_refresh = false) {
        // Crear clave de caché más específica que incluya tanto ASIN como hash de URL
        $url_hash = substr(md5($url), 0, 8); // Hash corto de la URL
        $cache_key = 'cosas_amazon_product_' . $asin . '_' . $url_hash;
        
        // Si es force_refresh, limpiar ambas claves de caché (simple y con hash)
        if ($force_refresh) {
            delete_transient($cache_key);
            delete_transient('cosas_amazon_product_' . $asin);
            self::log_debug('Force refresh: caché limpiada para ASIN: ' . $asin . ' (ambas claves)');
        }
        
        // Verificar caché (solo si no se fuerza refresh)
        if (!$force_refresh) {
            $cached_data = get_transient($cache_key);
            
            if ($cached_data !== false) {
                self::log_debug('Datos obtenidos desde caché para ASIN: ' . $asin . ' URL: ' . $url);
                
                // Validar que los datos en caché tengan descuentos coherentes si aplica
                if (self::is_strict_discount_validation_enabled() && !empty($cached_data['discount'])) {
                    if (!self::validate_cached_discount_data($cached_data)) {
                        self::log_debug('❌ Datos en caché con descuentos inválidos, invalidando caché para ' . $asin);
                        delete_transient($cache_key);
                    } else {
                        self::log_debug('✅ Datos de caché validados para ' . $asin . ' desde ' . $url);
                        return $cached_data;
                    }
                } else {
                    return $cached_data;
                }
            }
        }

        // Verificar configuración de fuente de datos
        $options = get_option('cosas_amazon_options', array());
        $data_source = isset($options['data_source']) ? $options['data_source'] : 'real';
        
        if ($data_source === 'simulated') {
            self::log_debug('Usando datos simulados por configuración');
            $simulated_data = self::get_simulated_data($asin, $url);
            
            // Guardar en caché con la nueva clave específica
            $cache_duration = self::get_cache_duration();
            set_transient($cache_key, $simulated_data, $cache_duration);
            
            return $simulated_data;
        }

        // Usar el nuevo sistema mejorado para datos reales
        $product_data = self::get_product_data_with_retry($url);
        
        if ($product_data && !empty($product_data['title'])) {
            // Verificar si son datos de fallback
            if (strpos($product_data['title'], 'Producto de Amazon –') === 0) {
                self::log_debug('Datos de fallback obtenidos para ASIN: ' . $asin);
            } else {
                self::log_debug('Datos reales obtenidos exitosamente para ASIN: ' . $asin . ' URL: ' . $url);
                
                // Log adicional para debugging de múltiples productos
                if (!empty($product_data['discount'])) {
                    self::log_debug('🏷️  Descuento detectado en producto: ' . $product_data['discount'] . '% para URL: ' . $url);
                }
            }
            
            // Guardar en caché con la nueva clave específica
            $cache_duration = self::get_cache_duration();
            set_transient($cache_key, $product_data, $cache_duration);
            self::log_debug('Datos guardados en caché por ' . $cache_duration . ' segundos con clave: ' . $cache_key);
            
            return $product_data;
        }

        self::log_debug('No se pudieron obtener datos del producto para ASIN: ' . $asin . ' URL: ' . $url);
        return false;
    }
    
    /**
     * Hacer scraping real de una página de producto de Amazon
     */
    public static function scrape_amazon_product($url, $asin) {
        self::log_debug('=== INICIO SCRAPING ===');
        self::log_debug('URL a scrapear: ' . $url);
        self::log_debug('ASIN: ' . $asin);
        
        // Obtener configuración de timeout (reducido para mejor rendimiento)
        $options = get_option('cosas_amazon_options', array());
        $timeout = isset($options['scraping_timeout']) ? min(intval($options['scraping_timeout']), 15) : 10;
        
        self::log_debug('Timeout configurado: ' . $timeout . ' segundos');
        
        // Configurar headers mejorados para simular un navegador real
        // User-Agents MÁS DIVERSOS (2025) con mayor variabilidad
        $user_agents = [
            // Chrome Windows (más recientes)
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            // Firefox
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0',
            // Safari macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 15_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15',
            // Edge
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
            // Chrome Linux
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            // Chrome macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ];
        
        $accept_languages = [
            'es-ES,es;q=0.9,en;q=0.8',
            'es-ES,es;q=0.9,en-US;q=0.8,en;q=0.7',
            'es,en-US;q=0.9,en;q=0.8',
        ];
        
        $headers = array(
            'User-Agent: ' . $user_agents[array_rand($user_agents)],
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: ' . $accept_languages[array_rand($accept_languages)],
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: max-age=0',
            'DNT: 1'
        );
        
        // Delay inicial ELIMINADO - Solo usamos delays en reintentos cuando hay bloqueo
        // Esto mejora significativamente el tiempo de carga inicial
        // Los reintentos con delay solo se ejecutan si la primera petición falla
        
        $html = '';
        $http_code = 0;
        $error = '';

        if (function_exists('curl_init')) {
            // Usar cURL si está disponible
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_ENCODING, ''); // Dejar vacío para auto-decodificar
            curl_setopt($ch, CURLOPT_COOKIEJAR, tempnam(sys_get_temp_dir(), 'cookies'));
            curl_setopt($ch, CURLOPT_COOKIEFILE, tempnam(sys_get_temp_dir(), 'cookies'));

            $html = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            self::log_debug('cURL completado - HTTP Code: ' . $http_code . ', Error: ' . ($error ?: 'ninguno') . ', HTML size: ' . strlen($html) . ' bytes');
        } else if (function_exists('wp_remote_get')) {
            // Fallback: WordPress HTTP API
            $response = @wp_remote_get($url, array(
                'timeout' => $timeout,
                'redirection' => 5,
                'sslverify' => false,
                'headers' => array(
                    'User-Agent' => $headers[0] ?? 'Mozilla/5.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache'
                ),
            ));
            if (!is_wp_error($response)) {
                $http_code = intval(wp_remote_retrieve_response_code($response));
                $html = wp_remote_retrieve_body($response);
            } else {
                $error = $response->get_error_message();
            }
        } else {
            // Sin cURL ni WP HTTP API
            self::log_debug('No hay cURL ni WP HTTP API disponible para scraping');
            return false;
        }
        
        // Verificar y descomprimir si es necesario
        if ($html && strlen($html) > 0) {
            // Verificar si está comprimido
            if (substr($html, 0, 2) == "\x1f\x8b") {
                // Gzip comprimido
                if (function_exists('gzdecode')) {
                    $decompressed = @gzdecode($html);
                    if ($decompressed !== false) {
                        $html = $decompressed;
                        self::log_debug("HTML descomprimido con gzdecode: " . strlen($html) . " bytes");
                    } else if (function_exists('gzinflate')) {
                        // Intentar con gzinflate
                        $decompressed = @gzinflate(substr($html, 10, -8));
                        if ($decompressed !== false) {
                            $html = $decompressed;
                            self::log_debug("HTML descomprimido con gzinflate: " . strlen($html) . " bytes");
                        } else {
                            self::log_debug("No se pudo descomprimir HTML gzip");
                        }
                    }
                }
            } else if (substr($html, 0, 2) == "\x78\x9c") {
                // Deflate comprimido
                if (function_exists('gzinflate')) {
                    $decompressed = @gzinflate($html);
                    if ($decompressed !== false) {
                        $html = $decompressed;
                        self::log_debug("HTML descomprimido con deflate: " . strlen($html) . " bytes");
                    } else {
                        self::log_debug("No se pudo descomprimir HTML deflate");
                    }
                }
            } else {
                self::log_debug("HTML no comprimido: " . strlen($html) . " bytes");
            }
            
            // Si el HTML es muy pequeño, puede ser bloqueo de Amazon - hacer UN solo reintento rápido
            if (strlen($html) < 100000) {
                self::log_debug("HTML pequeño detectado (" . strlen($html) . " bytes), posible bloqueo de Amazon");
                
                // Un solo reintento con User-Agent diferente y delay mínimo (2s)
                self::log_debug("Reintento único: Esperando 2 segundos y cambiando User-Agent...");
                sleep(2);
                
                $firefox_headers = array(
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language: es-ES,es;q=0.8,en-US;q=0.5,en;q=0.3',
                    'Accept-Encoding: gzip, deflate, br',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Sec-Fetch-Site: none',
                    'Sec-Fetch-User: ?1',
                    'DNT: 1'
                );
                
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, $url);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch2, CURLOPT_MAXREDIRS, 5);
                curl_setopt($ch2, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, $firefox_headers);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch2, CURLOPT_ENCODING, '');
                
                $html2 = curl_exec($ch2);
                $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                if ($html2 && strlen($html2) > strlen($html)) {
                    $html = $html2;
                    $http_code = $http_code2;
                    self::log_debug("✅ Reintento exitoso, HTML mejorado: " . strlen($html) . " bytes");
                } else {
                    self::log_debug("❌ Reintento falló. Se usarán datos de caché o fallback.");
                    self::log_debug("💡 SOLUCIÓN: Configurar Amazon PA-API para evitar bloqueos");
                }
            }
        }
        
        // Verificar la respuesta - solo fallar si hay error crítico
        if ($html === false || !empty($error)) {
            self::log_debug("❌ Error crítico en cURL: " . $error);
            self::log_debug("=== FIN SCRAPING (ERROR) ===");
            return false; // Devolver false para que se use el siguiente método
        }
        
        // Verificar el código de respuesta HTTP - solo fallar si no es 200
        if ($http_code !== 200) {
            self::log_debug("❌ HTTP Code no exitoso: " . $http_code);
            self::log_debug("=== FIN SCRAPING (HTTP ERROR) ===");
            return false; // Devolver false para que se use el siguiente método
        }
        
        // Si no hay HTML o es muy corto, también fallar
        if (empty($html) || strlen($html) < 1000) {
            self::log_debug("❌ HTML vacío o muy corto: " . strlen($html) . " bytes");
            self::log_debug("=== FIN SCRAPING (HTML CORTO) ===");
            return false; // Devolver false para que se use el siguiente método
        }
        
        self::log_debug("✅ HTML válido recibido, procediendo a parsear...");
        
        // Parsear el HTML para extraer datos
        $parsed_data = self::parse_amazon_html($html, $asin, $url);
        
        if ($parsed_data && !empty($parsed_data['title'])) {
            self::log_debug("✅ Scraping exitoso - Título: " . $parsed_data['title']);
            self::log_debug("=== FIN SCRAPING (ÉXITO) ===");
        } else {
            self::log_debug("❌ Scraping falló en el parseo");
            self::log_debug("=== FIN SCRAPING (PARSEO FALLIDO) ===");
        }
        
        return $parsed_data;
    }

    /**
     * Heurística: detectar contexto de precio por unidad (ml, L, kg, pack, xN) y ratios sospechosos
     */
    public static function is_suspicious_unit_price_context($title, $original_price_str, $current_price_str) {
    $title_lc = function_exists('mb_strtolower') ? mb_strtolower($title ?? '') : strtolower($title ?? '');
        $looks_like_unit = false;
        if (!empty($title_lc)) {
            $unit_patterns = [
                '/\b\d+\s?(ml|l|litro|litros|kg|g)\b/i',
                '/\bpor\s?(100|1\s?l|1\s?kg|kg|l)\b/i',
                '/\bpack\b/i',
                '/\b\d+\s?x\b/i',
                '/\b\d+\s?(unidades|unidad|capsulas|cápsulas|tabletas)\b/i',
                '/\b\d+\s?(ml|g)\s?(cada|c\/u)\b/i'
            ];
            foreach ($unit_patterns as $p) {
                if (preg_match($p, $title_lc)) { $looks_like_unit = true; break; }
            }
        }
        // También detectar en el string del original si parece €/L, €/kg, €/100ml
    $orig_lc = function_exists('mb_strtolower') ? mb_strtolower($original_price_str ?? '') : strtolower($original_price_str ?? '');
        if (!$looks_like_unit && $orig_lc) {
            if (preg_match('/(€|eur)\s*\/\s*(l|kg|100\s?g|100\s?ml)/i', $orig_lc)) {
                $looks_like_unit = true;
            }
        }
        $current = self::extract_numeric_price($current_price_str);
        $original = self::extract_numeric_price($original_price_str);
        $suspicious_ratio = false;
        if ($current > 0 && $original > 0 && $original > $current) {
            $ratio = $original / $current;
            if (($ratio >= 3.5 && $ratio <= 4.5) || $ratio >= 8) {
                $suspicious_ratio = true;
            }
        }
        return ($looks_like_unit && $suspicious_ratio);
    }
    
    /**
     * Parsear HTML de Amazon para extraer datos del producto
     */
    public static function parse_amazon_html($html, $asin, $url) {
        self::log_debug('=== INICIO PARSEO HTML ===');
        self::log_debug('Tamaño HTML: ' . strlen($html) . ' bytes');
        self::log_debug('ASIN: ' . $asin);
        
        // Log de una muestra del HTML para diagnóstico
        $html_sample = substr($html, 0, 500);
        self::log_debug('Muestra HTML (primeros 500 chars): ' . $html_sample);
        
        // Verificar si Amazon está bloqueando
        if (stripos($html, 'robot') !== false || stripos($html, 'captcha') !== false || stripos($html, 'automated') !== false) {
            self::log_debug('⚠️ ADVERTENCIA: Amazon parece estar bloqueando el scraping (detectado: robot/captcha/automated)');
        }
        
        // Convertir HTML a UTF-8 si es necesario
        $html = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($html, 'UTF-8', 'auto')
            : (function_exists('iconv') ? @iconv('UTF-8', 'UTF-8//IGNORE', $html) : $html);
        
        $product_data = array(
            'asin' => $asin,
            'url' => $url,
            'title' => '',
            'price' => '',
            'originalPrice' => '',
            'discount' => '',
            'image' => '',
            'description' => '',
            'specialOffer' => '',
            'rating' => '',
            'reviewCount' => '',
            'availability' => 'En stock' // Por defecto asumimos disponible
        );
        
        // Detectar disponibilidad del producto - ENFOQUE MEJORADO
        // Primero verificar indicadores POSITIVOS de disponibilidad (más confiables)
        $is_available = false;
        $is_unavailable = false;
        
        // Indicadores FUERTES de disponibilidad (presencia de botones de compra)
        $strong_available_patterns = array(
            '/id="add-to-cart-button"[^>]*>/i',
            '/id="buy-now-button"[^>]*>/i',
            '/id="submit\.add-to-cart"[^>]*>/i',
            '/name="submit\.add-to-cart"/i',
            '/"buyingOptionContent"/i',
            '/id="addToCart"[^>]*>/i',
            '/class="[^"]*add-to-cart[^"]*"/i'
        );
        
        foreach ($strong_available_patterns as $pattern) {
            if (preg_match($pattern, $html)) {
                $is_available = true;
                self::log_debug('✅ Producto DISPONIBLE - Detectado botón de compra');
                break;
            }
        }
        
        // Solo si NO encontramos indicadores fuertes de disponibilidad, buscar indicadores de NO disponibilidad
        if (!$is_available) {
            $unavailable_patterns = array(
                // Patrones muy específicos para productos sin stock
                '/id="availability"[^>]*>\s*<[^>]*>\s*(?:Actualmente no disponible|Currently unavailable)/is',
                '/id="outOfStock"/i',
                '/class="[^"]*a-color-price[^"]*"[^>]*>\s*(?:No disponible|Currently unavailable)\s*</is',
                '/"availability"\s*:\s*"[^"]*(?:OutOfStock|SoldOut|Discontinued)"/i',
                '/id="availability"[^>]*>.*?(?:Temporalmente sin existencias|out of stock)/is'
            );
            
            foreach ($unavailable_patterns as $pattern) {
                if (preg_match($pattern, $html)) {
                    $is_unavailable = true;
                    self::log_debug('❌ Producto NO DISPONIBLE - Patrón detectado');
                    break;
                }
            }
        }
        
        // Indicadores secundarios de disponibilidad (texto)
        if (!$is_available && !$is_unavailable) {
            $secondary_available_patterns = array(
                '/id="availability"[^>]*>.*?(?:En stock|In Stock|Disponible|Envío GRATIS)/is',
                '/"availability"\s*:\s*"[^"]*(?:InStock|Available|InStoreOnly|LimitedAvailability)"/i',
                '/id="deliveryMessageMirId"[^>]*>/i',  // Si hay mensaje de entrega, está disponible
                '/id="mir-layout-DELIVERY_BLOCK"/i'   // Bloque de entrega presente
            );
            
            foreach ($secondary_available_patterns as $pattern) {
                if (preg_match($pattern, $html)) {
                    $is_available = true;
                    self::log_debug('✅ Producto DISPONIBLE - Indicador secundario');
                    break;
                }
            }
        }
        
        // Establecer disponibilidad final
        // Por defecto asumimos disponible a menos que haya evidencia clara de lo contrario
        if ($is_unavailable && !$is_available) {
            $product_data['availability'] = 'No disponible';
        } else {
            $product_data['availability'] = 'En stock';
        }
        
        // Intentar extraer datos de JSON embebido primero
        $json_data = self::extract_json_data($html);
        if (!empty($json_data)) {
            if (!empty($json_data['title'])) {
                $product_data['title'] = $json_data['title'];
            }
            if (!empty($json_data['price'])) {
                $product_data['price'] = $json_data['price'];
            }
            if (!empty($json_data['originalPrice'])) {
                $product_data['originalPrice'] = $json_data['originalPrice'];
            }
            if (!empty($json_data['discount'])) {
                $product_data['discount'] = $json_data['discount'];
            }
            if (!empty($json_data['image'])) {
                $product_data['image'] = $json_data['image'];
            }
            if (!empty($json_data['rating'])) {
                $product_data['rating'] = $json_data['rating'];
            }
            if (!empty($json_data['reviewCount'])) {
                $product_data['reviewCount'] = $json_data['reviewCount'];
            }
        }
        
        // Extraer título
        $title_patterns = [
            '/<span[^>]*id="productTitle"[^>]*>([^<]+)<\/span>/i',
            '/<h1[^>]*class="[^"]*product[^"]*title[^"]*"[^>]*>([^<]+)<\/h1>/i',
            '/<title>([^<]+)<\/title>/i'
        ];
        
        foreach ($title_patterns as $i => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
                if (!empty($title)) {
                    $product_data['title'] = $title;
                    self::log_debug("Título extraído con patrón $i: " . $title);
                    break;
                }
            }
        }
        
        // Si no se encontró título, verificar si al menos existe el elemento productTitle
        if (empty($product_data['title']) && strpos($html, 'productTitle') !== false) {
            self::log_debug("Elemento productTitle encontrado pero no se pudo extraer el contenido");
            // Usar título de la página como fallback
            if (preg_match('/<title>([^<]+)<\/title>/i', $html, $matches)) {
                $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
                $product_data['title'] = $title;
                self::log_debug("Usando título de página como fallback: " . $title);
            }
        }
        
        // Extraer precio actual - Patrones mejorados y más robustos
        // NOTA: Para productos con variaciones, priorizar el precio del buybox (opción seleccionada)
        
        // Si ya tenemos precio del JSON (por ejemplo, de datos de twister/variantes), no buscar en HTML
        // Esto evita tomar precios de productos relacionados/recomendados
        if (!empty($product_data['price'])) {
            self::log_debug('✅ Precio ya obtenido de JSON embebido: ' . $product_data['price'] . ' - Saltando scraping HTML de precios');
        } else {
            // IMPORTANTE: Limpiar HTML de carruseles de productos relacionados ANTES de buscar precios
            // Esto evita capturar precios de productos recomendados como "Los clientes también vieron"
            $html_for_price = $html;
            
            // Eliminar carruseles de productos relacionados/recomendados que contienen precios de OTROS productos
            $carousel_patterns = [
                // CRÍTICO: Eliminar iframes de anuncios patrocinados (contienen precios de otros productos)
                '/<iframe[^>]*id="[^"]*ape_[^"]*"[^>]*>.*?<\/iframe>/is',
                '/<iframe[^>]*name="[^"]*arid[^"]*"[^>]*>.*?<\/iframe>/is',
                // Eliminar divs de anuncios de Amazon Advertising
                '/<div[^>]*class="[^"]*ad-feedback[^"]*"[^>]*>.*?<\/div>/is',
                '/<div[^>]*data-ad-feedback[^>]*>.*?<\/div>/is',
                // Eliminar secciones de "Comprados juntos habitualmente" (productos bundle)
                '/<div[^>]*id="[^"]*sims-fbt[^"]*"[^>]*>.*?<\/div>/is',
                '/<div[^>]*data-csa-c-type="item"[^>]*>.*?<\/div>/is',
                // Eliminar widgets de video de marcas patrocinadas
                '/<div[^>]*class="[^"]*_multi-brand-video[^"]*"[^>]*>.*?<\/div>/is',
                '/<div[^>]*data-video-ad-attributes-props[^>]*>.*?<\/div>/is',
                // Carruseles de "productos alternativos" y "también vieron"
                '/<div[^>]*class="[^"]*a-carousel-container[^"]*"[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>/is',
                // Widgets de productos similares (cerberus, p13n)
                '/<div[^>]*data-type="Cerberus"[^>]*>.*?<script>/is',
                '/<div[^>]*class="[^"]*p13n-sc-[^"]*"[^>]*>.*?<\/li>\s*<\/ol>/is',
                // Sección "También te puede interesar"
                '/<div[^>]*id="[^"]*sims-[^"]*"[^>]*>.*?<\/div>/is',
                // Sección "Libros relacionados" con anuncios
                '/<div[^>]*id="CardInstance[^"]*"[^>]*>.*?<\/div>/is',
                // Comentarios HTML con anuncios
                '/<!--CardsClient-->.*?<!--/is',
            ];
            
            foreach ($carousel_patterns as $pattern) {
                $html_for_price = preg_replace($pattern, '', $html_for_price);
            }
            
            self::log_debug('HTML limpio de carruseles y anuncios: ' . strlen($html_for_price) . ' bytes (original: ' . strlen($html) . ')');
        
            $price_patterns = [
            // PRIORIDAD MÁXIMA: Precio del buybox principal (productos con variaciones seleccionadas)
            // #corePrice_feature_div contiene el precio de la variación actualmente seleccionada
            '/<div[^>]*id="corePrice_feature_div"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            '/<div[^>]*id="corePriceDisplay_desktop_feature_div"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            // Precio en el buybox derecho (área de compra)
            '/<div[^>]*id="buyBoxInner"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            '/<div[^>]*id="apex_desktop"[^>]*>.*?<span[^>]*class="[^"]*a-price[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            // Patrón específico identificado por el usuario
            // div.a-section.a-spacing-micro > span.a-price.aok-align-center > span.a-offscreen
            '/<div[^>]*class="[^"]*a-section[^"]*a-spacing-micro[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-price[^"]*aok-align-center[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            '/<div[^>]*class="[^"]*a-section[^"]*a-spacing-micro[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            // Variaciones del patrón específico
            '/<span[^>]*class="[^"]*a-price[^"]*aok-align-center[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            '/<span[^>]*class="[^"]*aok-align-center[^"]*a-price[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            // Patrones específicos para precios de alto valor con separador de miles (formato europeo)
            // Ejemplo: 2.499,00 € o 2.499 €
            '/<span[^>]*class="[^"]*a-price-whole[^"]*"[^>]*>([0-9]{1,3}(?:\.[0-9]{3})*)<\/span>.*?<span[^>]*class="[^"]*a-price-fraction[^"]*"[^>]*>([0-9]{2})<\/span>/is',
            // Patrones específicos para Amazon España (.es)
            '/<span[^>]*class="[^"]*a-price-whole[^"]*"[^>]*>([^<]+)<\/span><span[^>]*class="[^"]*a-price-fraction[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price-whole[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([€$£¥₹₽][^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/i',
            // Patrones específicos para precios en euros
            '/<span[^>]*class="[^"]*a-price-whole[^"]*"[^>]*>([0-9]+)<\/span><span[^>]*class="[^"]*a-price-fraction[^"]*"[^>]*>([0-9]+)<\/span><span[^>]*class="[^"]*a-price-symbol[^"]*"[^>]*>€<\/span>/i',
            '/<span[^>]*class="[^"]*a-price-symbol[^"]*"[^>]*>€<\/span><span[^>]*class="[^"]*a-price-whole[^"]*"[^>]*>([0-9]+)<\/span>/i',
            '/<span[^>]*>€<\/span><span[^>]*>([0-9]+,[0-9]{2})<\/span>/i',
            '/<span[^>]*>([0-9]+,[0-9]{2})<\/span><span[^>]*>€<\/span>/i',
            // Patrones para precios de alto valor (más de 1000€)
            '/<span[^>]*>€<\/span><span[^>]*>([0-9]{1,3}\.[0-9]{3}(?:,[0-9]{2})?)<\/span>/i',
            '/<span[^>]*>([0-9]{1,3}\.[0-9]{3}(?:,[0-9]{2})?)<\/span><span[^>]*>€<\/span>/i',
            // Patrones originales mejorados
            '/<span[^>]*id="priceblock_[^"]*price"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*id="priceblock_dealprice"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price a-text-price a-size-medium a-color-price[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/i',
            // Patrones específicos para diferentes tipos de precio
            '/<span[^>]*class="[^"]*apexPriceToPay[^"]*"[^>]*>.*?<span[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price-range[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price a-text-price a-size-medium a-color-price[^"]*"[^>]*>([^<]+)<\/span>/i',
            // Patrones más genéricos como fallback
            '/<span[^>]*class="[^"]*a-price[^"]*"[^>]*>.*?<span[^>]*>([€$£¥₹₽][^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price[^"]*"[^>]*>.*?<span[^>]*>([^<]+)<\/span>/i',
            // Patrones para precios en elementos con id específico
            '/<span[^>]*id="price_inside_buybox"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*id="a-autoid-[^"]*-announce"[^>]*>([^<]+)<\/span>/i'
        ];
        
        foreach ($price_patterns as $i => $pattern) {
            self::log_debug("Probando patrón de precio $i: " . substr($pattern, 0, 80) . "...");
            if (preg_match($pattern, $html_for_price, $matches)) {
                // Para patrones con tres grupos (completo + decimales + símbolo)
                if (count($matches) > 3 && strpos($pattern, 'a-price-whole') !== false && strpos($pattern, 'a-price-fraction') !== false) {
                    $whole = trim($matches[1]);
                    $fraction = trim($matches[2]);
                    // Si la parte entera ya tiene formato con punto (separador de miles), mantenerlo
                    $price_text = $whole . ',' . $fraction . ' €';
                    self::log_debug("Precio extraído con patrón completo $i: " . $price_text);
                }
                // Para patrones con dos grupos (precio completo + decimales)
                else if (count($matches) > 2 && strpos($pattern, 'a-price-whole') !== false) {
                    $whole = trim($matches[1]);
                    $fraction = isset($matches[2]) ? trim($matches[2]) : '00';
                    // Mantener formato europeo con punto como separador de miles
                    $price_text = $whole . ',' . $fraction . ' €';
                    self::log_debug("Precio extraído con patrón dos grupos $i: " . $price_text);
                } else {
                    $price_text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
                    self::log_debug("Precio extraído con patrón estándar $i: " . $price_text);
                }
                
                // Logging especial para el patrón prioritario
                if ($i <= 3) {
                    self::log_debug("PATRÓN PRIORITARIO $i EXITOSO - Precio: " . $price_text);
                }
                
                if (!empty($price_text) && (preg_match('/[0-9]/', $price_text) || preg_match('/[€$£¥₹₽]/', $price_text))) {
                    // Limpiar precio de caracteres extraños pero mantener formato
                    $price_text = preg_replace('/[^\d€$£¥₹₽,.\s\-–—a]/i', '', $price_text);
                    $price_text = trim($price_text);
                    
                    // Detectar si es un rango de precios ("Desde 19,99€ - 29,99€" o "19,99€ a 29,99€")
                    // En ese caso, tomar el precio más bajo (primer precio)
                    if (preg_match('/(\d+[,.]?\d*)\s*[€$£¥₹₽]?\s*[-–—]\s*\d+[,.]?\d*\s*[€$£¥₹₽]?/', $price_text) ||
                        preg_match('/(\d+[,.]?\d*)\s*[€$£¥₹₽]?\s+a\s+\d+[,.]?\d*\s*[€$£¥₹₽]?/i', $price_text)) {
                        // Es un rango - extraer el primer precio (más bajo)
                        if (preg_match('/([€$£¥₹₽]?\s*\d+[,.]?\d*\s*[€$£¥₹₽]?)/', $price_text, $range_match)) {
                            $price_text = trim($range_match[1]);
                            self::log_debug("Rango de precios detectado, usando precio más bajo: " . $price_text);
                        }
                    }
                    
                    if (!empty($price_text)) {
                        $product_data['price'] = $price_text;
                        self::log_debug("✅ PRECIO FINAL ASIGNADO: " . $price_text);
                        self::log_debug("Precio encontrado con patrón $i: " . $price_text);
                        break;
                    }
                }
            }
        } // Fin del foreach de price_patterns
        
        // Si el precio extraído es cero o inválido, limpiarlo para activar fallback "Ver precio en Amazon"
        if (!empty($product_data['price'])) {
            $num_check = self::extract_numeric_price($product_data['price']);
            if ($num_check <= 0) {
                self::log_debug('Precio extraído no válido (0). Limpiando para usar texto por defecto.');
                $product_data['price'] = '';
            }
        }
        } // Fin del else - búsqueda de precio en HTML
        
        // Extraer descuento directo de Amazon (patrones mejorados y más específicos)
        $discount_patterns = [
            // Patrones específicos para descuentos directos de Amazon con alta precisión
            '/<span[^>]*class="[^"]*savingPriceOverride[^"]*"[^>]*>.*?([0-9]+)%[^<]*<\/span>/i',
            '/<span[^>]*class="[^"]*savingsPercentage[^"]*"[^>]*>.*?([0-9]+)%[^<]*<\/span>/i',
            '/<span[^>]*class="[^"]*reinventPriceSavingsPercentageMargin[^"]*"[^>]*>.*?([0-9]+)%[^<]*<\/span>/i',
            
            // Patrones específicos para descuentos con clases de Amazon conocidas
            '/<span[^>]*class="[^"]*a-size-large[^"]*a-color-price[^"]*"[^>]*>\s*-?\s*([0-9]+)%[^<]*<\/span>/i',
            '/<span[^>]*class="[^"]*a-size-base[^"]*a-color-price[^"]*"[^>]*>\s*-?\s*([0-9]+)%[^<]*<\/span>/i',
            
            // Patrones para texto en español con contexto específico de descuento
            '/<span[^>]*class="[^"]*[^"]*"[^>]*>\s*(?:descuento|ahorra|ahorras)\s*:?\s*([0-9]+)%[^<]*<\/span>/i',
            '/<span[^>]*>\s*\(\s*([0-9]+)%\s*descuento\s*\)\s*<\/span>/i',
            '/<span[^>]*>\s*-\s*([0-9]+)%\s*descuento\s*<\/span>/i',
            
            // Patrones más restrictivos para evitar falsos positivos
            '/<span[^>]*class="[^"]*a-color-success[^"]*"[^>]*>\s*([0-9]+)%\s*de\s*descuento[^<]*<\/span>/i',
            
            // Remover patrones demasiado genéricos que causaban falsos positivos:
            // '/<span[^>]*class="[^"]*a-letter-space[^"]*"[^>]*>.*?([0-9]+)%[^<]*<\/span>/i',
            // '/<span[^>]*class="[^"]*a-color-price[^"]*"[^>]*>.*?-([0-9]+)%[^<]*<\/span>/i',
            // '/<span[^>]*class="[^"]*a-color-success[^"]*"[^>]*>.*?([0-9]+)%[^<]*<\/span>/i',
            // '/<span[^>]*>.*?descuento[^0-9]*([0-9]+)%[^<]*<\/span>/i',
            // '/<span[^>]*>.*?ahorra[^0-9]*([0-9]+)%[^<]*<\/span>/i',
            // '/<span[^>]*>.*?ahorras[^0-9]*([0-9]+)%[^<]*<\/span>/i',
            // '/<span[^>]*>\s*-([0-9]+)%\s*<\/span>/i',
            // '/<span[^>]*>\s*\(([0-9]+)%\s*descuento\)\s*<\/span>/i',
            // '/([0-9]+)%\s*de\s*descuento/i',
            // '/descuento\s*:?\s*([0-9]+)%/i'
        ];
        
        $found_discount = false;
        foreach ($discount_patterns as $i => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $discount_value = intval($matches[1]);
                if ($discount_value >= 1 && $discount_value <= 90) {
                    // Si la validación estricta está activada, validar el contexto
                    if (self::is_strict_discount_validation_enabled()) {
                        if (self::validate_discount_context($html, $discount_value)) {
                            $product_data['discount'] = $discount_value;
                            $found_discount = true;
                            self::log_debug("✅ Descuento directo encontrado y validado con patrón $i: {$discount_value}%");
                            break;
                        } else {
                            self::log_debug("❌ Descuento {$discount_value}% descartado por contexto inválido (patrón $i)");
                        }
                    } else {
                        // Sin validación estricta, aceptar el descuento
                        $product_data['discount'] = $discount_value;
                        $found_discount = true;
                        self::log_debug("✅ Descuento directo encontrado con patrón $i: {$discount_value}% (validación estricta desactivada)");
                        break;
                    }
                } else {
                    self::log_debug("❌ Descuento {$discount_value}% fuera de rango válido (patrón $i)");
                }
            }
        }

        // Extraer precio original (tachado) - Patrones mejorados y más robustos
        $original_price_patterns = [
            // PRIORIDAD MÁXIMA: Patrón específico para precio original en la misma estructura
            // div.a-section.a-spacing-micro > span.a-price.aok-align-center > span.a-offscreen (con precio tachado)
            '/<div[^>]*class="[^"]*a-section[^"]*a-spacing-micro[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-price[^"]*a-text-price[^"]*aok-align-center[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            '/<div[^>]*class="[^"]*a-section[^"]*a-spacing-micro[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-text-price[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            // Variaciones del patrón específico para precio original
            '/<span[^>]*class="[^"]*a-price[^"]*a-text-price[^"]*aok-align-center[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            '/<span[^>]*class="[^"]*aok-align-center[^"]*a-text-price[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            // Patrones específicos para precios originales con clases exactas
            '/<span[^>]*class="[^"]*a-price a-text-price[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>\s*([^<]+)\s*<\/span>/i',
            '/<span[^>]*class="[^"]*a-price-was[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-text-strike[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price-was[^"]*"[^>]*>.*?<span[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-text-strike[^"]*"[^>]*>([^<]+)<\/span>/i',
            // Nuevos patrones más específicos para precios originales
            '/<span[^>]*class="[^"]*a-price a-text-price a-size-base[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price a-text-price a-size-small[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price a-text-price a-size-medium[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/i',
            // Patrones para precios tachados sin a-offscreen
            '/<span[^>]*class="[^"]*a-text-strike[^"]*"[^>]*>([€$£¥₹₽][^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-price-was[^"]*"[^>]*>([€$£¥₹₽][^<]+)<\/span>/i',
            // Patrones más genéricos para precios originales
            '/<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>\s*([^<]*)\s*<\/span>[^<]*<span[^>]*class="[^"]*a-text-strike[^"]*"/i',
            '/<span[^>]*text-decoration[^>]*line-through[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*style="[^"]*text-decoration[^"]*line-through[^"]*"[^>]*>([^<]+)<\/span>/i',
            // Patrones para IDs específicos de precio original
            '/<span[^>]*id="priceblock_saleprice"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*id="listPrice"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*id="was-price"[^>]*>([^<]+)<\/span>/i'
        ];
        
        $found_original_price = false;
        foreach ($original_price_patterns as $i => $pattern) {
            self::log_debug("Probando patrón de precio original $i: " . substr($pattern, 0, 80) . "...");
            if (preg_match($pattern, $html, $matches)) {
                $original_price = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
                
                // Logging especial para el patrón prioritario
                if ($i <= 3) {
                    self::log_debug("PATRÓN PRIORITARIO PRECIO ORIGINAL $i EXITOSO - Precio: " . $original_price);
                }
                
                if (!empty($original_price) && (preg_match('/[0-9]/', $original_price) || preg_match('/[€$£¥₹₽]/', $original_price))) {
                    $product_data['originalPrice'] = $original_price;
                    $found_original_price = true;
                    self::log_debug("Precio original encontrado con patrón $i: " . $original_price);
                    break;
                }
            }
        }
        
        // Validar descuento directo encontrado
        if ($found_discount && !empty($product_data['discount'])) {
            // Solo aplicar validaciones estrictas si están activadas
            if (self::is_strict_discount_validation_enabled()) {
                // Si se encontró descuento directo pero no hay precio original válido, es sospechoso
                if (!$found_original_price || empty($product_data['originalPrice'])) {
                    self::log_debug("⚠️  Descuento directo sin precio original - validación estricta activada");
                    
                    // Si no hay precio original para validar, el descuento es dudoso
                    $product_data['discount'] = '';
                    $found_discount = false;
                    self::log_debug("❌ Descuento directo eliminado: sin precio original para validar");
                } else {
                    // Validar que el descuento directo sea coherente con los precios usando la nueva función
                    if (!self::validate_price_difference($product_data['price'], $product_data['originalPrice'], $product_data['discount'])) {
                        self::log_debug("❌ Descuento directo eliminado: validación de precios falló");
                        $product_data['discount'] = '';
                        $product_data['originalPrice'] = '';
                        $found_discount = false;
                    } else {
                        self::log_debug("✅ Descuento directo validado completamente");
                    }
                }
            } else {
                self::log_debug("✅ Descuento directo aceptado (validación estricta desactivada)");
            }
        }
        
        // Solo calcular descuento desde precios si NO se encontró descuento directo válido
        if (!$found_discount && $found_original_price && !empty($product_data['price']) && !empty($product_data['originalPrice'])) {
            $current_price = self::extract_numeric_price($product_data['price']);
            $original_price = self::extract_numeric_price($product_data['originalPrice']);
            
            self::log_debug("Comparando precios - Actual: $current_price, Original: $original_price");
            
            if ($current_price > 0 && $original_price > 0 && $original_price > $current_price) {
                // Heurística anti-precio por unidad
                if (self::is_suspicious_unit_price_context($product_data['title'], $product_data['originalPrice'], $product_data['price'])) {
                    self::log_debug('❌ Descuento descartado por heurística de precio por unidad');
                    $product_data['discount'] = '';
                    $product_data['originalPrice'] = '';
                    $found_original_price = false;
                } else {
                    $discount = round((($original_price - $current_price) / $original_price) * 100);
                    // Solo asignar descuento si es un valor razonable (entre 1% y 90%)
                    if ($discount >= 1 && $discount <= 90) {
                        $product_data['discount'] = $discount;
                        self::log_debug("✅ Descuento calculado desde precios: {$discount}%");
                    } else {
                        self::log_debug("❌ Descuento inválido calculado: {$discount}%");
                    }
                }
            } else {
                self::log_debug("❌ No hay diferencia de precio válida para descuento");
                // Si no hay descuento válido, limpiar también el precio original
                $product_data['originalPrice'] = '';
            }
        } else {
            self::log_debug("No se calculó descuento desde precios - ya hay descuento directo o no se encontró precio original válido");
        }
        
        // Validación final: limpiar si no hay descuento válido
        if (empty($product_data['discount']) || $product_data['discount'] === 0) {
            $product_data['discount'] = '';
            $product_data['originalPrice'] = '';
            self::log_debug("🧹 Datos de descuento limpiados - no hay descuento válido");
        } else {
            self::log_debug("✅ Descuento final validado: " . $product_data['discount'] . "%");
        }
        
        // Extraer imagen principal - priorizar landingImage
        self::log_debug("Iniciando extracción de imagen principal");
        $image_found = false;
        $image_patterns = [
            // Prioridad máxima: landingImage con diferentes atributos
            '/<img[^>]*id="landingImage"[^>]*src="([^"]+)"/i',
            '/<img[^>]*id="landingImage"[^>]*data-src="([^"]+)"/i',
            '/<img[^>]*id="landingImage"[^>]*data-old-hires="([^"]+)"/i',
            '/<img[^>]*id="landingImage"[^>]*data-a-dynamic-image="([^"]+)"/i',
            // Patrones específicos para landingImage con JSON
            '/id="landingImage"[^>]*data-a-dynamic-image="([^"]+)"/i',
            // Imágenes dinámicas como fallback con más variaciones
            '/<img[^>]*class="[^"]*a-dynamic-image[^"]*"[^>]*src="([^"]+)"/i',
            '/<img[^>]*class="[^"]*a-dynamic-image[^"]*"[^>]*data-src="([^"]+)"/i',
            '/<img[^>]*class="[^"]*a-dynamic-image[^"]*"[^>]*data-old-hires="([^"]+)"/i',
            '/<img[^>]*data-src="([^"]+)"[^>]*class="[^"]*a-dynamic-image[^"]*"/i',
            '/<img[^>]*src="([^"]+)"[^>]*class="[^"]*a-dynamic-image[^"]*"/i',
            '/<img[^>]*data-old-hires="([^"]+)"[^>]*class="[^"]*a-dynamic-image[^"]*"/i',
            // Patrones con data-a-dynamic-image
            '/<img[^>]*data-a-dynamic-image="([^"]+)"[^>]*src="([^"]+)"/i',
            '/<img[^>]*data-a-dynamic-image="([^"]+)"[^>]*>/i',
            '/<img[^>]*data-a-dynamic-image[^>]*src="([^"]+)"/i',
            // Otros patrones específicos
            '/<img[^>]*id="imgBlkFront"[^>]*src="([^"]+)"/i',
            '/<img[^>]*class="[^"]*product-image[^"]*"[^>]*src="([^"]+)"/i',
            // Patrones adicionales para imágenes principales
            '/<img[^>]*id="main-image"[^>]*src="([^"]+)"/i',
            '/<img[^>]*class="[^"]*main-image[^"]*"[^>]*src="([^"]+)"/i'
        ];
        
        // Verificar si hay elementos img con a-dynamic-image en el HTML
        $img_count = preg_match_all('/<img[^>]*class="[^"]*a-dynamic-image[^"]*"/i', $html);
        self::log_debug("Elementos img con a-dynamic-image encontrados: " . $img_count);
        
        // Verificar si hay data-a-dynamic-image
        $data_dynamic_count = preg_match_all('/data-a-dynamic-image/i', $html);
        self::log_debug("Atributos data-a-dynamic-image encontrados: " . $data_dynamic_count);
        
        foreach ($image_patterns as $i => $pattern) {
            self::log_debug("Probando patrón $i: " . substr($pattern, 0, 50) . "...");
            if (preg_match($pattern, $html, $matches)) {
                // Determinar qué grupo de captura usar
                $capture_group = 1;
                
                // Para patrones con data-a-dynamic-image que tienen múltiples grupos
                if (strpos($pattern, 'data-a-dynamic-image="([^"]+)"[^>]*src="([^"]+)"') !== false) {
                    $capture_group = 2; // Usar el segundo grupo (src)
                } elseif (count($matches) > 2 && strpos($pattern, 'data-a-dynamic-image') !== false) {
                    // Si hay más de 2 grupos y contiene data-a-dynamic-image
                    $capture_group = count($matches) - 1; // Usar el último grupo
                }
                
                $image_url = $matches[$capture_group];
                
                // Si el patrón es para data-a-dynamic-image, puede contener JSON
                if (strpos($pattern, 'data-a-dynamic-image') !== false && $capture_group === 1) {
                    // Intentar decodificar JSON si contiene datos estructurados
                    $json_data = json_decode($image_url, true);
                    if (is_array($json_data) && !empty($json_data)) {
                        // Buscar la primera URL válida en el JSON
                        foreach ($json_data as $key => $urls) {
                            if (is_array($urls) && !empty($urls)) {
                                $image_url = $urls[0]; // Tomar la primera URL
                                self::log_debug("Extraída URL de JSON data-a-dynamic-image: " . $image_url);
                                break;
                            }
                        }
                    } else {
                        // Si no es JSON válido, intentar con regex
                        if (preg_match('/"([^"]+)"\s*:\s*\[\s*"([^"]+)"/', $image_url, $json_matches)) {
                            $image_url = $json_matches[2]; // La URL de la imagen (segundo grupo)
                            self::log_debug("Extraída URL con regex de data-a-dynamic-image: " . $image_url);
                        }
                    }
                }
                
                // Limpiar la URL de la imagen
                $image_url = html_entity_decode($image_url, ENT_QUOTES, 'UTF-8');
                
                // Verificar que sea una URL válida y no un logo/banner
                if (strpos($image_url, 'http') === 0 && !strpos($image_url, 'data:')) {
                    // Filtrar imágenes que no son del producto
                    $excluded_patterns = [
                        'prime', 'logo', 'banner', 'badge', 'icon', 
                        'marketing', 'brand', 'promo', 'advertising',
                        'Prime_Logo', 'Prime_', '_Logo_'
                    ];
                    
                    $is_excluded = false;
                    foreach ($excluded_patterns as $exclude) {
                        if (stripos($image_url, $exclude) !== false) {
                            $is_excluded = true;
                            self::log_debug("Imagen excluida por contener '$exclude': " . $image_url);
                            break;
                        }
                    }
                    
                    if (!$is_excluded) {
                        // Remover parámetros de tamaño para obtener imagen de mejor calidad
                        $image_url = preg_replace('/\._[A-Z0-9]+_\./', '.', $image_url);
                        $product_data['image'] = $image_url;
                        $image_found = true;
                        self::log_debug("Imagen extraída con patrón $i: " . $image_url);
                        
                        // Marcar si se extrajo desde landingImage
                        if (strpos($pattern, 'landingImage') !== false) {
                            self::log_debug("Imagen extraída desde landingImage element");
                        }
                        break;
                    }
                }
            }
        }
        
        // Si no se encontró imagen en el HTML, usar imagen directa
        if (!$image_found) {
            $product_data['image'] = self::get_fallback_image($asin);
        }
        
        // Extraer descripción de puntos clave con patrones mejorados
        $description_text = '';
        $description_patterns = [
            // Patrón original
            '/<div[^>]*id="feature-bullets"[^>]*>(.*?)<\/div>/is',
            // Patrones alternativos
            '/<div[^>]*id="featurebullets_feature_div"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class="[^"]*feature[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*data-feature-name="featurebullets"[^>]*>(.*?)<\/div>/is',
            '/<ul[^>]*class="[^"]*a-unordered-list[^"]*a-nostyle[^"]*"[^>]*>(.*?)<\/ul>/is'
        ];
        
        foreach ($description_patterns as $desc_pattern) {
            if (preg_match($desc_pattern, $html, $matches)) {
                $bullets_html = $matches[1];
                
                // Intentar diferentes patrones para extraer los puntos
                $bullet_patterns = [
                    '/<span[^>]*class="[^"]*a-list-item[^"]*"[^>]*>(.*?)<\/span>/is',
                    '/<li[^>]*class="[^"]*a-spacing-mini[^"]*"[^>]*>(.*?)<\/li>/is',
                    '/<span[^>]*class="[^"]*a-size-base[^"]*"[^>]*>(.*?)<\/span>/is'
                ];
                
                foreach ($bullet_patterns as $bullet_pattern) {
                    if (preg_match_all($bullet_pattern, $bullets_html, $bullet_matches)) {
                        $bullets = array();
                        foreach ($bullet_matches[1] as $bullet) {
                            $clean_bullet = trim(html_entity_decode(strip_tags($bullet), ENT_QUOTES, 'UTF-8'));
                            if (!empty($clean_bullet) && strlen($clean_bullet) > 10 && strlen($clean_bullet) < 200) {
                                $bullets[] = $clean_bullet;
                            }
                        }
                        if (!empty($bullets)) {
                            $description_text = implode(' ', array_slice($bullets, 0, 3));
                            break 2; // Salir de ambos loops
                        }
                    }
                }
            }
        }
        
        // Si no se encontró descripción en bullets, intentar otros elementos
        if (empty($description_text)) {
            $alt_description_patterns = [
                '/<div[^>]*id="productDescription"[^>]*>(.*?)<\/div>/is',
                '/<div[^>]*class="[^"]*a-section[^"]*"[^>]*data-module-name="productDescription"[^>]*>(.*?)<\/div>/is',
                '/<div[^>]*class="[^"]*a-expander-content[^"]*"[^>]*>(.*?)<\/div>/is'
            ];
            
            foreach ($alt_description_patterns as $alt_pattern) {
                if (preg_match($alt_pattern, $html, $matches)) {
                    $desc_html = $matches[1];
                    $clean_desc = trim(html_entity_decode(strip_tags($desc_html), ENT_QUOTES, 'UTF-8'));
                    if (!empty($clean_desc) && strlen($clean_desc) > 20) {
                        $description_text = $clean_desc;
                        break;
                    }
                }
            }
        }
        
        // Limitar descripción según configuración
        $description_length = get_option('cosas_amazon_description_length', 150);
        if (!empty($description_text)) {
            if (strlen($description_text) > $description_length) {
                $description_text = substr($description_text, 0, $description_length) . '...';
            }
            $product_data['description'] = $description_text;
        } else {
            // Si no se pudo extraer descripción del HTML, usar fallback
            $product_data['description'] = self::get_fallback_description($asin);
        }
        
        // Buscar ofertas especiales
        $special_offer_patterns = [
            '/<span[^>]*class="[^"]*a-badge[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<span[^>]*class="[^"]*a-color-success[^"]*"[^>]*>([^<]+)<\/span>/i'
        ];
        
        foreach ($special_offer_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $offer_text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
                if (!empty($offer_text) && strlen($offer_text) < 50) {
                    $product_data['specialOffer'] = $offer_text;
                    break;
                }
            }
        }
        
        // Extraer valoraciones (estrellas)
        $rating_patterns = [
            '/<span[^>]*class="[^"]*a-icon-alt[^"]*"[^>]*>([^<]*?([0-9,\.]+)[^<]*?estrellas?[^<]*?)<\/span>/i',
            '/<span[^>]*class="[^"]*a-icon-alt[^"]*"[^>]*>([^<]*?([0-9,\.]+)[^<]*?star[^<]*?)<\/span>/i',
            '/<span[^>]*class="[^"]*a-icon-alt[^"]*"[^>]*>([^<]*?([0-9,\.]+)[^<]*?de[^<]*?5[^<]*?)<\/span>/i'
        ];
        
        foreach ($rating_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $rating_text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
                if (!empty($matches[2])) {
                    $rating_value = str_replace(',', '.', $matches[2]);
                    if (is_numeric($rating_value) && $rating_value >= 0 && $rating_value <= 5) {
                        $product_data['rating'] = $rating_value;
                        break;
                    }
                }
            }
        }
        
        // Extraer número de reseñas
        $review_count_patterns = [
            // Patrones específicos para el ID acrCustomerReviewText
            '/<span[^>]*id="acrCustomerReviewText"[^>]*>([0-9.,]+)[^<]*?<\/span>/i',
            '/<span[^>]*id="acrCustomerReviewText"[^>]*>([^<]*?([0-9.,]+)[^<]*?)<\/span>/i',
            '/<span[^>]*id="acrCustomerReviewText"[^>]*>([0-9.,]+)[^<]*?valoraciones?[^<]*?<\/span>/i',
            '/<span[^>]*id="acrCustomerReviewText"[^>]*>([0-9.,]+)[^<]*?reseñas?[^<]*?<\/span>/i',
            '/<span[^>]*id="acrCustomerReviewText"[^>]*>([0-9.,]+)[^<]*?reviews?[^<]*?<\/span>/i',
            // Patrones existentes
            '/<span[^>]*class="[^"]*a-size-base[^"]*"[^>]*>([0-9.,]+)[^<]*?valoraciones?[^<]*?<\/span>/i',
            '/<span[^>]*class="[^"]*a-size-base[^"]*"[^>]*>([0-9.,]+)[^<]*?reseñas?[^<]*?<\/span>/i',
            '/<span[^>]*class="[^"]*a-size-base[^"]*"[^>]*>([0-9.,]+)[^<]*?reviews?[^<]*?<\/span>/i',
            '/<a[^>]*href="[^"]*#customerReviews[^"]*"[^>]*>([0-9.,]+)[^<]*?valoraciones?[^<]*?<\/a>/i'
        ];
        
        foreach ($review_count_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                // Para el primer patrón acrCustomerReviewText, el número está en matches[1]
                // Para el segundo patrón acrCustomerReviewText, el número está en matches[2]
                $review_count = '';
                if (isset($matches[2]) && !empty($matches[2])) {
                    // Patrón con grupo adicional
                    $review_count = preg_replace('/[^0-9]/', '', $matches[2]);
                } else {
                    // Patrón directo
                    $review_count = preg_replace('/[^0-9]/', '', $matches[1]);
                }
                
                if (!empty($review_count) && is_numeric($review_count)) {
                    $product_data['reviewCount'] = $review_count;
                    break;
                }
            }
        }
        
        // Verificar que tenemos datos mínimos - si no hay título, devolver false
        if (empty($product_data['title'])) {
            self::log_debug("No se pudo extraer título del producto");
            return false;
        }
        
        // Si no hay precio, usar un precio por defecto
        if (empty($product_data['price'])) {
            // Intento adicional con patrones más amplios antes de usar fallback
            $fallback_price_patterns = [
                // PRIORIDAD MÁXIMA: Patrón específico identificado por el usuario
                '/<div[^>]*class="[^"]*a-section[^"]*a-spacing-micro[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-price[^"]*aok-align-center[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
                '/<div[^>]*class="[^"]*a-section[^"]*a-spacing-micro[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
                '/<span[^>]*class="[^"]*a-price[^"]*aok-align-center[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
                // Patrones específicos para precios de alto valor (formato europeo con separador de miles)
                // Formato: 2.499,00 € o 2.499 € (con o sin céntimos)
                '/([0-9]{1,3}\.[0-9]{3}(?:,[0-9]{2})?)\s*€/i',
                '/€\s*([0-9]{1,3}\.[0-9]{3}(?:,[0-9]{2})?)/i',
                // Patrones específicos para Amazon España
                '/([0-9]+,[0-9]{2})\s*€/i',
                '/€\s*([0-9]+,[0-9]{2})/i',
                '/([0-9]+\.[0-9]{3},[0-9]{2})\s*€/i',
                '/€\s*([0-9]+\.[0-9]{3},[0-9]{2})/i',
                // Buscar cualquier precio que se vea como precio
                '/[€$£¥₹₽]\s*([0-9]+(?:[.,][0-9]+)?)/i',
                '/([0-9]+(?:[.,][0-9]+)?)\s*[€$£¥₹₽]/i',
                // Buscar precios en formato específico
                '/precio[^0-9]*([0-9]+(?:[.,][0-9]+)?)\s*€/i',
                '/price[^0-9]*([0-9]+(?:[.,][0-9]+)?)\s*€/i',
                // Patrones más genéricos
                '/precio[^0-9]*([0-9]+(?:[.,][0-9]+)?)/i',
                '/price[^0-9]*([0-9]+(?:[.,][0-9]+)?)/i',
                // Buscar cualquier número que parezca un precio en el contexto
                '/buybox[^0-9]*([0-9]+,[0-9]{2})/i',
                '/cost[^0-9]*([0-9]+,[0-9]{2})/i',
                // Patrones adicionales para a-offscreen en diferentes contextos
                '/<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([€$£¥₹₽][^<]+)<\/span>/i',
                '/<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([0-9]+[.,][0-9]+[€$£¥₹₽]?[^<]*)<\/span>/i',
                // Patrones específicos para precios dentro de a-price
                '/<span[^>]*class="[^"]*a-price[^"]*"[^>]*>.*?([0-9]+,[0-9]{2}\s*€)/is',
                '/<span[^>]*class="[^"]*a-price[^"]*"[^>]*>.*?(€\s*[0-9]+,[0-9]{2})/is'
            ];
            
            foreach ($fallback_price_patterns as $i => $pattern) {
                self::log_debug("Probando patrón fallback $i: " . substr($pattern, 0, 80) . "...");
                if (preg_match($pattern, $html, $matches)) {
                    $price_text = trim($matches[1]);
                    
                    // Logging especial para patrones prioritarios de fallback
                    if ($i <= 2) {
                        self::log_debug("PATRÓN FALLBACK PRIORITARIO $i EXITOSO - Precio: " . $price_text);
                    }
                    
                    if (!empty($price_text) && preg_match('/[0-9]/', $price_text)) {
                        // Añadir símbolo de euro si no está presente
                        if (!preg_match('/[€$£¥₹₽]/', $price_text)) {
                            $price_text = $price_text . '€';
                        }
                        $product_data['price'] = $price_text;
                        self::log_debug("✅ PRECIO FALLBACK ASIGNADO: " . $price_text);
                        self::log_debug("Precio extraído con patrón fallback $i: " . $price_text);
                        break;
                    }
                }
            }
        }
        
        // Si aún no hay precio, usar texto por defecto
        if (empty($product_data['price'])) {
            $product_data['price'] = 'Ver precio en Amazon';
            self::log_debug("No se encontró precio, usando precio por defecto");
        }
        
        // Asegurar que siempre tengamos una imagen
        if (empty($product_data['image'])) {
            $product_data['image'] = self::get_fallback_image($asin);
        }
        
        // Logging detallado de datos extraídos
        self::log_debug("=== RESUMEN DE DATOS PARSEADOS ===");
        self::log_debug("Título: " . ($product_data['title'] ?: '(vacío)'));
        self::log_debug("Precio: " . ($product_data['price'] ?: '(vacío)'));
        self::log_debug("Precio Original: " . ($product_data['originalPrice'] ?: '(vacío)'));
        self::log_debug("Descuento: " . ($product_data['discount'] ?: '(vacío)'));
        self::log_debug("Imagen: " . (strlen($product_data['image']) > 50 ? substr($product_data['image'], 0, 50) . '...' : $product_data['image']));
        self::log_debug("Descripción: " . (strlen($product_data['description']) > 50 ? substr($product_data['description'], 0, 50) . '...' : ($product_data['description'] ?: '(vacía)')));
        self::log_debug("Rating: " . ($product_data['rating'] ?: '(vacío)'));
        self::log_debug("Review Count: " . ($product_data['reviewCount'] ?: '(vacío)'));
        self::log_debug("=== FIN PARSEO HTML ===");
        
        return $product_data;
    }
    
    /**
     * Sistema de logging mejorado para diagnóstico
     */
    public static function log_debug($message, $data = null) {
        if (defined('COSAS_AMAZON_DEBUG') && COSAS_AMAZON_DEBUG) {
            $log_message = '[COSAS_AMAZON_DEBUG] ' . $message;
            if ($data !== null) {
                $log_message .= ' - Data: ' . print_r($data, true);
            }
            error_log($log_message);
        }
    }
    
    /**
     * Verificar si el scraping está siendo bloqueado
     */
    public static function is_scraping_blocked($html, $http_code) {
        // Indicadores de bloqueo de Amazon
        $block_indicators = [
            'Robot Check',
            'robots.txt',
            'automatisierter Anfragen',
            'automated queries',
            'something went wrong',
            'Service Unavailable',
            'Access Denied',
            'Blocked',
            'CAPTCHA'
        ];
        
        foreach ($block_indicators as $indicator) {
            if (strpos($html, $indicator) !== false) {
                return true;
            }
        }
        
        // Códigos HTTP que indican bloqueo
        return in_array($http_code, [403, 429, 503]);
    }
    
    /**
     * Obtener datos con múltiples intentos y diferentes estrategias
     */
    public static function get_product_data_with_retry($url) {
        $asin = self::extract_asin_from_url($url);
        if (!$asin) {
            self::log_debug('ASIN no válido para URL', $url);
            return false;
        }

        $final_url = self::clean_amazon_url($url);
        self::log_debug('Iniciando scraping para ASIN: ' . $asin, $final_url);
        
        // Verificar configuración de fuente de datos
        $options = get_option('cosas_amazon_options', array());
        $data_source = isset($options['data_source']) ? $options['data_source'] : 'real';
        
        if ($data_source === 'simulated') {
            self::log_debug('Usando datos simulados por configuración');
            return self::get_simulated_data($asin, $url);
        }
        
        // Intentar múltiples estrategias
        $strategies = [
            'standard' => ['delay' => 1, 'user_agent' => 0],
            'delayed' => ['delay' => 3, 'user_agent' => 1],
            'alternative' => ['delay' => 2, 'user_agent' => 2]
        ];
        
        foreach ($strategies as $strategy_name => $config) {
            self::log_debug("Intentando estrategia: $strategy_name");
            
            $result = self::scrape_amazon_product_advanced($final_url, $asin, $config);
            
            if ($result && !empty($result['title']) && strpos($result['title'], 'Producto de Amazon -') !== 0) {
                self::log_debug("Éxito con estrategia: $strategy_name", $result['title']);
                return $result;
            }
            
            // Esperar entre intentos
            sleep($config['delay']);
        }
        
        self::log_debug('Todos los intentos de scraping fallaron, usando fallback');
        return self::get_intelligent_fallback($asin, $url);
    }
    
    /**
     * Scraping avanzado con diferentes configuraciones
     */
    public static function scrape_amazon_product_advanced($url, $asin, $config) {
        $user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/120.0'
        ];
        
        $user_agent_index = isset($config['user_agent']) ? $config['user_agent'] : 0;
        $delay = isset($config['delay']) ? intval($config['delay']) : 0;
        
        $headers = array(
            'User-Agent: ' . $user_agents[$user_agent_index],
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'DNT: 1',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        );
        
        if ($delay > 0) {
            sleep($delay);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');
        
        $html = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $http_code !== 200) {
            self::log_debug("Scraping falló - HTTP: $http_code, Error: $error");
            return false;
        }
        
        if (self::is_scraping_blocked($html, $http_code)) {
            self::log_debug("Scraping bloqueado por Amazon");
            return false;
        }
        
        $parsed_data = self::parse_amazon_html($html, $asin, $url);
        
        // Verificar calidad de los datos
        if (empty($parsed_data['title']) || strpos($parsed_data['title'], 'Amazon') === false) {
            self::log_debug("Datos de baja calidad obtenidos");
            return false;
        }
        
        return $parsed_data;
    }
    
    /**
     * Fallback inteligente con datos más realistas
     */
    public static function get_intelligent_fallback($asin, $url) {
        // Intentar obtener al menos la imagen del producto
        $image = self::get_fallback_image($asin);
        
        // Devolver datos neutrales sin precio/ratings simulados
        return array(
            'title' => 'Producto de Amazon – ' . $asin,
            'price' => '',
            'originalPrice' => '',
            'discount' => '',
            'image' => $image,
            'description' => '',
            'asin' => $asin,
            'url' => $url,
            'specialOffer' => '',
            'rating' => '',
            'reviewCount' => ''
        );
    }
    
    /**
     * Datos simulados mejorados
     */
    public static function get_simulated_data($asin, $url) {
        return array(
            'title' => 'Producto de Amazon (Simulado) - ' . $asin,
            'price' => '',
            'originalPrice' => '',
            'discount' => '',
            'image' => 'https://via.placeholder.com/300x300.png?text=Producto+Amazon',
            'description' => 'Datos simulados para pruebas. Sustituye por una URL real para ver precio.',
            'asin' => $asin,
            'url' => $url,
            'specialOffer' => '',
            'rating' => '',
            'reviewCount' => ''
        );
    }
    
    /**
     * Extraer valor numérico de un precio
     */
    public static function extract_numeric_price($price_string) {
        if (empty($price_string)) {
            return 0;
        }
        
        // Limpiar el string manteniendo solo números, comas, puntos y símbolos de moneda
        $clean_price = preg_replace('/[^0-9,.]/', '', $price_string);
        
        if (empty($clean_price)) {
            return 0;
        }
        
        // Manejar diferentes formatos de precio
        if (strpos($clean_price, ',') !== false && strpos($clean_price, '.') !== false) {
            // Formato con separador de miles y decimales: 1.234,56 o 1,234.56
            $comma_pos = strrpos($clean_price, ',');
            $dot_pos = strrpos($clean_price, '.');
            
            if ($comma_pos > $dot_pos) {
                // Formato europeo: 1.234,56 (común en Amazon España)
                $clean_price = str_replace('.', '', $clean_price);
                $clean_price = str_replace(',', '.', $clean_price);
            } else {
                // Formato americano: 1,234.56
                $clean_price = str_replace(',', '', $clean_price);
            }
        } elseif (strpos($clean_price, ',') !== false) {
            // Solo comas
            $parts = explode(',', $clean_price);
            if (count($parts) == 2 && strlen($parts[1]) <= 2) {
                // Formato decimal europeo: 12,34 (común en Amazon España)
                $clean_price = str_replace(',', '.', $clean_price);
            } else {
                // Separador de miles: 1,234
                $clean_price = str_replace(',', '', $clean_price);
            }
        } elseif (strpos($clean_price, '.') !== false) {
            // Solo puntos - distinguir entre decimal y separador de miles
            // Patrones de separador de miles europeo: 1.234 o 2.499 (grupos de 3 dígitos después del punto)
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $clean_price)) {
                // Formato europeo con separador de miles: 1.234 o 2.499.000
                $clean_price = str_replace('.', '', $clean_price);
            } elseif (preg_match('/\.\d{1,2}$/', $clean_price)) {
                // Formato decimal: 12.34
                // No hacer nada, ya está en formato correcto
            } else {
                // Otros casos con puntos - probablemente separador de miles
                $clean_price = str_replace('.', '', $clean_price);
            }
        }
        
        $numeric_value = floatval($clean_price);
        
        // Validar que sea un precio razonable
        if ($numeric_value > 0 && $numeric_value < 999999) {
            return $numeric_value;
        }
        
        return 0;
    }
    
    /**
     * Verificar si la validación estricta de descuentos está activada
     */
    public static function is_strict_discount_validation_enabled() {
        $options = get_option('cosas_amazon_options', array());
        return isset($options['strict_discount_validation']) ? (bool)$options['strict_discount_validation'] : true; // Por defecto activado
    }
    
    /**
     * Validar si un descuento detectado está en un contexto válido de Amazon
     */
    public static function validate_discount_context($html, $discount_percentage) {
        if (empty($discount_percentage) || $discount_percentage <= 0) {
            return false;
        }
        
        // Buscar indicadores de que el descuento está en un contexto real de descuento
        $valid_context_patterns = [
            // Contextos donde el descuento es probablemente real
            '/class="[^"]*savings[^"]*"[^>]*>[^<]*' . $discount_percentage . '%/i',
            '/class="[^"]*discount[^"]*"[^>]*>[^<]*' . $discount_percentage . '%/i',
            '/class="[^"]*price[^"]*"[^>]*>[^<]*-\s*' . $discount_percentage . '%/i',
            '/id="[^"]*price[^"]*"[^>]*>[^<]*' . $discount_percentage . '%/i',
            '/class="[^"]*deal[^"]*"[^>]*>[^<]*' . $discount_percentage . '%/i',
            '/descuento[^0-9]*' . $discount_percentage . '%/i',
            '/ahorro[^0-9]*' . $discount_percentage . '%/i',
            '/rebaja[^0-9]*' . $discount_percentage . '%/i'
        ];
        
        foreach ($valid_context_patterns as $pattern) {
            if (preg_match($pattern, $html)) {
                self::log_debug("✅ Descuento {$discount_percentage}% validado por contexto");
                return true;
            }
        }
        
        // Buscar contextos sospechosos donde el porcentaje NO es un descuento
        $invalid_context_patterns = [
            // Porcentajes que probablemente no son descuentos
            '/valoración[^0-9]*' . $discount_percentage . '%/i',
            '/rating[^0-9]*' . $discount_percentage . '%/i',
            '/satisfaction[^0-9]*' . $discount_percentage . '%/i',
            '/satisfacción[^0-9]*' . $discount_percentage . '%/i',
            '/recomiendan[^0-9]*' . $discount_percentage . '%/i',
            '/recommend[^0-9]*' . $discount_percentage . '%/i',
            '/battery[^0-9]*' . $discount_percentage . '%/i',
            '/batería[^0-9]*' . $discount_percentage . '%/i',
            '/efficiency[^0-9]*' . $discount_percentage . '%/i',
            '/eficiencia[^0-9]*' . $discount_percentage . '%/i'
        ];
        
        foreach ($invalid_context_patterns as $pattern) {
            if (preg_match($pattern, $html)) {
                self::log_debug("❌ Descuento {$discount_percentage}% invalidado por contexto sospechoso");
                return false;
            }
        }
        
        // Si no encontramos contexto válido específico, es sospechoso
        self::log_debug("⚠️  Descuento {$discount_percentage}% sin contexto válido encontrado");
        return false;
    }
    
    /**
     * Validar que los precios actual y original sean diferentes y coherentes
     */
    public static function validate_price_difference($current_price_str, $original_price_str, $expected_discount = null) {
        if (empty($current_price_str) || empty($original_price_str)) {
            return false;
        }
        
        $current_price = self::extract_numeric_price($current_price_str);
        $original_price = self::extract_numeric_price($original_price_str);
        
        // Ambos precios deben ser válidos y positivos
        if ($current_price <= 0 || $original_price <= 0) {
            self::log_debug("❌ Precios inválidos: actual={$current_price}, original={$original_price}");
            return false;
        }
        
        // El precio original debe ser mayor que el actual para que haya descuento
        if ($original_price <= $current_price) {
            self::log_debug("❌ Precio original no es mayor que el actual: {$original_price} <= {$current_price}");
            return false;
        }
        
        // Si se proporciona un descuento esperado, verificar que sea coherente
        if ($expected_discount !== null && $expected_discount > 0) {
            $real_discount = round((($original_price - $current_price) / $original_price) * 100);
            $difference = abs($real_discount - $expected_discount);
            
            // Permitir diferencia máxima de 5%
            if ($difference > 5) {
                self::log_debug("❌ Descuento incoherente: esperado {$expected_discount}%, real {$real_discount}%");
                return false;
            }
            
            self::log_debug("✅ Descuento coherente: esperado {$expected_discount}%, real {$real_discount}%");
        }
        
        // Validar que la diferencia de precio sea significativa (mínimo 1%)
        $min_discount = ($original_price - $current_price) / $original_price * 100;
        if ($min_discount < 1) {
            self::log_debug("❌ Diferencia de precio insignificante: {$min_discount}%");
            return false;
        }
        
        self::log_debug("✅ Precios validados: actual={$current_price}, original={$original_price}");
        return true;
    }
    
    /**
     * Validar datos de descuento en caché para múltiples productos
     */
    public static function validate_cached_discount_data($cached_data) {
        if (empty($cached_data['discount']) || $cached_data['discount'] <= 0) {
            return true; // No hay descuento, está bien
        }
        
        // Si hay descuento, debe haber precio original y coherencia
        if (empty($cached_data['originalPrice'])) {
            self::log_debug('❌ Descuento en caché sin precio original');
            return false;
        }
        
        // Validar coherencia de precios si están disponibles
        if (!empty($cached_data['price']) && !empty($cached_data['originalPrice'])) {
            return self::validate_price_difference(
                $cached_data['price'], 
                $cached_data['originalPrice'], 
                $cached_data['discount']
            );
        }
        
        return true; // Asumir válido si no se puede validar completamente
    }
    
    /**
     * Extraer datos de JSON embebido en las páginas de Amazon
     * Esta función busca precios y datos del producto en múltiples fuentes JSON
     */
    public static function extract_json_data($html) {
        $json_data = array();
        
        // MÉTODO 1: Buscar precio en "displayPrice" del buybox (formato texto: "2.499,00 €")
        if (preg_match('/"displayPrice"\s*:\s*"([^"]+€[^"]*|[^"]*€[^"]+)"/', $html, $display_match)) {
            $price_candidate = trim($display_match[1]);
            // Verificar que tenga un número válido
            if (preg_match('/[0-9]/', $price_candidate)) {
                $json_data['price'] = $price_candidate;
                self::log_debug('Precio extraído de displayPrice: ' . $json_data['price']);
            }
        }
        
        // MÉTODO 2: Buscar priceAmount (formato numérico: 2499.0 o 2499 o 24.99)
        if (empty($json_data['price'])) {
            // Buscar todos los priceAmount y tomar el primero válido que no sea 0
            if (preg_match_all('/"priceAmount"\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $html, $price_matches)) {
                foreach ($price_matches[1] as $price_str) {
                    $price_value = floatval($price_str);
                    if ($price_value > 0) {
                        // Formatear precio al estilo español
                        // Detectar si es un precio con céntimos (tiene parte decimal significativa)
                        $has_cents = (fmod($price_value, 1) != 0);
                        if ($has_cents) {
                            $json_data['price'] = number_format($price_value, 2, ',', '.') . ' €';
                        } else {
                            // Para precios enteros, mostrar sin céntimos para precios >= 100
                            if ($price_value >= 100) {
                                $json_data['price'] = number_format($price_value, 0, ',', '.') . ' €';
                            } else {
                                $json_data['price'] = number_format($price_value, 2, ',', '.') . ' €';
                            }
                        }
                        self::log_debug('Precio extraído de priceAmount: ' . $json_data['price']);
                        break;
                    }
                }
            }
        }
        
        // MÉTODO 3: Buscar en datos de variantes/twister
        if (empty($json_data['price'])) {
            if (preg_match('/"priceWithoutCurrencySymbol"\s*:\s*"([0-9.,]+)"/', $html, $price_match)) {
                $price_str = str_replace(',', '.', $price_match[1]);
                $price_value = floatval($price_str);
                if ($price_value > 0) {
                    $has_cents = (fmod($price_value, 1) != 0);
                    if ($has_cents) {
                        $json_data['price'] = number_format($price_value, 2, ',', '.') . ' €';
                    } else {
                        $json_data['price'] = number_format($price_value, 0, ',', '.') . ' €';
                    }
                    self::log_debug('Precio extraído de priceWithoutCurrencySymbol: ' . $json_data['price']);
                }
            }
        }
        
        // MÉTODO 4: Buscar "price" directo con valor numérico en contexto de ofertas
        if (empty($json_data['price'])) {
            // Patrón para encontrar precio en formato JSON dentro de scripts
            if (preg_match('/"price"\s*:\s*"?([0-9]+(?:[.,][0-9]+)?)"?\s*,\s*"priceCurrency"\s*:\s*"EUR"/', $html, $price_eur)) {
                $price_str = str_replace(',', '.', $price_eur[1]);
                $price_value = floatval($price_str);
                if ($price_value > 0) {
                    $json_data['price'] = number_format($price_value, 2, ',', '.') . ' €';
                    self::log_debug('Precio extraído de price+priceCurrency: ' . $json_data['price']);
                }
            }
        }
        
        // Buscar datos JSON-LD estructurados
        $json_patterns = [
            // Patrón para datos estructurados JSON-LD (Schema.org)
            '/<script type="application\/ld\+json"[^>]*>([^<]+)<\/script>/i',
        ];
        
        foreach ($json_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $json_string = $matches[1];
                $decoded = json_decode($json_string, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Extraer datos relevantes del JSON
                    if (isset($decoded['name'])) {
                        $json_data['title'] = $decoded['name'];
                    }
                    // Solo sobrescribir precio si no lo tenemos de twister
                    if (empty($json_data['price']) && isset($decoded['offers']['price'])) {
                        $json_data['price'] = $decoded['offers']['price'];
                    }
                    if (isset($decoded['offers']['priceCurrency'])) {
                        $json_data['currency'] = $decoded['offers']['priceCurrency'];
                    }
                    if (isset($decoded['aggregateRating']['ratingValue'])) {
                        $json_data['rating'] = $decoded['aggregateRating']['ratingValue'];
                    }
                    if (isset($decoded['aggregateRating']['reviewCount'])) {
                        $json_data['reviewCount'] = $decoded['aggregateRating']['reviewCount'];
                    }
                    if (isset($decoded['image'])) {
                        $json_data['image'] = is_array($decoded['image']) ? $decoded['image'][0] : $decoded['image'];
                    }
                    
                    break;
                }
            }
        }
        
        return $json_data;
    }
    
    /**
     * Obtener imagen de fallback
     */
    public static function get_fallback_image($asin = '') {
        // Intentar imágenes directas de Amazon si tenemos ASIN
        if (!empty($asin)) {
            $direct_image_urls = [
                "https://images-na.ssl-images-amazon.com/images/P/{$asin}.01.L.jpg",
                "https://images-na.ssl-images-amazon.com/images/P/{$asin}.01._AC_SL1500_.jpg",
                "https://m.media-amazon.com/images/P/{$asin}.01._AC_SL1500_.jpg",
                "https://images-na.ssl-images-amazon.com/images/P/{$asin}.01.MAIN.jpg",
                "https://images-na.ssl-images-amazon.com/images/P/{$asin}.jpg"
            ];
            
            foreach ($direct_image_urls as $image_url) {
                // Verificar si la imagen existe usando cURL para ser más rápido
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $image_url,
                    CURLOPT_NOBODY => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]);
                
                curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code === 200) {
                    return $image_url;
                }
            }
        }
        
        // Si no se encontró imagen válida, usar imagen directa sin verificar
        if (!empty($asin)) {
            return "https://m.media-amazon.com/images/P/{$asin}.01._AC_SL1500_.jpg";
        }
        
        // Fallback a placeholder local
        return COSAS_AMAZON_PLUGIN_URL . 'assets/images/fallback-default.svg';
    }
    
    /**
     * Procesar URL de imagen
     */
    public static function process_image_url($image_url, $asin = '') {
        if (empty($image_url)) {
            return self::get_fallback_image($asin);
        }
        return $image_url;
    }
    
    /**
     * Resolver URL corta de Amazon (como amzn.to) siguiendo redirects
     */
    public static function resolve_short_url($url) {
        if (empty($url)) {
            return false;
        }
        
        // Verificar si es una URL corta de Amazon
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return false;
        }
        
        $host = strtolower($parsed['host']);
        $short_domains = ['amzn.to', 'a.co'];
        
        if (!in_array($host, $short_domains)) {
            return $url; // No es URL corta, devolver la original
        }
        
        // Cachear resoluciones para evitar redirecciones repetidas
        $cache_key = 'cosas_amazon_resolve_' . md5($url);
        $cached = get_transient($cache_key);
        if (!empty($cached)) {
            return $cached;
        }
        
        self::log_debug("Intentando resolver URL corta: $url");

        // Método 1: cURL con GET real y follow redirects (más compatible que HEAD)
        // Timeout reducido para mejor rendimiento
        if (function_exists('curl_init')) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 8);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            // Realizar GET pero limitar el cuerpo al primer byte para no descargar toda la página
            curl_setopt($curl, CURLOPT_HTTPGET, true);
            curl_setopt($curl, CURLOPT_RANGE, '0-0');
            // Evitar compresión compleja
            curl_setopt($curl, CURLOPT_ENCODING, '');
            // Headers típicos de navegador
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Connection: keep-alive',
            ]);

            $response = @curl_exec($curl);
            $final_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($curl);
            curl_close($curl);

            self::log_debug("URL resuelta con cURL(GET): $final_url (HTTP: $http_code)", $curl_err ?: null);

            if (!empty($final_url) && $http_code >= 200 && $http_code < 400 && $final_url !== $url) {
                set_transient($cache_key, $final_url, DAY_IN_SECONDS * 7);
                return $final_url;
            }

            // Si GET no funcionó, intentar HEAD como fallback (timeout más corto)
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            curl_setopt($curl, CURLOPT_NOBODY, true);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

            @curl_exec($curl);
            $final_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($curl);
            curl_close($curl);

            self::log_debug("URL resuelta con cURL(HEAD): $final_url (HTTP: $http_code)", $curl_err ?: null);

            if (!empty($final_url) && $http_code >= 200 && $http_code < 400 && $final_url !== $url) {
                set_transient($cache_key, $final_url, DAY_IN_SECONDS * 7);
                return $final_url;
            }
        }
        
        // Método 2: Usar la API HTTP de WordPress (HEAD manual con seguimiento) si está disponible
        if (function_exists('wp_remote_head') && function_exists('wp_remote_retrieve_response_code')) {
            $current = $url;
            $maxHops = 8;
            for ($i = 0; $i < $maxHops; $i++) {
                $response = @wp_remote_head($current, array(
                    'timeout' => 15,
                    'redirection' => 0,
                    'sslverify' => false,
                    'headers' => array(
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ),
                ));
                if (is_wp_error($response)) {
                    break;
                }
                $code = intval(wp_remote_retrieve_response_code($response));
                $loc = wp_remote_retrieve_header($response, 'location');
                if (in_array($code, array(301, 302, 303, 307, 308)) && !empty($loc)) {
                    // Resolver relativa si aplica
                    if (strpos($loc, 'http') !== 0) {
                        $p = parse_url($current);
                        $scheme = isset($p['scheme']) ? $p['scheme'] : 'https';
                        $host = isset($p['host']) ? $p['host'] : '';
                        $loc = rtrim($scheme . '://' . $host, '/') . '/' . ltrim($loc, '/');
                    }
                    $current = $loc;
                    continue;
                }
                if ($code >= 200 && $code < 400) {
                    // Si ya no es dominio corto, damos por resuelta
                    $h = parse_url($current, PHP_URL_HOST);
                    if ($h && !in_array(strtolower($h), $short_domains)) {
                        self::log_debug("URL resuelta con WP HTTP API (HEAD): $current (HTTP: $code)");
                        set_transient($cache_key, $current, DAY_IN_SECONDS * 7);
                        return $current;
                    }
                }
                break; // No más redirecciones
            }

            // Intentar GET sin seguir redirecciones para obtener Location en algunos hosts
            if (function_exists('wp_remote_get')) {
                $response = @wp_remote_get($url, array(
                    'timeout' => 15,
                    'redirection' => 0,
                    'sslverify' => false,
                    'headers' => array(
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ),
                ));
                if (!is_wp_error($response)) {
                    $code = intval(wp_remote_retrieve_response_code($response));
                    $loc = wp_remote_retrieve_header($response, 'location');
                    if (in_array($code, array(301,302,303,307,308)) && !empty($loc)) {
                        if (strpos($loc, 'http') !== 0) {
                            $p = parse_url($url);
                            $scheme = isset($p['scheme']) ? $p['scheme'] : 'https';
                            $host = isset($p['host']) ? $p['host'] : '';
                            $loc = rtrim($scheme . '://' . $host, '/') . '/' . ltrim($loc, '/');
                        }
                        self::log_debug("URL resuelta con WP HTTP API (GET): $loc (HTTP: $code)");
                        set_transient($cache_key, $loc, DAY_IN_SECONDS * 7);
                        return $loc;
                    }
                }
            }
        }

        // Método 3: Usar get_headers como fallback final
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'follow_location' => 1,
                'max_redirects' => 5,
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; CosasAmazon/1.0)'
            ]
        ]);
        
        $headers = @get_headers($url, 1, $context);
        if ($headers && isset($headers[0])) {
            // Buscar la URL final en los headers
            $final_url = $url;
            
            // Buscar redirects en los headers
            if (isset($headers['Location'])) {
                $location = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
                if (!empty($location)) {
                    $final_url = $location;
                    self::log_debug("URL resuelta con get_headers: $final_url");
                    set_transient($cache_key, $final_url, DAY_IN_SECONDS * 7);
                    return $final_url;
                }
            }
        }
        
        // Método 4: Construcción manual para URLs conocidas
        if (strpos($url, 'amzn.to') !== false) {
            // Intentar extraer información de la URL corta
            $path_parts = explode('/', parse_url($url, PHP_URL_PATH));
            if (count($path_parts) >= 2) {
                $short_code = end($path_parts);
                // Esto es un intento, pero generalmente necesita resolución real
                self::log_debug("Código corto detectado: $short_code");
            }
        }
        
        self::log_debug("No se pudo resolver URL corta: $url");
        set_transient($cache_key, $url, DAY_IN_SECONDS);
        return $url;
    }
    
    /**
     * Limpiar URL de Amazon eliminando parámetros innecesarios
     */
    public static function clean_amazon_url($url) {
        if (empty($url)) {
            return $url;
        }
        
        // Parsear la URL
        $parsed_url = parse_url($url);
        if (!$parsed_url) {
            return $url;
        }
        
        // Extraer ASIN para reconstruir URL limpia
        $asin = self::extract_asin_from_url($url);
        if (!$asin) {
            return $url;
        }
        
        // Determinar dominio de Amazon
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : 'amazon.es';
        
        // Asegurar que tenga el prefijo www
        if (!preg_match('/^www\./', $host)) {
            $host = 'www.' . $host;
        }
        
        // Reconstruir URL limpia
        $clean_url = 'https://' . $host . '/dp/' . $asin;
        
        return $clean_url;
    }

    /**
     * Generar HTML para estrellas de valoración
     */
    public static function generate_rating_stars($rating, $max_rating = 5) {
        if (empty($rating) || !is_numeric($rating)) {
            return '';
        }
        
        $rating = floatval($rating);
        $stars_html = '<div class="cosas-amazon-stars">';
        
        for ($i = 1; $i <= $max_rating; $i++) {
            if ($rating >= $i) {
                // Estrella completa
                $stars_html .= '<span class="cosas-amazon-star full">★</span>';
            } elseif ($rating >= $i - 0.5) {
                // Media estrella
                $stars_html .= '<span class="cosas-amazon-star half">☆</span>';
            } else {
                // Estrella vacía
                $stars_html .= '<span class="cosas-amazon-star empty">☆</span>';
            }
        }
        
        $stars_html .= '</div>';
        return $stars_html;
    }
    
    /**
     * Formatear número de reseñas
     */
    public static function format_review_count($count) {
        if (empty($count) || !is_numeric($count)) {
            return '';
        }
        
        $count = intval($count);
        
        if ($count >= 1000) {
            return number_format($count / 1000, 1) . 'K';
        }
        
        return number_format($count);
    }
    
    /**
     * Verificar si las valoraciones están habilitadas
     */
    public static function are_ratings_enabled() {
        return get_theme_mod('cosas_amazon_show_ratings', true);
    }
    
    /**
     * Obtener descripción de fallback basada en el ASIN
     */
    public static function get_fallback_description($asin = '') {
        // Descripciones específicas para ASINs conocidos
        $known_descriptions = array(
            'B08N5WRWNW' => 'Altavoz inteligente con Alexa. Controla tu hogar inteligente con la voz. Reproduce música, responde preguntas y mucho más.',
            'B0BDHB9Y8Z' => 'Echo Dot (5.ª generación). Nuestro altavoz inteligente con Alexa más popular. Sonido más potente, hub de hogar inteligente integrado.',
            'B0DN9JNXJQ' => 'iPhone 16. Cámara Fusion de 48 MP con teleobjetivo 2x. Chip A18 con Neural Engine de 16 núcleos.',
            'B08XYZABC1' => 'Auriculares inalámbricos con cancelación de ruido. Batería de larga duración, sonido de alta calidad.',
            'B07XYZDEF2' => 'Tableta con pantalla de alta resolución. Procesador rápido, ideal para entretenimiento y productividad.',
            'B09XYZGHI3' => 'Smartwatch con monitor de salud. Seguimiento de actividad, notificaciones inteligentes, resistente al agua.',
        );
        
        // Si tenemos una descripción específica para este ASIN, usarla
        if (!empty($asin) && isset($known_descriptions[$asin])) {
            return $known_descriptions[$asin];
        }
        
        // Generar descripción basada en patrones del ASIN
        if (!empty($asin)) {
            // Intentar inferir categoría por el ASIN
            $first_char = substr($asin, 0, 1);
            $category_descriptions = array(
                'B0' => 'Producto tecnológico avanzado con características premium. Diseño moderno y funcionalidad intuitiva.',
                'B1' => 'Dispositivo electrónico de calidad superior. Ofrece rendimiento excepcional y durabilidad.',
                'B2' => 'Artículo de hogar inteligente con conectividad avanzada. Fácil de usar y configurar.',
                'B3' => 'Accesorio premium con materiales de alta calidad. Diseño elegante y funcional.',
                'B4' => 'Producto de entretenimiento con tecnología de vanguardia. Experiencia inmersiva garantizada.',
                'B5' => 'Dispositivo de salud y bienestar con sensores avanzados. Monitoreo preciso y confiable.',
                'B6' => 'Herramienta profesional con prestaciones superiores. Ideal para uso intensivo y profesional.',
                'B7' => 'Producto de moda y estilo con materiales premium. Comodidad y elegancia en un solo producto.',
                'B8' => 'Dispositivo de comunicación con tecnología innovadora. Conectividad rápida y estable.',
                'B9' => 'Accesorio de viaje duradero y funcional. Diseñado para aventureros y profesionales.',
            );
            
            $prefix = substr($asin, 0, 2);
            if (isset($category_descriptions[$prefix])) {
                return $category_descriptions[$prefix];
            }
        }
        
        // Descripción genérica pero informativa
        return 'Producto de Amazon con excelente relación calidad-precio. Envío rápido y garantía del fabricante. Miles de reseñas positivas de clientes satisfechos.';
    }
}

/**
 * Funciones globales de utilidad
 */
if (!function_exists('cosas_amazon_log')) {
    function cosas_amazon_log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[COSAS_AMAZON] ' . $message);
        }
    }
}

if (!function_exists('cosas_amazon_get_product_data')) {
    function cosas_amazon_get_product_data($url, $force_refresh = false) {
        return CosasAmazonHelpers::get_product_data($url, $force_refresh);
    }
}

if (!function_exists('cosas_amazon_get_option')) {
    function cosas_amazon_get_option($option, $default = null) {
        $options = get_option('cosas_amazon_options', array());
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('cosas_amazon_generate_rating_stars')) {
    function cosas_amazon_generate_rating_stars($rating, $max_rating = 5) {
        return CosasAmazonHelpers::generate_rating_stars($rating, $max_rating);
    }
}

if (!function_exists('cosas_amazon_format_review_count')) {
    function cosas_amazon_format_review_count($count) {
        return CosasAmazonHelpers::format_review_count($count);
    }
}

if (!function_exists('cosas_amazon_are_ratings_enabled')) {
    function cosas_amazon_are_ratings_enabled() {
        return CosasAmazonHelpers::are_ratings_enabled();
    }
}
