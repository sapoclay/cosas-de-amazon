<?php
/**
 * Script de prueba para PA-API con debugging
 */

// Cargar WordPress
try {
    $wp_config_paths = [
        '../../../wp-config.php',
        '../../../../wp-config.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php'
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

echo "🧪 Test de PA-API con Fallback Local\n";
echo "=====================================\n\n";

// Cargar clase PA-API
require_once('includes/class-amazon-paapi.php');

$api = new CosasAmazonPAAPI();

echo "1️⃣ Verificando entorno...\n";
$local_env = $api->detectLocalEnvironment();
echo "- Es local: " . ($local_env['is_local'] ? 'SÍ' : 'NO') . "\n";
echo "- Host: " . $local_env['host'] . "\n";
echo "- Indicador: " . $local_env['indicator'] . "\n\n";

echo "2️⃣ Verificando credenciales...\n";
$options = get_option('cosas_amazon_api_options', array());
echo "- Access Key: " . (!empty($options['amazon_access_key']) ? 'Configurada (' . strlen($options['amazon_access_key']) . ' chars)' : 'NO configurada') . "\n";
echo "- Secret Key: " . (!empty($options['amazon_secret_key']) ? 'Configurada (' . strlen($options['amazon_secret_key']) . ' chars)' : 'NO configurada') . "\n";
echo "- Associate Tag: " . (!empty($options['amazon_associate_tag']) ? $options['amazon_associate_tag'] : 'NO configurado') . "\n\n";

echo "3️⃣ Ejecutando test con fallback local...\n";
$result = $api->testConnectionWithLocalFallback();

echo "Resultado:\n";
echo "- Éxito: " . ($result['success'] ? 'SÍ' : 'NO') . "\n";
echo "- Mensaje: " . $result['message'] . "\n";
echo "- Paso: " . ($result['step'] ?? 'N/A') . "\n";

if (isset($result['data'])) {
    echo "- Datos obtenidos: SÍ\n";
    echo "- Título: " . ($result['data']['title'] ?? 'N/A') . "\n";
    echo "- ASIN: " . ($result['data']['asin'] ?? 'N/A') . "\n";
    echo "- Modo test: " . (isset($result['data']['_test_mode']) ? 'SÍ' : 'NO') . "\n";
}

if (isset($result['environment'])) {
    echo "\nInformación de entorno:\n";
    foreach ($result['environment'] as $key => $value) {
        echo "- " . $key . ": " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
    }
}

if (isset($result['details'])) {
    echo "\nDetalles técnicos:\n";
    print_r($result['details']);
}

echo "\n✅ Test completado\n";
?>
