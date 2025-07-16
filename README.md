# Cosas de Amazon - Un plugin para WordPress

Plugin para mostrar productos de Amazon con extracción avanzada de datos y múltiples estilos de visualización. Optimizado para Amazon España con soporte completo para formato europeo de precios.

## 🚀 Características Principales

### 📊 Extracción Avanzada de Datos
- **Patrones específicos** para Amazon España con estructura HTML optimizada
- **Resolución inteligente** de URLs cortas (amzn.to, a.co)
- **Formato europeo** de precios (1.234,56€) con detección automática
- **Extracción de descuentos** con precios originales y porcentajes
- **Imágenes de alta calidad** con fallbacks automáticos
- **Caché inteligente** para optimizar rendimiento

### 🎨 Estilos de Visualización
- **Tarjeta compacta** - Diseño minimalista para barras laterales
- **Tarjeta destacada** - Formato grande para contenido principal
- **Muestra mínima** - Vista reducida con elementos esenciales
- **Carousel responsive** - Desplazamiento horizontal con múltiples productos
- **Tabla comparativa** - Comparación lado a lado con 6 columnas

### 🔧 Integración WordPress
- **Bloque Gutenberg** nativo con editor visual
- **Shortcode tradicional** compatible con cualquier tema
- **Responsive design** adaptado a todos los dispositivos
- **Configuración avanzada** desde el panel de administración

### 🛡️ Características Técnicas
- **Logging detallado** para diagnóstico y debugging
- **Manejo de errores** robusto con fallbacks
- **Optimización de rendimiento** con sistema de caché
- **Compatibilidad** con múltiples versiones de WordPress

## 📦 Instalación

1. Descarga el plugin y súbelo a `/wp-content/plugins/cosas-de-amazon/`
2. Activa el plugin desde el panel de administración
3. Configura las opciones en **Ajustes → Cosas de Amazon**

## 🎯 Uso Básico

### Bloque Gutenberg
1. Añade el bloque **"Cosas de Amazon"** en el editor
2. Introduce la URL del producto de Amazon
3. Selecciona el estilo de visualización
4. Configura las opciones de mostrado

### Shortcode
```php
[cosas-amazon url="https://amzn.to/xxxxxxx" style="card"]
```

### Múltiples Productos
```php
[cosas-amazon url="https://amzn.to/xxxxxxx,https://amzn.to/yyyyyyy" style="carousel"]
```

## 🎨 Estilos Disponibles

### `compacto`
Tarjeta pequeña y minimalista, ideal para barras laterales o contenido secundario.

### `destacado`
Tarjeta grande con imagen prominente, perfecta para contenido principal.

### `muestra-minima`
Vista reducida con elementos esenciales, útil para listas de productos.

### `carousel`
Desplazamiento horizontal con múltiples productos, con navegación por flechas.

### `table`
Tabla comparativa con columnas para imagen, título, valoración, precio, descuento y acción.

## ⚙️ Configuración

### Opciones Generales
- **Duración de caché**: Tiempo de almacenamiento temporal (minutos)
- **Timeout de scraping**: Límite de tiempo para extracción de datos
- **Longitud de descripción**: Caracteres máximos en descripciones
- **Fuente de datos**: Modo real o datos simulados para testing

### Opciones de Visualización
- **Mostrar precio**: Activar/desactivar precios
- **Mostrar descuento**: Mostrar información de descuentos
- **Mostrar descripción**: Incluir descripción del producto
- **Mostrar botón**: Botón de enlace a Amazon
- **Texto del botón**: Personalizable (por defecto: "Ver en Amazon")
- **Pequeño**: Para barras laterales o espacios reducidos
- **Mediano**: Tamaño estándar recomendado
- **Grande**: Para contenido principal destacado

## Estructura de Archivos

