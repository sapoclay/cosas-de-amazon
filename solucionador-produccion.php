<?php
/**
 * SOLUCIONADOR AUTOMÁTICO - Problemas de Producción
 * Script para configurar automáticamente el plugin en producción
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
    
    // Cargar funciones de admin si están disponibles
    if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/admin.php')) {
        require_once(ABSPATH . 'wp-admin/includes/admin.php');
    }
} catch (Exception $e) {
    die('❌ Error cargando WordPress: ' . $e->getMessage());
}

echo '<html><head><meta charset="utf-8"><title>🔧 Solucionador Automático - Cosas de Amazon</title>';
echo '<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; }
.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; }
.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; }
.info { background: #e8f4fd; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; }
h1 { color: #2c3e50; margin-bottom: 30px; }
h2 { color: #34495e; border-bottom: 2px solid #e74c3c; padding-bottom: 10px; }
.action { background: #f8f9fa; border-left: 4px solid #007cba; padding: 15px; margin: 15px 0; }
</style></head><body>';

echo '<div class="container">';
echo '<h1>🔧 Solucionador Automático - Cosas de Amazon</h1>';

$fixes_applied = [];
$errors_found = [];

// Fix 1: Configurar opciones por defecto del plugin
echo '<h2>1️⃣ Configurando Opciones por Defecto</h2>';

$current_options = get_option('cosas_amazon_options', []);

$default_options = [
    'show_button_by_default' => true,
    'default_button_text' => 'Ver en Amazon',
    'amazon_associate_tag' => $current_options['amazon_associate_tag'] ?? '',
    'amazon_access_key' => $current_options['amazon_access_key'] ?? '',
    'amazon_secret_key' => $current_options['amazon_secret_key'] ?? '',
    'cache_duration' => 24,
    'enable_cache' => true,
    'track_clicks' => true,
    'button_style' => 'modern',
    'open_in_new_tab' => true,
    'enable_price_alerts' => false,
    'enable_fallback_images' => true,
];

// Merged options preserving existing values
$updated_options = array_merge($default_options, $current_options);

$result = update_option('cosas_amazon_options', $updated_options);

// update_option returns false if the value is the same (no update needed)
// So we need to check if the options are actually set correctly
$final_options = get_option('cosas_amazon_options', []);
$options_configured = !empty($final_options) && isset($final_options['show_button_by_default']);

if ($options_configured) {
    if ($result) {
        echo '<div class="success">✅ Opciones por defecto configuradas correctamente</div>';
    } else {
        echo '<div class="success">✅ Opciones por defecto ya estaban configuradas</div>';
    }
    echo '<div class="action">Configuración aplicada:<br>';
    echo '• Mostrar botón por defecto: <strong>' . ($final_options['show_button_by_default'] ? 'Habilitado' : 'Deshabilitado') . '</strong><br>';
    echo '• Texto del botón: <strong>' . ($final_options['default_button_text'] ?? 'Ver en Amazon') . '</strong><br>';
    echo '• Cache habilitado: <strong>' . ($final_options['cache_duration'] ?? 24) . ' horas</strong><br>';
    echo '• Tracking de clicks: <strong>' . ($final_options['track_clicks'] ? 'Habilitado' : 'Deshabilitado') . '</strong><br>';
    echo '• Abrir en nueva pestaña: <strong>' . ($final_options['open_in_new_tab'] ? 'Habilitado' : 'Deshabilitado') . '</strong></div>';
    $fixes_applied[] = 'Opciones por defecto configuradas';
} else {
    echo '<div class="error">❌ Error configurando opciones por defecto</div>';
    $errors_found[] = 'No se pudieron configurar las opciones por defecto';
}

// Fix 2: Forzar regeneración de assets
echo '<h2>2️⃣ Regenerando Assets del Plugin</h2>';

$plugin_url = plugins_url('', __FILE__);
$plugin_path = plugin_dir_path(__FILE__);

// Verificar y generar versión de cache busting
$asset_version = time(); // Usar timestamp actual para forzar recarga
update_option('cosas_amazon_asset_version', $asset_version);

echo '<div class="success">✅ Versión de assets actualizada: ' . $asset_version . '</div>';
echo '<div class="action">Esto forzará a los navegadores a recargar los archivos CSS y JavaScript del plugin.</div>';
$fixes_applied[] = 'Assets regenerados con cache busting';

// Fix 3: Verificar y corregir capabilities del menú
echo '<h2>3️⃣ Verificando Permisos del Menú</h2>';

// Obtener el usuario actual
$current_user = wp_get_current_user();

if ($current_user->ID === 0) {
    echo '<div class="warning">⚠️ No hay usuario logueado. Es necesario estar logueado como administrador.</div>';
    $errors_found[] = 'Usuario no logueado';
} else {
    echo '<div class="info">Usuario actual: <strong>' . $current_user->user_login . '</strong></div>';
    
    if (current_user_can('manage_options')) {
        echo '<div class="success">✅ El usuario tiene permisos de "manage_options"</div>';
        $fixes_applied[] = 'Permisos verificados correctamente';
    } else {
        echo '<div class="error">❌ El usuario NO tiene permisos de "manage_options"</div>';
        echo '<div class="action">Solución: Acceder con una cuenta de Administrador</div>';
        $errors_found[] = 'Usuario sin permisos adecuados';
    }
}

// Fix 4: Limpiar cache si es LiteSpeed
echo '<h2>4️⃣ Detectando y Configurando para LiteSpeed</h2>';

$is_litespeed = strpos(strtolower($_SERVER['SERVER_SOFTWARE'] ?? ''), 'litespeed') !== false;

if ($is_litespeed) {
    echo '<div class="info">🚀 LiteSpeed detectado</div>';
    
    // Configurar headers anti-cache para el plugin en LiteSpeed
    $litespeed_config = [
        'cache_control' => 'no-cache, no-store, must-revalidate',
        'expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        'pragma' => 'no-cache'
    ];
    
    update_option('cosas_amazon_litespeed_config', $litespeed_config);
    
    echo '<div class="success">✅ Configuración anti-cache para LiteSpeed aplicada</div>';
    echo '<div class="action">Se han configurado headers especiales para evitar problemas de cache con LiteSpeed.</div>';
    $fixes_applied[] = 'Configuración LiteSpeed aplicada';
    
    // Crear archivo .htaccess específico para el plugin
    $htaccess_content = '# Cosas de Amazon - Configuración LiteSpeed
<IfModule mod_headers.c>
    <FilesMatch "\.(js|css)$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires "Thu, 01 Jan 1970 00:00:00 GMT"
    </FilesMatch>
</IfModule>

# Asegurar que los archivos del plugin se sirvan correctamente
<Files "*.js">
    ForceType application/javascript
</Files>

<Files "*.css">
    ForceType text/css
</Files>';

    $htaccess_path = $plugin_path . '.htaccess';
    if (file_put_contents($htaccess_path, $htaccess_content)) {
        echo '<div class="success">✅ Archivo .htaccess específico para LiteSpeed creado</div>';
        $fixes_applied[] = 'Archivo .htaccess para LiteSpeed creado';
    } else {
        echo '<div class="warning">⚠️ No se pudo crear el archivo .htaccess (verificar permisos)</div>';
    }
} else {
    echo '<div class="info">🐧 Servidor no-LiteSpeed detectado</div>';
}

// Fix 5: Forzar re-activación del plugin
echo '<h2>5️⃣ Re-activando Plugin y Verificando Menús</h2>';

$plugin_file = 'cosas-de-amazon/cosas-de-amazon.php';

if (is_plugin_active($plugin_file)) {
    echo '<div class="info">Plugin actualmente activo</div>';
    
    // Forzar creación de instancia admin si no existe
    if (class_exists('CosasAmazonAdmin')) {
        echo '<div class="info">Clase CosasAmazonAdmin encontrada</div>';
        
        // Crear instancia si no existe en global
        if (!isset($GLOBALS['cosas_amazon_admin_instance'])) {
            $GLOBALS['cosas_amazon_admin_instance'] = new CosasAmazonAdmin();
            echo '<div class="success">✅ Instancia admin creada</div>';
            
            // Forzar registro de menús
            $GLOBALS['cosas_amazon_admin_instance']->add_admin_menu();
            echo '<div class="success">✅ Menús registrados manualmente</div>';
            $fixes_applied[] = 'Menús registrados manualmente';
        } else {
            echo '<div class="success">✅ Instancia admin ya existe</div>';
        }
    } else {
        echo '<div class="error">❌ Clase CosasAmazonAdmin no encontrada</div>';
        $errors_found[] = 'Clase admin no disponible';
    }
    
    // Forzar hooks de activación
    do_action('activate_plugin', $plugin_file);
    do_action('activate_' . $plugin_file);
    
    echo '<div class="success">✅ Hooks de activación ejecutados</div>';
    $fixes_applied[] = 'Hooks de activación ejecutados';
} else {
    echo '<div class="error">❌ El plugin no está activo</div>';
    $errors_found[] = 'Plugin no activo';
}

// Fix 6: Verificar menús específicamente
echo '<h2>6️⃣ Verificación de Menús</h2>';

global $menu, $submenu;

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

echo '<div class="action">';
echo '<strong>Estado de los Menús:</strong><br>';
echo '• Menú Principal: ' . ($main_menu_exists ? '✅ Registrado' : '❌ No registrado') . '<br>';
echo '• Menú Configuración: ' . ($settings_menu_exists ? '✅ Registrado' : '❌ No registrado') . '<br>';

if ($main_menu_exists) {
    echo '• <a href="' . admin_url('admin.php?page=cosas-amazon-main') . '" target="_blank">🔗 Probar menú principal</a><br>';
}
if ($settings_menu_exists) {
    echo '• <a href="' . admin_url('options-general.php?page=cosas-amazon-settings') . '" target="_blank">🔗 Probar menú configuración</a><br>';
}
echo '</div>';

if ($main_menu_exists && $settings_menu_exists) {
    echo '<div class="success">✅ Todos los menús están registrados correctamente</div>';
    $fixes_applied[] = 'Menús verificados correctamente';
} else {
    echo '<div class="warning">⚠️ Algunos menús pueden no estar registrados correctamente</div>';
}

// Fix 7: Crear configuración de emergencia
echo '<h2>7️⃣ Configuración de Emergencia</h2>';

$emergency_config = [
    'last_fix_applied' => current_time('mysql'),
    'server_type' => $is_litespeed ? 'litespeed' : 'other',
    'asset_version' => $asset_version,
    'force_button_display' => true,
    'debug_mode' => true,
];

update_option('cosas_amazon_emergency_config', $emergency_config);

echo '<div class="success">✅ Configuración de emergencia guardada</div>';
echo '<div class="action">Se ha activado el modo debug y forzado la visualización de botones.</div>';
$fixes_applied[] = 'Configuración de emergencia aplicada';

// Resumen final
echo '<h2>📋 Resumen de Correcciones</h2>';

if (!empty($fixes_applied)) {
    echo '<div class="success">';
    echo '<strong>✅ Correcciones aplicadas (' . count($fixes_applied) . '):</strong><br>';
    foreach ($fixes_applied as $fix) {
        echo '• ' . $fix . '<br>';
    }
    echo '</div>';
}

if (!empty($errors_found)) {
    echo '<div class="error">';
    echo '<strong>❌ Problemas pendientes (' . count($errors_found) . '):</strong><br>';
    foreach ($errors_found as $error) {
        echo '• ' . $error . '<br>';
    }
    echo '</div>';
}

// Instrucciones finales
echo '<h2>🚀 Siguientes Pasos</h2>';

echo '<div class="info">';
echo '<strong>1. INMEDIATO:</strong><br>';
echo '• Ir a <a href="/wp-admin/admin.php?page=cosas-amazon" target="_blank">WordPress Admin → Cosas de Amazon</a><br>';
echo '• Verificar que las opciones estén configuradas<br>';
echo '• Probar insertar un producto en una página/post<br><br>';

echo '<strong>2. SI LOS BOTONES SIGUEN SIN APARECER:</strong><br>';
echo '• Limpiar cache de LiteSpeed (si aplicable)<br>';
echo '• Hacer refresh forzado (Ctrl+F5) en la página con productos<br>';
echo '• Verificar consola del navegador (F12) por errores<br><br>';

echo '<strong>3. VERIFICACIÓN FINAL:</strong><br>';
echo '• Comprobar que los archivos CSS/JS se cargan desde: <br>';
echo '&nbsp;&nbsp;- /wp-content/plugins/cosas-de-amazon/assets/css/style.css<br>';
echo '&nbsp;&nbsp;- /wp-content/plugins/cosas-de-amazon/assets/js/frontend.js<br>';
echo '• Verificar que el texto "Ver en Amazon" aparece en los botones<br>';
echo '</div>';

echo '<div class="action">';
echo '<strong>💡 NOTA IMPORTANTE:</strong><br>';
echo 'Si después de estos pasos sigues teniendo problemas, ejecuta primero el diagnóstico completo:<br>';
echo '<a href="diagnostico-produccion.php?token=cosas_amazon_prod_diag_2025" target="_blank">🔬 Ejecutar Diagnóstico Completo</a>';
echo '</div>';

echo '</div>'; // Cerrar container
echo '</body></html>';
?>
