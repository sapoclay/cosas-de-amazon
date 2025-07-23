<?php
/**
 * VERIFICADOR DE MENÚS - Cosas de Amazon
 * Script para verificar que los menús de administración funcionan correctamente
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

echo '<html><head><meta charset="utf-8"><title>🔍 Verificador de Menús - Cosas de Amazon</title>';
echo '<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; }
.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; }
.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; }
.info { background: #e8f4fd; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; }
h1 { color: #2c3e50; margin-bottom: 30px; }
h2 { color: #34495e; border-bottom: 2px solid #e74c3c; padding-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background: #f8f9fa; font-weight: 600; }
.action { background: #f8f9fa; border-left: 4px solid #007cba; padding: 15px; margin: 15px 0; }
</style></head><body>';

echo '<div class="container">';
echo '<h1>🔍 Verificador de Menús - Cosas de Amazon</h1>';

// Verificar estado del usuario
echo '<h2>👤 Estado del Usuario</h2>';

$current_user = wp_get_current_user();
if ($current_user->ID === 0) {
    echo '<div class="error">❌ No hay usuario logueado</div>';
    echo '<div class="action">Para probar los menús, debes estar logueado como administrador de WordPress.</div>';
    echo '</div></body></html>';
    exit;
}

echo '<table>';
echo '<tr><th>Usuario</th><td>' . $current_user->user_login . '</td></tr>';
echo '<tr><th>Email</th><td>' . $current_user->user_email . '</td></tr>';
echo '<tr><th>Roles</th><td>' . implode(', ', $current_user->roles) . '</td></tr>';

// Verificar permisos específicos
$permissions = [
    'manage_options' => current_user_can('manage_options'),
    'administrator' => current_user_can('administrator'),
    'edit_posts' => current_user_can('edit_posts'),
    'activate_plugins' => current_user_can('activate_plugins'),
];

echo '<tr><th>Permisos</th><td>';
foreach ($permissions as $perm => $has_perm) {
    echo '<span style="color: ' . ($has_perm ? 'green' : 'red') . '">' . $perm . ': ' . ($has_perm ? '✅' : '❌') . '</span><br>';
}
echo '</td></tr>';
echo '</table>';

if (!current_user_can('manage_options')) {
    echo '<div class="error">❌ El usuario actual no tiene permisos suficientes para ver los menús del plugin</div>';
    echo '</div></body></html>';
    exit;
}

// Verificar menús globales
echo '<h2>📋 Menús de WordPress</h2>';

global $menu, $submenu;

echo '<h3>Menú Principal:</h3>';
$found_main_menu = false;
echo '<table>';
echo '<tr><th>Posición</th><th>Título</th><th>Capability</th><th>Slug</th></tr>';

if (is_array($menu)) {
    foreach ($menu as $position => $menu_item) {
        if (is_array($menu_item) && isset($menu_item[2])) {
            if ($menu_item[2] === 'cosas-amazon-main') {
                $found_main_menu = true;
                echo '<tr style="background: #d4edda;">';
            } else {
                echo '<tr>';
            }
            echo '<td>' . $position . '</td>';
            echo '<td>' . (isset($menu_item[0]) ? $menu_item[0] : 'N/A') . '</td>';
            echo '<td>' . (isset($menu_item[1]) ? $menu_item[1] : 'N/A') . '</td>';
            echo '<td>' . $menu_item[2] . '</td>';
            echo '</tr>';
        }
    }
}
echo '</table>';

echo '<h3>Submenú de Configuración:</h3>';
$found_settings_menu = false;
echo '<table>';
echo '<tr><th>Página Padre</th><th>Título</th><th>Capability</th><th>Slug</th></tr>';

if (is_array($submenu) && isset($submenu['options-general.php'])) {
    foreach ($submenu['options-general.php'] as $submenu_item) {
        if (is_array($submenu_item) && isset($submenu_item[2])) {
            if ($submenu_item[2] === 'cosas-amazon-settings') {
                $found_settings_menu = true;
                echo '<tr style="background: #d4edda;">';
            } else {
                echo '<tr>';
            }
            echo '<td>options-general.php</td>';
            echo '<td>' . (isset($submenu_item[0]) ? $submenu_item[0] : 'N/A') . '</td>';
            echo '<td>' . (isset($submenu_item[1]) ? $submenu_item[1] : 'N/A') . '</td>';
            echo '<td>' . $submenu_item[2] . '</td>';
            echo '</tr>';
        }
    }
}
echo '</table>';

// Verificar estado de la clase admin
echo '<h2>🔧 Estado de la Clase Admin</h2>';

echo '<table>';
echo '<tr><th>Verificación</th><th>Estado</th></tr>';

// Verificar si la clase existe
$class_exists = class_exists('CosasAmazonAdmin');
echo '<tr><td>Clase CosasAmazonAdmin existe</td><td>' . ($class_exists ? '✅ Sí' : '❌ No') . '</td></tr>';

// Verificar si el archivo está cargado
$admin_file_path = COSAS_AMAZON_PLUGIN_PATH . 'includes/admin.php';
$admin_file_exists = file_exists($admin_file_path);
echo '<tr><td>Archivo admin.php existe</td><td>' . ($admin_file_exists ? '✅ Sí' : '❌ No') . '</td></tr>';

// Verificar si está en la instancia global
$global_instance_exists = isset($GLOBALS['cosas_amazon_admin_instance']);
echo '<tr><td>Instancia global creada</td><td>' . ($global_instance_exists ? '✅ Sí' : '❌ No') . '</td></tr>';

echo '</table>';

// Test de creación manual de menús
echo '<h2>🧪 Test de Creación Manual</h2>';

if ($class_exists) {
    echo '<div class="info">Intentando crear una instancia de CosasAmazonAdmin manualmente...</div>';
    
    try {
        $test_instance = new CosasAmazonAdmin();
        echo '<div class="success">✅ Instancia creada exitosamente</div>';
        
        // Intentar llamar al método add_admin_menu manualmente
        echo '<div class="info">Intentando registrar menús manualmente...</div>';
        $test_instance->add_admin_menu();
        echo '<div class="success">✅ Método add_admin_menu ejecutado sin errores</div>';
        
    } catch (Exception $e) {
        echo '<div class="error">❌ Error creando instancia: ' . $e->getMessage() . '</div>';
    }
} else {
    echo '<div class="error">❌ No se puede hacer el test porque la clase no existe</div>';
}

// Estado de los menús después del test
echo '<h2>📊 Estado Final de Menús</h2>';

// Re-verificar menús después del test manual
global $menu, $submenu;

echo '<div class="info">Re-verificando menús después del test manual...</div>';

echo '<table>';
echo '<tr><th>Menú</th><th>Estado</th><th>URL de Prueba</th></tr>';

// Verificar menú principal
$main_menu_exists = false;
if (is_array($menu)) {
    foreach ($menu as $menu_item) {
        if (is_array($menu_item) && isset($menu_item[2]) && $menu_item[2] === 'cosas-amazon-main') {
            $main_menu_exists = true;
            break;
        }
    }
}

echo '<tr>';
echo '<td>Menú Principal</td>';
echo '<td>' . ($main_menu_exists ? '✅ Existe' : '❌ No existe') . '</td>';
echo '<td><a href="' . admin_url('admin.php?page=cosas-amazon-main') . '" target="_blank">Probar menú principal</a></td>';
echo '</tr>';

// Verificar menú de configuración
$settings_menu_exists = false;
if (is_array($submenu) && isset($submenu['options-general.php'])) {
    foreach ($submenu['options-general.php'] as $submenu_item) {
        if (is_array($submenu_item) && isset($submenu_item[2]) && $submenu_item[2] === 'cosas-amazon-settings') {
            $settings_menu_exists = true;
            break;
        }
    }
}

echo '<tr>';
echo '<td>Menú de Configuración</td>';
echo '<td>' . ($settings_menu_exists ? '✅ Existe' : '❌ No existe') . '</td>';
echo '<td><a href="' . admin_url('options-general.php?page=cosas-amazon-settings') . '" target="_blank">Probar menú configuración</a></td>';
echo '</tr>';

echo '</table>';

// Resumen y recomendaciones
echo '<h2>📋 Resumen y Recomendaciones</h2>';

$total_issues = 0;
$issues = [];

if (!$main_menu_exists) {
    $issues[] = "Menú principal no registrado";
    $total_issues++;
}

if (!$settings_menu_exists) {
    $issues[] = "Menú de configuración no registrado";
    $total_issues++;
}

if (!$class_exists) {
    $issues[] = "Clase CosasAmazonAdmin no existe";
    $total_issues++;
}

if (!$global_instance_exists) {
    $issues[] = "Instancia global no creada";
    $total_issues++;
}

if ($total_issues === 0) {
    echo '<div class="success">';
    echo '<strong>✅ TODOS LOS TESTS PASARON</strong><br>';
    echo 'Los menús del plugin están funcionando correctamente.<br>';
    echo 'Puedes acceder a la configuración usando los enlaces de prueba arriba.';
    echo '</div>';
} else {
    echo '<div class="error">';
    echo '<strong>❌ PROBLEMAS DETECTADOS (' . $total_issues . '):</strong><br>';
    foreach ($issues as $issue) {
        echo '• ' . $issue . '<br>';
    }
    echo '</div>';
    
    echo '<div class="warning">';
    echo '<strong>🔧 SOLUCIONES RECOMENDADAS:</strong><br>';
    echo '1. Desactivar y reactivar el plugin<br>';
    echo '2. Verificar que el archivo includes/admin.php existe<br>';
    echo '3. Comprobar logs de error de WordPress<br>';
    echo '4. Verificar permisos de archivos<br>';
    echo '5. Probar con un usuario administrador diferente';
    echo '</div>';
}

echo '<div class="action">';
echo '<strong>📞 SIGUIENTE PASO:</strong><br>';
echo 'Si los menús no funcionan después de este test, ejecuta:<br>';
echo '<a href="' . COSAS_AMAZON_PLUGIN_URL . 'solucionador-produccion.php?token=cosas_amazon_fix_prod_2025" target="_blank">🔧 Solucionador de Producción</a>';
echo '</div>';

echo '</div>'; // Cerrar container
echo '</body></html>';
?>
