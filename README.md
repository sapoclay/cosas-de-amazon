# 🛒 Cosas de Amazon - Plugin de WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2+-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-2.1.0-orange.svg)](#)

Plugin para WordPress para mostrar productos de Amazon con integración completa con Gutenberg, múltiples estilos de visualización, personalización avanzada y sistema de valoraciones. Diseñado para utilizar con los afiliados de Amazon ya que hay gente que te cobra por una cosa parecida ....

## 🌟 Características principales

### ✨ **Estilos de visualización**
- **7 estilos diferentes**: Horizontal, Vertical, Compacta, Destacada, Mínima, Carrusel y Tabla comparativa
- **Diseño completamente responsive** adaptable a cualquier dispositivo
- **Personalización visual avanzada** con colores, tipografía y espaciado
- **Sistema de alineación inteligente** (izquierda, centro, derecha)

### 🎨 **Personalización**
- **Colores personalizables**: Primario, secundario, acento, texto y fondo
- **Tipografía configurable**: Familia de fuente, tamaños de título, texto y precios
- **Espaciado flexible**: Padding, margin, border-radius y tamaño de imagen
- **Temas predefinidos**: Default, Minimalista, Moderno, Modo Oscuro y Vibrante
- **Etiquetas de oferta especial** totalmente personalizables

### ⭐ **Sistema de valoraciones**
- **Extracción automática de valoraciones** desde Amazon
- **Visualización de estrellas** con rating numérico
- **Contador de reseñas** con formateo inteligente
- **Activación/desactivación configurable** desde el customizer

### ⚡ **Rendimiento**
- **Sistema de cache inteligente** configurable (5 min - 24 horas)
- **Actualización automática de precios** con frecuencias personalizables
- **Optimización de imágenes** con fallback automático
- **Scraping robusto** con headers actualizados y delays aleatorios

### 🔧 **Funcionalidades Avanzadas**
- **Integración total con Gutenberg** (Editor de bloques)
- **Soporte para múltiples productos** en una sola visualización
- **Detección automática de ofertas** con badges dinámicos
- **Herramientas de diagnóstico** integradas
- **Panel de administración completo** con estadísticas
- **Sincronización completa** entre editor y frontend

## 🚀 Instalación

### Instalación manual

1. **Descarga** el plugin desde este repositorio
2. **Sube** la carpeta `cosas-de-amazon` a `/wp-content/plugins/`
3. **Activa** el plugin desde el panel de WordPress
4. **Configura** las opciones en `Ajustes > Cosas de Amazon`

### Instalación vía WordPress admin

1. Ve a `Plugins > Añadir nuevo`
2. Sube el archivo ZIP del plugin
3. Activa el plugin
4. Configura las opciones

## 📖 Guía de uso

### 1. Uso básico con gutenberg

1. **Abre el editor de bloques** de WordPress
2. **Busca "Producto de Amazon"** en el insertor de bloques
3. **Pega la URL del producto** de Amazon España
4. **Selecciona el estilo** que prefieras
5. **Personaliza** colores y opciones si es necesario
6. **Publica** tu contenido

### 2. Configuración avanzada

#### Panel de administración
- Ve a `Ajustes > Cosas de Amazon`
- Configura opciones por defecto
- Personaliza colores y tipografía
- Configura cache y actualizaciones automáticas
- **Nuevo**: Consulta estadísticas de uso

#### Opciones disponibles
```php
// Estilos disponibles
'horizontal', 'vertical', 'compact', 'featured', 'minimal', 'carousel', 'table'

// Opciones de personalización
show_price: true/false
show_discount: true/false
show_description: true/false
show_ratings: true/false        // ⭐ NUEVO
description_length: 50-500 caracteres
alignment: 'left', 'center', 'right'  // ⭐ NUEVO
```

#### Configuración de valoraciones
```php
// Customizer > Cosas de Amazon
ratings_enabled: true/false     // Activar/desactivar valoraciones
rating_style: 'stars'          // Estilo de visualización
```

### 3. Uso con múltiples productos

```html
<!-- Ejemplo de múltiples URLs -->
https://www.amazon.es/dp/PRODUCTO1
https://www.amazon.es/dp/PRODUCTO2
https://www.amazon.es/dp/PRODUCTO3
```

## ⚙️ Configuración

