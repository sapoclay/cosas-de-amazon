<?php
/**
 * Funciones helper del plugin Cosas de Amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonHelpers {
    
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
     * Normalizar precio para mostrar en formato español con símbolo de euro y sin caracteres invisibles.
     * Resultado: "1.234,56 €"
     */
    public static function normalize_price_display($price) {
        if (empty($price)) { return ''; }
        $s = html_entity_decode((string)$price, ENT_QUOTES, 'UTF-8');
        // Eliminar BOM/ZW y caracteres invisibles: FEFF, ZWSP/ZWNJ/ZWJ, soft hyphen; convertir NBSP/narrow NBSP a espacio normal; quitar � (FFFD)
        $s = preg_replace('/[\x{FEFF}\x{200B}-\x{200D}\x{00AD}\x{FFFD}]/u', '', $s);
        $s = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim($s);
        // Extraer valor numérico y formatear a es_ES (coma decimal, punto miles)
        $value = self::extract_numeric_price($s);
        if ($value > 0) {
            $formatted = number_format($value, 2, ',', '.');
            return $formatted . ' €';
        }
        // Si no pudimos parsear, al menos asegurar símbolo € y quitar artefactos
        if (strpos($s, '€') === false && preg_match('/[0-9]/', $s)) {
            $s = $s . ' €';
        }
        // Si viene como "€ 12,34" convertir a "12,34 €"
        $s = preg_replace('/^€\s*(.+)$/u', '$1 €', $s);
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
        $cache_duration = 3600; // 1 hora por defecto
        
        // Obtener duración del caché de la configuración
        $options = get_option('cosas_amazon_options', array());
        if (isset($options['cache_duration'])) {
            $cache_duration = intval($options['cache_duration']) * 60; // minutos a segundos
        }
        
        set_transient($cache_key, $product_data, $cache_duration);
        self::log_debug('Datos guardados en caché para ASIN: ' . $asin);
        
        return true;
    }
    
    /**
     * Obtener datos de fallback (datos simulados)
     */
    public static function get_fallback_data($asin) {
        self::log_debug('Usando datos de fallback para ASIN: ' . $asin);
        return self::get_simulated_data($asin, 'https://www.amazon.es/dp/' . $asin);
    }
    
    /**
     * Obtener datos del producto (versión simplificada)
     */
    public static function get_product_data($url, $force_refresh = false) {
        // Log del inicio
        self::log_debug('Iniciando get_product_data', $url);
        
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
            self::log_debug('No se pudo extraer ASIN', $url);
            return false;
        }
        
        self::log_debug('ASIN extraído: ' . $asin);
        
        // Verificar caché primero (si no es refresh forzado)
        if (!$force_refresh) {
            $cached_data = self::get_cached_product_data($asin);
            if ($cached_data) {
                self::log_debug('Datos obtenidos de caché');
                return $cached_data;
            }
        }
        
        // Intentar primero con Amazon PA-API si está configurada
        $product_data = self::get_product_data_from_api($asin);
        
        // Si la API falla o no está configurada, usar scraping como fallback
        if (!$product_data) {
            $options = get_option('cosas_amazon_api_options', array());
            $fallback_enabled = isset($options['fallback_to_scraping']) ? $options['fallback_to_scraping'] : 1;
            
            if ($fallback_enabled) {
                self::log_debug('API falló, intentando scraping como fallback');
                $product_data = self::get_product_data_scraping($final_url, $asin);
            }
        }
        
        // Si todo falla, usar datos de fallback reales (no simulados)
        if (!$product_data) {
            $general_options = get_option('cosas_amazon_options', array());
            $data_source = isset($general_options['data_source']) ? $general_options['data_source'] : 'real';
            
            if ($data_source === 'simulated') {
                self::log_debug('Usando datos simulados según configuración');
                $product_data = self::get_fallback_data($asin);
            } else {
                // Intentar obtener datos básicos reales como último recurso
                self::log_debug('Intentando obtener datos básicos reales como último recurso');
                $product_data = self::get_fallback_data($asin);
                // Marcar que son datos de fallback pero no simulados
                if ($product_data) {
                    $product_data['is_fallback'] = true;
                    $product_data['title'] = 'Producto de Amazon – ' . $asin;
                }
            }
        }
        
        // Guardar en caché si obtuvimos datos
        if ($product_data) {
            self::cache_product_data($asin, $product_data);
        }
        
        return $product_data;
    }
    
    /**
     * Obtener datos del producto usando Amazon PA-API
     */
    public static function get_product_data_from_api($asin) {
        // Cargar clase PA-API si no está cargada
        if (!class_exists('CosasAmazonPAAPI')) {
            require_once dirname(__FILE__) . '/class-amazon-paapi.php';
        }
        
        $api = new CosasAmazonPAAPI();
        
        if (!$api->isEnabled() || !$api->isConfigured()) {
            self::log_debug('Amazon PA-API no configurada o deshabilitada');
            return false;
        }
        
        self::log_debug('Intentando obtener datos con Amazon PA-API para ASIN: ' . $asin);
        
        try {
            $api_data = $api->getProductData($asin);
            
            if ($api_data && !empty($api_data['title'])) {
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
        // Obtener configuración de timeout
        $options = get_option('cosas_amazon_options', array());
        $timeout = isset($options['scraping_timeout']) ? intval($options['scraping_timeout']) : 15;
        
        // Configurar headers mejorados para simular un navegador real
        $user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        
        $headers = array(
            'User-Agent: ' . $user_agents[array_rand($user_agents)],
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'DNT: 1',
            'Sec-GPC: 1'
        );
        
        // Añadir delay aleatorio para evitar bloqueos
        sleep(rand(1, 2));
        
        // Usar cURL para obtener el contenido
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
        
        // Verificar y descomprimir si es necesario
        if ($html && strlen($html) > 0) {
            // Verificar si está comprimido
            if (substr($html, 0, 2) == "\x1f\x8b") {
                // Gzip comprimido
                $decompressed = @gzdecode($html);
                if ($decompressed !== false) {
                    $html = $decompressed;
                    self::log_debug("HTML descomprimido con gzdecode: " . strlen($html) . " bytes");
                } else {
                    // Intentar con gzinflate
                    $decompressed = @gzinflate(substr($html, 10, -8));
                    if ($decompressed !== false) {
                        $html = $decompressed;
                        self::log_debug("HTML descomprimido con gzinflate: " . strlen($html) . " bytes");
                    } else {
                        self::log_debug("No se pudo descomprimir HTML gzip");
                    }
                }
            } else if (substr($html, 0, 2) == "\x78\x9c") {
                // Deflate comprimido
                $decompressed = @gzinflate($html);
                if ($decompressed !== false) {
                    $html = $decompressed;
                    self::log_debug("HTML descomprimido con deflate: " . strlen($html) . " bytes");
                } else {
                    self::log_debug("No se pudo descomprimir HTML deflate");
                }
            } else {
                self::log_debug("HTML no comprimido: " . strlen($html) . " bytes");
            }
            
            // Si el HTML es muy pequeño, intentar con un User-Agent diferente
            if (strlen($html) < 100000) {
                self::log_debug("HTML pequeño detectado, reintentando con User-Agent diferente");
                
                // Usar un User-Agent más específico
                $mobile_headers = array(
                    'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: es-es',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: keep-alive'
                );
                
                sleep(2); // Esperar un poco más
                
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, $url);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch2, CURLOPT_MAXREDIRS, 5);
                curl_setopt($ch2, CURLOPT_TIMEOUT, $timeout + 5);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, $mobile_headers);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch2, CURLOPT_ENCODING, '');
                
                $html2 = curl_exec($ch2);
                $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                if ($html2 && strlen($html2) > strlen($html)) {
                    $html = $html2;
                    $http_code = $http_code2;
                    self::log_debug("Reintento exitoso, HTML mejorado: " . strlen($html) . " bytes");
                }
            }
        }
        
        // Verificar la respuesta - solo fallar si hay error crítico
        if ($html === false || !empty($error)) {
            self::log_debug("Error crítico en cURL: " . $error);
            return false; // Devolver false para que se use el siguiente método
        }
        
        // Verificar el código de respuesta HTTP - solo fallar si no es 200
        if ($http_code !== 200) {
            self::log_debug("HTTP Code no exitoso: " . $http_code);
            return false; // Devolver false para que se use el siguiente método
        }
        
        // Si no hay HTML o es muy corto, también fallar
        if (empty($html) || strlen($html) < 1000) {
            self::log_debug("HTML vacío o muy corto: " . strlen($html) . " bytes");
            return false; // Devolver false para que se use el siguiente método
        }
        
        // Parsear el HTML para extraer datos
        return self::parse_amazon_html($html, $asin, $url);
    }

    /**
     * Heurística: detectar contexto de precio por unidad (ml, L, kg, pack, xN) y ratios sospechosos
     */
    public static function is_suspicious_unit_price_context($title, $original_price_str, $current_price_str) {
        $title_lc = mb_strtolower($title ?? '');
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
        $orig_lc = mb_strtolower($original_price_str ?? '');
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
        // Convertir HTML a UTF-8 si es necesario
        $html = mb_convert_encoding($html, 'UTF-8', 'auto');
        
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
            'reviewCount' => ''
        );
        
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
        $price_patterns = [
            // PRIORIDAD MÁXIMA: Patrón específico identificado por el usuario
            // div.a-section.a-spacing-micro > span.a-price.aok-align-center > span.a-offscreen
            '/<div[^>]*class="[^"]*a-section[^"]*a-spacing-micro[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-price[^"]*aok-align-center[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            '/<div[^>]*class="[^"]*a-section[^"]*a-spacing-micro[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            // Variaciones del patrón específico
            '/<span[^>]*class="[^"]*a-price[^"]*aok-align-center[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
            '/<span[^>]*class="[^"]*aok-align-center[^"]*a-price[^"]*"[^>]*>.*?<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>([^<]+)<\/span>/is',
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
            if (preg_match($pattern, $html, $matches)) {
                // Para patrones con tres grupos (completo + decimales + símbolo)
                if (count($matches) > 3 && strpos($pattern, 'a-price-whole') !== false && strpos($pattern, 'a-price-fraction') !== false) {
                    $price_text = trim($matches[1]) . ',' . trim($matches[2]) . '€';
                    self::log_debug("Precio extraído con patrón completo $i: " . $price_text);
                }
                // Para patrones con dos grupos (precio completo + decimales)
                else if (count($matches) > 2 && strpos($pattern, 'a-price-whole') !== false) {
                    $price_text = trim($matches[1]) . ',' . trim($matches[2]);
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
                    $price_text = preg_replace('/[^\d€$£¥₹₽,.\s]/', '', $price_text);
                    $price_text = trim($price_text);
                    
                    if (!empty($price_text)) {
                        $product_data['price'] = $price_text;
                        self::log_debug("✅ PRECIO FINAL ASIGNADO: " . $price_text);
                        self::log_debug("Precio encontrado con patrón $i: " . $price_text);
                        break;
                    }
                }
            }
        }
        
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
        
        // Logging de éxito
        self::log_debug("Scraping exitoso - Título: " . $product_data['title'] . ", Precio: " . $product_data['price']);
        
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
        $image = self::get_direct_amazon_image($asin);
        
        return array(
            'title' => 'Producto de Amazon – ' . $asin,
            'price' => '29,99€',
            'originalPrice' => '',
            'discount' => '',
            'image' => $image,
            'description' => 'Producto tecnológico avanzado con características premium. Diseño moderno y funcionalidad intuitiva.',
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
            'price' => '29,99€',
            'originalPrice' => '39,99€',
            'discount' => '25',
            'image' => 'https://via.placeholder.com/300x300.png?text=Producto+Amazon',
            'description' => 'Producto de Amazon con excelente calidad y garantía (datos simulados para testing).',
            'asin' => $asin,
            'url' => $url,
            'specialOffer' => 'Oferta especial',
            'rating' => '4.5',
            'reviewCount' => '2847'
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
     */
    public static function extract_json_data($html) {
        $json_data = array();
        
        // Buscar datos JSON embebidos en scripts
        $json_patterns = [
            // Patrón para datos de producto embebidos
            '/window\.P\s*=\s*window\.P\s*\|\|\s*\{\};\s*P\.when\(\'A\'\)\.execute\(function\(A\)\s*\{\s*return\s*A\.declarative\([^}]+\}\s*,\s*"product-facts"\s*,\s*({[^}]+})\s*\)\s*;\s*\}\s*\);/s',
            // Patrón para datos de precios en JSON
            '/priceblock_dealprice[^{]+({[^}]+})/s',
            // Patrón para datos estructurados JSON-LD
            '/<script type="application\/ld\+json"[^>]*>([^<]+)<\/script>/i',
            // Patrón para datos de configuración del producto
            '/window\.ue_pdp\s*=\s*window\.ue_pdp\s*\|\|\s*\{\};\s*ue_pdp\.asin\s*=\s*"[^"]+"\s*;\s*ue_pdp\.productData\s*=\s*({[^}]+})\s*;/s'
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
                    if (isset($decoded['offers']['price'])) {
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
        
    self::log_debug("Intentando resolver URL corta: $url");

        // Método 1: cURL con GET real y follow redirects (más compatible que HEAD)
        if (function_exists('curl_init')) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 20);
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
                return $final_url;
            }

            // Si GET no funcionó, intentar HEAD como fallback
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 20);
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
        return false;
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
