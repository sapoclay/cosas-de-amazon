#!/bin/bash

# Diagnóstico directo del problema de extracción de precios

echo "=== DIAGNÓSTICO EXTRACCIÓN DE PRECIOS ==="
echo "URL: https://amzn.to/4nT8KHo"
echo "Fecha: $(date)"
echo ""

# 1. Verificar DEBUG
echo "1. Verificando DEBUG:"
if grep -q "define('COSAS_AMAZON_DEBUG', true)" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/cosas-de-amazon.php; then
    echo "   ✅ DEBUG activado"
else
    echo "   ❌ DEBUG desactivado"
fi

# 2. Verificar patrones
echo ""
echo "2. Verificando patrones implementados:"
OFFSCREEN=$(grep -c "a-offscreen" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones a-offscreen: $OFFSCREEN"

SPACING=$(grep -c "a-spacing-micro" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones a-spacing-micro: $SPACING"

ALIGN=$(grep -c "aok-align-center" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php)
echo "   ✓ Patrones aok-align-center: $ALIGN"

# 3. Verificar función principal
echo ""
echo "3. Verificando función parse_amazon_html:"
if grep -q "parse_amazon_html" /var/www/html/wordpress/wp-content/plugins/cosas-de-amazon/includes/helpers.php; then
    echo "   ✅ Función parse_amazon_html existe"
else
    echo "   ❌ Función parse_amazon_html no encontrada"
fi

# 4. Test de URL
echo ""
echo "4. Test de URL:"
URL_TEST=$(curl -s -I "https://amzn.to/4nT8KHo" | grep -i "location:" | head -1 | cut -d' ' -f2 | tr -d '\r')
if [ ! -z "$URL_TEST" ]; then
    echo "   ✅ URL resuelta: $URL_TEST"
else
    echo "   ❌ URL no resuelta"
fi

# 5. Verificar logs
echo ""
echo "5. Verificando logs:"
if [ -f "/var/www/html/wordpress/wp-content/debug.log" ]; then
    echo "   ✅ debug.log existe"
    echo "   🔍 Últimas entradas COSAS_AMAZON:"
    tail -20 /var/www/html/wordpress/wp-content/debug.log | grep "COSAS_AMAZON" | tail -5 || echo "   ⚠️  No hay logs recientes"
else
    echo "   ⚠️  debug.log no encontrado"
fi

echo ""
echo "=== RECOMENDACIONES ==="
echo "1. Activar DEBUG si no está activo"
echo "2. Probar la URL en el plugin"
echo "3. Monitorear logs en tiempo real:"
echo "   tail -f /var/www/html/wordpress/wp-content/debug.log | grep COSAS_AMAZON"
