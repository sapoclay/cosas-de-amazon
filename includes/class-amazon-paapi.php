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
     * Obtener host según región
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
     * Obtener datos de producto usando ASIN
     */
    public function getProductData($asin) {
        // Limpiar errores anteriores
        $this->clearLastError();
        
        if (!$this->isConfigured() || !$this->isEnabled()) {
            $this->last_error = 'No configurado o deshabilitado';
            error_log('[CosasAmazon PA-API] getProductData: No configurado o deshabilitado');
            return false;
        }
        
        error_log('[CosasAmazon PA-API] getProductData: Iniciando petición para ASIN: ' . $asin);
        
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
                'Marketplace' => 'www.amazon.' . ($this->region === 'uk' ? 'co.uk' : $this->region)
            );
            
            error_log('[CosasAmazon PA-API] getProductData: Payload preparado para ' . $this->associate_tag);
            error_log('[CosasAmazon PA-API] getProductData: Marketplace: ' . $payload['Marketplace']);
            
            $response = $this->makeRequest('GetItems', $payload);
            $this->last_response = $response;
            
            error_log('[CosasAmazon PA-API] getProductData: Respuesta recibida: ' . (is_string($response) ? $response : json_encode($response)));
            
            // Si la respuesta es un string, intentar decodificarla
            if (is_string($response)) {
                $decoded_response = json_decode($response, true);
                if ($decoded_response !== null) {
                    $response = $decoded_response;
                }
            }
            
            if ($response && isset($response['ItemsResult']['Items'][0])) {
                error_log('[CosasAmazon PA-API] getProductData: Item encontrado, procesando datos');
                $parsed_data = $this->parseProductData($response['ItemsResult']['Items'][0]);
                error_log('[CosasAmazon PA-API] getProductData: Datos procesados: ' . json_encode($parsed_data));
                return $parsed_data;
            } elseif ($response && isset($response['ItemsResult']['Items']) && empty($response['ItemsResult']['Items'])) {
                $this->last_error = 'No se encontraron items para el ASIN: ' . $asin;
                error_log('[CosasAmazon PA-API] getProductData: No se encontraron items para el ASIN');
                return false;
            } elseif ($response && isset($response['Errors'])) {
                $this->last_error = 'Errores en respuesta: ' . json_encode($response['Errors']);
                error_log('[CosasAmazon PA-API] getProductData: Errores en respuesta: ' . json_encode($response['Errors']));
                return false;
            } else {
                $this->last_error = 'Respuesta inesperada o vacía: ' . (is_string($response) ? $response : json_encode($response));
                error_log('[CosasAmazon PA-API] getProductData: Respuesta inesperada o vacía: ' . (is_string($response) ? $response : json_encode($response)));
                return false;
            }
            
        } catch (Exception $e) {
            $this->last_error = 'Excepción: ' . $e->getMessage();
            error_log('[CosasAmazon PA-API] getProductData: Excepción capturada: ' . $e->getMessage());
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
            'original_price' => '',
            'discount' => '',
            'description' => '',
            'image' => '',
            'rating' => '',
            'reviews_count' => '',
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
            $data['original_price'] = $item['Offers']['Listings'][0]['SavingBasis']['DisplayAmount'];
            
            // Calcular descuento
            if ($data['price'] && $data['original_price']) {
                $current = floatval(preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $data['price'])));
                $original = floatval(preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $data['original_price'])));
                
                if ($original > $current) {
                    $discount_amount = $original - $current;
                    $discount_percent = round(($discount_amount / $original) * 100);
                    $data['discount'] = $discount_percent . '%';
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
            $data['reviews_count'] = $item['CustomerReviews']['Count'];
        }
        
        return $data;
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
                    $error_message = 'Error Amazon InternalFailure: ' . $body;
                }
            } else {
                $error_message = 'Error Amazon InternalFailure: ' . $body;
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
    /**
     * Crear firma AWS4
     */
    private function createAWS4Signature($method, $path, $payload, $headers, $timestamp = null) {
        if (!$timestamp) {
            $timestamp = gmdate('Ymd\THis\Z');
        }
        $date = gmdate('Ymd', strtotime($timestamp));
        
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
        
        // Crear string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $this->region . '/' . $this->service . '/aws4_request';
        $string_to_sign = $algorithm . "\n" .
                         $timestamp . "\n" .
                         $credential_scope . "\n" .
                         hash('sha256', $canonical_request);
        
        // Log del string to sign
        error_log('[CosasAmazon PA-API] String to sign: ' . $string_to_sign);
        
        // Crear signing key paso a paso
        $k_date = hash_hmac('sha256', $date, 'AWS4' . $this->secret_key, true);
        $k_region = hash_hmac('sha256', $this->region, $k_date, true);
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
                    'ItemIds' => array('B0D6C3JVGD'),
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
        // Validar configuración básica
        $validation_errors = $this->validateConfiguration();
        if (!empty($validation_errors)) {
            return array(
                'success' => false,
                'message' => 'Errores de configuración: ' . implode(', ', $validation_errors)
            );
        }
        
        if (!$this->isEnabled()) {
            return array(
                'success' => false,
                'message' => 'Amazon PA-API deshabilitada en configuración'
            );
        }
        
        try {
            // Test con un ASIN conocido
            $test_asin = 'B0D6C3JVGD';
            error_log('[CosasAmazon PA-API] Iniciando test de conexión con ASIN: ' . $test_asin);
            
            $result = $this->getProductData($test_asin);
            
            if ($result && !empty($result['title'])) {
                error_log('[CosasAmazon PA-API] Test exitoso - Título: ' . $result['title']);
                return array(
                    'success' => true,
                    'message' => 'Conexión exitosa con Amazon PA-API',
                    'data' => $result
                );
            } else {
                error_log('[CosasAmazon PA-API] Test falló - respuesta vacía o malformada');
                return array(
                    'success' => false,
                    'message' => 'No se pudieron obtener datos del producto (respuesta vacía o malformada)'
                );
            }
            
        } catch (Exception $e) {
            error_log('[CosasAmazon PA-API] Test falló con excepción: ' . $e->getMessage());
            
            // Proporcionar información más detallada sobre el error
            $error_message = $e->getMessage();
            
            // Analizar tipos de error comunes
            if (strpos($error_message, 'HTTP 401') !== false) {
                $error_message = 'Error 401: Credenciales inválidas o expiradas';
            } elseif (strpos($error_message, 'HTTP 403') !== false) {
                $error_message = 'Error 403: Acceso denegado - verifica Associate Tag y permisos';
            } elseif (strpos($error_message, 'HTTP 429') !== false) {
                $error_message = 'Error 429: Límite de requests superado - intenta más tarde';
            } elseif (strpos($error_message, 'HTTP 500') !== false) {
                $error_message = 'Error 500: Error interno de Amazon - intenta más tarde';
            } elseif (strpos($error_message, 'SignatureDoesNotMatch') !== false) {
                $error_message = 'Error de firma: Secret Key incorrecto o problema de timestamp';
            } elseif (strpos($error_message, 'InvalidAssociateTag') !== false) {
                $error_message = 'Associate Tag inválido - verifica tu ID de afiliado';
            } elseif (strpos($error_message, 'Error de conexión') !== false) {
                $error_message = 'Error de conectividad: No se pudo conectar con Amazon PA-API';
            }
            
            return array(
                'success' => false,
                'message' => $error_message,
                'original_error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Test de conexión básica sin procesar datos
     */
    public function testBasicConnection($asin = 'B0D6C3JVGD') {
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
            $response = $this->makeRequest('GetItems', $payload);
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
