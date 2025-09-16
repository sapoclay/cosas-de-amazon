# Cosas de Amazon - Un plugin para WordPress

<img width="1024" height="1024" alt="logo" src="https://github.com/user-attachments/assets/41e86344-90d3-497d-98af-30fa1e165510" />

Plugin para mostrar productos de Amazon con extracción avanzada de datos, múltiples estilos de visualización y sistema de limitaciones progresivas. Optimizado para Amazon España con soporte completo para formato europeo de precios.

## 🆕 Versión 2.12.0 - Correcciones y Limpieza

### ✅ Problemas resueltos
- **💶 Precios en €**: Normalización robusta sin caracteres extraños (¿?) y formato consistente "12,34 €" en servidor y editor.
- **🧱 Tarjeta horizontal**: La descripción ahora aparece debajo del bloque de precio/oferta, como se esperaba.
- **🎠 Carrusel**: Navegación precisa por paso de item; muestra correctamente el 4º producto y añade soporte táctil con “snap”. Paridad total editor ↔ frontend.

### 🚀 Mejoras técnicas
- Normalización/parseo de precios en PHP y JS (eliminación de invisibles, NBSP y entidades; parseo seguro a float; cálculo de descuento por precios).
- Vista previa del carrusel funcional en el editor, reutilizando el mismo DOM/CSS.
- Limpieza de archivos de diagnóstico y pruebas antes de publicar en repositorio.

## 🚀 Características principales

### 📊 Extracción avanzada de datos
- **Patrones específicos** para Amazon España con estructura HTML optimizada
- **Resolución inteligente** de URLs cortas (amzn.to, a.co)
- **Formato europeo** de precios (1.234,56€) con detección automática
- **Extracción de descuentos** con precios originales y porcentajes
- **Imágenes de alta calidad** con fallbacks automáticos
- **Caché inteligente** para optimizar rendimiento

### 🎨 Estilos de Visualización
<img width="574" height="291" alt="horizontal" src="https://github.com/user-attachments/assets/c3782d5d-1d47-4495-9bfe-8ef806d6a825" />

- **Horizontal** - Layout tradicional con imagen izquierda (máx. 2 productos)

  <img width="594" height="284" alt="compacta" src="https://github.com/user-attachments/assets/6754ddb5-fb6e-4759-9071-d3f5614d28b7" />

- **Compacta** - Diseño minimalista con limitaciones progresivas (2-3 productos)

  <img width="768" height="588" alt="vertical" src="https://github.com/user-attachments/assets/8293a60b-5ec0-455d-983f-389769aa23ad" />

- **Vertical** - Vista centrada con limitaciones progresivas (2-3 productos)

  <img width="813" height="484" alt="minima" src="https://github.com/user-attachments/assets/223375cd-27b9-4dfd-b673-cb405704ed37" />

- **Muestra mínima** - Vista reducida con estructura columnar (máx. 3 productos)

  <img width="819" height="504" alt="carousel" src="https://github.com/user-attachments/assets/78c05e66-1796-4f50-82b4-170b79af940c" />

- **Carousel responsive** - Desplazamiento horizontal sin limitaciones

  <img width="806" height="687" alt="destacados" src="https://github.com/user-attachments/assets/f9e2240c-5316-4a86-a959-3851d22662e7" />

- **Destacada** - Formato grande con gradiente

  <img width="776" height="700" alt="tabla" src="https://github.com/user-attachments/assets/b3c524a3-80f7-4aff-ac26-28c9f3737841" />

- **Tabla comparativa** - Comparación lado a lado hasta 6 columnas

### ⚙️ Sistema de limitaciones progresivas
- **Validación dual** - Frontend (editor) y backend (renderizado)
- **Dropdowns dinámicos** - Opciones que se adaptan al estilo y tamaño
- **Grid responsivo** - Layout optimizado para múltiples productos
- **Sincronización editor-frontend** - Vista previa exacta

### 🔧 Integración WordPress
- **Bloque Gutenberg** nativo con editor visual sincronizado
- **Shortcode tradicional** compatible con cualquier tema
- **Responsive design** adaptado a todos los dispositivos
- **Configuración avanzada** desde el panel de administración

### 🛡️ Características técnicas
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
- Soportados: `[amazon_producto]` y alias `[cosas-amazon]`

Ejemplo básico:
```text
[amazon_producto url="https://amzn.to/xxxxxxx" style="horizontal" size="medium"]
```

### Múltiples productos
Puedes pasar varias URLs separadas por comas en el atributo `url`.

```text
[cosas-amazon url="https://amzn.to/xxxxxxx, https://amzn.to/yyyyyyy" style="carousel"]
```

También funciona con el shortcode principal:
```text
[amazon_producto url="https://amzn.to/aaaaaaa, https://amzn.to/bbbbbbb" style="table"]
```

## ⚙️ Configuración

### Opciones generales
- **Duración de caché**: Tiempo de almacenamiento temporal (minutos)
- **Timeout de scraping**: Límite de tiempo para extracción de datos
- **Longitud de descripción**: Caracteres máximos en descripciones
- **Fuente de datos**: Modo real o datos simulados para testing

### Opciones de visualización
- **Mostrar precio**: Activar/desactivar precios
- **Mostrar descuento**: Mostrar información de descuentos
- **Mostrar descripción**: Incluir descripción del producto
- **Mostrar botón**: Botón de enlace a Amazon
- **Texto del botón**: Personalizable (por defecto: "Ver en Amazon")
- **Pequeño**: Para barras laterales o espacios reducidos
- **Mediano**: Tamaño estándar recomendado
- **Grande**: Para contenido principal destacado


## Desarrollo

### Requisitos
- WordPress 5.0 o superior
- PHP 7.4 o superior
- Soporte para Gutenberg

### Hooks y filtros
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
### Configuración avanzada
- **Debugging**: Activar logs detallados para diagnóstico
- **Caché personalizado**: Configurar tiempo de vida del caché
- **Fallbacks de imagen**: Imágenes por defecto por categoría

## 🌐 Compatibilidad

### Sitios Amazon soportados
- **Amazon España** (.es) - Optimizado


### URLs soportadas
- URLs completas de Amazon
- URLs cortas (amzn.to, a.co)
- Enlaces con parámetros de afiliado
- Enlaces directos a productos

### Formatos de precio
- **Formato europeo**: 1.234,56€
- **Formato estadounidense**: $1,234.56
- **Formato británico**: £1,234.56
- **Detección automática** según el dominio

## 🔧 Personalización

### CSS personalizado
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

<img width="338" height="578" alt="diagnositico" src="https://github.com/user-attachments/assets/b133e0bf-d592-4c87-876e-716c7c9a1128" />

Activar el modo debug en la configuración del plugin para obtener información detallada sobre el procesamiento.

## 📄 Licencia

Este plugin está licenciado bajo GPL v2 o posterior.

## 👨‍💻 Desarrollo

Desarrollado por **entreunosyceros.net** con enfoque en:
- Extracción optimizada para Amazon España
- Soporte completo para formato europeo
- Rendimiento y compatibilidad

---

Para más información y soporte técnico, visita [entreunosyceros.net](https://entreunosyceros.net).

*Este plugin no está afiliado con Amazon de ninguna manera. Este plugin solo se ha realizado a modo de prueba sin pretensiones ni garantías de ningún tipo*
