<?php
/**
 * CONFIGURACIÓN RÁPIDA DE EMERGENCIA
 * Ejecutar este script SOLO si los botones no aparecen en producción
 */

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
} catch (Exception $e) {
    die('❌ Error cargando WordPress: ' . $e->getMessage());
}

echo "🚀 CONFIGURACIÓN RÁPIDA - Cosas de Amazon\n\n";

// 1. Configurar opciones por defecto
echo "1️⃣ Configurando opciones por defecto...\n";

$options = get_option('cosas_amazon_options', []);
$options['show_button_by_default'] = true;
$options['default_button_text'] = 'Ver en Amazon';
$options['enable_cache'] = true;
$options['button_style'] = 'modern';
$options['open_in_new_tab'] = true;

$result1 = update_option('cosas_amazon_options', $options);
$verify1 = get_option('cosas_amazon_options', []);
$config1_ok = !empty($verify1) && isset($verify1['show_button_by_default']);
echo ($result1 || $config1_ok) ? "✅ Opciones configuradas\n" : "❌ Error configurando opciones\n";

// 2. Configurar modo producción
echo "\n2️⃣ Activando modo producción...\n";

$prod_config = [
    'force_button_display' => true,
    'server_type' => stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'litespeed') !== false ? 'litespeed' : 'other',
    'debug_mode' => true,
    'activation_date' => current_time('mysql')
];

$result2 = update_option('cosas_amazon_production_config', $prod_config);
$verify2 = get_option('cosas_amazon_production_config', []);
$config2_ok = !empty($verify2) && isset($verify2['force_button_display']);
echo ($result2 || $config2_ok) ? "✅ Modo producción activado\n" : "❌ Error activando modo producción\n";

// 3. Regenerar versión de assets
echo "\n3️⃣ Regenerando assets...\n";

$asset_version = time();
$result3 = update_option('cosas_amazon_asset_version', $asset_version);
$verify3 = get_option('cosas_amazon_asset_version');
$config3_ok = !empty($verify3);
echo ($result3 || $config3_ok) ? "✅ Assets regenerados (v$asset_version)\n" : "❌ Error regenerando assets\n";

// 4. Configuración de emergencia
echo "\n4️⃣ Activando configuración de emergencia...\n";

$emergency = [
    'last_fix_applied' => current_time('mysql'),
    'force_button_display' => true,
    'debug_mode' => true,
    'manual_fix' => true
];

$result4 = update_option('cosas_amazon_emergency_config', $emergency);
$verify4 = get_option('cosas_amazon_emergency_config', []);
$config4_ok = !empty($verify4) && isset($verify4['force_button_display']);
echo ($result4 || $config4_ok) ? "✅ Configuración de emergencia activada\n" : "❌ Error en configuración de emergencia\n";

// Resumen
echo "\n📋 RESUMEN:\n";
echo "Opciones por defecto: " . (($result1 || $config1_ok) ? "✅" : "❌") . "\n";
echo "Modo producción: " . (($result2 || $config2_ok) ? "✅" : "❌") . "\n";
echo "Assets regenerados: " . (($result3 || $config3_ok) ? "✅" : "❌") . "\n";
echo "Emergencia activada: " . (($result4 || $config4_ok) ? "✅" : "❌") . "\n";

echo "\n🚀 SIGUIENTES PASOS:\n";
echo "1. Limpiar cache de LiteSpeed\n";
echo "2. Hacer refresh (Ctrl+F5) en las páginas\n";
echo "3. Verificar que aparecen los botones 'Ver en Amazon'\n";
echo "4. Acceder a WordPress Admin → Cosas de Amazon\n\n";

echo "✅ Configuración rápida completada!\n";
?>
