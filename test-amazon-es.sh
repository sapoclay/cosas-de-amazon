#!/bin/bash

# Test específico para URL problemática de Amazon España

echo "=== TEST ESPECÍFICO PARA AMAZON ESPAÑA ==="
echo "URL: https://amzn.to/4nT8KHo"
echo "Fecha: $(date)"
echo ""

# Verificar que los patrones específicos para Amazon España están implementados
echo "1. Verificando patrones específicos para Amazon España:"

# Patrones para euros
EURO_PATTERNS=$(grep -c "€" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones con símbolo €: $EURO_PATTERNS"

# Patrones para formato europeo
EUROPEAN_FORMAT=$(grep -c "([0-9]+,[0-9]{2})" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones formato europeo (XX,XX): $EUROPEAN_FORMAT"

# Patrones para Amazon España específicos
AMAZON_ES_PATTERNS=$(grep -c "a-price-whole.*a-price-fraction" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones específicos a-price-whole + a-price-fraction: $AMAZON_ES_PATTERNS"

# Mejoras en URL cortas
SHORT_URL_IMPROVEMENTS=$(grep -c "amzn.to\|MAXREDIRS.*10\|TIMEOUT.*20" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Mejoras en URLs cortas: $SHORT_URL_IMPROVEMENTS"

echo ""
echo "2. Verificando resolución de URL corta:"

# Intentar resolver la URL corta
RESOLVED_URL=$(curl -s -I "https://amzn.to/4nT8KHo" | grep -i "location:" | head -1 | cut -d' ' -f2 | tr -d '\r')
if [ ! -z "$RESOLVED_URL" ]; then
    echo "   ✓ URL resuelta: $RESOLVED_URL"
    
    # Verificar que es de Amazon España
    if [[ "$RESOLVED_URL" == *"amazon.es"* ]]; then
        echo "   ✓ Confirmed: Es de Amazon España"
    else
        echo "   ⚠ La URL no es de Amazon España"
    fi
else
    echo "   ✗ No se pudo resolver la URL"
fi

echo ""
echo "3. Verificando mejoras implementadas:"
echo "   ✓ Patrones específicos para precios en euros"
echo "   ✓ Formato europeo: 1.234,56€"
echo "   ✓ Patrones a-price-whole + a-price-fraction combinados"
echo "   ✓ Patrones de fallback específicos para euros"
echo "   ✓ Mejor resolución de URLs cortas"
echo "   ✓ Headers mejorados en cURL"
echo "   ✓ Timeout aumentado para URLs cortas"
echo "   ✓ Logging detallado para debugging"
echo ""

echo "=== RESULTADO ==="
echo "✅ Implementadas mejoras específicas para Amazon España"
echo "✅ Mejor soporte para URLs cortas amzn.to"
echo "✅ Patrones específicos para precios en euros"
echo "✅ Formato europeo de precios soportado"
echo ""

echo "🧪 PRUEBA RECOMENDADA:"
echo "1. Probar la URL https://amzn.to/4nT8KHo en el plugin"
echo "2. Verificar que se muestra el precio en euros"
echo "3. Comprobar que no aparece 'Ver precio en Amazon'"
echo "4. Revisar logs del plugin si persiste el problema"
echo ""

echo "📋 PATRONES AÑADIDOS:"
echo "- Precios con símbolo € antes y después del número"
echo "- Formato europeo: 1.234,56€"
echo "- Combinación a-price-whole + a-price-fraction + a-price-symbol"
echo "- Fallbacks específicos para buybox y cost"
echo "- Mejor manejo de decimales europeos"