```
cosas-de-amazon/
├── cosas-de-amazon.php          # Archivo principal del plugin
├── README.md                    # Documentación
├── LICENSE                      # Licencia GPL v2
├── CHANGELOG.md                 # Historial de cambios
├── assets/
│   ├── css/
│   │   ├── style.css           # CSS del frontend
│   │   └── editor.css          # CSS del editor
│   ├── js/
│   │   ├── block.js            # JavaScript del bloque
│   │   ├── frontend.js         # JavaScript del frontend
│   │   └── carousel.js         # JavaScript del carousel
│   └── images/                 # Imágenes del plugin
├── core/
│   ├── class-cosas-de-amazon.php # Clase principal
│   └── rest-endpoints.php      # Endpoints REST
├── includes/
│   ├── helpers.php             # Funciones auxiliares
│   ├── admin.php               # Panel de administración
│   └── [otros archivos]        # Funciones específicas
└── languages/
    └── cosas-de-amazon-es_ES.po # Traducciones
```

## Desarrollo

### Requisitos
- WordPress 5.0 o superior
- PHP 7.4 o superior
- Soporte para Gutenberg

### Hooks y Filtros
El plugin incluye varios hooks para personalización:
- `cosas_amazon_before_render`
- `cosas_amazon_after_render`
- `cosas_amazon_product_data`

### Personalización CSS
Puedes personalizar los estilos añadiendo CSS adicional en tu tema:

```css
.cosas-amazon-carousel {
    /* Personalización del carousel */
}

.cosas-amazon-table {
    /* Personalización de la tabla */
}
```

## Soporte

Para soporte técnico o reportar bugs:
### Configuración Avanzada
- **Debugging**: Activar logs detallados para diagnóstico
- **Caché personalizado**: Configurar tiempo de vida del caché
- **Fallbacks de imagen**: Imágenes por defecto por categoría

## 🌐 Compatibilidad

### Sitios Amazon Soportados
- **Amazon España** (.es) - Optimizado
- **Amazon Francia** (.fr)
- **Amazon Alemania** (.de)
- **Amazon Reino Unido** (.co.uk)
- **Amazon Italia** (.it)
- **Amazon Estados Unidos** (.com)

### URLs Soportadas
- URLs completas de Amazon
- URLs cortas (amzn.to, a.co)
- Enlaces con parámetros de afiliado
- Enlaces directos a productos

### Formatos de Precio
- **Formato europeo**: 1.234,56€
- **Formato estadounidense**: $1,234.56
- **Formato británico**: £1,234.56
- **Detección automática** según el dominio

## 🔧 Personalización

### CSS Personalizado
El plugin incluye estilos propios que se pueden personalizar:

```css
.cosas-amazon-card {
    /* Personalizar tarjetas */
}

.cosas-amazon-carousel {
    /* Personalizar carousel */
}

.cosas-amazon-table {
    /* Personalizar tabla */
}
```

### Hooks Disponibles
```php
// Filtrar datos del producto
add_filter('cosas_amazon_product_data', 'mi_funcion_personalizada');

// Personalizar HTML de salida
add_filter('cosas_amazon_output_html', 'mi_html_personalizado');
```

## 📝 Requisitos

- **WordPress**: 5.0 o superior
- **PHP**: 7.4 o superior
- **cURL**: Activado en el servidor
- **Memoria**: Mínimo 64MB recomendado

## 🆘 Soporte

### Resolución de Problemas
- **Precios no aparecen**: Verificar configuración de caché y debugging
- **Imágenes no cargan**: Comprobar conectividad y fallbacks
- **Errores de formato**: Revisar configuración regional

### Debugging
Activar el modo debug en la configuración del plugin para obtener información detallada sobre el procesamiento.

## 📄 Licencia

Este plugin está licenciado bajo GPL v2 o posterior.

## 👨‍💻 Desarrollo

Desarrollado por **entreunosyceros.net** con enfoque en:
- Extracción optimizada para Amazon España
- Soporte completo para formato europeo
- Rendimiento y compatibilidad

---

Para más información y soporte técnico, visita [entreunosyceros.com](https://entreunosyceros.net).

*Este plugin no está afiliado con Amazon. Este plugin solo se ha realizado a modo de prueba sin pretensiones ni garantías de ningún tipo*
