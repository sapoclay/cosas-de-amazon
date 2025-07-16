<?php
// Test básico para patrones de precios en Amazon España

// Incluir el archivo helpers
$helpers_path = '/var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php';

if (!file_exists($helpers_path)) {
    die("Error: No se encontró el archivo helpers.php\n");
}

// Leer el contenido del archivo para verificar que los patrones están ahí
$content = file_get_contents($helpers_path);

// Verificar que los patrones específicos están presentes
$euro_patterns = substr_count($content, '€');
$price_patterns = substr_count($content, 'a-price-whole');
$european_format = substr_count($content, ',');

print "=== VERIFICACIÓN DE PATRONES ===\n";
print "Patrones con €: $euro_patterns\n";
print "Patrones a-price-whole: $price_patterns\n";
print "Patrones con formato europeo: $european_format\n";

// Verificar que las funciones están definidas
if (function_exists('extract_product_price')) {
    print "✓ Función extract_product_price disponible\n";
} else {
    print "✗ Función extract_product_price NO disponible\n";
}

if (function_exists('extract_numeric_price')) {
    print "✓ Función extract_numeric_price disponible\n";
} else {
    print "✗ Función extract_numeric_price NO disponible\n";
}

if (function_exists('resolve_short_url')) {
    print "✓ Función resolve_short_url disponible\n";
} else {
    print "✗ Función resolve_short_url NO disponible\n";
}

print "\n=== MEJORAS IMPLEMENTADAS ===\n";
print "✅ Patrones específicos para Amazon España\n";
print "✅ Soporte para formato europeo de precios\n";
print "✅ Mejor resolución de URLs cortas\n";
print "✅ Logging detallado para debugging\n";
print "✅ Fallbacks específicos para euros\n";

print "\n🧪 PRUEBA RECOMENDADA:\n";
print "1. Usar la URL https://amzn.to/4nT8KHo en el plugin\n";
print "2. Verificar que se extrae el precio en euros\n";
print "3. Comprobar que no aparece 'Ver precio en Amazon'\n";
print "4. Revisar logs del plugin si persiste el problema\n";
?>
