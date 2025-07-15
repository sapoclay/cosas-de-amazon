# Cosas de Amazon v2.1.0 - Plugin WordPress

<img width="1024" height="1024" alt="logo" src="https://github.com/user-attachments/assets/5c4b8ce9-b993-45fa-b259-b1b50986a432" />

Plugin avanzado para mostrar productos de Amazon en WordPress usando bloques de Gutenberg. Versión mejorada con extracción avanzada de imágenes, CSS forzado y compatibilidad total.

## ✨ Características Principales

### 🎨 **Frontend Display**
- ✅ **CSS Forzado Inline** - Garantiza visibilidad en todos los temas
- ✅ **Responsive Design** - Compatible con dispositivos móviles
- ✅ **Múltiples Estilos** - Horizontal, vertical, grid, tabla

### 🖼️ **Extracción de Imágenes Avanzada**
- ✅ **18 Patrones de Extracción** - Soporte completo para a-dynamic-image
- ✅ **Procesamiento JSON** - Decodificación automática de data-a-dynamic-image
- ✅ **Filtrado Inteligente** - Evita logos de Prime y elementos no deseados
- ✅ **Imágenes en Alta Resolución** - Extrae la mejor calidad disponible

### 💰 **Detección de Precios y Descuentos**
- ✅ **Clases Amazon Específicas** - savingPriceOverride, savingsPercentage
- ✅ **Precios Originales** - Detección de a-price a-text-price con a-offscreen
- ✅ **Descuentos Automáticos** - Cálculo y visualización de porcentajes
- ✅ **Monitoreo de Precios** - Sistema de caché con actualización diaria

### 🔧 **Sistema Robusto**
- ✅ **Triple Fallback** - PA-API → Web Scraping → Placeholder
- ✅ **Manejo de Errores** - Recuperación automática ante fallos
- ✅ **Caché Inteligente** - Optimización de rendimiento
- ✅ **REST API** - Endpoints para integración externa

## 🚀 Requisitos

- WordPress 5.0 o superior
- PHP 7.4 o superior
- Extensión cURL de PHP
- Gutenberg (editor de bloques)

## Instalación

1. Descargar el plugin
2. Subir la carpeta `cosas-de-amazon` a `/wp-content/plugins/`
3. Activar el plugin desde el panel de WordPress
4. Ir a **Ajustes → Cosas de Amazon** para configurar

## Configuración

### Configuración básica

1. **Ajustes → Cosas de Amazon**
   - Configurar estilo por defecto
   - Ajustar límite de descripción
   - Configurar duración del caché
   - Seleccionar fuente de datos (real o simulada)

2. **Personalizar → Cosas de Amazon**
   - Personalizar colores y tipografía
   - Configurar espaciado y alineación
   - Activar/desactivar elementos

### Configuración avanzada

#### Amazon PA-API (Opcional)
Para usar la API oficial de Amazon:
1. Obtener credenciales en Amazon Associates
2. Configurar Access Key, Secret Key y Associate Tag
3. Activar la API en la configuración del plugin

#### Optimización
- **Caché**: Configurar entre 5 minutos y 24 horas
- **Timeout**: Ajustar tiempo de espera para scraping
- **Actualizaciones automáticas**: Configurar frecuencia de actualización

## Uso

### Uso básico

1. **Editar una entrada o página**
2. **Añadir bloque** → Buscar "Cosas de Amazon"
3. **Introducir URL** del producto de Amazon
4. **Seleccionar estilo** de visualización
5. **Personalizar opciones** (precio, descuento, descripción)
6. **Publicar**

### Estilos disponibles

- **Horizontal**: Imagen a la izquierda, contenido a la derecha
- **Vertical**: Imagen arriba, contenido abajo
- **Compacto**: Versión reducida para espacios pequeños
- **Destacado**: Estilo llamativo para productos principales
- **Mínimo**: Solo información esencial
- **Carrusel**: Múltiples productos en carrusel
- **Tabla**: Comparación de productos en tabla

### Múltiples productos

Para mostrar varios productos:
1. Activar **"Modo múltiples productos"** en el bloque
2. Introducir URLs adicionales (una por línea)
3. Seleccionar productos por fila
4. Personalizar visualización

## Opciones del bloque

### Visualización
- **Mostrar precio**: Activar/desactivar precio
- **Mostrar descuento**: Activar/desactivar etiqueta de descuento
- **Mostrar descripción**: Activar/desactivar descripción
- **Mostrar botón**: Activar/desactivar botón "Ver en Amazon"
- **Mostrar valoraciones**: Activar/desactivar estrellas y reseñas

### Personalización
- **Colores**: Primario, secundario, texto, botón
- **Alineación**: Izquierda, centro, derecha
- **Tamaño de fuente**: Pequeño, mediano, grande
- **Borde**: Estilo y color del borde

## Solución de problemas

### Problema: Aparecen datos de muestra
**Causa**: Amazon está bloqueando el scraping desde tu servidor.

**Soluciones**:
1. Cambiar a "Datos simulados" temporalmente
2. Verificar que el hosting permite peticiones externas
3. Contactar con el proveedor de hosting
4. Configurar Amazon PA-API como alternativa

### Problema: Las imágenes no se muestran
**Soluciones**:
1. Verificar conectividad con Amazon
2. Limpiar caché del plugin
3. Verificar que no hay conflictos CSS

### Problema: Los precios no se actualizan
**Soluciones**:
1. Limpiar caché manualmente
2. Verificar configuración de actualizaciones automáticas
3. Aumentar timeout de scraping

### Depuración
Para habilitar logs detallados, añadir en `wp-config.php`:
```php
define('COSAS_AMAZON_DEBUG', true);
```

## Configuración recomendada

### Para sitios con poco tráfico
- Caché: 2-6 horas
- Timeout: 15 segundos
- Actualizaciones: Diarias

### Para sitios con mucho tráfico
- Caché: 6-24 horas
- Timeout: 10 segundos
- Actualizaciones: Dos veces al día
- Usar Amazon PA-API si es posible

## Compatibilidad

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **Temas**: Compatible con cualquier tema
- **Plugins**: Compatible con plugins de caché principales

## Soporte

Para soporte técnico:
- Website: [entreunosyceros.net](https://entreunosyceros.net)
- Email: admin@entreunosyceros.net

## Licencia

MIT License