### Opciones generales
- **Estilo por defecto**: Horizontal, Vertical, Compacta, Destacada
- **Límite de descripción**: 50-500 caracteres
- **Duración del cache**: 5 minutos - 24 horas
- **Fuente de datos**: Real o Simulada (para testing)
- **⭐ Valoraciones**: Activar/desactivar sistema de estrellas

### Actualizaciones automáticas
- **Frecuencia**: Diaria, Dos veces al día, Cada hora (testing)
- **Umbral de descuento alto**: 0-100%
- **Timeout de scraping**: 5-30 segundos

### Personalización visual
- **Colores**: Primario, Secundario, Acento, Texto, Fondo
- **Tipografía**: Fuente, Tamaños de título/texto/precios
- **Espaciado**: Padding, Margin, Border-radius, Tamaño de imagen
- **⭐ Alineación**: Izquierda, Centro, Derecha para cada bloque

## 🔧 Herramientas de diagnóstico

El plugin incluye herramientas avanzadas de diagnóstico:

- **Test de conectividad** con Amazon
- **Verificación de dependencias** de WordPress
- **Estadísticas de cache** detalladas
- **Debug AJAX** para solución de problemas
- **Validación de URLs** de productos

## 🎨 Temas predefinidos

### Default (Amazon)
- Colores: `#e47911`, `#232f3e`, `#ff9900`
- Estilo clásico de Amazon

### Minimalista
- Colores: `#333333`, `#f8f9fa`, `#6c757d`
- Diseño limpio y moderno

### Modo Oscuro
- Colores: `#1a1a1a`, `#333333`, `#ff6b6b`
- Perfecto para sitios con temas oscuros

### Vibrante
- Colores: `#ff6b6b`, `#4ecdc4`, `#45b7d1`
- Colores llamativos y energéticos

## 🛠️ Desarrollo

### Estructura del proyecto
```
cosas-de-amazon/
├── assets/
│   ├── css/
│   │   ├── editor.css     # Estilos del editor de bloques
│   │   └── style.css      # Estilos frontend
│   ├── js/
│   │   ├── admin.js       # JavaScript del panel de administración
│   │   ├── block.js       # JavaScript del bloque de Gutenberg
│   │   ├── carousel.js    # JavaScript del carrusel
│   │   ├── frontend.js    # JavaScript del frontend
│   │   └── tracking.js    # JavaScript de seguimiento
│   └── images/
│       ├── fallback-default.svg     # Imagen fallback por defecto
│       ├── fallback-electronics.svg # Imagen fallback electrónicos
│       ├── fallback-home.svg        # Imagen fallback hogar
│       ├── icon.svg                 # Icono del plugin
│       └── logo.png                 # Logo del plugin
├── core/
│   ├── class-cosas-de-amazon.php    # Clase principal del plugin
│   └── rest-endpoints.php           # Endpoints de la API REST
├── includes/
│   ├── admin.php          # Panel de administración
│   ├── comparator.php     # Comparador de productos
│   ├── custom-css.php     # CSS personalizado
│   ├── customizer.php     # Configuración del customizer
│   ├── helpers.php        # Funciones auxiliares
│   ├── install.php        # Instalación y activación
│   ├── price-alerts.php   # Alertas de precios
│   ├── security.php       # Funciones de seguridad
│   └── stats.php          # Estadísticas y métricas
├── languages/
│   └── cosas-de-amazon-es_ES.po     # Traducciones en español
├── cosas-de-amazon.php    # Archivo principal del plugin
└── README.md              # Este archivo
```

### Hooks y filtros

#### Filtros disponibles
```php
// Modificar datos del producto antes de mostrar
add_filter('cosas_amazon_product_data', 'mi_funcion', 10, 2);

// Personalizar HTML del producto
add_filter('cosas_amazon_product_html', 'mi_funcion', 10, 3);

// Modificar opciones por defecto
add_filter('cosas_amazon_default_options', 'mi_funcion');
```

#### Actions disponibles
```php
// Después de obtener datos del producto
add_action('cosas_amazon_after_product_fetch', 'mi_funcion', 10, 2);

// Antes de mostrar el producto
add_action('cosas_amazon_before_product_display', 'mi_funcion');
```

### Extensibilidad

El plugin está diseñado para ser extensible:

```php
// Agregar nuevo estilo de visualización
function mi_estilo_personalizado($product_data, $params) {
    // Tu código personalizado
    return $html;
}
add_filter('cosas_amazon_custom_styles', function($styles) {
    $styles['mi_estilo'] = 'mi_estilo_personalizado';
    return $styles;
});
```

