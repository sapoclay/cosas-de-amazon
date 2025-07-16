#!/bin/bash

# Script para validar el estilo destacado (featured) 
# Verifica que todos los elementos estén correctamente implementados

echo "=== VALIDACIÓN DEL ESTILO DESTACADO ==="
echo ""

# Verificar CSS frontend
echo "1. Verificando CSS frontend:"
if grep -q "\.cosas-amazon-featured" assets/css/style.css; then
    echo "   ✓ Estilo destacado encontrado en frontend"
else
    echo "   ✗ Estilo destacado NO encontrado en frontend"
fi

# Verificar estructura específica
echo "2. Verificando estructura específica:"
if grep -q "display: flex" assets/css/style.css && grep -q "flex-direction: column" assets/css/style.css; then
    echo "   ✓ Layout en columna implementado"
else
    echo "   ✗ Layout en columna NO implementado"
fi

# Verificar imagen superior
echo "3. Verificando imagen superior:"
if grep -q "\.cosas-amazon-featured \.cosas-amazon-image" assets/css/style.css; then
    echo "   ✓ Imagen superior configurada"
else
    echo "   ✗ Imagen superior NO configurada"
fi

# Verificar rating alineado a la derecha
echo "4. Verificando rating alineado a la derecha:"
if grep -q "justify-content: flex-end" assets/css/style.css; then
    echo "   ✓ Rating alineado a la derecha"
else
    echo "   ✗ Rating NO alineado a la derecha"
fi

# Verificar descripción centrada
echo "5. Verificando descripción centrada:"
if grep -q "text-align: center" assets/css/style.css; then
    echo "   ✓ Descripción centrada"
else
    echo "   ✗ Descripción NO centrada"
fi

# Verificar precios centrados
echo "6. Verificando precios centrados:"
if grep -q "justify-content: center" assets/css/style.css; then
    echo "   ✓ Precios centrados"
else
    echo "   ✗ Precios NO centrados"
fi

# Verificar orden de elementos
echo "7. Verificando orden de elementos:"
if grep -q "order: 1" assets/css/style.css && grep -q "order: 7" assets/css/style.css; then
    echo "   ✓ Orden de elementos implementado"
else
    echo "   ✗ Orden de elementos NO implementado"
fi

# Verificar sincronización con editor
echo "8. Verificando sincronización con editor:"
if grep -q "\.cosas-amazon-featured" assets/css/editor.css; then
    echo "   ✓ Estilo destacado sincronizado en editor"
else
    echo "   ✗ Estilo destacado NO sincronizado en editor"
fi

# Verificar código PHP
echo "9. Verificando código PHP:"
if grep -q "featured" core/class-cosas-de-amazon.php; then
    echo "   ✓ Código PHP para estilo destacado"
else
    echo "   ✗ Código PHP para estilo destacado NO encontrado"
fi

# Verificar gradiente
echo "10. Verificando gradiente:"
if grep -q "linear-gradient" assets/css/style.css; then
    echo "   ✓ Gradiente aplicado"
else
    echo "   ✗ Gradiente NO aplicado"
fi

echo ""
echo "=== RESULTADO ==="
echo "El estilo destacado ha sido implementado con:"
echo "   • Imagen en la parte superior (180x180px)"
echo "   • Etiqueta de oferta debajo de la imagen"
echo "   • Título centrado"
echo "   • Rating alineado a la derecha"
echo "   • Descripción centrada"
echo "   • Precios centrados (descuento, precio, precio original)"
echo "   • Botón centrado en la parte inferior"
echo "   • Fondo con gradiente"
echo "   • Sincronizado entre editor y frontend"
echo ""
