#!/bin/bash

echo "=== VERIFICACIÓN DE AJUSTES ESTILO COMPACTO ==="
echo ""
echo "Verificando ajustes de tamaño de imagen..."

# Verificar tamaño de imagen base
echo "1. Tamaño de imagen base:"
image_size=$(grep -A 3 "\.cosas-amazon-compact \.cosas-amazon-image {" assets/css/style.css | grep "width:" | head -1 | cut -d: -f2 | tr -d ' ;')
echo "   - Ancho de imagen: $image_size"

# Verificar tamaño de imagen para tamaño medio
echo "2. Tamaño de imagen para tamaño medio:"
if grep -q "cosas-amazon-compact.cosas-amazon-size-medium" assets/css/style.css; then
    echo "   ✓ Ajustes específicos para tamaño medio encontrados"
else
    echo "   ✗ Ajustes específicos para tamaño medio NO encontrados"
fi

# Verificar sincronización con editor
echo "3. Sincronización con editor:"
editor_image_size=$(grep -A 3 "\.editor-styles-wrapper \.cosas-amazon-compact \.cosas-amazon-image," assets/css/editor.css | grep "width:" | head -1 | cut -d: -f2 | tr -d ' ;')
echo "   - Tamaño en editor: $editor_image_size"

if [ "$image_size" == "$editor_image_size" ]; then
    echo "   ✓ Tamaños sincronizados correctamente"
else
    echo "   ✗ Tamaños NO sincronizados"
fi

# Verificar responsive
echo "4. Responsive móvil:"
if grep -q "cosas-amazon-main-content" assets/css/style.css; then
    echo "   ✓ Ajustes responsive para mobile encontrados"
else
    echo "   ✗ Ajustes responsive para mobile NO encontrados"
fi

echo ""
echo "=== RESUMEN DE CAMBIOS ==="
echo "• Imagen base: 80x80px → 70x70px"
echo "• Tamaño medio: Específicamente configurado para 70x70px"
echo "• Editor: Sincronizado con frontend"
echo "• Responsive: Ajustado para mobile (90x90px)"
echo ""
echo "Estos cambios evitan el truncamiento de la imagen en el estilo compacto."
echo ""
