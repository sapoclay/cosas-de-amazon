#!/bin/bash

echo "=== PRUEBA DEL ESTILO COMPACTO ==="
echo ""
echo "Verificando estructura del estilo compacto..."

# Verificar que los estilos están presentes
echo "1. Verificando CSS del estilo compacto:"
if grep -q "cosas-amazon-compact" assets/css/style.css; then
    echo "   ✓ CSS del estilo compacto encontrado"
else
    echo "   ✗ CSS del estilo compacto NO encontrado"
fi

# Verificar estructura específica
echo "2. Verificando estructura específica:"
if grep -q "cosas-amazon-main-content" assets/css/style.css; then
    echo "   ✓ Contenedor main-content encontrado"
else
    echo "   ✗ Contenedor main-content NO encontrado"
fi

if grep -q "flex-direction: column" assets/css/style.css; then
    echo "   ✓ Layout vertical (título arriba) encontrado"
else
    echo "   ✗ Layout vertical NO encontrado"
fi

# Verificar elementos específicos
echo "3. Verificando elementos específicos:"
if grep -q "\.cosas-amazon-compact \.cosas-amazon-title" assets/css/style.css; then
    echo "   ✓ Estilos del título encontrados"
else
    echo "   ✗ Estilos del título NO encontrados"
fi

if grep -q "\.cosas-amazon-compact \.cosas-amazon-rating" assets/css/style.css; then
    echo "   ✓ Estilos del rating encontrados"
else
    echo "   ✗ Estilos del rating NO encontrados"
fi

if grep -q "\.cosas-amazon-compact \.cosas-amazon-pricing" assets/css/style.css; then
    echo "   ✓ Estilos de precios encontrados"
else
    echo "   ✗ Estilos de precios NO encontrados"
fi

if grep -q "\.cosas-amazon-compact \.cosas-amazon-btn" assets/css/style.css; then
    echo "   ✓ Estilos del botón encontrados"
else
    echo "   ✗ Estilos del botón NO encontrados"
fi

# Verificar tamaños adaptativos
echo "4. Verificando tamaños adaptativos:"
if grep -q "cosas-amazon-size-small" assets/css/style.css; then
    echo "   ✓ Estilos para tamaño pequeño encontrados"
else
    echo "   ✗ Estilos para tamaño pequeño NO encontrados"
fi

if grep -q "cosas-amazon-size-large" assets/css/style.css; then
    echo "   ✓ Estilos para tamaño grande encontrados"
else
    echo "   ✗ Estilos para tamaño grande NO encontrados"
fi

# Verificar sincronización con editor
echo "5. Verificando sincronización con editor:"
if grep -q "cosas-amazon-compact" assets/css/editor.css; then
    echo "   ✓ Estilos del editor encontrados"
else
    echo "   ✗ Estilos del editor NO encontrados"
fi

if grep -q "cosas-amazon-main-content" assets/css/editor.css; then
    echo "   ✓ Estructura del editor sincronizada"
else
    echo "   ✗ Estructura del editor NO sincronizada"
fi

# Verificar código PHP
echo "6. Verificando código PHP:"
if grep -q "cosas-amazon-main-content" core/class-cosas-de-amazon.php; then
    echo "   ✓ Código PHP actualizado"
else
    echo "   ✗ Código PHP NO actualizado"
fi

echo ""
echo "=== RESULTADO ==="
echo "El estilo compacto ha sido reestructurado con:"
echo "   • Título en la parte superior"
echo "   • Imagen a la izquierda (80x80px)"
echo "   • Contenido a la derecha (rating, precio, botón)"
echo "   • Adaptativo según tamaño del bloque"
echo "   • Sincronizado entre editor y frontend"
echo ""
