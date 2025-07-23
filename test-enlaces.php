<?php
/**
 * SCRIPT DE PRUEBA - Enlaces de Diagnóstico
 */

// Cargar WordPress para generar tokens válidos
try {
    $wp_config_paths = [
        dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-config.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php',
        dirname($_SERVER['DOCUMENT_ROOT']) . '/wp-config.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_config_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        throw new Exception('wp-config.php no encontrado');
    }
} catch (Exception $e) {
    die('❌ Error cargando WordPress: ' . $e->getMessage());
}

echo '<html><head><meta charset="utf-8"><title>🧪 Test de Enlaces - Cosas de Amazon</title></head><body>';
echo '<h1>🧪 Test de Enlaces de Diagnóstico</h1>';

// Generar token de seguridad válido
$security_token = wp_create_nonce('cosas_amazon_diagnostic_' . date('Y-m-d-H'));
$base_url = plugins_url('', __FILE__);

echo '<h2>Enlaces con tokens válidos:</h2>';
echo '<ul>';
echo '<li><a href="' . $base_url . '/verificacion-enlaces.php?token=' . $security_token . '" target="_blank">🔍 Verificación de Enlaces</a></li>';
echo '<li><a href="' . $base_url . '/diagnostico-produccion.php?token=' . $security_token . '" target="_blank">🏥 Diagnóstico de Producción</a></li>';
echo '<li><a href="' . $base_url . '/verificador-menus.php?token=' . $security_token . '" target="_blank">📋 Verificador de Menús</a></li>';
echo '<li><a href="' . $base_url . '/solucionador-produccion.php?token=' . $security_token . '" target="_blank">🔧 Solucionador de Producción</a></li>';
echo '</ul>';

echo '<h2>Información de Debug:</h2>';
echo '<p><strong>Token base:</strong> cosas_amazon_diagnostic_' . date('Y-m-d-H') . '</p>';
echo '<p><strong>Token generado:</strong> ' . $security_token . '</p>';
echo '<p><strong>URL base:</strong> ' . $base_url . '</p>';

echo '</body></html>';
?>
