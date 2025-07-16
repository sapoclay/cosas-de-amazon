<?php
// Test directo de extracción de precios con debugging

// Simular entorno WordPress básico
if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/wordpress/');
}

// Simular funciones WordPress básicas
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $timeout) {
        return true;
    }
}

if (!function_exists('html_entity_decode')) {
    // Ya existe en PHP
}

if (!function_exists('mb_convert_encoding')) {
    // Ya existe en PHP
}

// Definir constante de debug
define('COSAS_AMAZON_DEBUG', true);

// Incluir helpers
require_once '/var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php';

echo "=== TEST DIRECTO DE EXTRACCIÓN DE PRECIOS ===\n";
echo "URL: https://amzn.to/4nT8KHo\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Test de get_product_data
echo "1. Testing get_product_data...\n";
$product_data = CosasAmazonHelpers::get_product_data('https://amzn.to/4nT8KHo');

if ($product_data) {
    echo "   ✅ get_product_data devolvió datos:\n";
    echo "   📄 Título: " . $product_data['title'] . "\n";
    echo "   💰 Precio: " . $product_data['price'] . "\n";
    echo "   💸 Precio original: " . $product_data['originalPrice'] . "\n";
    echo "   🔥 Descuento: " . $product_data['discount'] . "\n";
    echo "   🖼️ Imagen: " . (empty($product_data['image']) ? 'No' : 'Sí') . "\n";
} else {
    echo "   ❌ get_product_data devolvió false\n";
}

echo "\n";

// 2. Test de extracción directa de ASIN
echo "2. Testing extract_asin_from_url...\n";
$asin = CosasAmazonHelpers::extract_asin_from_url('https://amzn.to/4nT8KHo');
echo "   ASIN: " . ($asin ? $asin : 'No encontrado') . "\n";

// 3. Test de resolución de URL
echo "3. Testing resolve_short_url...\n";
$resolved = CosasAmazonHelpers::resolve_short_url('https://amzn.to/4nT8KHo');
echo "   URL resuelta: " . ($resolved ? $resolved : 'No resuelta') . "\n";

if ($resolved) {
    $asin_resolved = CosasAmazonHelpers::extract_asin_from_url($resolved);
    echo "   ASIN de URL resuelta: " . ($asin_resolved ? $asin_resolved : 'No encontrado') . "\n";
}

echo "\n";

// 4. Test de scraping directo
echo "4. Testing scrape_amazon_product...\n";
if ($resolved && $asin_resolved) {
    $scraped_data = CosasAmazonHelpers::scrape_amazon_product($resolved, $asin_resolved);
    
    if ($scraped_data) {
        echo "   ✅ scrape_amazon_product devolvió datos:\n";
        echo "   📄 Título: " . $scraped_data['title'] . "\n";
        echo "   💰 Precio: " . $scraped_data['price'] . "\n";
        echo "   💸 Precio original: " . $scraped_data['originalPrice'] . "\n";
        echo "   🔥 Descuento: " . $scraped_data['discount'] . "\n";
    } else {
        echo "   ❌ scrape_amazon_product devolvió false\n";
    }
} else {
    echo "   ⚠️  No se puede hacer scraping sin URL resuelta y ASIN\n";
}

echo "\n";

// 5. Test de función extract_numeric_price
echo "5. Testing extract_numeric_price...\n";
$test_prices = ['15,99€', '€15,99', '15.99€', '1.234,56€', '€1.234,56'];
foreach ($test_prices as $test_price) {
    $numeric = CosasAmazonHelpers::extract_numeric_price($test_price);
    echo "   '$test_price' → $numeric\n";
}

echo "\n=== RESULTADO DEL TEST ===\n";
if ($product_data && !empty($product_data['title']) && $product_data['price'] !== 'Ver precio en Amazon') {
    echo "✅ ÉXITO: El plugin está extrayendo datos correctamente\n";
} else {
    echo "❌ PROBLEMA: El plugin no está extrayendo precios correctamente\n";
    echo "💡 Revisa los logs de debug para más información\n";
}

echo "\n💡 Para monitorear logs en tiempo real:\n";
echo "tail -f /var/www/html/wordpress/wp-content/debug.log | grep COSAS_AMAZON\n";
?>
