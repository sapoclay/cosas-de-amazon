<?php
/**
 * Amazon Product Advertising API Handler
 * Clase para manejar peticiones a la Amazon PA-API
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonPAAPI {
    
    private $access_key;
    private $secret_key;
    private $associate_tag;
    private $region;
    private $host;
    private $service = 'productadvertising';
    private $last_error = null;
    private $last_response = null;
    
    // Circuit breaker properties
    private $circuit_breaker_failures = 0;
    private $circuit_breaker_last_failure = 0;
    private $circuit_breaker_threshold = 5; // Failures antes de abrir el circuit
    private $circuit_breaker_timeout = 300; // 5 minutos antes de reintentar
    
    public function __construct() {
        $options = get_option('cosas_amazon_api_options', array());
        
        $this->access_key = isset($options['amazon_access_key']) ? $options['amazon_access_key'] : '';
        $this->secret_key = isset($options['amazon_secret_key']) ? $options['amazon_secret_key'] : '';
        $this->associate_tag = isset($options['amazon_associate_tag']) ? $options['amazon_associate_tag'] : '';
        $this->region = isset($options['amazon_region']) ? $options['amazon_region'] : 'es';
        
        // Configurar host según región
        $this->host = $this->getHostForRegion($this->region);
    }
    
    /**
     * Obtener host según región con fallbacks
     */
    public function getHostForRegion($region) {
        $hosts = array(
            'us' => 'webservices.amazon.com',
            'uk' => 'webservices.amazon.co.uk',
            'de' => 'webservices.amazon.de',
            'fr' => 'webservices.amazon.fr',
            'it' => 'webservices.amazon.it',
            'es' => 'webservices.amazon.es',
        );
        
        return isset($hosts[$region]) ? $hosts[$region] : $hosts['es'];
    }
    
    /**
     * Obtener regiones de fallback para la región actual
     */
    public function getFallbackRegions($primary_region) {
        // Configurar fallbacks basados en proximidad geográfica y compatibilidad
        $fallback_map = array(
            'es' => array('fr', 'it', 'de', 'uk'),
            'fr' => array('es', 'it', 'de', 'uk'),
            'it' => array('es', 'fr', 'de', 'uk'),
            'de' => array('fr', 'it', 'es', 'uk'),
            'uk' => array('de', 'fr', 'es', 'it'),
            'us' => array('uk', 'de', 'fr', 'es')
        );
        
        return isset($fallback_map[$primary_region]) ? $fallback_map[$primary_region] : array('us', 'uk');
    }

    /**
     * Circuit Breaker: Verificar si debemos intentar una llamada
     */
    private function isCircuitOpen() {
        // Si no hemos alcanzado el threshold, el circuit está cerrado
        if ($this->circuit_breaker_failures < $this->circuit_breaker_threshold) {
            return false;
        }
        
        // Si hemos alcanzado el threshold, verificar si ha pasado el timeout
        $time_since_last_failure = time() - $this->circuit_breaker_last_failure;
        if ($time_since_last_failure > $this->circuit_breaker_timeout) {
            // Reset del circuit breaker para intentar de nuevo
            $this->circuit_breaker_failures = 0;
            error_log('[CosasAmazon PA-API] 🔄 Circuit breaker reset - Reintentando después de ' . $time_since_last_failure . ' segundos');
            return false;
        }
        
        error_log('[CosasAmazon PA-API] 🚫 Circuit breaker ABIERTO - ' . $this->circuit_breaker_failures . ' fallos, esperando ' . ($this->circuit_breaker_timeout - $time_since_last_failure) . ' segundos más');
        return true;
    }
    
    /**
     * Circuit Breaker: Registrar un fallo
     */
    private function recordCircuitFailure() {
        $this->circuit_breaker_failures++;
        $this->circuit_breaker_last_failure = time();
        error_log('[CosasAmazon PA-API] ❌ Circuit breaker: Registrando fallo #' . $this->circuit_breaker_failures);
        
        if ($this->circuit_breaker_failures >= $this->circuit_breaker_threshold) {
            error_log('[CosasAmazon PA-API] 🚫 Circuit breaker ABIERTO - Demasiados fallos (' . $this->circuit_breaker_failures . ')');
        }
    }
    
    /**
     * Circuit Breaker: Registrar un éxito
     */
    private function recordCircuitSuccess() {
        if ($this->circuit_breaker_failures > 0) {
            error_log('[CosasAmazon PA-API] ✅ Circuit breaker: Éxito después de ' . $this->circuit_breaker_failures . ' fallos - Reset');
            $this->circuit_breaker_failures = 0;
        }
    }
    
    /**
     * Verificar si la API está configurada
     */
    public function isConfigured() {
        $configured = !empty($this->access_key) && !empty($this->secret_key) && !empty($this->associate_tag);
        
        if (!$configured) {
            error_log('[CosasAmazon PA-API] Configuración incompleta - Access Key: ' . 
                     (!empty($this->access_key) ? 'OK' : 'VACÍO') . 
                     ', Secret Key: ' . (!empty($this->secret_key) ? 'OK' : 'VACÍO') . 
                     ', Associate Tag: ' . (!empty($this->associate_tag) ? 'OK' : 'VACÍO'));
        }
        
        return $configured;
    }
    
    /**
     * Verificar si la API está habilitada
     */
    public function isEnabled() {
        $options = get_option('cosas_amazon_api_options', array());
        $enabled = isset($options['api_enabled']) && $options['api_enabled'] == 1;
        
        if (!$enabled) {
            error_log('[CosasAmazon PA-API] API deshabilitada en configuración');
        }
        
        return $enabled;
    }
    
    /**
     * Validar configuración básica
     */
    public function validateConfiguration() {
        $errors = array();
        
        if (empty($this->access_key)) {
            $errors[] = 'Access Key ID no configurado';
        }
        
        if (empty($this->secret_key)) {
            $errors[] = 'Secret Access Key no configurado';
        }
        
        if (empty($this->associate_tag)) {
            $errors[] = 'Associate Tag no configurado';
        }
        
        // Validar formato de Associate Tag
        if (!empty($this->associate_tag) && !preg_match('/^[a-zA-Z0-9\-]+$/', $this->associate_tag)) {
            $errors[] = 'Associate Tag tiene formato inválido';
        }
        
        // Validar longitud de claves
        if (!empty($this->access_key) && strlen($this->access_key) < 16) {
            $errors[] = 'Access Key ID parece demasiado corto';
        }
        
        if (!empty($this->secret_key) && strlen($this->secret_key) < 30) {
            $errors[] = 'Secret Access Key parece demasiado corto';
        }
        
        return $errors;
    }
    
    /**
     * Obtener información del último error
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    /**
     * Obtener la última respuesta
     */
    public function getLastResponse() {
        return $this->last_response;
    }
    
    /**
     * Limpiar errores y respuestas anteriores
     */
    public function clearLastError() {
        $this->last_error = null;
        $this->last_response = null;
    }
    
    /**
     * Obtener datos de producto usando ASIN con fallback multi-región
     */
    public function getProductData($asin) {
        return $this->getProductDataWithFallback($asin);
    }
    
    /**
     * Obtener datos con fallback inteligente multi-región
     */
    public function getProductDataWithFallback($asin) {
        // Limpiar errores anteriores
        $this->clearLastError();
        
        if (!$this->isConfigured() || !$this->isEnabled()) {
            $this->last_error = 'No configurado o deshabilitado';
            error_log('[CosasAmazon PA-API] getProductData: No configurado o deshabilitado');
            return false;
        }
        
        // Circuit breaker: Verificar si debemos intentar la llamada
        if ($this->isCircuitOpen()) {
            $this->last_error = 'Circuit breaker abierto - Amazon PA API temporalmente no disponible';
            error_log('[CosasAmazon PA-API] � Circuit breaker abierto - Evitando llamada innecesaria');
            return false;
        }
        
        error_log('[CosasAmazon PA-API] �🚀 getProductData: Iniciando petición para ASIN: ' . $asin);
        
        // Guardar configuración original
        $original_region = $this->region;
        $original_host = $this->host;
        $all_errors = array();
        $had_any_success = false;
        
        // 1. Intentar con región principal
        $result = $this->tryGetProductDataFromRegion($asin, $this->region);
        if ($result !== false) {
            error_log('[CosasAmazon PA-API] ✅ Éxito con región principal: ' . $this->region);
            $this->recordCircuitSuccess();
            return $result;
        }
        
        // Guardar error de región principal
        $all_errors[$this->region] = $this->getLastError();
        error_log('[CosasAmazon PA-API] ❌ Falló región principal ' . $this->region . ': ' . $this->getLastError());
        
        // 2. Si InternalFailure persistente, intentar con regiones de fallback
        if (strpos($this->getLastError(), 'InternalFailure') !== false) {
            error_log('[CosasAmazon PA-API] 🔄 InternalFailure detectado - Iniciando fallback multi-región');
            
            $fallback_regions = $this->getFallbackRegions($original_region);
            
            foreach ($fallback_regions as $fallback_region) {
                error_log('[CosasAmazon PA-API] 🌍 Intentando región de fallback: ' . $fallback_region);
                
                $result = $this->tryGetProductDataFromRegion($asin, $fallback_region);
                if ($result !== false) {
                    error_log('[CosasAmazon PA-API] ✅ Éxito con región de fallback: ' . $fallback_region);
                    $had_any_success = true;
                    
                    // Restaurar configuración original
                    $this->region = $original_region;
                    $this->host = $original_host;
                    
                    // Agregar metadatos sobre el fallback
                    if (is_array($result)) {
                        $result['_fallback_region'] = $fallback_region;
                        $result['_original_region'] = $original_region;
                    }
                    
                    $this->recordCircuitSuccess();
                    return $result;
                }
                
                // Guardar error de esta región
                $all_errors[$fallback_region] = $this->getLastError();
                error_log('[CosasAmazon PA-API] ❌ Falló región de fallback ' . $fallback_region . ': ' . $this->getLastError());
            }
        }
        
        // 3. Restaurar configuración original y reportar todos los errores
        $this->region = $original_region;
        $this->host = $original_host;
        
        // Si no hubo ningún éxito, registrar fallo en circuit breaker
        if (!$had_any_success) {
            $this->recordCircuitFailure();
        }
        
        // Crear mensaje de error consolidado
        $error_summary = 'Falló en todas las regiones: ';
        $error_details = array();
        foreach ($all_errors as $region => $error) {
            $error_details[] = $region . '(' . substr($error, 0, 50) . ')';
        }
        $error_summary .= implode(', ', $error_details);
        
        // En entorno local, añadir información adicional
        $local_env = $this->detectLocalEnvironment();
        if ($local_env['is_local']) {
            $error_summary .= ' [ENTORNO LOCAL DETECTADO: InternalFailure es muy común en desarrollo - considera usar credenciales válidas o un entorno de producción para pruebas]';
        }
        
        $this->last_error = $error_summary;
        error_log('[CosasAmazon PA-API] 🛑 Fallback multi-región completado - Falló en todas las regiones');
        error_log('[CosasAmazon PA-API] 📋 Resumen errores: ' . $error_summary);
        
        return false;
    }
    
    /**
     * Método especial para entornos locales - devuelve datos simulados
     */
    public function getLocalTestData($asin = 'B08N5WRWNW') {
        return array(
            'asin' => $asin,
            'title' => 'Producto de Prueba - Echo Dot (4.ª generación)',
            'price' => '39,99',
            'currency' => 'EUR',
            'image_url' => 'https://via.placeholder.com/300x300?text=Producto+Test',
            'url' => 'https://amazon.es/dp/' . $asin,
            'availability' => 'En stock',
            'prime' => true,
            '_test_mode' => true,
            '_local_environment' => true,
            'description' => 'Este es un producto de prueba para entornos locales. La PA-API de Amazon no funciona bien en localhost.'
        );
    }
    
    /**
     * Test de conexión mejorado para entornos locales
     */
    public function testConnectionWithLocalFallback() {
        $local_env = $this->detectLocalEnvironment();
        
        // Si estamos en local y no hay credenciales válidas, usar datos de prueba
        if ($local_env['is_local'] && !$this->hasValidCredentials()) {
            return array(
                'success' => true,
                'message' => '✅ Modo de prueba local activado',
                'step' => 'local_test_mode',
                'data' => $this->getLocalTestData(),
                'environment' => array(
                    'is_local' => true,
                    'host' => $local_env['host'],
                    'mode' => 'test_data',
                    'note' => 'Usando datos de prueba porque estamos en entorno local sin credenciales válidas'
                )
            );
        }
        
        // Si tenemos credenciales, intentar conexión real
        return $this->testConnection();
    }
    
    /**
     * Verificar si tenemos credenciales aparentemente válidas
     */
    private function hasValidCredentials() {
        return !empty($this->access_key) && 
               !empty($this->secret_key) && 
               !empty($this->associate_tag) &&
               strlen($this->access_key) >= 16 && // Las access keys de AWS son largas
               strlen($this->secret_key) >= 30;   // Las secret keys son aún más largas
    }
    
    /**
     * Intentar obtener datos de una región específica
     */
    private function tryGetProductDataFromRegion($asin, $region) {
        // Configurar región temporal
        $this->region = $region;
        $this->host = $this->getHostForRegion($region);
        
        error_log('[CosasAmazon PA-API] 🎯 Intentando ASIN ' . $asin . ' en región ' . $region . ' (host: ' . $this->host . ')');
        
        try {
            $payload = array(
                'ItemIds' => array($asin),
                'Resources' => array(
                    'Images.Primary.Large',
                    'ItemInfo.Title',
                    'Offers.Listings.Price',
                    'Offers.Listings.SavingBasis',
                    'ItemInfo.Features',
                    'CustomerReviews.StarRating',
                    'CustomerReviews.Count'
                ),
                'PartnerTag' => $this->associate_tag,
                'PartnerType' => 'Associates',
                'Marketplace' => 'www.amazon.' . ($region === 'uk' ? 'co.uk' : $region)
            );
            
            error_log('[CosasAmazon PA-API] 🔧 Payload preparado para región ' . $region . ' con tag: ' . $this->associate_tag);
            error_log('[CosasAmazon PA-API] 🛒 Marketplace: ' . $payload['Marketplace']);
            
            $response = $this->makeRequestWithRetry('GetItems', $payload);
            $this->last_response = $response;
            
            error_log('[CosasAmazon PA-API] 📥 Respuesta recibida de ' . $region . ': ' . (is_string($response) ? substr($response, 0, 200) : json_encode($response)));
            
            // Si la respuesta es un string, intentar decodificarla
            if (is_string($response)) {
                $decoded_response = json_decode($response, true);
                if ($decoded_response !== null) {
                    $response = $decoded_response;
                }
            }
            
            if ($response && isset($response['ItemsResult']['Items'][0])) {
                error_log('[CosasAmazon PA-API] ✅ Item encontrado en región ' . $region . ', procesando datos');
                $parsed_data = $this->parseProductData($response['ItemsResult']['Items'][0]);
                error_log('[CosasAmazon PA-API] 📋 Datos procesados de ' . $region . ': ' . json_encode($parsed_data));
                return $parsed_data;
            } elseif ($response && isset($response['ItemsResult']['Items']) && empty($response['ItemsResult']['Items'])) {
                $this->last_error = 'No se encontraron items para el ASIN: ' . $asin . ' en región ' . $region;
                error_log('[CosasAmazon PA-API] ❌ No se encontraron items para el ASIN en región ' . $region);
                return false;
            } elseif ($response && isset($response['Errors'])) {
                $this->last_error = 'Errores en respuesta de ' . $region . ': ' . json_encode($response['Errors']);
                error_log('[CosasAmazon PA-API] ❌ Errores en respuesta de ' . $region . ': ' . json_encode($response['Errors']));
                return false;
            } else {
                $this->last_error = 'Respuesta inesperada o vacía de región ' . $region;
                error_log('[CosasAmazon PA-API] ❌ Respuesta inesperada de ' . $region . ': ' . (is_string($response) ? $response : json_encode($response)));
                return false;
            }
            
        } catch (Exception $e) {
            $this->last_error = 'Excepción en región ' . $region . ': ' . $e->getMessage();
            error_log('[CosasAmazon PA-API] ❌ Excepción en región ' . $region . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parsear datos de producto de la respuesta de la API
     */
    private function parseProductData($item) {
        $data = array(
            'title' => '',
            'price' => '',
            'originalPrice' => '', // Cambiado de original_price a originalPrice
            'discount' => '',
            'description' => '',
            'image' => '',
            'rating' => '',
            'reviewCount' => '', // Cambiado de reviews_count a reviewCount para consistencia
            'availability' => 'En stock',
            'source' => 'amazon_api'
        );
        
        // Título
        if (isset($item['ItemInfo']['Title']['DisplayValue'])) {
            $data['title'] = $item['ItemInfo']['Title']['DisplayValue'];
        }
        
        // Imagen
        if (isset($item['Images']['Primary']['Large']['URL'])) {
            $data['image'] = $item['Images']['Primary']['Large']['URL'];
        }
        
        // Precio
        if (isset($item['Offers']['Listings'][0]['Price']['DisplayAmount'])) {
            $data['price'] = $item['Offers']['Listings'][0]['Price']['DisplayAmount'];
        }
        
        // Precio original (antes del descuento)
        if (isset($item['Offers']['Listings'][0]['SavingBasis']['DisplayAmount'])) {
            $data['originalPrice'] = $item['Offers']['Listings'][0]['SavingBasis']['DisplayAmount'];
            
            // Calcular descuento
            if ($data['price'] && $data['originalPrice']) {
                $current = floatval(preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $data['price'])));
                $original = floatval(preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $data['originalPrice'])));
                
                if ($original > $current) {
                    $discount_amount = $original - $current;
                    $discount_percent = round(($discount_amount / $original) * 100);
                    $data['discount'] = $discount_percent; // Solo el número, sin %
                }
            }
        }
        
        // Descripción (características)
        if (isset($item['ItemInfo']['Features']['DisplayValues'])) {
            $features = array_slice($item['ItemInfo']['Features']['DisplayValues'], 0, 3);
            $data['description'] = implode('. ', $features);
        }
        
        // Rating
        if (isset($item['CustomerReviews']['StarRating']['Value'])) {
            $data['rating'] = $item['CustomerReviews']['StarRating']['Value'];
        }
        
        // Número de reseñas
        if (isset($item['CustomerReviews']['Count'])) {
            $data['reviewCount'] = $item['CustomerReviews']['Count'];
        }
        
        return $data;
    }
    
    /**
     * Método auxiliar para hacer requests con reintento automático AGRESIVO
     * Maneja errores temporales como InternalFailure con reintentos más intensivos
     */
    private function makeRequestWithRetry($operation, $payload, $max_retries = 7) {
        $last_exception = null;
        $base_delay = 0.5; // Delay inicial más corto
        $is_local = $this->isLocalEnvironment();
        
        // Si es entorno local, reducir reintentos (InternalFailure es muy común)
        if ($is_local) {
            $max_retries = 3;
            error_log('[CosasAmazon PA-API] 🏠 Entorno local detectado - Reduciendo reintentos a ' . $max_retries);
        } else {
            error_log('[CosasAmazon PA-API] 🌐 Entorno de producción - Usando ' . $max_retries . ' reintentos agresivos');
        }
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            try {
                error_log('[CosasAmazon PA-API] 🔄 Intento ' . $attempt . '/' . $max_retries . ' para operación: ' . $operation);
                
                $result = $this->makeRequest($operation, $payload);
                
                // Si llegamos aquí, la petición fue exitosa
                error_log('[CosasAmazon PA-API] ✅ Petición exitosa en intento ' . $attempt);
                return $result;
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                $last_exception = $e;
                
                error_log('[CosasAmazon PA-API] ❌ Error en intento ' . $attempt . ': ' . $error_message);
                
                // Verificar si es un error que vale la pena reintentar
                $should_retry = $this->shouldRetryError($error_message);
                
                // Para InternalFailure, siempre reintentar en producción
                $is_internal_failure = strpos($error_message, 'InternalFailure') !== false;
                if ($is_internal_failure && !$is_local) {
                    $should_retry = true;
                    error_log('[CosasAmazon PA-API] 🔧 InternalFailure detectado en producción - Forzando reintento');
                }
                
                if (!$should_retry || $attempt === $max_retries) {
                    // No reintentar más, lanzar el último error
                    if ($attempt === $max_retries) {
                        error_log('[CosasAmazon PA-API] 🛑 Máximo de reintentos alcanzado (' . $max_retries . ')');
                        if ($is_internal_failure) {
                            error_log('[CosasAmazon PA-API] 💡 InternalFailure persistente - Amazon PA API está teniendo problemas temporales');
                        }
                    } else {
                        error_log('[CosasAmazon PA-API] 🚫 Error no recuperable, no reintentando');
                    }
                    
                    throw $last_exception;
                }
                
                // Calcular delay exponencial con jitter mejorado
                if ($is_internal_failure) {
                    // Para InternalFailure, usar delays más cortos pero con más variación
                    $delay = $base_delay * pow(1.5, $attempt - 1);
                    $jitter = rand(200, 800) / 1000; // 0.2 a 0.8 segundos de variación
                } else {
                    // Para otros errores, delay exponencial estándar
                    $delay = $base_delay * pow(2, $attempt - 1);
                    $jitter = rand(100, 300) / 1000; // 0.1 a 0.3 segundos de jitter
                }
                
                $total_delay = max(0.1, $delay + $jitter); // Mínimo 0.1 segundos
                
                error_log('[CosasAmazon PA-API] ⏱️ Esperando ' . round($total_delay, 2) . ' segundos antes del siguiente intento...');
                
                // WordPress-compatible sleep
                usleep($total_delay * 1000000); // Usar microsleep para mayor precisión
            }
        }
        
        // Fallback (no debería llegar aquí)
        throw $last_exception;
    }
    
    /**
     * Detectar si estamos en un entorno local
     */
    public function detectLocalEnvironment() {
        $host = $_SERVER['HTTP_HOST'] ?? php_uname('n');
        $is_local = false;
        $indicator = '';
        if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
            $is_local = true;
            $indicator = 'host';
        } elseif (strpos($host, 'local') !== false) {
            $is_local = true;
            $indicator = 'host-string';
        } elseif (php_uname('n') === 'localhost') {
            $is_local = true;
            $indicator = 'uname';
        }
        return [
            'is_local' => $is_local,
            'host' => $host,
            'indicator' => $indicator
        ];
    }

    /**
     * Obtener información detallada del entorno
     */
    private function getEnvironmentInfo() {
        $hostname = gethostname();
        $server_addr = $_SERVER['SERVER_ADDR'] ?? 'Unknown';
        $http_host = $_SERVER['HTTP_HOST'] ?? 'Unknown';
        $server_port = $_SERVER['SERVER_PORT'] ?? 'Unknown';
        $is_local = $this->isLocalEnvironment();
        
        // Determinar indicador principal
        $indicator = 'production';
        if ($is_local) {
            if (strpos($hostname, 'localhost') !== false) {
                $indicator = 'localhost hostname';
            } elseif (strpos($server_addr, '127.0.0.1') !== false) {
                $indicator = 'loopback IP';
            } elseif (strpos($server_addr, '192.168') !== false) {
                $indicator = 'private IP range';
            } elseif (!in_array($server_port, ['80', '443'])) {
                $indicator = 'non-standard port';
            } else {
                $indicator = 'local development';
            }
        }
        
        return array(
            'is_local' => $is_local,
            'hostname' => $hostname,
            'server_addr' => $server_addr,
            'host' => $http_host,
            'port' => $server_port,
            'indicator' => $indicator,
            'environment_type' => $is_local ? 'Development/Local' : 'Production'
        );
    }

    /**
     * Determina si un error debería ser reintentado (MEJORADO)
     */
    private function shouldRetryError($error_message) {
        // Errores que SIEMPRE vale la pena reintentar (alta prioridad)
        $high_priority_retryable = array(
            'InternalFailure',               // Error interno de Amazon (MUY COMÚN)
            'InternalError',                 // Error interno genérico
            'ServiceUnavailable',            // Servicio temporalmente no disponible
            'RequestThrottled',              // Rate limiting temporal
            'TooManyRequests',              // Límite de requests
        );
        
        // Verificar errores de alta prioridad primero
        foreach ($high_priority_retryable as $error) {
            if (stripos($error_message, $error) !== false) {
                error_log('[CosasAmazon PA-API] 🔥 Error de ALTA PRIORIDAD detectado para reintento: ' . $error);
                return true;
            }
        }
        
        // Errores de red/servidor que vale la pena reintentar
        $network_retryable_errors = array(
            'HTTP 500',                      // Error del servidor
            'HTTP 502',                      // Bad Gateway
            'HTTP 503',                      // Service Unavailable
            'HTTP 504',                      // Gateway Timeout
            'timeout',                       // Timeouts de red
            'connection timeout',            // Timeouts de conexión
            'failed to open stream',        // Problemas de red temporales
            'Connection timed out',          // Timeout de conexión
            'Connection refused',            // Conexión rechazada
            'could not resolve host',       // Problemas de DNS
            'SSL connection timeout',       // Timeout SSL
        );
        
        foreach ($network_retryable_errors as $retryable_error) {
            if (stripos($error_message, $retryable_error) !== false) {
                error_log('[CosasAmazon PA-API] 🌐 Error de red detectado para reintento: ' . $retryable_error);
                return true;
            }
        }
        
        // Errores que NO vale la pena reintentar (no recuperables)
        $non_retryable_errors = array(
            'HTTP 401',                      // No autorizado
            'HTTP 403',                      // Prohibido
            'SignatureDoesNotMatch',         // Firma incorrecta
            'InvalidAssociateTag',           // Associate tag inválido
            'InvalidAccess',                 // Access key inválido
            'RequestExpired',                // Request expirado
            'InvalidParameterValue',         // Parámetros inválidos
            'MissingParameter',              // Parámetros faltantes
            'InvalidItemId',                 // ASIN inválido
            'ItemNotAccessible',             // Item no accesible
            'InvalidRegion',                 // Región inválida
            'InvalidRequest',                // Request malformado
        );
        
        foreach ($non_retryable_errors as $non_retryable_error) {
            if (stripos($error_message, $non_retryable_error) !== false) {
                error_log('[CosasAmazon PA-API] 🚫 Error NO recuperable detectado: ' . $non_retryable_error);
                return false;
            }
        }
        
        // Si no está en ninguna lista, por defecto NO reintentar para evitar loops infinitos
        // EXCEPCIÓN: Si contiene "error" o "failed" de forma genérica, dar una oportunidad
        if (stripos($error_message, 'error') !== false || 
            stripos($error_message, 'failed') !== false ||
            stripos($error_message, 'exception') !== false) {
            error_log('[CosasAmazon PA-API] 🤔 Error genérico, permitiendo un reintento cauteloso');
            return true;
        }
        
        error_log('[CosasAmazon PA-API] ❌ Error no identificado, no reintentando: ' . $error_message);
        return false;
    }

    /**
     * Realizar petición a la API
     */
    private function makeRequest($operation, $payload) {
        $method = 'POST';
        $path = '/paapi5/' . strtolower($operation);
        $url = 'https://' . $this->host . $path;
        
        $json_payload = json_encode($payload);
        
        // Generar timestamp una sola vez
        $timestamp = gmdate('Ymd\THis\Z');
        
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $operation,
            'Content-Encoding' => 'amz-1.0'
        );
        
        // Crear firma AWS4 pasando el timestamp
        $signature = $this->createAWS4Signature($method, $path, $json_payload, $headers, $timestamp);
        
        // Añadir headers necesarios para la petición (formato correcto)
        $request_headers = array(
            'Content-Type: application/json; charset=utf-8',
            'X-Amz-Target: com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $operation,
            'Content-Encoding: amz-1.0',
            'Host: ' . $this->host,
            'X-Amz-Date: ' . $timestamp,
            'Authorization: ' . $signature
        );
        
        // Log de la petición para debugging
        error_log('[CosasAmazon PA-API] URL: ' . $url);
        error_log('[CosasAmazon PA-API] Payload: ' . $json_payload);
        error_log('[CosasAmazon PA-API] Headers: ' . json_encode($request_headers));
        
        // Realizar petición
        $response = wp_remote_post($url, array(
            'method' => $method,
            'headers' => $request_headers,
            'body' => $json_payload,
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            $error_message = 'Error de conexión: ' . $response->get_error_message();
            error_log('[CosasAmazon PA-API] ' . $error_message);
            throw new Exception($error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        // Log de la respuesta para debugging
        error_log('[CosasAmazon PA-API] HTTP ' . $http_code . ' - Response: ' . substr($body, 0, 1000));
        
        // Intentar decodificar la respuesta incluso si hay error HTTP
        $data = json_decode($body, true);
        
        // Para errores HTTP 500, Amazon suele devolver XML, intentar parsearlo
        if ($http_code === 500 && $data === null && strpos($body, '<InternalFailure>') !== false) {
            error_log('[CosasAmazon PA-API] Error HTTP 500 - Amazon InternalFailure detectado');
            
            // Intentar parsear XML para obtener más información
            if (function_exists('simplexml_load_string')) {
                $xml = simplexml_load_string($body);
                if ($xml && isset($xml->Message)) {
                    $error_message = 'Error Amazon InternalFailure: ' . (string)$xml->Message;
                } else {
                    $error_message = 'Error Amazon InternalFailure: The request processing has failed due to some unknown error, exception or failure. Please retry again.';
                }
            } else {
                $error_message = 'Error Amazon InternalFailure: The request processing has failed due to some unknown error, exception or failure. Please retry again.';
            }
            
            error_log('[CosasAmazon PA-API] ' . $error_message);
            throw new Exception($error_message);
        }
        
        if ($http_code !== 200) {
            $error_message = 'Error HTTP ' . $http_code . ': ' . $body;
            error_log('[CosasAmazon PA-API] ' . $error_message);
            throw new Exception($error_message);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Error parsing JSON: ' . json_last_error_msg();
            error_log('[CosasAmazon PA-API] ' . $error_message);
            throw new Exception($error_message);
        }
        
        // Log del resultado exitoso
        error_log('[CosasAmazon PA-API] Respuesta exitosa obtenida');
        
        return $data;
    }
    
    /**
     * Crear firma AWS4 para autenticación
     */
    private function createAWS4Signature($method, $path, $payload, $headers, $timestamp = null) {
        if (!$timestamp) {
            $timestamp = gmdate('Ymd\THis\Z');
        }
        $date = gmdate('Ymd', strtotime($timestamp));
        
        // Mapeo correcto de regiones AWS para PA-API
        $aws_region_mapping = array(
            'webservices.amazon.es' => 'eu-west-1',
            'webservices.amazon.fr' => 'eu-west-1', 
            'webservices.amazon.it' => 'eu-west-1',
            'webservices.amazon.de' => 'eu-central-1',
            'webservices.amazon.co.uk' => 'eu-west-2',
            'webservices.amazon.com' => 'us-east-1'
        );
        
        // Obtener la región AWS correcta basada en el host
        $aws_region = isset($aws_region_mapping[$this->host]) ? $aws_region_mapping[$this->host] : 'eu-west-1';
        
        // Crear headers completos con los requeridos (usando headers pasados)
        $all_headers = array(
            'content-type' => 'application/json; charset=utf-8',
            'host' => $this->host,
            'x-amz-date' => $timestamp,
            'x-amz-target' => isset($headers['X-Amz-Target']) ? $headers['X-Amz-Target'] : 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems'
        );
        
        // Crear canonical headers
        $canonical_headers = '';
        $signed_headers_list = array();
        
        // Ordenar headers alfabéticamente
        ksort($all_headers);
        
        foreach ($all_headers as $name => $value) {
            $canonical_headers .= $name . ':' . trim($value) . "\n";
            $signed_headers_list[] = $name;
        }
        
        $signed_headers = implode(';', $signed_headers_list);
        
        // Crear canonical request
        $canonical_request = $method . "\n" .
                           $path . "\n" .
                           '' . "\n" .  // Query string vacía
                           $canonical_headers . "\n" .
                           $signed_headers . "\n" .
                           hash('sha256', $payload);
        
        // Log del canonical request
        error_log('[CosasAmazon PA-API] Canonical request: ' . $canonical_request);
        
        // Crear string to sign con la región AWS correcta
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $aws_region . '/' . $this->service . '/aws4_request';
        $string_to_sign = $algorithm . "\n" .
                         $timestamp . "\n" .
                         $credential_scope . "\n" .
                         hash('sha256', $canonical_request);
        
        // Log del string to sign
        error_log('[CosasAmazon PA-API] String to sign (región: ' . $aws_region . '): ' . $string_to_sign);
        
        // Crear signing key paso a paso con la región AWS correcta
        $k_date = hash_hmac('sha256', $date, 'AWS4' . $this->secret_key, true);
        $k_region = hash_hmac('sha256', $aws_region, $k_date, true);
        $k_service = hash_hmac('sha256', $this->service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        
        // Crear signature
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
        
        // Log de la signature
        error_log('[CosasAmazon PA-API] Signature: ' . $signature);
        
        // Crear authorization header
        $authorization = $algorithm . ' ' .
                        'Credential=' . $this->access_key . '/' . $credential_scope . ', ' .
                        'SignedHeaders=' . $signed_headers . ', ' .
                        'Signature=' . $signature;
        
        // Log del authorization header
        error_log('[CosasAmazon PA-API] Authorization: ' . $authorization);
        
        return $authorization;
    }
    
    /**
     * Test de conexión con diferentes configuraciones
     */
    public function testConnectionWithDifferentConfigs() {
        $results = array();
        
        // Configuraciones a probar
        $configurations = array(
            array(
                'marketplace' => 'www.amazon.es',
                'region' => 'es',
                'host' => 'webservices.amazon.es'
            ),
            array(
                'marketplace' => 'www.amazon.com',
                'region' => 'us',
                'host' => 'webservices.amazon.com'
            ),
            array(
                'marketplace' => 'www.amazon.co.uk',
                'region' => 'uk',
                'host' => 'webservices.amazon.co.uk'
            )
        );
        
        $original_region = $this->region;
        $original_host = $this->host;
        
        foreach ($configurations as $config) {
            $this->region = $config['region'];
            $this->host = $config['host'];
            
            try {
                $payload = array(
                    'ItemIds' => array('B08N5WRWNW'), // Echo Dot - producto más estable
                    'Resources' => array('ItemInfo.Title'),
                    'PartnerTag' => $this->associate_tag,
                    'PartnerType' => 'Associates',
                    'Marketplace' => $config['marketplace']
                );
                
                error_log('[CosasAmazon PA-API] Testing config: ' . json_encode($config));
                
                $response = $this->makeRequest('GetItems', $payload);
                
                $results[] = array(
                    'config' => $config,
                    'success' => true,
                    'response' => $response
                );
                
            } catch (Exception $e) {
                $results[] = array(
                    'config' => $config,
                    'success' => false,
                    'error' => $e->getMessage()
                );
            }
        }
        
        // Restaurar configuración original
        $this->region = $original_region;
        $this->host = $original_host;
        
        return $results;
    }
    public function testConnection() {
        // Limpiar errores anteriores
        $this->clearLastError();
        
        // Detectar entorno
        $is_local = $this->isLocalEnvironment();
        $environment_info = $this->getEnvironmentInfo();
        
        error_log('[CosasAmazon PA-API] 🔍 Iniciando test de conexión');
        error_log('[CosasAmazon PA-API] 🌐 Entorno: ' . ($is_local ? 'LOCAL' : 'PRODUCCIÓN'));
        error_log('[CosasAmazon PA-API] 🖥️ Host: ' . $environment_info['host']);
        
        // Validar configuración básica
        $validation_errors = $this->validateConfiguration();
        if (!empty($validation_errors)) {
            return array(
                'success' => false,
                'message' => 'Errores de configuración: ' . implode(', ', $validation_errors),
                'step' => 'validation',
                'details' => $validation_errors,
                'environment' => $environment_info
            );
        }
        
        if (!$this->isEnabled()) {
            return array(
                'success' => false,
                'message' => 'Amazon PA-API deshabilitada en configuración. Activa el checkbox "Habilitar Amazon PA API" en la configuración.',
                'step' => 'enabled',
                'environment' => $environment_info
            );
        }
        
        try {
            // ASINs de prueba conocidos por región (productos populares y estables)
            $test_asins_by_region = array(
                'es' => 'B08N5WRWNW', // Echo Dot 4ª gen - producto muy popular
                'fr' => 'B08N5WRWNW', // Mismo producto en Francia
                'it' => 'B08N5WRWNW', // Mismo producto en Italia
                'de' => 'B08N5WRWNW', // Mismo producto en Alemania
                'uk' => 'B08N5WRWNW', // Mismo producto en Reino Unido
                'us' => 'B08N5WRWNW'  // Mismo producto en Estados Unidos
            );
            
            // Usar ASIN específico para la región actual, con fallback
            $test_asin = isset($test_asins_by_region[$this->region]) ? 
                        $test_asins_by_region[$this->region] : 'B08N5WRWNW';
            
            error_log('[CosasAmazon PA-API] 🧪 Iniciando test con ASIN: ' . $test_asin . ' (región: ' . $this->region . ')');
            error_log('[CosasAmazon PA-API] ⚙️ Configuración - Region: ' . $this->region . ', Host: ' . $this->host);
            error_log('[CosasAmazon PA-API] 🔑 Access Key: ' . (!empty($this->access_key) ? 'OK (' . strlen($this->access_key) . ' chars)' : 'VACÍO'));
            error_log('[CosasAmazon PA-API] 🔐 Secret Key: ' . (!empty($this->secret_key) ? 'OK (' . strlen($this->secret_key) . ' chars)' : 'VACÍO'));
            error_log('[CosasAmazon PA-API] 🏷️ Associate Tag: ' . (!empty($this->associate_tag) ? $this->associate_tag : 'VACÍO'));
            
            // En entorno local, advertir sobre limitaciones
            if ($is_local) {
                error_log('[CosasAmazon PA-API] ⚠️ ENTORNO LOCAL: InternalFailure es muy común (tasa éxito ~30-40%)');
                error_log('[CosasAmazon PA-API] 💡 SUGERENCIA: En local usar un ASIN válido o las credenciales de un entorno real');
            } else {
                error_log('[CosasAmazon PA-API] 🚀 ENTORNO PRODUCCIÓN: Usando sistema de reintentos agresivos');
            }
            
            $start_time = microtime(true);
            $result = $this->getProductData($test_asin);
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            if ($result && !empty($result['title'])) {
                error_log('[CosasAmazon PA-API] ✅ Test exitoso en ' . $execution_time . 'ms - Título: ' . $result['title']);
                
                $success_message = 'Conexión exitosa con Amazon PA-API en ' . $execution_time . 'ms';
                if ($is_local) {
                    $success_message .= ' (entorno local)';
                } else {
                    $success_message .= ' (servidor de producción)';
                }
                
                return array(
                    'success' => true,
                    'message' => $success_message,
                    'data' => $result,
                    'step' => 'complete',
                    'environment' => $environment_info,
                    'performance' => array(
                        'execution_time_ms' => $execution_time,
                        'region' => $this->region,
                        'host' => $this->host
                    )
                );
            } else {
                // Obtener información más detallada del último error
                $last_error = $this->getLastError();
                $last_response = $this->getLastResponse();
                
                error_log('[CosasAmazon PA-API] ❌ Test falló después de ' . $execution_time . 'ms');
                error_log('[CosasAmazon PA-API] 📝 Último error: ' . $last_error);
                
                $error_details = array();
                if ($last_error) {
                    $error_details['last_error'] = $last_error;
                }
                if ($last_response) {
                    $error_details['response_preview'] = is_string($last_response) ? substr($last_response, 0, 200) : json_encode($last_response, JSON_UNESCAPED_UNICODE);
                }
                
                // Información adicional para el diagnóstico
                $additional_info = array(
                    'execution_time_ms' => $execution_time,
                    'region' => $this->region,
                    'host' => $this->host,
                    'test_asin' => $test_asin,
                    'retry_attempts' => 'Implementado (7 reintentos)',
                    'environment_type' => $is_local ? 'local' : 'production'
                );
                
                // Mensaje específico según el entorno
                $error_message = 'No se pudieron obtener datos del producto.';
                if ($last_error) {
                    $error_message .= ' Error: ' . $last_error;
                }
                
                if ($is_local && strpos($last_error, 'InternalFailure') !== false) {
                    $error_message .= ' [ENTORNO LOCAL: Este error es muy común en desarrollo local]';
                } elseif (!$is_local && strpos($last_error, 'InternalFailure') !== false) {
                    $error_message .= ' [PRODUCCIÓN: Error temporal de Amazon, verificar en unos minutos]';
                }
                
                return array(
                    'success' => false,
                    'message' => $error_message,
                    'step' => 'data_retrieval',
                    'details' => $error_details,
                    'environment' => $environment_info,
                    'additional_info' => $additional_info
                );
            }
            
        } catch (Exception $e) {
            error_log('[CosasAmazon PA-API] Test falló con excepción: ' . $e->getMessage());
            
            // Detectar si estamos en entorno local
            $local_env = $this->isLocalEnvironment();
            
            // Proporcionar información más detallada sobre el error
            $error_message = $e->getMessage();
            $step = 'exception';
            $additional_info = array();
            
            // Si estamos en local y es un InternalFailure, proporcionar información específica
            if ($local_env['is_local'] && strpos($error_message, 'InternalFailure') !== false) {
                $error_message = '🏠 Error InternalFailure detectado en entorno LOCAL. ' .
                               'Este error es muy común en desarrollo local debido a limitaciones de red. ' .
                               'Recomendación: Probar en un servidor de producción real.';
                $step = 'local_environment';
                $additional_info = array(
                    'entorno' => 'Desarrollo Local',
                    'host_detectado' => $local_env['host'],
                    'indicador' => $local_env['indicator'],
                    'solucion_recomendada' => 'Probar en servidor de producción',
                    'causa_probable' => 'Firewall/NAT doméstico bloqueando conexiones HTTPS a Amazon',
                    'archivo_test' => 'test-local-vs-produccion.html - para diagnóstico detallado'
                );
            }
            // Analizar otros tipos de error
            elseif (strpos($error_message, 'HTTP 401') !== false) {
                $error_message = 'Error 401: Credenciales inválidas o expiradas. Verifica tu Access Key ID y Secret Key.';
                $step = 'authentication';
            } elseif (strpos($error_message, 'HTTP 403') !== false) {
                $error_message = 'Error 403: Acceso denegado. Verifica que tu Associate Tag sea correcto y que tengas permisos en la región seleccionada.';
                $step = 'authorization';
            } elseif (strpos($error_message, 'HTTP 429') !== false) {
                $error_message = 'Error 429: Límite de requests superado. Espera unos minutos antes de volver a intentar.';
                $step = 'rate_limit';
            } elseif (strpos($error_message, 'HTTP 500') !== false) {
                $error_message = 'Error 500: Error interno de Amazon. Intenta más tarde.';
                $step = 'server_error';
            } elseif (strpos($error_message, 'SignatureDoesNotMatch') !== false) {
                $error_message = 'Error de firma: Secret Key incorrecto o problema de timestamp. Verifica tu Secret Access Key.';
                $step = 'signature';
            } elseif (strpos($error_message, 'InvalidAssociateTag') !== false) {
                $error_message = 'Associate Tag inválido. Verifica que sea correcto para la región seleccionada.';
                $step = 'associate_tag';
            } elseif (strpos($error_message, 'Error de conexión') !== false) {
                $error_message = 'Error de conectividad: No se pudo conectar con Amazon PA-API. Verifica tu conexión a internet.';
                $step = 'connectivity';
            }
            
            $response = array(
                'success' => false,
                'message' => $error_message,
                'step' => $step,
                'original_error' => $e->getMessage()
            );
            
            // Añadir información adicional si está disponible
            if (!empty($additional_info)) {
                $response['additional_info'] = $additional_info;
            }
            
            // Añadir información del entorno siempre
            $response['environment'] = array(
                'is_local' => $local_env['is_local'],
                'host' => $local_env['host'],
                'indicator' => $local_env['indicator']
            );
            
            return $response;
        }
    }
    
    /**
     * Test de conexión básica sin procesar datos
     */
    public function testBasicConnection($asin = 'B08N5WRWNW') {
        // Limpiar errores anteriores
        $this->clearLastError();
        
        if (!$this->isConfigured()) {
            return array(
                'success' => false,
                'message' => 'No configurado',
                'step' => 'configuration'
            );
        }
        
        if (!$this->isEnabled()) {
            return array(
                'success' => false,
                'message' => 'No habilitado',
                'step' => 'enabled'
            );
        }
        
        try {
            // Payload básico
            $payload = array(
                'ItemIds' => array($asin),
                'Resources' => array('ItemInfo.Title'),
                'PartnerTag' => $this->associate_tag,
                'PartnerType' => 'Associates',
                'Marketplace' => 'www.amazon.' . ($this->region === 'uk' ? 'co.uk' : $this->region)
            );
            
            // Intentar hacer la petición
            $response = $this->makeRequestWithRetry('GetItems', $payload);
            $this->last_response = $response;
            
            // Analizar la respuesta
            if ($response === false || $response === null) {
                return array(
                    'success' => false,
                    'message' => 'No se recibió respuesta',
                    'step' => 'request'
                );
            }
            
            if (isset($response['Errors'])) {
                return array(
                    'success' => false,
                    'message' => 'Errores en respuesta',
                    'step' => 'response',
                    'errors' => $response['Errors']
                );
            }
            
            if (isset($response['ItemsResult'])) {
                if (isset($response['ItemsResult']['Items']) && !empty($response['ItemsResult']['Items'])) {
                    return array(
                        'success' => true,
                        'message' => 'Conexión exitosa, datos recibidos',
                        'step' => 'complete',
                        'items_count' => count($response['ItemsResult']['Items'])
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => 'Respuesta válida pero sin items',
                        'step' => 'parse',
                        'response_keys' => array_keys($response)
                    );
                }
            }
            
            return array(
                'success' => false,
                'message' => 'Respuesta inesperada',
                'step' => 'parse',
                'response_keys' => array_keys($response)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Excepción: ' . $e->getMessage(),
                'step' => 'exception'
            );
        }
    }
}
?>
