<?php
/**
 * Sistema de alertas de precio para Cosas de Amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonPriceAlerts {
    
    public function __construct() {
        add_action('wp_ajax_subscribe_price_alert', array($this, 'subscribe_price_alert'));
        add_action('wp_ajax_nopriv_subscribe_price_alert', array($this, 'subscribe_price_alert'));
        add_action('cosas_amazon_daily_price_update', array($this, 'check_price_alerts'));
        add_action('wp_ajax_unsubscribe_price_alert', array($this, 'unsubscribe_price_alert'));
        add_action('wp_ajax_nopriv_unsubscribe_price_alert', array($this, 'unsubscribe_price_alert'));
    }
    
    /**
     * Suscribirse a alertas de precio
     */
    public function subscribe_price_alert() {
        check_ajax_referer('price_alert_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $product_url = sanitize_url($_POST['product_url']);
        $target_price = floatval($_POST['target_price']);
        $current_price = floatval($_POST['current_price']);
        
        if (!is_email($email)) {
            wp_send_json_error('Email no válido');
            return;
        }
        
        if (empty($product_url) || !CosasAmazonHelpers::is_amazon_url($product_url)) {
            wp_send_json_error('URL de producto no válida');
            return;
        }
        
        if ($target_price <= 0 || $target_price >= $current_price) {
            wp_send_json_error('El precio objetivo debe ser menor al precio actual');
            return;
        }
        
        // Crear tabla si no existe
        $this->create_alerts_table();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cosas_amazon_price_alerts';
        
        // Verificar si ya existe una alerta para este email y producto
        $existing_alert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s AND product_url = %s AND status = 'active'",
            $email, $product_url
        ));
        
        if ($existing_alert) {
            // Actualizar alerta existente
            $wpdb->update(
                $table_name,
                array(
                    'target_price' => $target_price,
                    'current_price' => $current_price,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing_alert->id),
                array('%f', '%f', '%s'),
                array('%d')
            );
            
            wp_send_json_success(array('message' => 'Alerta de precio actualizada correctamente'));
        } else {
            // Crear nueva alerta
            $result = $wpdb->insert(
                $table_name,
                array(
                    'email' => $email,
                    'product_url' => $product_url,
                    'target_price' => $target_price,
                    'current_price' => $current_price,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%f', '%f', '%s', '%s', '%s')
            );
            
            if ($result) {
                wp_send_json_success(array('message' => 'Alerta de precio creada correctamente'));
            } else {
                wp_send_json_error('Error al crear la alerta de precio');
            }
        }
    }
    
    /**
     * Desuscribirse de alertas
     */
    public function unsubscribe_price_alert() {
        $token = sanitize_text_field($_GET['token']);
        $email = sanitize_email($_GET['email']);
        
        if (empty($token) || empty($email)) {
            wp_die('Parámetros no válidos');
        }
        
        // Verificar token
        $expected_token = wp_hash($email . 'price_alert_unsubscribe');
        if (!hash_equals($expected_token, $token)) {
            wp_die('Token no válido');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cosas_amazon_price_alerts';
        
        // Desactivar todas las alertas del email
        $wpdb->update(
            $table_name,
            array('status' => 'unsubscribed', 'updated_at' => current_time('mysql')),
            array('email' => $email),
            array('%s', '%s'),
            array('%s')
        );
        
        wp_die('Te has desuscrito correctamente de todas las alertas de precio.');
    }
    
    /**
     * Verificar alertas de precio durante la actualización diaria
     */
    public function check_price_alerts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cosas_amazon_price_alerts';
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return;
        }
        
        // Obtener alertas activas
        $active_alerts = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active' ORDER BY product_url"
        );
        
        if (empty($active_alerts)) {
            return;
        }
        
        $processed_urls = array();
        $triggered_alerts = array();
        
        foreach ($active_alerts as $alert) {
            // Evitar procesar la misma URL múltiples veces
            if (in_array($alert->product_url, $processed_urls)) {
                continue;
            }
            
            $processed_urls[] = $alert->product_url;
            
            // Obtener precio actual del producto
            $product_data = CosasAmazonHelpers::get_product_data($alert->product_url, true);
            
            if (!$product_data || !isset($product_data['price_history']['current'])) {
                continue;
            }
            
            $current_price = $product_data['price_history']['current'];
            
            // Buscar todas las alertas para este producto que se cumplan
            $url_alerts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE product_url = %s AND status = 'active' AND target_price >= %f",
                $alert->product_url, $current_price
            ));
            
            foreach ($url_alerts as $url_alert) {
                $triggered_alerts[] = array(
                    'alert' => $url_alert,
                    'product_data' => $product_data,
                    'current_price' => $current_price
                );
                
                // Marcar alerta como disparada
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'triggered',
                        'triggered_at' => current_time('mysql'),
                        'final_price' => $current_price
                    ),
                    array('id' => $url_alert->id),
                    array('%s', '%s', '%f'),
                    array('%d')
                );
            }
        }
        
        // Enviar emails para alertas disparadas
        if (!empty($triggered_alerts)) {
            $this->send_price_alert_emails($triggered_alerts);
        }
        
        cosas_amazon_log("Procesadas " . count($triggered_alerts) . " alertas de precio", 'info');
    }
    
    /**
     * Enviar emails de alerta de precio
     */
    private function send_price_alert_emails($triggered_alerts) {
        foreach ($triggered_alerts as $alert_data) {
            $alert = $alert_data['alert'];
            $product_data = $alert_data['product_data'];
            $current_price = $alert_data['current_price'];
            
            $subject = '¡Bajada de precio! ' . $product_data['title'];
            
            $unsubscribe_token = wp_hash($alert->email . 'price_alert_unsubscribe');
            $unsubscribe_url = add_query_arg(array(
                'action' => 'unsubscribe_price_alert',
                'email' => urlencode($alert->email),
                'token' => $unsubscribe_token
            ), admin_url('admin-ajax.php'));
            
            $message = "
            <html>
            <head><title>Alerta de Precio - Cosas de Amazon</title></head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #ff9500;'>¡Buenas noticias!</h2>
                    
                    <p>El producto que estabas siguiendo ha bajado de precio:</p>
                    
                    <div style='border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; background: #f9f9f9;'>
                        <h3 style='margin-top: 0;'>" . esc_html($product_data['title']) . "</h3>
                        
                        <div style='margin: 15px 0;'>
                            <span style='font-size: 18px; color: #999; text-decoration: line-through;'>
                                Precio anterior: " . number_format($alert->current_price, 2, ',', '.') . "€
                            </span>
                        </div>
                        
                        <div style='margin: 15px 0;'>
                            <span style='font-size: 24px; color: #b12704; font-weight: bold;'>
                                Precio actual: " . number_format($current_price, 2, ',', '.') . "€
                            </span>
                        </div>
                        
                        <div style='margin: 15px 0;'>
                            <span style='background: #4CAF50; color: white; padding: 5px 10px; border-radius: 4px;'>
                                ¡Ahorro: " . number_format($alert->current_price - $current_price, 2, ',', '.') . "€!
                            </span>
                        </div>
                        
                        <div style='margin: 20px 0;'>
                            <a href='" . esc_url($alert->product_url) . "' 
                               style='background: linear-gradient(135deg, #ff9500 0%, #ff7b00 100%); 
                                      color: white; 
                                      padding: 12px 24px; 
                                      text-decoration: none; 
                                      border-radius: 6px; 
                                      display: inline-block;'>
                                Ver en Amazon
                            </a>
                        </div>
                    </div>
                    
                    <p><small>
                        Si no quieres recibir más alertas de precio, 
                        <a href='" . esc_url($unsubscribe_url) . "'>haz clic aquí para desuscribirte</a>.
                    </small></p>
                </div>
            </body>
            </html>";
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option('blogname') . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
            );
            
            wp_mail($alert->email, $subject, $message, $headers);
        }
    }
    
    /**
     * Crear tabla de alertas de precio
     */
    private function create_alerts_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cosas_amazon_price_alerts';
        
        // Verificar si la tabla ya existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            product_url varchar(500) NOT NULL,
            target_price decimal(10,2) NOT NULL,
            current_price decimal(10,2) NOT NULL,
            final_price decimal(10,2) DEFAULT NULL,
            status enum('active','triggered','unsubscribed') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            triggered_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY email_index (email),
            KEY product_url_index (product_url),
            KEY status_index (status),
            KEY created_at_index (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Obtener estadísticas de alertas
     */
    public static function get_alerts_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cosas_amazon_price_alerts';
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return array(
                'total_alerts' => 0,
                'active_alerts' => 0,
                'triggered_alerts' => 0
            );
        }
        
        $total_alerts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $active_alerts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
        $triggered_alerts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'triggered'");
        
        return array(
            'total_alerts' => intval($total_alerts),
            'active_alerts' => intval($active_alerts),
            'triggered_alerts' => intval($triggered_alerts)
        );
    }
}

// Inicializar alertas de precio
new CosasAmazonPriceAlerts();
