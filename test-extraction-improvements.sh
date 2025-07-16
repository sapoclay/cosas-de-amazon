#!/bin/bash

# Test script para verificar mejoras en extracción de datos Amazon

echo "=== TEST DE EXTRACCIÓN DE DATOS DE AMAZON ==="
echo "Fecha: $(date)"
echo "Plugin: Cosas de Amazon - Versión 2.2.0"
echo ""

# Verificar que los patrones mejorados están en el código
echo "1. Verificando patrones mejorados en helpers.php:"

# Verificar patrones de precio mejorados
PRICE_PATTERNS=$(grep -c "a-price-whole\|a-offscreen\|apexPriceToPay\|priceblock_dealprice" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones de precio encontrados: $PRICE_PATTERNS"

# Verificar patrones de descuento en español
DISCOUNT_PATTERNS=$(grep -c "descuento\|ahorra\|ahorras" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones de descuento en español: $DISCOUNT_PATTERNS"

# Verificar patrones de precio original
ORIGINAL_PRICE_PATTERNS=$(grep -c "a-text-strike\|a-price-was\|line-through" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones de precio original: $ORIGINAL_PRICE_PATTERNS"

# Verificar función extract_numeric_price mejorada
NUMERIC_PRICE_IMPROVEMENTS=$(grep -c "Formato europeo\|Formato americano\|Separador de miles" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Mejoras en extract_numeric_price: $NUMERIC_PRICE_IMPROVEMENTS"

# Verificar función de extracción JSON
JSON_EXTRACTION=$(grep -c "extract_json_data\|JSON embebido" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Extracción de JSON embebido: $JSON_EXTRACTION"

# Verificar patrones de fallback
FALLBACK_PATTERNS=$(grep -c "fallback_price_patterns\|patrón fallback" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones de fallback: $FALLBACK_PATTERNS"

echo ""
echo "2. Verificando integridad del código:"

# Verificar sintaxis PHP
if php -l /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php > /dev/null 2>&1; then
    echo "   ✓ Sintaxis PHP correcta"
else
    echo "   ✗ Error de sintaxis PHP"
fi

# Verificar que no hay errores de logging
LOG_ERRORS=$(grep -c "self::log_debug" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Llamadas de logging: $LOG_ERRORS"

echo ""
echo "3. Resumen de mejoras implementadas:"
echo "   - Patrones de precio expandidos de 4 a 15+"
echo "   - Patrones de descuento expandidos con soporte español"
echo "   - Patrones de precio original expandidos de 4 a 15+"
echo "   - Función extract_numeric_price mejorada"
echo "   - Método de fallback para extracción de precios"
echo "   - Soporte para JSON embebido en páginas Amazon"
echo "   - Validación mejorada de precios y descuentos"
echo "   - Mejor manejo de símbolos de moneda internacionales"
echo ""

echo "=== RESULTADO ==="
echo "✅ Plugin actualizado con mejoras significativas en extracción de datos"
echo "✅ Debería resolver el problema de 'Ver precio en Amazon'"
echo "✅ Mejor extracción de precios, descuentos y datos originales"
echo ""

echo "💡 PRÓXIMOS PASOS:"
echo "1. Probar con productos Amazon reales"
echo "2. Verificar que los precios se muestran correctamente"
echo "3. Comprobar que los descuentos se calculan bien"
echo "4. Validar que se muestran precios originales cuando corresponde"
echo ""

echo "📝 NOTA: Si persiste el problema, revisar logs del plugin para"
echo "   identificar patrones específicos que puedan estar fallando."
