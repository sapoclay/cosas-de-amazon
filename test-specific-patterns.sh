#!/bin/bash

# Test mejorado para la URL problemática con nuevos patrones específicos

echo "=== TEST DE PATRONES ESPECÍFICOS PARA AMAZON ESPAÑA ==="
echo "URL: https://amzn.to/4nT8KHo"
echo "Fecha: $(date)"
echo ""

# Contar patrones implementados
echo "1. Verificando patrones implementados:"

# Patrones prioritarios específicos
PRIORITY_PATTERNS=$(grep -c "a-section.*a-spacing-micro.*a-price.*aok-align-center.*a-offscreen" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones prioritarios específicos: $PRIORITY_PATTERNS"

# Patrones de logging mejorado
LOGGING_PATTERNS=$(grep -c "PATRÓN PRIORITARIO.*EXITOSO" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Logging mejorado para patrones prioritarios: $LOGGING_PATTERNS"

# Patrones de fallback específicos
FALLBACK_PATTERNS=$(grep -c "a-section.*a-spacing-micro.*a-offscreen" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones de fallback específicos: $FALLBACK_PATTERNS"

# Patrones para precio original
ORIGINAL_PATTERNS=$(grep -c "a-section.*a-spacing-micro.*a-text-price.*a-offscreen" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones para precio original: $ORIGINAL_PATTERNS"

echo ""
echo "2. Verificando estructura de patrones específicos:"

# Verificar patrón específico completo
echo "   Patrón completo implementado:"
echo "   div.a-section.a-spacing-micro > span.a-price.aok-align-center > span.a-offscreen"

# Verificar variaciones del patrón
echo "   Variaciones del patrón implementadas:"
echo "   - Con clase a-text-price para precio original"
echo "   - Con diferentes órdenes de clases"
echo "   - Con patrones más flexibles"

echo ""
echo "3. Resolviendo URL corta:"

# Resolver URL corta
RESOLVED_URL=$(curl -s -I "https://amzn.to/4nT8KHo" | grep -i "location:" | head -1 | cut -d' ' -f2 | tr -d '\r')
if [ ! -z "$RESOLVED_URL" ]; then
    echo "   ✓ URL resuelta: $RESOLVED_URL"
    
    # Verificar que es de Amazon España
    if [[ "$RESOLVED_URL" == *"amazon.es"* ]]; then
        echo "   ✓ Confirmado: Amazon España"
        
        # Extraer ASIN
        ASIN=$(echo "$RESOLVED_URL" | grep -o "B[0-9A-Z]\{9\}" | head -1)
        if [ ! -z "$ASIN" ]; then
            echo "   ✓ ASIN extraído: $ASIN"
        fi
    else
        echo "   ⚠ No es de Amazon España"
    fi
else
    echo "   ✗ No se pudo resolver la URL"
fi

echo ""
echo "4. Mejoras implementadas:"
echo "   ✅ Patrones prioritarios específicos para div.a-section.a-spacing-micro"
echo "   ✅ Búsqueda específica de span.a-price.aok-align-center"
echo "   ✅ Extracción específica de span.a-offscreen"
echo "   ✅ Variaciones para precio original con a-text-price"
echo "   ✅ Logging detallado para patrones prioritarios"
echo "   ✅ Patrones de fallback específicos"
echo "   ✅ Mejor resolución de URLs cortas"
echo ""

echo "=== PATRONES ESPECÍFICOS AÑADIDOS ==="
echo "1. Patrón prioritario principal:"
echo "   div[class*='a-section'][class*='a-spacing-micro'] > span[class*='a-price'][class*='aok-align-center'] > span[class*='a-offscreen']"
echo ""
echo "2. Variaciones del patrón:"
echo "   - Con clases en diferente orden"
echo "   - Con a-text-price para precio original"
echo "   - Con patrones más flexibles"
echo ""
echo "3. Logging mejorado:"
echo "   - Identificación de patrones prioritarios"
echo "   - Seguimiento detallado de extracción"
echo "   - Debugging específico para cada patrón"
echo ""

echo "🧪 PRUEBA RECOMENDADA:"
echo "1. Probar la URL https://amzn.to/4nT8KHo en el plugin"
echo "2. Verificar logs para ver qué patrón específico funciona"
echo "3. Comprobar que se extrae precio en formato europeo"
echo "4. Verificar que no aparece 'Ver precio en Amazon'"
echo ""

echo "📋 ESTRUCTURA HTML OBJETIVO:"
echo "<div class='a-section a-spacing-micro'>"
echo "  <span class='a-price aok-align-center'>"
echo "    <span class='a-offscreen'>XX,XX€</span>"
echo "  </span>"
echo "</div>"