## 🌍 Compatibilidad

### WordPress
- **Versión mínima**: WordPress 5.0+
- **Versión recomendada**: WordPress 6.0+
- **Compatible con**: Gutenberg, Classic Editor

### PHP
- **Versión mínima**: PHP 7.4+
- **Versión recomendada**: PHP 8.1+
- **Extensiones requeridas**: cURL, JSON

### Temas
- Compatible con cualquier tema de WordPress
- Responsive design adaptable
- Soporte para temas oscuros

## 🐛 Solución de Problemas

### Problemas Comunes

#### Los productos no se muestran
1. Verifica que la URL de Amazon sea válida
2. Comprueba la configuración de cache
3. Usa las herramientas de diagnóstico
4. **⭐ Nuevo**: Verifica las estadísticas en el panel de administración

#### Los precios no se actualizan
1. Verifica las actualizaciones automáticas
2. Limpia el cache manualmente
3. Comprueba el timeout de scraping
4. **⭐ Nuevo**: Usa la herramienta de limpieza de caché

#### Las imágenes no aparecen
1. **⭐ Nuevo**: Verifica que el sistema de fallback esté funcionando
2. Comprueba la conectividad con Amazon
3. Usa las herramientas de diagnóstico de imágenes
4. Verifica que no hay conflictos CSS con el tema

#### Las valoraciones no se muestran
1. **⭐ Nuevo**: Verifica que las valoraciones estén activadas en el customizer
2. Comprueba que el producto tenga valoraciones en Amazon
3. Revisa el cache de datos del producto
4. Usa el modo debug para ver los datos extraídos

#### La alineación no funciona
1. **⭐ Nuevo**: Verifica que el tema no sobrescriba los estilos
2. Usa las reglas CSS reforzadas con !important
3. Comprueba la configuración de alineación en el bloque
4. Verifica que el wrapper flexbox esté funcionando

#### Las etiquetas de oferta no aparecen
1. **⭐ Nuevo**: Verifica que las etiquetas estén activadas
2. Comprueba que el producto tenga descuentos
3. Revisa la configuración de colores de las etiquetas
4. Verifica la posición entre imagen y título

### Herramientas de diagnóstico

#### Panel de Estadísticas
- **Ubicación**: `Ajustes > Cosas de Amazon > Estadísticas`
- **Información**: Productos renderizados, estilos más usados, métricas
- **Herramientas**: Limpieza de caché, diagnóstico de conexión

#### Modo Debug Avanzado
```php
// En wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('COSAS_AMAZON_DEBUG', true);
define('COSAS_AMAZON_VERBOSE', true);  // ⭐ Nuevo: Logging detallado
```

#### Verificaciones Automáticas
- **Extracción de datos**: Comprueba si se obtienen título, precio, imagen
- **Sistema de valoraciones**: Verifica extracción de estrellas y reseñas
- **Cache**: Comprueba estado y limpieza automática
- **Compatibilidad**: Verifica requisitos de PHP y WordPress

## 🤝 Contribuir

Las contribuciones son bienvenidas. Para contribuir:

1. **Fork** el repositorio
2. **Crea** una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. **Commit** tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. **Push** a la rama (`git push origin feature/AmazingFeature`)
5. **Abre** un Pull Request

### Guías de contribución

- Sigue los estándares de código de WordPress
- Incluye tests para nuevas funcionalidades
- Documenta los cambios en el README
- Respeta la estructura del proyecto existente

## 📄 Licencia

Este proyecto está licenciado bajo la GPL v2+ - ver el archivo [LICENSE](LICENSE) para más detalles.

## 👨‍💻 Autor

**entreunosyceros**
- Website: [entreunosyceros.net](https://entreunosyceros.net)
- Email: admin@entreunosyceros.net

## 🙏 Agradecimientos

- Equipo de WordPress por la excelente plataforma
- Comunidad de desarrolladores de plugins de WordPress
- Usuarios que proporcionan feedback y sugerencias
- **⭐ Contribuyentes que ayudaron con**:
  - Sistema de valoraciones y estrellas
  - Sincronización editor-frontend
  - Mejoras en el scraping de Amazon
  - Optimización del sistema de imágenes
  - Panel de estadísticas y diagnósticos

