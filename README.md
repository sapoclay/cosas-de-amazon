# Cosas de Amazon - WordPress Plugin

Plugin de WordPress para mostrar productos de Amazon usando enlaces cortos con diferentes estilos de tarjetas. Incluye soporte completo para Gutenberg con múltiples estilos de visualización.

## Descripción

**Cosas de Amazon** es un plugin avanzado para WordPress que permite mostrar productos de Amazon de manera elegante y profesional. El plugin ofrece múltiples estilos de visualización, desde tarjetas individuales hasta carousels y tablas comparativas.

## Características Principales

### 🎨 Estilos de Visualización
- **Compacto**: Tarjetas pequeñas y minimalistas
- **Destacado**: Tarjetas grandes con posicionamiento absoluto
- **Muestra mínima**: Diseño reducido con elementos esenciales
- **Carousel**: Desplazamiento horizontal con múltiples productos
- **Tabla comparativa**: Comparación lado a lado de múltiples productos

### 📱 Responsive Design
- Totalmente adaptativo a dispositivos móviles
- Optimizado para tablets y desktop
- Scroll horizontal en carousels y tablas

### 🔧 Integración con Gutenberg
- Bloque nativo de WordPress
- Editor visual en tiempo real
- Sincronización perfecta entre editor y frontend

### 🛒 Características del Producto
- Extracción automática de datos de Amazon
- Soporte para múltiples productos
- Mostrar/ocultar elementos específicos:
  - Precios
  - Descuentos
  - Descripciones
  - Botones de acción
  - Valoraciones y reseñas

## Versión Actual

**Versión 2.2.0** - Incluye soporte completo para carousel con elementos centrados y tabla comparativa implementada

## Instalación

1. Sube el plugin a la carpeta `/wp-content/plugins/cosas-de-amazon`
2. Activa el plugin desde el panel de administración de WordPress
3. Busca el bloque "Cosas de Amazon" en el editor de Gutenberg

## Uso

### Configuración Básica
1. Añade el bloque "Cosas de Amazon" en tu entrada o página
2. Introduce la URL de Amazon del producto
3. Selecciona el estilo de visualización deseado
4. Configura las opciones de visualización

### Múltiples Productos
Para mostrar múltiples productos (carousel o tabla):
1. Selecciona el estilo "Carousel" o "Tabla comparativa"
2. Añade URLs adicionales en el campo "URLs adicionales"
3. Haz clic en "Obtener Múltiples Productos"

### Estilos Disponibles

#### Compacto
- Tarjeta pequeña y minimalista
- Ideal para barras laterales
- Elementos centrados

#### Destacado
- Tarjeta grande con imagen prominente
- Posicionamiento absoluto de elementos
- Ideal para contenido principal

#### Muestra Mínima
- Diseño reducido con elementos esenciales
- Perfecto para listas de productos
- Ordenación específica de elementos

#### Carousel
- Scroll horizontal con múltiples productos
- Elementos centrados en cada tarjeta
- Soporte para productos individuales o múltiples
- Efectos hover y transiciones suaves

#### Tabla Comparativa
- Comparación lado a lado de múltiples productos
- Columnas para imagen, título, valoración, precio, descuento y acción
- Responsive con scroll horizontal en móviles
- Placeholders para productos sin datos

## Configuración Avanzada

### Opciones de Visualización
- **Mostrar precio**: Activa/desactiva la visualización del precio
- **Mostrar descuento**: Muestra información de descuentos
- **Mostrar descripción**: Incluye descripción del producto
- **Mostrar botón**: Botón de enlace a Amazon
- **Texto del botón**: Personalizable (por defecto: "Ver en Amazon")

### Tamaños de Bloque
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
- Repositorio: [GitHub](https://github.com/sapoclay/cosas-de-amazon)
- Web: [entreunosyceros.com](https://entreunosyceros.com)

## Changelog

### 2.2.0 (2025-07-16)
- ✅ Implementada tabla comparativa completa
- ✅ Soporte para múltiples productos en tabla
- ✅ Elementos centrados en carousel
- ✅ Sincronización completa entre editor y frontend
- ✅ Mejoras en responsive design

### 2.1.0 (2025-07-15)
- ✅ Carousel completamente funcional
- ✅ Sincronización de estilos mejorada
- ✅ Correcciones en lógica de renderizado

### 2.0.0 (2025-07-14)
- ✅ Refactorización completa del código
- ✅ Soporte para Gutenberg
- ✅ Múltiples estilos de visualización
- ✅ Diseño responsive

## Licencia

Este plugin está licenciado bajo GPL v2 o posterior.

## Créditos

Desarrollado por [entreunosyceros](https://entreunosyceros.com)

---

*Este plugin no está afiliado con Amazon. Amazon es una marca comercial de Amazon.com, Inc.*
