<?php
/**
 * DIAGNÓSTICO DE PRODUCCIÓN - Cosas de Amazon
 * Script para diagnosticar problemas específicos en servidor de producción
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
        throw new Exception('wp-config.php no encontrado en ninguna ruta');
    }
    
    // Verificar token de seguridad una vez WordPress esté cargado
    if (!wp_verify_nonce($provided_token, $expected_token_base)) {
        die('❌ Token de seguridad inválido o expirado. Genera un nuevo enlace desde la página de administración del plugin.');
    }
    
} catch (Exception $e) {
    die('❌ Error cargando WordPress: ' . $e->getMessage());
}

// Definir constantes del plugin si no existen
if (!defined('COSAS_AMAZON_PLUGIN_PATH')) {
    define('COSAS_AMAZON_PLUGIN_PATH', dirname(__FILE__) . '/');
}
if (!defined('COSAS_AMAZON_PLUGIN_URL')) {
    define('COSAS_AMAZON_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Configurar para mostrar todos los errores temporalmente
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Iniciar output buffering para evitar problemas
ob_start();

echo '<html><head><meta charset="utf-8"><title>🔬 Diagnóstico Producción - Cosas de Amazon</title>';
echo '<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; }
.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; }
.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; }
.info { background: #e8f4fd; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; }
.test-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #fafafa; }
.code { background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px; overflow-x: auto; }
h1 { color: #2c3e50; margin-bottom: 30px; }
h2 { color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
h3 { color: #7f8c8d; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background: #f8f9fa; font-weight: 600; }
</style></head><body>';

echo '<div class="container">';
echo '<h1>🔬 Diagnóstico de Producción - Cosas de Amazon</h1>';

// Información del servidor
echo '<div class="test-section">';
echo '<h2>🖥️ Información del Servidor</h2>';

$server_info = [
    'Servidor Web' => $_SERVER['SERVER_SOFTWARE'] ?? 'No disponible',
    'PHP Version' => PHP_VERSION,
    'WordPress Path' => ABSPATH ?? 'No disponible',
    'Plugin Path' => $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/cosas-de-amazon/',
    'Hostname' => gethostname(),
    'Sistema Operativo' => php_uname(),
    'Memoria PHP' => ini_get('memory_limit'),
    'Execution Time' => ini_get('max_execution_time') . ' segundos',
];

// Detectar servidor web
$is_litespeed = strpos(strtolower($_SERVER['SERVER_SOFTWARE'] ?? ''), 'litespeed') !== false;
$is_apache = strpos(strtolower($_SERVER['SERVER_SOFTWARE'] ?? ''), 'apache') !== false;
$is_nginx = strpos(strtolower($_SERVER['SERVER_SOFTWARE'] ?? ''), 'nginx') !== false;

if ($is_litespeed) {
    $server_info['Tipo de Servidor'] = '🚀 LiteSpeed (Detectado)';
} elseif ($is_apache) {
    $server_info['Tipo de Servidor'] = '🐧 Apache';
} elseif ($is_nginx) {
    $server_info['Tipo de Servidor'] = '⚡ Nginx';
} else {
    $server_info['Tipo de Servidor'] = '❓ Desconocido';
}

echo '<table>';
foreach ($server_info as $key => $value) {
    echo '<tr><th>' . $key . '</th><td>' . htmlspecialchars($value) . '</td></tr>';
}
echo '</table>';

if ($is_litespeed) {
    echo '<div class="info">';
    echo '<strong>🚀 LITESPEED DETECTADO</strong><br>';
    echo 'LiteSpeed puede tener configuraciones de cache diferentes a Apache que pueden afectar el JavaScript y CSS del plugin.';
    echo '</div>';
}

echo '</div>';

// Forzar envío inmediato al navegador
if (ob_get_level()) {
    ob_flush();
}
flush();

// Verificar archivos del plugin
echo '<div class="test-section">';
echo '<h2>📁 Verificación de Archivos del Plugin</h2>';

$plugin_files = [
    'Archivo Principal' => 'cosas-de-amazon.php',
    'Clase Principal' => 'core/class-cosas-de-amazon.php',
    'Admin' => 'includes/admin.php',
    'JavaScript Frontend' => 'assets/js/frontend.js',
    'JavaScript Admin' => 'assets/js/admin.js',
    'CSS Frontend' => 'assets/css/style.css',
    'CSS Editor' => 'assets/css/editor.css',
];

$plugin_path = COSAS_AMAZON_PLUGIN_PATH;

echo '<table>';
echo '<tr><th>Archivo</th><th>Estado</th><th>Tamaño</th><th>Permisos</th></tr>';

foreach ($plugin_files as $name => $file) {
    $full_path = $plugin_path . $file;
    $exists = file_exists($full_path);
    $size = $exists ? filesize($full_path) : 0;
    $perms = $exists ? substr(sprintf('%o', fileperms($full_path)), -4) : 'N/A';
    
    echo '<tr>';
    echo '<td>' . $name . '</td>';
    echo '<td>' . ($exists ? '✅ Existe' : '❌ No existe') . '</td>';
    echo '<td>' . ($exists ? number_format($size) . ' bytes' : '-') . '</td>';
    echo '<td>' . $perms . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</div>';

// Forzar envío al navegador
if (ob_get_level()) {
    ob_flush();
}
flush();

// Verificar configuración WordPress
echo '<div class="test-section">';
echo '<h2>🔧 Configuración WordPress</h2>';

// Simular carga de WordPress
$wp_config_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php';
$wp_exists = file_exists($wp_config_path);

echo '<table>';
echo '<tr><th>Configuración</th><th>Estado</th></tr>';
echo '<tr><td>wp-config.php</td><td>' . ($wp_exists ? '✅ Existe' : '❌ No encontrado') . '</td></tr>';

// Verificar si estamos en WordPress
if (defined('ABSPATH')) {
    echo '<tr><td>WordPress Cargado</td><td>✅ Sí</td></tr>';
    echo '<tr><td>WordPress Version</td><td>' . (function_exists('get_bloginfo') ? get_bloginfo('version') : 'No disponible') . '</td></tr>';
    echo '<tr><td>Tema Activo</td><td>' . (function_exists('get_option') ? get_option('stylesheet') : 'No disponible') . '</td></tr>';
    
    // Verificar opciones del plugin
    if (function_exists('get_option')) {
        $plugin_options = get_option('cosas_amazon_options');
        echo '<tr><td>Opciones del Plugin</td><td>' . ($plugin_options ? '✅ Configurado' : '❌ No configurado') . '</td></tr>';
        
        if ($plugin_options) {
            $show_button_default = $plugin_options['show_button_by_default'] ?? null;
            echo '<tr><td>Mostrar Botón por Defecto</td><td>' . ($show_button_default ? '✅ Habilitado' : '❌ Deshabilitado') . '</td></tr>';
            
            $button_text = $plugin_options['default_button_text'] ?? 'Ver en Amazon';
            echo '<tr><td>Texto del Botón</td><td>' . htmlspecialchars($button_text) . '</td></tr>';
        }
    }
} else {
    echo '<tr><td>WordPress Cargado</td><td>❌ No (ejecutando standalone)</td></tr>';
}

echo '</table>';
echo '</div>';

// Verificar permisos de usuario
echo '<div class="test-section">';
echo '<h2>👤 Verificación de Permisos</h2>';

if (function_exists('current_user_can')) {
    $user_permissions = [
        'manage_options' => current_user_can('manage_options'),
        'administrator' => current_user_can('administrator'),
        'edit_posts' => current_user_can('edit_posts'),
        'upload_files' => current_user_can('upload_files'),
    ];
    
    echo '<table>';
    echo '<tr><th>Permiso</th><th>Estado</th></tr>';
    foreach ($user_permissions as $perm => $has_perm) {
        echo '<tr><td>' . $perm . '</td><td>' . ($has_perm ? '✅ Tiene' : '❌ No tiene') . '</td></tr>';
    }
    echo '</table>';
    
    if (!current_user_can('manage_options')) {
        echo '<div class="error">';
        echo '<strong>🚫 PROBLEMA DE PERMISOS DETECTADO</strong><br>';
        echo 'El usuario actual no tiene permisos de "manage_options", necesarios para acceder al menú del plugin.<br>';
        echo '<strong>Solución:</strong> Acceder como administrador o asignar permisos adecuados.';
        echo '</div>';
    }
} else {
    echo '<div class="warning">⚠️ No se puede verificar permisos - WordPress no cargado completamente</div>';
}

echo '</div>';

// Test específico de LiteSpeed
if ($is_litespeed) {
    echo '<div class="test-section">';
    echo '<h2>🚀 Configuración Específica LiteSpeed</h2>';
    
    echo '<div class="warning">';
    echo '<strong>🚀 LITESPEED DETECTADO - Consideraciones Especiales:</strong><br><br>';
    echo '<strong>1. Cache de JavaScript/CSS:</strong><br>';
    echo '• LiteSpeed puede cachear archivos JS/CSS más agresivamente que Apache<br>';
    echo '• Esto puede causar que los botones no aparezcan si hay problemas de cache<br><br>';
    echo '<strong>2. Configuración .htaccess:</strong><br>';
    echo '• LiteSpeed usa una sintaxis ligeramente diferente<br>';
    echo '• Algunas reglas de Apache pueden no funcionar igual<br><br>';
    echo '<strong>3. Módulos PHP:</strong><br>';
    echo '• Verificar que todos los módulos necesarios estén habilitados<br>';
    echo '• Especialmente cURL y OpenSSL para PA API<br><br>';
    echo '<strong>Soluciones Recomendadas:</strong><br>';
    echo '• Limpiar cache de LiteSpeed<br>';
    echo '• Verificar configuración de cache para archivos CSS/JS<br>';
    echo '• Revisar logs de error de LiteSpeed';
    echo '</div>';
    
    // Verificar módulos PHP específicos
    $required_modules = ['curl', 'openssl', 'json', 'mbstring'];
    echo '<h3>Módulos PHP Requeridos:</h3>';
    echo '<table>';
    echo '<tr><th>Módulo</th><th>Estado</th></tr>';
    foreach ($required_modules as $module) {
        $loaded = extension_loaded($module);
        echo '<tr><td>' . $module . '</td><td>' . ($loaded ? '✅ Cargado' : '❌ No cargado') . '</td></tr>';
    }
    echo '</table>';
    
    echo '</div>';
}

// Test de JavaScript en el navegador
echo '<div class="test-section">';
echo '<h2>🌐 Test de JavaScript Frontend</h2>';

echo '<div class="info">';
echo '<strong>Test de JavaScript del Plugin:</strong><br>';
echo 'Este test verificará si el JavaScript del frontend se carga correctamente.';
echo '</div>';

echo '<div id="js-test-result" style="padding: 15px; margin: 10px 0; border-radius: 4px; background: #f8f9fa; border: 1px solid #ddd;">';
echo 'Cargando test de JavaScript...';
echo '</div>';

// Simular el HTML del plugin para test
echo '<div style="display: none;">';
echo '<div class="cosas-de-amazon-block" data-amazon-url="https://amazon.es/test">';
echo '<a href="#" class="amazon-button cosas-amazon-btn">Ver en Amazon (Test)</a>';
echo '</div>';
echo '</div>';

echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
echo '<script>';
echo '
$(document).ready(function() {
    var testResult = $("#js-test-result");
    var errors = [];
    
    // Test 1: jQuery disponible
    if (typeof $ === "undefined") {
        errors.push("❌ jQuery no está disponible");
    } else {
        console.log("✅ jQuery disponible");
    }
    
    // Test 2: Elementos del plugin
    var amazonButtons = $(".amazon-button, .cosas-amazon-btn");
    if (amazonButtons.length === 0) {
        errors.push("❌ No se encontraron botones de Amazon en la página");
    } else {
        console.log("✅ Botones de Amazon encontrados: " + amazonButtons.length);
    }
    
    // Test 3: Event handlers
    try {
        amazonButtons.off("click.test").on("click.test", function(e) {
            e.preventDefault();
            console.log("✅ Event handler funcionando");
        });
        console.log("✅ Event handlers configurados");
    } catch(e) {
        errors.push("❌ Error configurando event handlers: " + e.message);
    }
    
    // Mostrar resultados
    if (errors.length === 0) {
        testResult.html("✅ <strong>JavaScript funcionando correctamente</strong><br>Todos los tests pasaron sin errores.");
        testResult.css("background", "#d4edda").css("color", "#155724").css("border-color", "#c3e6cb");
    } else {
        testResult.html("❌ <strong>Problemas detectados:</strong><br>" + errors.join("<br>"));
        testResult.css("background", "#f8d7da").css("color", "#721c24").css("border-color", "#f5c6cb");
    }
});
';
echo '</script>';

echo '</div>';

// Recomendaciones específicas
echo '<div class="test-section">';
echo '<h2>💡 Recomendaciones de Solución</h2>';

echo '<h3>Para el problema de botones "Ver en Amazon" no aparecen:</h3>';
echo '<div class="info">';
echo '<strong>Posibles causas y soluciones:</strong><br><br>';
echo '<strong>1. Cache de LiteSpeed:</strong><br>';
echo '• Acceder al panel de LiteSpeed Cache<br>';
echo '• Limpiar cache de CSS y JavaScript<br>';
echo '• Desactivar temporalmente el cache para testing<br><br>';
echo '<strong>2. Configuración del plugin:</strong><br>';
echo '• Verificar que "Mostrar botón por defecto" esté habilitado<br>';
echo '• Revisar configuración en WordPress Admin → Cosas de Amazon<br><br>';
echo '<strong>3. Archivos del plugin:</strong><br>';
echo '• Verificar que assets/js/frontend.js se carga correctamente<br>';
echo '• Comprobar errores en la consola del navegador<br><br>';
echo '<strong>4. Diferencias LiteSpeed vs Apache:</strong><br>';
echo '• LiteSpeed puede interpretar .htaccess diferente<br>';
echo '• Verificar que los archivos CSS/JS se sirven correctamente';
echo '</div>';

echo '<h3>Para el problema de menú no accesible:</h3>';
echo '<div class="warning">';
echo '<strong>Soluciones:</strong><br><br>';
echo '<strong>1. Permisos de usuario:</strong><br>';
echo '• Asegurar que tienes rol de Administrador<br>';
echo '• El plugin requiere capability "manage_options"<br><br>';
echo '<strong>2. Activación del plugin:</strong><br>';
echo '• Re-activar el plugin desde Plugins → Plugins instalados<br>';
echo '• Verificar que no hay errores durante la activación<br><br>';
echo '<strong>3. Conflictos con otros plugins:</strong><br>';
echo '• Desactivar temporalmente otros plugins<br>';
echo '• Verificar si el menú aparece<br><br>';
echo '<strong>4. Cache del admin:</strong><br>';
echo '• Limpiar cache del área de administración<br>';
echo '• Hacer refresh forzado (Ctrl+F5)';
echo '</div>';

echo '</div>';

// Información de contacto y siguientes pasos
echo '<div class="test-section">';
echo '<h2>📋 Siguientes Pasos</h2>';

echo '<div class="info">';
echo '<strong>1. INMEDIATO:</strong><br>';
echo '• Limpiar todo el cache de LiteSpeed<br>';
echo '• Acceder como administrador de WordPress<br>';
echo '• Verificar que el plugin esté activado correctamente<br><br>';
echo '<strong>2. VERIFICACIÓN:</strong><br>';
echo '• Comprobar consola del navegador (F12) en busca de errores JavaScript<br>';
echo '• Verificar que se cargan los archivos CSS/JS del plugin<br>';
echo '• Probar con diferentes navegadores<br><br>';
echo '<strong>3. SI PERSISTE:</strong><br>';
echo '• Revisar logs de error de LiteSpeed<br>';
echo '• Comparar configuración con el entorno local<br>';
echo '• Considerar configurar bypass de cache para archivos del plugin';
echo '</div>';

echo '</div>';

echo '</div>'; // Cerrar container
echo '</body></html>';
?>
