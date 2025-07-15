<?php
/**
 * Sistema de estadísticas para Cosas de Amazon
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class CosasAmazonStats {
    
    public function __construct() {
        add_action('wp_footer', array($this, 'track_product_views'));
        add_action('wp_ajax_track_amazon_click', array($this, 'track_amazon_click'));
        add_action('wp_ajax_nopriv_track_amazon_click', array($this, 'track_amazon_click'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking_script'));
    }
    
    /**
     * Encolar script de tracking
     */
    public function enqueue_tracking_script() {
        if ($this->has_amazon_products()) {
            wp_enqueue_script(
                'cosas-amazon-tracking',
                COSAS_AMAZON_PLUGIN_URL . 'assets/js/tracking.js',
                array('jquery'),
                COSAS_AMAZON_VERSION,
                true
            );
            
            wp_localize_script('cosas-amazon-tracking', 'cosasAmazonTracking', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('amazon_tracking')
            ));
        }
    }
    
    /**
     * Detectar si la página tiene productos de Amazon
     */
    private function has_amazon_products() {
        global $post;
        if (!$post) return false;
        
        return has_block('cosas-amazon/producto-amazon', $post);
    }
    
    /**
     * Tracking de visualizaciones de productos
     */
    public function track_product_views() {
        if (!$this->has_amazon_products()) return;
        
        global $post;
        $post_id = $post->ID;
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        // Registrar visualización
        $this->record_stat('view', array(
            'post_id' => $post_id,
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));
    }
    
    /**
     * Tracking de clicks en productos
     */
    public function track_amazon_click() {
        check_ajax_referer('amazon_tracking', 'nonce');
        
        $product_url = sanitize_url($_POST['product_url']);
        $post_id = intval($_POST['post_id']);
        $style = sanitize_text_field($_POST['style']);
        
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        // Registrar click
        $this->record_stat('click', array(
            'post_id' => $post_id,
            'product_url' => $product_url,
            'style' => $style,
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));
        
        wp_send_json_success();
    }
    
    /**
     * Registrar estadística en la base de datos
     */
    private function record_stat($action, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cosas_amazon_stats';
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $this->create_stats_table();
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'action' => $action,
                'post_id' => $data['post_id'] ?? 0,
                'product_url' => $data['product_url'] ?? '',
                'style' => $data['style'] ?? '',
                'user_id' => $data['user_id'] ?? 0,
                'ip_address' => $data['ip_address'] ?? '',
                'user_agent' => substr($data['user_agent'] ?? '', 0, 500),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Crear tabla de estadísticas
     */
    private function create_stats_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cosas_amazon_stats';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            post_id bigint(20) DEFAULT 0,
            product_url varchar(500) DEFAULT '',
            style varchar(50) DEFAULT '',
            user_id bigint(20) DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            user_agent varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action_index (action),
            KEY post_id_index (post_id),
            KEY created_at_index (created_at),
            KEY style_index (style)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Obtener estadísticas básicas
     */
    public static function get_basic_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cosas_amazon_stats';
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return array(
                'total_views' => 0,
                'total_clicks' => 0,
                'ctr' => 0,
                'popular_styles' => array(),
                'recent_activity' => array()
            );
        }
        
        // Total de visualizaciones
        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE action = 'view' AND created_at >= %s",
            $date_limit
        ));
        
        // Total de clicks
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE action = 'click' AND created_at >= %s",
            $date_limit
        ));
        
        // CTR (Click Through Rate)
        $ctr = $total_views > 0 ? round(($total_clicks / $total_views) * 100, 2) : 0;
        
        // Estilos más populares
        $popular_styles = $wpdb->get_results($wpdb->prepare(
            "SELECT style, COUNT(*) as count 
             FROM $table_name 
             WHERE action = 'click' AND style != '' AND created_at >= %s 
             GROUP BY style 
             ORDER BY count DESC 
             LIMIT 10",
            $date_limit
        ), ARRAY_A);
        
        // Actividad reciente
        $recent_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT action, product_url, style, created_at 
             FROM $table_name 
             WHERE created_at >= %s 
             ORDER BY created_at DESC 
             LIMIT 20",
            $date_limit
        ), ARRAY_A);
        
        return array(
            'total_views' => intval($total_views),
            'total_clicks' => intval($total_clicks),
            'ctr' => $ctr,
            'popular_styles' => $popular_styles,
            'recent_activity' => $recent_activity
        );
    }
    
    /**
     * Obtener estadísticas por producto
     */
    public static function get_product_stats($product_url, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cosas_amazon_stats';
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return array('views' => 0, 'clicks' => 0, 'ctr' => 0);
        }
        
        $views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action = 'view' AND product_url = %s AND created_at >= %s",
            $product_url, $date_limit
        ));
        
        $clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE action = 'click' AND product_url = %s AND created_at >= %s",
            $product_url, $date_limit
        ));
        
        $ctr = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;
        
        return array(
            'views' => intval($views),
            'clicks' => intval($clicks),
            'ctr' => $ctr
        );
    }
}

// Nota: La inicialización se hace en el archivo principal cosas-de-amazon.php
