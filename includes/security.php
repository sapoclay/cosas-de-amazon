<?php
/**
 * Validación de seguridad avanzada para el plugin Cosas de Amazon
 * 
 * @package CosasDeAmazon
 * @subpackage Security
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonSecurity {
    
    private static $instance = null;
    private $rate_limits = array();
    private $security_logs = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_security_measures'));
        add_filter('cosas_amazon_validate_request', array($this, 'validate_request'), 10, 2);
    }
    
    /**
     * Inicializar medidas de seguridad
     */
    public function init_security_measures() {
        // Rate limiting para AJAX
        // Eliminados hooks AJAX legacy para obtención de productos (migrado a REST API)
        
        // Sanitización de entradas
        add_filter('cosas_amazon_sanitize_input', array($this, 'sanitize_input'), 10, 2);
        
        // Logs de seguridad
        add_action('cosas_amazon_security_violation', array($this, 'log_security_violation'), 10, 3);
    }
    
    /**
     * Verificar rate limiting
     */
    public function check_rate_limit() {
        $ip = $this->get_client_ip();
        $action = $_POST['action'] ?? '';
        
        // Crear clave única para IP y acción
        $rate_key = 'rate_limit_' . md5($ip . $action);
        
        // Obtener contador actual
        $current_count = get_transient($rate_key);
        
        if ($current_count === false) {
            // Primera petición en este minuto
            set_transient($rate_key, 1, MINUTE_IN_SECONDS);
            return;
        }
        
        // Verificar límites por acción
        // Limitar solo acciones AJAX legacy si existieran (migrado a REST API)
        $limits = array();
        $limit = 20;
        
        if ($current_count >= $limit) {
            $this->log_security_violation('rate_limit_exceeded', array(
                'ip' => $ip,
                'action' => $action,
                'count' => $current_count,
                'limit' => $limit
            ), 'warning');
            
            wp_send_json_error('Demasiadas peticiones. Intenta de nuevo en unos minutos.');
            exit;
        }
        
        // Incrementar contador
        set_transient($rate_key, $current_count + 1, MINUTE_IN_SECONDS);
    }
    
    /**
     * Validar nonce de forma mejorada
     */
    public function validate_nonce() {
        $nonce = $_POST['nonce'] ?? '';
        $action = $_POST['action'] ?? '';
        
        if (!wp_verify_nonce($nonce, 'cosas_amazon_nonce')) {
            $this->log_security_violation('invalid_nonce', array(
                'ip' => $this->get_client_ip(),
                'action' => $action,
                'nonce' => $nonce,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ), 'error');
            
            wp_send_json_error('Token de seguridad inválido.');
            exit;
        }
    }
    
    /**
     * Sanitizar entrada de forma avanzada
     */
    public function sanitize_input($value, $type = 'string') {
        switch ($type) {
            case 'url':
                return $this->sanitize_amazon_url($value);
                
            case 'array_urls':
                if (!is_array($value)) {
                    $value = json_decode($value, true);
                }
                
                if (!is_array($value)) {
                    return array();
                }
                
                return array_map(array($this, 'sanitize_amazon_url'), $value);
                
            case 'display_style':
                $allowed_styles = array('horizontal', 'vertical', 'compact', 'featured', 'list', 'carousel');
                return in_array($value, $allowed_styles) ? $value : 'horizontal';
                
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'integer':
                return (int) $value;
                
            case 'string':
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Sanitizar URL de Amazon de forma estricta
     */
    private function sanitize_amazon_url($url) {
        // Sanitizar la URL básicamente
        $url = sanitize_url($url);
        
        // Verificar que es una URL válida
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        // Verificar que es de un dominio de Amazon válido
        $parsed_url = parse_url($url);
        if (!$parsed_url || !isset($parsed_url['host'])) {
            return '';
        }
        
        $host = strtolower($parsed_url['host']);
        $amazon_domains = array(
            'amazon.com', 'www.amazon.com',
            'amazon.es', 'www.amazon.es',
            'amazon.co.uk', 'www.amazon.co.uk',
            'amazon.de', 'www.amazon.de',
            'amazon.fr', 'www.amazon.fr',
            'amazon.it', 'www.amazon.it',
            'amazon.ca', 'www.amazon.ca',
            'amazon.com.au', 'www.amazon.com.au',
            'amazon.co.jp', 'www.amazon.co.jp',
            'amzn.to'
        );
        
        $is_amazon = false;
        foreach ($amazon_domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                $is_amazon = true;
                break;
            }
        }
        
        if (!$is_amazon) {
            $this->log_security_violation('invalid_amazon_domain', array(
                'ip' => $this->get_client_ip(),
                'url' => $url,
                'host' => $host
            ), 'warning');
            return '';
        }
        
        // Verificar esquema HTTPS
        if ($parsed_url['scheme'] !== 'https') {
            $url = str_replace('http://', 'https://', $url);
        }
        
        return $url;
    }
    
    /**
     * Validar petición completa
     */
    public function validate_request($is_valid, $request_data) {
        // Verificar tamaño de la petición
        $max_size = 10240; // 10KB
        $request_size = strlen(serialize($request_data));
        
        if ($request_size > $max_size) {
            $this->log_security_violation('request_too_large', array(
                'ip' => $this->get_client_ip(),
                'size' => $request_size,
                'max_size' => $max_size
            ), 'warning');
            return false;
        }
        
        // Verificar patrones sospechosos en los datos
        $suspicious_patterns = array(
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/data:text\/html/i'
        );
        
        $request_string = serialize($request_data);
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $request_string)) {
                $this->log_security_violation('suspicious_content', array(
                    'ip' => $this->get_client_ip(),
                    'pattern' => $pattern,
                    'data' => substr($request_string, 0, 200) . '...'
                ), 'error');
                return false;
            }
        }
        
        return $is_valid;
    }
    
    /**
     * Obtener IP del cliente de forma segura
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Registrar violaciones de seguridad
     */
    public function log_security_violation($type, $data, $severity = 'info') {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'severity' => $severity,
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => get_current_user_id(),
            'data' => $data
        );
        
        // Guardar en logs de WordPress
        $log_message = sprintf(
            'COSAS_AMAZON_SECURITY [%s][%s]: %s - %s',
            strtoupper($severity),
            $type,
            $this->get_client_ip(),
            json_encode($data)
        );
        
        error_log($log_message);
        
        // Guardar en opción para el dashboard de administración
        $security_logs = get_option('cosas_amazon_security_logs', array());
        $security_logs[] = $log_entry;
        
        // Mantener solo los últimos 100 logs
        if (count($security_logs) > 100) {
            $security_logs = array_slice($security_logs, -100);
        }
        
        update_option('cosas_amazon_security_logs', $security_logs);
        
        // Para violaciones críticas, enviar email al administrador
        if ($severity === 'error') {
            $this->notify_admin_security_incident($log_entry);
        }
    }
    
    /**
     * Notificar al administrador sobre incidentes de seguridad
     */
    private function notify_admin_security_incident($log_entry) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        // Verificar que no se envíen demasiados emails
        $last_notification = get_transient('cosas_amazon_last_security_notification');
        if ($last_notification) {
            return; // Ya se envió una notificación en la última hora
        }
        
        $subject = '[' . get_bloginfo('name') . '] Incidente de Seguridad - Plugin Cosas de Amazon';
        
        $message = "Se ha detectado un incidente de seguridad en el plugin Cosas de Amazon:\n\n";
        $message .= "Tipo: " . $log_entry['type'] . "\n";
        $message .= "Severidad: " . $log_entry['severity'] . "\n";
        $message .= "IP: " . $log_entry['ip'] . "\n";
        $message .= "Fecha: " . $log_entry['timestamp'] . "\n";
        $message .= "Detalles: " . json_encode($log_entry['data'], JSON_PRETTY_PRINT) . "\n\n";
        $message .= "Por favor, revisa los logs de seguridad en el panel de administración.";
        
        wp_mail($admin_email, $subject, $message);
        
        // Marcar que se envió una notificación
        set_transient('cosas_amazon_last_security_notification', time(), HOUR_IN_SECONDS);
    }
    
    /**
     * Obtener logs de seguridad para el dashboard
     */
    public function get_security_logs($limit = 50) {
        $logs = get_option('cosas_amazon_security_logs', array());
        return array_slice(array_reverse($logs), 0, $limit);
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanup_old_logs() {
        $logs = get_option('cosas_amazon_security_logs', array());
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $filtered_logs = array_filter($logs, function($log) use ($cutoff_date) {
            return $log['timestamp'] > $cutoff_date;
        });
        
        update_option('cosas_amazon_security_logs', array_values($filtered_logs));
    }
    
    /**
     * Generar reporte de seguridad
     */
    public function generate_security_report() {
        $logs = $this->get_security_logs(1000);
        
        $report = array(
            'total_incidents' => count($logs),
            'by_severity' => array(),
            'by_type' => array(),
            'top_ips' => array(),
            'recent_incidents' => array_slice($logs, 0, 10)
        );
        
        foreach ($logs as $log) {
            // Contar por severidad
            $severity = $log['severity'];
            $report['by_severity'][$severity] = ($report['by_severity'][$severity] ?? 0) + 1;
            
            // Contar por tipo
            $type = $log['type'];
            $report['by_type'][$type] = ($report['by_type'][$type] ?? 0) + 1;
            
            // Contar por IP
            $ip = $log['ip'];
            $report['top_ips'][$ip] = ($report['top_ips'][$ip] ?? 0) + 1;
        }
        
        // Ordenar IPs por frecuencia
        arsort($report['top_ips']);
        $report['top_ips'] = array_slice($report['top_ips'], 0, 10, true);
        
        return $report;
    }
}

// Inicializar la clase de seguridad
CosasAmazonSecurity::get_instance();

/**
 * Función helper para obtener la instancia de seguridad
 */
function cosas_amazon_security() {
    return CosasAmazonSecurity::get_instance();
}

/**
 * AJAX handler para obtener reporte de seguridad
 */
function cosas_amazon_ajax_security_report() {
    if (!check_ajax_referer('cosas_amazon_nonce', 'nonce', false)) {
        wp_send_json_error('Error de seguridad');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos suficientes');
        return;
    }
    
    $report = cosas_amazon_security()->generate_security_report();
    wp_send_json_success($report);
}

add_action('wp_ajax_cosas_amazon_security_report', 'cosas_amazon_ajax_security_report');

/**
 * Cleanup programado de logs antiguos
 */
function cosas_amazon_cleanup_security_logs() {
    cosas_amazon_security()->cleanup_old_logs();
}

add_action('cosas_amazon_daily_maintenance', 'cosas_amazon_cleanup_security_logs');
