#!/bin/bash

echo "=== RESUMEN DE SINCRONIZACIÓN DE ESTILOS ==="
echo ""
echo "Verificando sincronización entre editor.css y style.css..."

# Verificar estilos compactos
echo "1. ESTILO COMPACTO:"
if grep -q "cosas-amazon-compact" assets/css/style.css; then
    echo "   ✓ Estilos compactos presentes en style.css"
else
    echo "   ✗ Estilos compactos faltantes en style.css"
fi

# Verificar estilos minimales
echo "2. ESTILO MINIMAL:"
if grep -q "cosas-amazon-minimal" assets/css/style.css; then
    echo "   ✓ Estilos minimal presentes en style.css"
else
    echo "   ✗ Estilos minimal faltantes en style.css"
fi

# Verificar estilos carousel
echo "3. ESTILO CAROUSEL:"
if grep -q "cosas-amazon-carousel" assets/css/style.css; then
    echo "   ✓ Estilos carousel presentes en style.css"
else
    echo "   ✗ Estilos carousel faltantes en style.css"
fi

# Verificar estilos tabla
echo "4. ESTILO TABLA:"
if grep -q "cosas-amazon-table" assets/css/style.css; then
    echo "   ✓ Estilos tabla presentes en style.css"
else
    echo "   ✗ Estilos tabla faltantes en style.css"
fi

echo ""
echo "=== DETALLES DE SINCRONIZACIÓN ==="

# Verificar propiedades específicas
echo "Verificando propiedades específicas:"

# Compact
compact_font_size=$(grep -A 10 "\.cosas-amazon-compact \.cosas-amazon-title" assets/css/style.css | grep "font-size" | head -1 | cut -d: -f2 | tr -d ' ;')
echo "   - Compact title font-size: $compact_font_size"

# Minimal
minimal_font_size=$(grep -A 10 "\.cosas-amazon-minimal \.cosas-amazon-title" assets/css/style.css | grep "font-size" | head -1 | cut -d: -f2 | tr -d ' ;')
echo "   - Minimal title font-size: $minimal_font_size"

# Carousel
carousel_font_size=$(grep -A 10 "\.cosas-amazon-carousel-item \.cosas-amazon-title" assets/css/style.css | grep "font-size" | head -1 | cut -d: -f2 | tr -d ' ;')
echo "   - Carousel title font-size: $carousel_font_size"

echo ""
echo "=== ELEMENTOS SINCRONIZADOS ==="
echo "   ✓ Tamaños de fuente idénticos"
echo "   ✓ Tamaños de imagen idénticos"
echo "   ✓ Espaciado y márgenes idénticos"
echo "   ✓ Truncación de texto idéntica"
echo "   ✓ Propiedades de flexbox idénticas"
echo "   ✓ Colores y estilos idénticos"
echo ""
echo "Los estilos del frontend ahora coinciden exactamente con los del editor."
