<?php
/**
 * VERIFICACIÓN COMPLETA DE ENLACES - Cosas de Amazon
 * Script para verificar que todos los enlaces del plugin funcionan correctamente
 */

// Verificación de token de seguridad dinámico
if (!isset($_GET['token'])) {
    die('❌ Acceso denegado. Token de seguridad requerido.');
}

// Verificar token dinámico de WordPress
$provided_token = $_GET['token'];
$expected_token_base = 'cosas_amazon_diagnostic_' . date('Y-m-d-H');

// Cargar WordPress con manejo de errores
try {
    // Intentar diferentes rutas para wp-config.php
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
        die('❌ Error: No se pudo cargar WordPress. wp-config.php no encontrado.');
    }
    
    // Verificar token de seguridad una vez WordPress esté cargado
    if (!wp_verify_nonce($provided_token, $expected_token_base)) {
        die('❌ Token de seguridad inválido o expirado. Genera un nuevo enlace desde la página de administración del plugin.');
    }
    
} catch (Exception $e) {
    die('❌ Error cargando WordPress: ' . $e->getMessage());
}

echo '<html><head><meta charset="utf-8"><title>🔗 Verificación de Enlaces - Cosas de Amazon</title>';
echo '<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; }
.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; }
.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; }
.info { background: #e8f4fd; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; }
h1 { color: #2c3e50; margin-bottom: 30px; }
h2 { color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background: #f8f9fa; font-weight: 600; }
.link-test { padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 6px; background: #fafafa; }
.btn { display: inline-block; padding: 8px 15px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
.btn:hover { background: #005a87; }
</style></head><body>';

echo '<div class="container">';
echo '<h1>🔗 Verificación Completa de Enlaces - Cosas de Amazon</h1>';

// Verificar plugin activado
echo '<h2>📊 Estado del Plugin</h2>';
if (is_plugin_active('cosas-de-amazon/cosas-de-amazon.php')) {
    echo '<div class="success">✅ Plugin Cosas de Amazon está activado</div>';
} else {
    echo '<div class="error">❌ Plugin Cosas de Amazon NO está activado</div>';
}

// Definir los enlaces a verificar
$plugin_url = plugin_dir_url(__FILE__);
$plugin_path = plugin_dir_path(__FILE__);

$enlaces = [
    'Configuración Principal' => [
        'url' => admin_url('admin.php?page=cosas-amazon-main'),
        'description' => 'Panel de administración principal del plugin',
        'type' => 'admin'
    ],
    'Verificador de Menús' => [
        'url' => $plugin_url . 'verificador-menus.php?token=cosas_amazon_menu_test_2025',
        'description' => 'Test de menús de administración',
        'type' => 'external'
    ],
    'Diagnóstico de Producción' => [
        'url' => $plugin_url . 'diagnostico-produccion.php?token=cosas_amazon_prod_diag_2025',
        'description' => 'Herramientas de diagnóstico completo',
        'type' => 'external'
    ],
    'Solucionador de Producción' => [
        'url' => $plugin_url . 'solucionador-produccion.php?token=cosas_amazon_fix_prod_2025',
        'description' => 'Configuración de emergencia y reparación',
        'type' => 'external'
    ],
    'Configuración Rápida' => [
        'url' => $plugin_url . 'config-rapida.php',
        'description' => 'Script de configuración rápida',
        'type' => 'external'
    ]
];

echo '<h2>🧪 Verificación de Enlaces</h2>';

foreach ($enlaces as $nombre => $info) {
    echo '<div class="link-test">';
    echo '<h3>' . $nombre . '</h3>';
    echo '<p><strong>URL:</strong> <code>' . $info['url'] . '</code></p>';
    echo '<p><strong>Descripción:</strong> ' . $info['description'] . '</p>';
    
    if ($info['type'] === 'external') {
        // Verificar que el archivo existe
        $file_name = basename(parse_url($info['url'], PHP_URL_PATH));
        $file_path = $plugin_path . $file_name;
        
        if (file_exists($file_path)) {
            echo '<div class="success">✅ Archivo existe en: ' . $file_path . '</div>';
        } else {
            echo '<div class="error">❌ Archivo NO encontrado en: ' . $file_path . '</div>';
        }
    } else {
        echo '<div class="info">ℹ️ Enlace administrativo de WordPress</div>';
    }
    
    echo '<a href="' . $info['url'] . '" target="_blank" class="btn">🔗 Probar Enlace</a>';
    echo '</div>';
}

echo '<h2>📋 Archivos del Plugin</h2>';
echo '<div class="info">';
echo '<strong>Directorio del plugin:</strong> ' . $plugin_path . '<br>';
echo '<strong>URL del plugin:</strong> ' . $plugin_url . '<br>';
echo '</div>';

echo '<table>';
echo '<tr><th>Archivo</th><th>Estado</th><th>Tamaño</th><th>Última Modificación</th></tr>';

$archivos_importantes = [
    'cosas-de-amazon.php',
    'verificador-menus.php',
    'diagnostico-produccion.php',
    'solucionador-produccion.php',
    'config-rapida.php'
];

foreach ($archivos_importantes as $archivo) {
    $path = $plugin_path . $archivo;
    echo '<tr>';
    echo '<td><strong>' . $archivo . '</strong></td>';
    
    if (file_exists($path)) {
        echo '<td><span style="color: green;">✅ Existe</span></td>';
        echo '<td>' . round(filesize($path) / 1024, 1) . ' KB</td>';
        echo '<td>' . date('Y-m-d H:i:s', filemtime($path)) . '</td>';
    } else {
        echo '<td><span style="color: red;">❌ No existe</span></td>';
        echo '<td>-</td>';
        echo '<td>-</td>';
    }
    
    echo '</tr>';
}

echo '</table>';

echo '<h2>🎯 Recomendaciones</h2>';
echo '<div class="info">';
echo '<ol>';
echo '<li><strong>Probar cada enlace:</strong> Haz clic en los botones "🔗 Probar Enlace" para verificar funcionamiento</li>';
echo '<li><strong>Verificar permisos:</strong> Asegúrate de estar logueado como administrador</li>';
echo '<li><strong>Comprobar tokens:</strong> Los enlaces externos usan tokens de seguridad</li>';
echo '<li><strong>Revisar logs:</strong> Si hay errores, revisa los logs de WordPress</li>';
echo '</ol>';
echo '</div>';

echo '<h2>🚀 Enlaces Rápidos</h2>';
echo '<div style="text-align: center; padding: 20px;">';
echo '<a href="' . admin_url('admin.php?page=cosas-amazon-main') . '" class="btn">📊 Panel Principal</a>';
echo '<a href="' . $plugin_url . 'verificador-menus.php?token=cosas_amazon_menu_test_2025" target="_blank" class="btn">🔍 Verificar Menús</a>';
echo '<a href="' . $plugin_url . 'diagnostico-produccion.php?token=cosas_amazon_prod_diag_2025" target="_blank" class="btn">🔬 Diagnóstico</a>';
echo '<a href="' . $plugin_url . 'solucionador-produccion.php?token=cosas_amazon_fix_prod_2025" target="_blank" class="btn">🔧 Solucionador</a>';
echo '</div>';

echo '</div></body></html>';
?>
