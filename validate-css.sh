#!/bin/bash

# Validar CSS básico - contar brackets
file="assets/css/style.css"

echo "Validando CSS..."
open_brackets=$(grep -o '{' "$file" | wc -l)
close_brackets=$(grep -o '}' "$file" | wc -l)

echo "Brackets abiertos: $open_brackets"
echo "Brackets cerrados: $close_brackets"

if [ "$open_brackets" -eq "$close_brackets" ]; then
    echo "✓ Brackets balanceados correctamente"
else
    echo "✗ Error: Brackets no balanceados"
fi

# Verificar que no haya errores obvios
echo "Verificando errores comunes..."
if grep -q ";;;" "$file"; then
    echo "✗ Error: Triple semicolon encontrado"
else
    echo "✓ No hay triple semicolons"
fi

if grep -q "{{" "$file"; then
    echo "✗ Error: Brackets dobles encontrados"
else
    echo "✓ No hay brackets dobles"
fi

echo "Validación completada."
