/**
 * Bloque de Gutenberg para Cosas de Amazon
 * Version: 2.3.4 - SOPORTE HORIZONTAL+LARGE
 */

(function(blocks, element, components, blockEditor, data, compose, apiFetch) {
    const { registerBlockType } = blocks;
    const { createElement: el, Fragment, useState, useEffect } = element;
    const { 
        TextControl, 
        TextareaControl,
        SelectControl, 
        PanelBody, 
        ToggleControl, 
        Button,
        Spinner,
        Notice,
        ColorPalette,
        FontSizePicker,
        RangeControl
    } = components;
    const { InspectorControls } = blockEditor;
    
    // Icono personalizado de Cosas de Amazon
    const amazonIcon = el('svg', {
        xmlns: 'http://www.w3.org/2000/svg',
        viewBox: '0 0 24 24',
        width: 24,
        height: 24
    },
        el('path', {
            d: 'M19 7h-3.5l-1.5-1.5h-5L7.5 7H4c-1.1 0-2 .9-2 2v9c0 1.1.9 2 2 2h15c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zm-7 9c-2.8 0-5-2.2-5-5s2.2-5 5-5 5 2.2 5 5-2.2 5-5 5z',
            fill: '#ff9900'
        }),
        el('circle', {
            cx: '12',
            cy: '11',
            r: '3',
            fill: 'white'
        }),
        el('text', {
            x: '12',
            y: '15',
            textAnchor: 'middle',
            fontSize: '6',
            fill: '#ff9900',
            fontFamily: 'Arial, sans-serif'
        }, 'A')
    );
    
    // Función para generar estrellas de valoración
    function generateRatingStars(rating, maxRating = 5) {
        if (!rating || isNaN(rating)) return '';
        
        const stars = [];
        const ratingValue = parseFloat(rating);
        
        for (let i = 1; i <= maxRating; i++) {
            if (ratingValue >= i) {
                // Estrella completa
                stars.push(el('span', { 
                    key: i,
                    className: 'cosas-amazon-star full' 
                }, '★'));
            } else if (ratingValue >= i - 0.5) {
                // Media estrella
                stars.push(el('span', { 
                    key: i,
                    className: 'cosas-amazon-star half' 
                }, '☆'));
            } else {
                // Estrella vacía
                stars.push(el('span', { 
                    key: i,
                    className: 'cosas-amazon-star empty' 
                }, '☆'));
            }
        }
        
        return el('div', { className: 'cosas-amazon-stars' }, stars);
    }
    
    // Normalizar string de precio: sustituye espacios especiales por espacios normales
    function normalizePriceStringJS(s) {
        if (!s || typeof s !== 'string') return s;
        try {
            // Decodificar entidades simples usando un textarea temporal
            const ta = document.createElement('textarea');
            ta.innerHTML = s;
            s = ta.value;
        } catch (e) { /* noop */ }
    // Eliminar invisibles (ZWSP, ZWNJ, ZWJ, LRM, RLM, BOM) y replacement/soft hyphen
    s = s.replace(/[\u200B\u200C\u200D\u200E\u200F\uFFFD\uFEFF\u00AD]/g, '');
        // Reemplazar múltiples espacios Unicode por espacio normal
        s = s.replace(/[\u00A0\u202F\u2007\u2008\u2009\u200A\u2002\u2003\u2004\u2005\u2006]/g, ' ');
        // Colapsar espacios
        s = s.replace(/\s+/g, ' ').trim();
        // Reglas específicas por moneda
        // EUR como sufijo => forzar espacio antes
        s = s.replace(/(\d)\s*€/u, '$1 €');
        // EUR como prefijo => sin espacio tras el símbolo al inicio
        s = s.replace(/^€\s+/, '€');
        // USD/GBP/JPY como prefijo => sin espacio tras el símbolo al inicio
        s = s.replace(/^([$£¥])\s+(\d)/u, '$1$2');
        return s;
    }

    // Extrae valor numérico de un string de precio con manejo de separadores
    function extractNumericPriceJS(input) {
        if (input === undefined || input === null) return null;
        let s = String(input);
        s = normalizePriceStringJS(s);
        if (!s) return null;
        let val = s.replace(/[^0-9.,]/g, '');
        if (!val) return null;
        const lastComma = val.lastIndexOf(',');
        const lastDot = val.lastIndexOf('.');
        if (lastComma !== -1 && lastDot !== -1) {
            if (lastComma > lastDot) {
                val = val.replace(/\./g, '');
                val = val.replace(',', '.');
            } else {
                val = val.replace(/,/g, '');
            }
        } else if (lastComma !== -1) {
            if (/,\d{1,2}$/.test(val)) {
                val = val.replace(/\./g, '');
                val = val.replace(',', '.');
            } else {
                val = val.replace(/,/g, '');
            }
        } else if (lastDot !== -1) {
            if (/\.\d{1,2}$/.test(val)) {
                val = val.replace(/,/g, '');
            } else {
                val = val.replace(/[.,]/g, '');
            }
        } else {
            val = val.replace(/[.,]/g, '');
        }
        const num = parseFloat(val);
        return isNaN(num) ? null : num;
    }

    function sanitizePriceFieldsJS(obj) {
        if (!obj || typeof obj !== 'object') return obj;
        const clone = { ...obj };
        if (typeof clone.price === 'string') {
            clone.price = normalizePriceStringJS(clone.price);
            const n = extractNumericPriceJS(clone.price);
            if (n === null || n <= 0) clone.price = '';
        }
        if (typeof clone.originalPrice === 'string') {
            clone.originalPrice = normalizePriceStringJS(clone.originalPrice);
            const n0 = extractNumericPriceJS(clone.originalPrice);
            if (n0 === null || n0 <= 0) clone.originalPrice = '';
        }
        if (typeof clone.original_price === 'string') {
            clone.original_price = normalizePriceStringJS(clone.original_price);
            const n1 = extractNumericPriceJS(clone.original_price);
            if (n1 === null || n1 <= 0) clone.original_price = '';
        }
        return clone;
    }

    // Fallback de descripción basado en ASIN (paridad con PHP)
    function getFallbackDescriptionJS(asin = '') {
        if (!asin) return '';
        const known = {
            'B08N5WRWNW': 'Altavoz inteligente con Alexa. Controla tu hogar inteligente con la voz. Reproduce música, responde preguntas y mucho más.',
            'B0BDHB9Y8Z': 'Echo Dot (5.ª generación). Nuestro altavoz inteligente con Alexa más popular. Sonido más potente, hub de hogar inteligente integrado.',
            'B0DN9JNXJQ': 'iPhone 16. Cámara Fusion de 48 MP con teleobjetivo 2x. Chip A18 con Neural Engine de 16 núcleos.',
            'B08XYZABC1': 'Auriculares inalámbricos con cancelación de ruido. Batería de larga duración, sonido de alta calidad.',
            'B07XYZDEF2': 'Tableta con pantalla de alta resolución. Procesador rápido, ideal para entretenimiento y productividad.',
            'B09XYZGHI3': 'Smartwatch con monitor de salud. Seguimiento de actividad, notificaciones inteligentes, resistente al agua.'
        };
        if (known[asin]) return known[asin];
        const prefix = asin.slice(0, 2);
        const category = {
            'B0': 'Producto tecnológico avanzado con características premium. Diseño moderno y funcionalidad intuitiva.',
            'B1': 'Dispositivo electrónico de calidad superior. Ofrece rendimiento excepcional y durabilidad.',
            'B2': 'Artículo de hogar inteligente con conectividad avanzada. Fácil de usar y configurar.',
            'B3': 'Accesorio premium con materiales de alta calidad. Diseño elegante y funcional.',
            'B4': 'Producto de entretenimiento con tecnología de vanguardia. Experiencia inmersiva garantizada.',
            'B5': 'Dispositivo de salud y bienestar con sensores avanzados. Monitoreo preciso y confiable.',
            'B6': 'Herramienta profesional con prestaciones superiores. Ideal para uso intensivo y profesional.',
            'B7': 'Producto de moda y estilo con materiales premium. Comodidad y elegancia en un solo producto.',
            'B8': 'Dispositivo de comunicación con tecnología innovadora. Conectividad rápida y estable.',
            'B9': 'Accesorio de viaje duradero y funcional. Diseñado para aventureros y profesionales.'
        };
        if (category[prefix]) return category[prefix];
        return 'Producto de Amazon con excelente relación calidad-precio. Envío rápido y garantía del fabricante. Miles de reseñas positivas de clientes satisfechos.';
    }

    // Obtener descripción efectiva respetando showDescription y descriptionLength
    function getEffectiveDescription(product, descriptionLength) {
        if (!product) return '';
        let desc = '';
        if (product.description && String(product.description).trim() !== '') {
            desc = String(product.description).trim();
        } else if (product.asin) {
            desc = getFallbackDescriptionJS(String(product.asin));
        }
        if (!desc) return '';
        if (typeof descriptionLength === 'number' && descriptionLength > 0 && desc.length > descriptionLength) {
            return desc.substring(0, descriptionLength) + '...';
        }
        return desc;
    }
    
    // Función para formatear el número de reseñas
    function formatReviewCount(count) {
        if (!count || isNaN(count)) return '';
        
        const num = parseInt(count);
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toLocaleString();
    }
    
    // Función para obtener valores por defecto del plugin
    const getDefaultValue = (key, fallback) => {
        if (typeof cosasAmazonAjax !== 'undefined' && cosasAmazonAjax.defaultConfig && cosasAmazonAjax.defaultConfig[key] !== undefined) {
            return cosasAmazonAjax.defaultConfig[key];
        }
        return fallback;
    };
    
    // Función para verificar si las valoraciones están habilitadas
    const areRatingsEnabled = () => {
        return getDefaultValue('showRatings', true);
    };
    
    registerBlockType('cosas-amazon/producto-amazon', {
        title: 'Producto de Amazon',
        icon: amazonIcon,
        category: 'widgets',
        description: 'Muestra un producto de Amazon con diferentes estilos.',
        
        attributes: {
            amazonUrl: {
                type: 'string',
                default: ''
            },
            amazonUrls: {
                type: 'array',
                default: []
            },
            displayStyle: {
                type: 'string',
                default: 'horizontal'
            },
            blockSize: {
                type: 'string',
                default: 'medium'
            },
            productData: {
                type: 'object',
                default: {}
            },
            productsData: {
                type: 'array',
                default: []
            },
            showPrice: {
                type: 'boolean',
                default: true
            },
            showDiscount: {
                type: 'boolean',
                default: true
            },
            showDescription: {
                type: 'boolean',
                default: true
            },
            descriptionLength: {
                type: 'number',
                default: 150
            },
            color: {
                type: 'string',
                default: '#000000',
            },
            fontSize: {
                type: 'string',
                default: '16px',
            },
            borderStyle: {
                type: 'string',
                default: 'solid',
            },
            borderColor: {
                type: 'string',
                default: '#cccccc',
            },
            backgroundColor: {
                type: 'string',
                default: '#ffffff',
            },
            alignment: {
                type: 'string',
                default: 'center',
            },
            showButton: {
                type: 'boolean',
                default: true,
            },
            buttonText: {
                type: 'string',
                default: 'Ver en Amazon',
            },
            buttonColor: {
                type: 'string',
                default: '#FF9900',
            },
            showSpecialOffer: {
                type: 'boolean',
                default: true,
            },
            specialOfferText: {
                type: 'string',
                default: '',
            },
            specialOfferColor: {
                type: 'string',
                default: '#e74c3c',
            },
            multipleProductsMode: {
                type: 'boolean',
                default: false,
            },
            productsPerRow: {
                type: 'number',
                default: 2,
            },
            priceColor: {
                type: 'string',
                default: '#B12704',
            },
            discountColor: {
                type: 'string',
                default: '#d93025',
            },
            originalPriceColor: {
                type: 'string',
                default: '#999999',
            },
            featuredBackgroundColor: {
                type: 'string',
                default: '',
            },
            featuredBackgroundGradient: {
                type: 'string',
                default: '',
            },
            modifiedAttributes: {
                type: 'array',
                default: []
            },
            debugMode: {
                type: 'boolean',
                default: false
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { amazonUrl, amazonUrls, displayStyle, blockSize, productData, productsData, showPrice, showDiscount, showDescription, descriptionLength, color, fontSize, borderStyle, borderColor, backgroundColor, alignment, showButton, buttonText, buttonColor, showSpecialOffer, specialOfferText, specialOfferColor, multipleProductsMode, productsPerRow, priceColor, discountColor, originalPriceColor, featuredBackgroundColor, featuredBackgroundGradient, debugMode } = attributes;
            
            const markModified = (name, value) => {
                const prev = attributes.modifiedAttributes || [];
                const next = [...new Set([...prev, name])];
                setAttributes({ [name]: value, modifiedAttributes: next });
            };
            
            const [isLoading, setIsLoading] = useState(false);
            const [error, setError] = useState('');
            const [hasProductData, setHasProductData] = useState(false);
            const [isInitialized, setIsInitialized] = useState(false);
            // Mantener texto crudo del textarea para permitir líneas vacías (Enter)
            const [urlsTextarea, setUrlsTextarea] = useState(Array.isArray(amazonUrls) ? amazonUrls.join('\n') : '');

            // Sincronizar texto crudo cuando cambian las URLs por fuera (p.ej., al limpiar o cargar)
            useEffect(() => {
                const joined = Array.isArray(amazonUrls) ? amazonUrls.join('\n') : '';
                // Solo sincronizar si el contenido "significativo" difiere (ignorando líneas vacías extra)
                const normalize = (s) => String(s || '').split('\n').map(l => l.trim()).filter(Boolean).join('\n');
                if (normalize(urlsTextarea) !== normalize(joined)) {
                    setUrlsTextarea(joined);
                }
            }, [amazonUrls]);
            
            // Verificar configuración al cargar el bloque
            useEffect(() => {
                if (typeof cosasAmazonAjax === 'undefined') {
                    console.error('❌ cosasAmazonAjax no está definido');
                    setError('Error de configuración del plugin. Recarga la página.');
                } else {
                    console.log('✅ cosasAmazonAjax cargado:', cosasAmazonAjax);
                    
                    // Inicializar valores por defecto del plugin solo si no están ya establecidos
                    if (!isInitialized && cosasAmazonAjax.defaultConfig) {
                        const defaultConfig = cosasAmazonAjax.defaultConfig;
                        const newAttributes = {};
                        
                        // Solo usar valores por defecto si el atributo actual es el valor por defecto hardcodeado
                        if (color === '#000000' && defaultConfig.color !== '#000000') {
                            newAttributes.color = defaultConfig.color;
                        }
                        if (fontSize === '16px' && defaultConfig.fontSize !== '16px') {
                            newAttributes.fontSize = defaultConfig.fontSize;
                        }
                        if (borderColor === '#cccccc' && defaultConfig.borderColor !== '#cccccc') {
                            newAttributes.borderColor = defaultConfig.borderColor;
                        }
                        if (backgroundColor === '#ffffff' && defaultConfig.backgroundColor !== '#ffffff') {
                            newAttributes.backgroundColor = defaultConfig.backgroundColor;
                        }
                        // No hay default para featuredBackgroundColor en config; se deja vacío por defecto
                        if (buttonText === 'Ver en Amazon' && defaultConfig.buttonText !== 'Ver en Amazon') {
                            newAttributes.buttonText = defaultConfig.buttonText;
                        }
                        if (buttonColor === '#FF9900' && defaultConfig.buttonColor !== '#FF9900') {
                            newAttributes.buttonColor = defaultConfig.buttonColor;
                        }
                        if (specialOfferColor === '#e74c3c' && defaultConfig.specialOfferColor !== '#e74c3c') {
                            newAttributes.specialOfferColor = defaultConfig.specialOfferColor;
                        }
                        if (displayStyle === 'horizontal' && defaultConfig.displayStyle !== 'horizontal') {
                            newAttributes.displayStyle = defaultConfig.displayStyle;
                        }
                        if (descriptionLength === 150 && defaultConfig.descriptionLength !== 150) {
                            newAttributes.descriptionLength = defaultConfig.descriptionLength;
                        }
                        
                        if (Object.keys(newAttributes).length > 0) {
                            console.log('Aplicando configuración por defecto del plugin:', newAttributes);
                            setAttributes(newAttributes);
                        }
                        
                        setIsInitialized(true);
                    }
                }
            }, [isInitialized, color, fontSize, borderColor, backgroundColor, buttonText, buttonColor, specialOfferColor, displayStyle, descriptionLength]);
            
            useEffect(() => {
                if (productData && Object.keys(productData).length > 0) {
                    setHasProductData(true);
                } else {
                    setHasProductData(false);
                }
            }, [productData]);
            
            // Limitar URLs y productsPerRow: ORIGINAL para horizontal (max 2), NUEVAS limitaciones para compacta/vertical/minimal
            useEffect(() => {
                if (displayStyle === 'horizontal' || displayStyle === 'compact' || displayStyle === 'vertical' || displayStyle === 'minimal') {
                    const updates = {};
                    
                    // Determinar límites según el estilo y tamaño
                    let maxProducts, maxUrls;
                    
                    if (displayStyle === 'horizontal') {
                        // COMPORTAMIENTO ORIGINAL HORIZONTAL: siempre máximo 2 productos
                        maxProducts = 2;
                        maxUrls = 1; // 1 principal + 1 adicional = 2 total
                    } else if (displayStyle === 'compact' || displayStyle === 'vertical') {
                        // COMPACTA Y VERTICAL tienen las mismas limitaciones progresivas
                        switch(blockSize) {
                            case 'xlarge':
                            case 'large':
                                maxProducts = 2;
                                maxUrls = 1; // 1 principal + 1 adicional = 2 total
                                break;
                            case 'medium':
                            case 'small':
                                maxProducts = 3;
                                maxUrls = 2; // 1 principal + 2 adicionales = 3 total
                                break;
                            default:
                                maxProducts = 3; // Máximo 3 para compacta/vertical por defecto
                                maxUrls = 2;
                                break;
                        }
                    } else if (displayStyle === 'minimal') {
                        // MINIMAL: siempre máximo 3 productos para todos los tamaños
                        maxProducts = 3;
                        maxUrls = 2; // 1 principal + 2 adicionales = 3 total
                    }
                    
                    // Limitar URLs adicionales según el tamaño
                    if (Array.isArray(amazonUrls) && amazonUrls.length > maxUrls) {
                        const limitedUrls = amazonUrls.slice(0, maxUrls);
                        console.log(`⚠️ ${blockSize.toUpperCase()} ${displayStyle.toUpperCase()}: Limitando URLs de ${amazonUrls.length} a ${limitedUrls.length}`);
                        updates.amazonUrls = limitedUrls;
                    }
                    
                    // Limitar productos por fila según el tamaño
                    if (productsPerRow > maxProducts) {
                        console.log(`⚠️ ${blockSize.toUpperCase()} ${displayStyle.toUpperCase()}: Limitando productos por fila de ${productsPerRow} a ${maxProducts}`);
                        updates.productsPerRow = maxProducts;
                    }
                    
                    // Aplicar actualizaciones si es necesario
                    if (Object.keys(updates).length > 0) {
                        setAttributes(updates);
                    }
                }
            }, [displayStyle, blockSize, amazonUrls, productsPerRow]);
            
            const fetchProductData = () => {
                if (!amazonUrl) {
                    setError('Por favor, introduce una URL de Amazon');
                    return;
                }
                
                setIsLoading(true);
                setError('');
                
                console.log('🔍 Iniciando fetch con URL:', amazonUrl);
                console.log('🔍 apiFetch disponible:', typeof apiFetch);
                console.log('🔍 cosasAmazonAjax:', cosasAmazonAjax);
                
                // Configurar apiFetch con nonce si está disponible
                if (cosasAmazonAjax && cosasAmazonAjax.restNonce) {
                    console.log('🔍 Configurando nonce REST:', cosasAmazonAjax.restNonce);
                    apiFetch.use(apiFetch.createNonceMiddleware(cosasAmazonAjax.restNonce));
                }
                
                // Usar fetch directo como fallback si apiFetch falla
                const fetchWithFallback = async () => {
                    try {
                        // Intentar primero con apiFetch
                        const response = await apiFetch({
                            path: '/cda/v1/fetch-product-data',
                            method: 'POST',
                            data: { url: amazonUrl },
                        });
                        return response;
                    } catch (error) {
                        console.log('⚠️ apiFetch falló, intentando con fetch directo:', error);
                        
                        // Fallback a fetch directo
                        const response = await fetch(cosasAmazonAjax.restUrl + 'fetch-product-data', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': cosasAmazonAjax.restNonce
                            },
                            body: JSON.stringify({ url: amazonUrl })
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        return await response.json();
                    }
                };
                
                fetchWithFallback()
                .then(data => {
                    setIsLoading(false);
                    console.log('✅ Datos del producto obtenidos:', data);
                    
                    if (data && data.title) {
                        setAttributes({ productData: sanitizePriceFieldsJS(data) });
                        setError('');
                    } else {
                        console.error('❌ Datos incompletos recibidos:', data);
                        setError('Error: Datos del producto incompletos');
                        setAttributes({ productData: {} });
                    }
                })
                .catch(err => {
                    setIsLoading(false);
                    console.error('❌ Error de fetch completo:', err);
                    console.error('❌ Error message:', err.message);
                    console.error('❌ Error stack:', err.stack);
                    
                    let errorMessage = 'Error al obtener los datos del producto';
                    
                    if (err.message) {
                        // Mensaje de error más específico
                        if (err.message.includes('403')) {
                            errorMessage = 'Error de permisos - Verifica que estés logueado';
                        } else if (err.message.includes('rest_cookie_invalid_nonce')) {
                            errorMessage = 'Error de autenticación - Recarga la página';
                        } else if (err.message.includes('Network')) {
                            errorMessage = 'Error de conexión - Verifica tu conexión a internet';
                        } else {
                            errorMessage = err.message;
                        }
                    } else if (err.code) {
                        errorMessage = `Error ${err.code}: ${err.message || 'Error desconocido'}`;
                    }
                    
                    setError(errorMessage);
                    setAttributes({ productData: {} });
                });
            };
            
            const fetchMultipleProducts = () => {
                const allUrls = [amazonUrl, ...amazonUrls].filter(url => url && url.trim());
                
                if (allUrls.length === 0) {
                    setError('Por favor, introduce al menos una URL de Amazon');
                    return;
                }
                
                setIsLoading(true);
                setError('');
                
                // Función para obtener datos de un producto individual
                const fetchSingleProduct = (url) => {
                    return apiFetch({
                        path: '/cda/v1/fetch-product-data',
                        method: 'POST',
                        data: { url: url },
                    }).then(resp => sanitizePriceFieldsJS(resp))
                    .catch(err => {
                        console.error(`Failed to fetch ${url}`, err);
                        return null; // Return null on error to not break Promise.all
                    });
                };
                
                // Obtener todos los productos en paralelo
                Promise.all(allUrls.map(fetchSingleProduct))
                    .then(responses => {
                        setIsLoading(false);
                        
                        const validProducts = responses.filter(response => response !== null);
                        const failedCount = allUrls.length - validProducts.length;
                        
                        if (validProducts.length > 0) {
                            setAttributes({ 
                                productsData: validProducts,
                                productData: validProducts[0] // Mantener el primer producto como principal
                            });
                            
                            if (failedCount > 0) {
                                setError(`✅ ${validProducts.length} productos obtenidos correctamente. ⚠️ ${failedCount} productos fallaron.`);
                            } else {
                                setError('');
                            }
                        } else {
                            setError('No se pudieron obtener datos de ningún producto');
                            setAttributes({ productsData: [], productData: {} });
                        }
                    })
                    .catch(err => {
                        setIsLoading(false);
                        console.error('Error al obtener múltiples productos:', err);
                        setError('Error de conexión: ' + err.message);
                        setAttributes({ productsData: [], productData: {} });
                    });
            };
            
            const renderMultipleProductsGrid = () => {
                // FORZAR productsPerRow = 2 para horizontal + small/medium/large/xlarge (consistencia con PHP)
                let effectiveProductsPerRow = productsPerRow;
                if (displayStyle === 'horizontal' && ['small', 'medium', 'large', 'xlarge'].includes(blockSize)) {
                    effectiveProductsPerRow = 2;
                }
                
                const gridStyle = {
                    display: 'grid',
                    gridTemplateColumns: `repeat(${effectiveProductsPerRow}, 1fr)`,
                    gap: '20px',
                    width: '100%',
                    justifyContent: alignment === 'left' ? 'flex-start' : alignment === 'right' ? 'flex-end' : 'center'
                };
                
                const renderSingleProductCard = (product, index) => {
                    const cardStyle = {
                        backgroundColor: backgroundColor,
                        color: color,
                        fontSize: fontSize,
                        border: borderStyle !== 'none' ? `1px ${borderStyle} ${borderColor}` : 'none',
                        textAlign: alignment
                    };
                    
                    // Para horizontal + small/medium/large/xlarge, crear estructura con contenedor de contenido
                    if (displayStyle === 'horizontal' && (blockSize === 'small' || blockSize === 'medium' || blockSize === 'large' || blockSize === 'xlarge')) {
                        return el('div', {
                            key: `product-${index}`,
                            className: `cosas-amazon-product cosas-amazon-${displayStyle} cosas-amazon-size-${blockSize} cosas-amazon-align-${alignment}`,
                            style: cardStyle,
                            'data-cosas-amazon-debug': 'true',
                            'data-display-style': displayStyle,
                            'data-block-size': blockSize
                        },
                            // Imagen del producto (columna izquierda)
                            product.image && el('div', { 
                                className: 'cosas-amazon-image'
                            },
                                el('img', {
                                    src: product.image,
                                    alt: product.title || 'Producto de Amazon'
                                })
                            ),
                            
                            // Contenedor de contenido (columna derecha)
                            el('div', {
                                className: 'cosas-amazon-content'
                            },
                                // Título del producto
                                product.title && el('h3', { 
                                    className: 'cosas-amazon-title',
                                    'data-cosas-amazon-title': 'true',
                                    'data-block-size': blockSize,
                                    'data-display-style': displayStyle
                                }, product.title),
                                
                                // Valoraciones del producto
                                areRatingsEnabled() && (product.rating || product.reviewCount) && el('div', { 
                                    className: 'cosas-amazon-rating'
                                },
                                    product.rating && generateRatingStars(product.rating),
                                    product.rating && el('span', { 
                                        className: 'cosas-amazon-rating-number'
                                    }, product.rating),
                                    product.reviewCount && el('span', { 
                                        className: 'cosas-amazon-review-count'
                                    }, formatReviewCount(product.reviewCount))
                                ),
                                
                                // Precios
                                (showPrice || showDiscount) && el('div', { 
                                    className: 'cosas-amazon-pricing'
                                },
                                    showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                        className: 'cosas-amazon-discount',
                                        style: { backgroundColor: discountColor }
                                    }, `-${product.discount}%`),
                                    showPrice && normalizePriceStringJS(product.price) && extractNumericPriceJS(product.price) > 0 && el('div', { 
                                        className: 'cosas-amazon-price',
                                        style: { color: priceColor }
                                    }, normalizePriceStringJS(product.price)),
                                    showPrice && normalizePriceStringJS(product.originalPrice) && extractNumericPriceJS(product.originalPrice) > 0 && el('div', { 
                                        className: 'cosas-amazon-original-price',
                                        style: { color: originalPriceColor }
                                    }, normalizePriceStringJS(product.originalPrice))
                                ),
                                
                                // Etiqueta de oferta especial (DENTRO del contenido)
                                showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', {
                                    className: 'cosas-amazon-special-offer'
                                }, el('span', {
                                    style: {
                                        backgroundColor: specialOfferColor
                                    }
                                }, specialOfferText || product.specialOffer || 'Oferta')),

                                // Descripción (con fallback y límite) en horizontal dentro del grid
                                (() => {
                                    if (!showDescription) return null;
                                    const d = getEffectiveDescription(product, descriptionLength || 150);
                                    return d ? el('div', { className: 'cosas-amazon-description' }, d) : null;
                                })(),
                                
                                // Botón "Ver en Amazon"
                                showButton && el('div', { 
                                    className: 'cosas-amazon-button'
                                },
                                    el('a', {
                                        href: [amazonUrl, ...amazonUrls][index] || amazonUrl,
                                        target: '_blank',
                                        rel: 'noopener noreferrer',
                                        className: 'cosas-amazon-btn',
                                        style: {
                                            backgroundColor: buttonColor
                                        }
                                    }, buttonText || 'Ver en Amazon')
                                )
                            )
                        );
                    }
                    
                    // Para estilo minimal: estructura específica
                    if (displayStyle === 'minimal') {
                        return el('div', {
                            key: `product-${index}`,
                            className: `cosas-amazon-product cosas-amazon-${displayStyle} cosas-amazon-size-${blockSize} cosas-amazon-align-${alignment}`,
                            style: {
                                ...cardStyle,
                                display: 'flex',
                                flexDirection: 'column',
                                padding: '10px',
                                maxWidth: 'none', // Anular max-width para grid
                                minHeight: '120px',
                                fontSize: '12px',
                                overflow: 'hidden',
                                boxSizing: 'border-box',
                                position: 'relative'
                            },
                            'data-cosas-amazon-debug': 'true',
                            'data-display-style': displayStyle,
                            'data-block-size': blockSize
                        },
                            // Título del producto en la parte superior
                            product.title && el('h3', { 
                                className: 'cosas-amazon-title',
                                style: { 
                                    fontSize: '17px', 
                                    fontWeight: 'bold', 
                                    margin: '0 0 10px 0', 
                                    lineHeight: '1.3',
                                    color: '#333',
                                    order: 1
                                }
                            }, product.title.length > 50 ? product.title.substring(0, 50) + '...' : product.title),
                            
                            // Contenedor principal con imagen a la izquierda
                            el('div', { 
                                className: 'cosas-amazon-main-content',
                                style: { 
                                    display: 'flex', 
                                    gap: '10px', 
                                    flex: 1, 
                                    order: 2 
                                } 
                            },
                                // Imagen a la izquierda
                                product.image && el('div', { 
                                    className: 'cosas-amazon-image',
                                    style: { 
                                        width: '60px', 
                                        height: '60px', 
                                        flexShrink: 0, 
                                        overflow: 'hidden', 
                                        borderRadius: '4px' 
                                    } 
                                },
                                    el('img', {
                                        src: product.image,
                                        alt: product.title || 'Producto de Amazon',
                                        style: { 
                                            width: '100%', 
                                            height: '100%', 
                                            objectFit: 'contain', 
                                            borderRadius: '4px' 
                                        }
                                    })
                                ),
                                
                                // Contenido a la derecha
                                el('div', { 
                                    className: 'cosas-amazon-content',
                                    style: { 
                                        flex: 1, 
                                        display: 'flex', 
                                        flexDirection: 'column', 
                                        gap: '6px', 
                                        overflow: 'hidden' 
                                    } 
                                },
                                    // Precio
                                    showPrice && normalizePriceStringJS(product.price) && extractNumericPriceJS(product.price) > 0 && el('div', { 
                                        className: 'cosas-amazon-price',
                                        style: { 
                                            fontSize: '19px', 
                                            color: priceColor || '#B12704', 
                                            fontWeight: 'bold', 
                                            margin: '0', 
                                            order: 1 
                                        }
                                    }, normalizePriceStringJS(product.price)),
                                    
                                    // Línea de descuento y precio anterior
                                    (showDiscount && product.discount && product.discount > 0 && product.discount < 100) || product.originalPrice ? 
                                        el('div', { 
                                            className: 'cosas-amazon-pricing-line',
                                            style: { 
                                                display: 'flex', 
                                                alignItems: 'center', 
                                                gap: '8px', 
                                                margin: '0', 
                                                order: 2 
                                            } 
                                        },
                                            showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                                className: 'cosas-amazon-discount',
                                                style: { 
                                                    fontSize: '11px', 
                                                    padding: '2px 6px', 
                                                    borderRadius: '3px', 
                                                    background: discountColor, 
                                                    color: 'white', 
                                                    fontWeight: 'bold' 
                                                } 
                                            }, `-${product.discount}%`),
                                            normalizePriceStringJS(product.originalPrice) && extractNumericPriceJS(product.originalPrice) > 0 && el('span', { 
                                                className: 'cosas-amazon-original-price',
                                                style: { 
                                                    fontSize: '12px', 
                                                    color: originalPriceColor || '#999', 
                                                    textDecoration: 'line-through' 
                                                } 
                                            }, normalizePriceStringJS(product.originalPrice))
                                        ) : null,
                                    
                                    // Etiqueta de oferta especial
                                    showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', { 
                                        className: 'cosas-amazon-special-offer',
                                        style: { margin: '0', order: 3 } 
                                    },
                                        el('span', { 
                                            style: { 
                                                fontSize: '9px', 
                                                padding: '2px 6px', 
                                                borderRadius: '10px', 
                                                background: specialOfferColor, 
                                                color: 'white', 
                                                fontWeight: 'bold',
                                                textTransform: 'uppercase'
                                            } 
                                        }, specialOfferText || (product.specialOffer ? product.specialOffer.substring(0, 8) : 'Oferta'))
                                    ),
                                    
                                    // Botón en la parte inferior
                                    showButton && el('a', {
                                        href: [amazonUrl, ...amazonUrls][index] || amazonUrl,
                                        target: '_blank',
                                        rel: 'noopener noreferrer',
                                        className: 'cosas-amazon-btn',
                                        style: {
                                            fontSize: '11px',
                                            padding: '8px 12px',
                                            marginTop: 'auto',
                                            width: '100%',
                                            boxSizing: 'border-box',
                                            order: 5,
                                            borderRadius: '4px',
                                            minHeight: '32px',
                                            display: 'inline-block',
                                            background: buttonColor,
                                            color: 'white',
                                            fontWeight: '600',
                                            textDecoration: 'none',
                                            textAlign: 'center'
                                        }
                                    }, (buttonText || 'Ver en Amazon').substring(0, 15))
                                )
                            )
                        );
                    }
                    
                    // Para otros estilos, mantener estructura original
                    return el('div', {
                        key: `product-${index}`,
                        className: `cosas-amazon-product cosas-amazon-${displayStyle} cosas-amazon-size-${blockSize} cosas-amazon-align-${alignment}`,
                        style: cardStyle,
                        'data-cosas-amazon-debug': 'true',
                        'data-display-style': displayStyle,
                        'data-block-size': blockSize
                    },
                        // Imagen del producto
                        product.image && el('div', { 
                            className: 'cosas-amazon-image'
                        },
                            el('img', {
                                src: product.image,
                                alt: product.title || 'Producto de Amazon'
                            })
                        ),
                        
                        // Etiqueta de oferta especial (entre imagen y título)
                        showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', {
                            className: 'cosas-amazon-special-offer'
                        }, el('span', {
                            style: {
                                backgroundColor: specialOfferColor
                            }
                        }, specialOfferText || product.specialOffer || 'Oferta')),
                        
                        // Título del producto
                        product.title && el('h3', { 
                            className: 'cosas-amazon-title',
                            'data-cosas-amazon-title': 'true',
                            'data-block-size': blockSize,
                            'data-display-style': displayStyle
                        }, product.title),
                        
                        // Valoraciones del producto
                        areRatingsEnabled() && (product.rating || product.reviewCount) && el('div', { 
                            className: 'cosas-amazon-rating'
                        },
                            product.rating && generateRatingStars(product.rating),
                            product.rating && el('span', { 
                                className: 'cosas-amazon-rating-number'
                            }, product.rating),
                            product.reviewCount && el('span', { 
                                className: 'cosas-amazon-review-count'
                            }, formatReviewCount(product.reviewCount))
                        ),

                        // Descripción (con fallback y límite) antes de precios para no-horizontales (excepto 'compact')
                        (() => {
                            if (!showDescription || displayStyle === 'compact') return null;
                            const d = getEffectiveDescription(product, descriptionLength || 150);
                            return d ? el('div', { className: 'cosas-amazon-description' }, d) : null;
                        })(),
                        
                        // Precios
                        (showPrice || showDiscount) && el('div', { 
                            className: 'cosas-amazon-pricing'
                        },
                            showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                className: 'cosas-amazon-discount',
                                style: { backgroundColor: discountColor }
                            }, `-${product.discount}%`),
                            showPrice && normalizePriceStringJS(product.price) && extractNumericPriceJS(product.price) > 0 && el('div', { 
                                className: 'cosas-amazon-price',
                                style: { color: priceColor }
                            }, normalizePriceStringJS(product.price)),
                            showPrice && normalizePriceStringJS(product.originalPrice) && extractNumericPriceJS(product.originalPrice) > 0 && el('div', { 
                                className: 'cosas-amazon-original-price',
                                style: { color: originalPriceColor }
                            }, normalizePriceStringJS(product.originalPrice))
                        ),
                        
                        // Botón "Ver en Amazon"
                        showButton && el('div', { 
                            className: 'cosas-amazon-button'
                        },
                            el('a', {
                                href: [amazonUrl, ...amazonUrls][index] || amazonUrl,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                className: 'cosas-amazon-btn',
                                style: {
                                    backgroundColor: buttonColor
                                }
                            }, buttonText || 'Ver en Amazon')
                        )
                    );
                };
                
                return el('div', {
                    className: `cosas-amazon-multiple-products cosas-amazon-grid-${effectiveProductsPerRow}-cols`,
                    style: gridStyle
                }, productsData.map((product, index) => renderSingleProductCard(product, index)));
            };
            
            const renderProductPreview = () => {
                // Debug: verificar estado de datos del producto
                console.log('hasProductData:', hasProductData);
                console.log('productData:', productData);
                console.log('productsData:', productsData);
                console.log('displayStyle:', displayStyle);
                console.log('multipleProductsMode:', multipleProductsMode);

                // Definir estilos dinámicos basados en el tamaño del bloque
                const getSizeStyles = (size) => {
                    const sizeStyles = {
                        small: {
                            fontSize: '12px',
                            padding: '10px',
                            maxWidth: '300px'
                        },
                        medium: {
                            fontSize: '14px',
                            padding: '15px',
                            maxWidth: '450px'
                        },
                        large: {
                            fontSize: '16px',
                            padding: '20px',
                            maxWidth: '600px'
                        },
                        xlarge: {
                            fontSize: '18px',
                            padding: '25px',
                            maxWidth: '800px'
                        }
                    };
                    return sizeStyles[size] || sizeStyles.medium;
                };

                const currentSizeStyles = getSizeStyles(blockSize);

                // Para 'table', no mostramos placeholder genérico; la tabla gestiona placeholders por fila
                if (displayStyle !== 'table' && (!hasProductData && (!multipleProductsMode || !productsData || productsData.length === 0))) {
                    return el('div', {
                        className: 'cosas-amazon-placeholder',
                        style: {
                            padding: '40px',
                            textAlign: 'center',
                            border: '2px dashed #ccd0d4',
                            borderRadius: '8px',
                            background: '#f9f9f9',
                            ...currentSizeStyles
                        }
                    },
                        el('p', { style: { margin: '0 0 20px 0', color: '#666' } }, 
                            multipleProductsMode ? 
                            '🛒 Introduce URLs de Amazon para mostrar múltiples productos' :
                            '🛒 Introduce una URL de Amazon para mostrar el producto'
                        ),
                        amazonUrl && el(Button, {
                            isPrimary: true,
                            isBusy: isLoading,
                            onClick: multipleProductsMode ? fetchMultipleProducts : fetchProductData,
                            disabled: !amazonUrl || isLoading
                        }, isLoading ? 'Obteniendo datos...' : (multipleProductsMode ? 'Obtener Productos' : 'Obtener Producto'))
                    );
                }

                // Estilo 'table' SIEMPRE tiene prioridad sobre grid/carousel
                if (displayStyle === 'table') {
                    // Para tabla, usar múltiples productos si están disponibles
                    const tableProducts = productsData && productsData.length > 0 ? productsData : [product];
                    const tableUrls = [];
                    if (amazonUrl) tableUrls.push(amazonUrl);
                    if (amazonUrls && amazonUrls.length > 0) tableUrls.push(...amazonUrls);
                    return el('div', { className: `cosas-amazon-table-container cosas-amazon-size-${blockSize}` },
                        el('table', { className: 'cosas-amazon-table' },
                            el('thead', {},
                                el('tr', {},
                                    el('th', {}, 'Imagen'),
                                    el('th', {}, 'Producto'),
                                    el('th', {}, 'Valoración'),
                                    showPrice && el('th', {}, 'Precio'),
                                    showDiscount && el('th', {}, 'Descuento'),
                                    showButton && el('th', {}, 'Acción')
                                )
                            ),
                            el('tbody', {},
                                tableUrls.map((url, index) => {
                                    const productData = tableProducts[index] || {};
                                    return el('tr', { key: index },
                                        el('td', { className: 'cosas-amazon-table-image' },
                                            productData.image ? el('img', { src: productData.image, alt: productData.title || 'Producto de Amazon' }) : el('div', { className: 'cosas-amazon-placeholder-image' }, '📦')
                                        ),
                                        el('td', { className: 'cosas-amazon-table-title' },
                                            el('h4', {}, productData.title || 'Producto de Amazon'),
                                            (() => {
                                                if (!showDescription) return null;
                                                const d = getEffectiveDescription(productData, descriptionLength || 150);
                                                if (!d) return null;
                                                const words = String(d).split(/\s+/);
                                                const short = words.length > 15 ? words.slice(0, 15).join(' ') + '…' : words.join(' ');
                                                return el('p', { className: 'cosas-amazon-table-description' }, short);
                                            })()
                                        ),
                                        el('td', { className: 'cosas-amazon-table-rating' },
                                            productData.rating ? el('div', { className: 'cosas-amazon-rating' },
                                                el('div', { className: 'cosas-amazon-stars' },
                                                    Array.from({ length: 5 }, (_, i) => {
                                                        const starClass = i < Math.floor(productData.rating) ? 'cosas-amazon-star filled' : 'cosas-amazon-star';
                                                        return el('span', { key: i, className: starClass }, '★');
                                                    })
                                                ),
                                                el('span', { className: 'cosas-amazon-rating-number' }, productData.rating),
                                                (productData.review_count || productData.reviewCount) && el('span', { className: 'cosas-amazon-review-count' }, `(${productData.review_count || productData.reviewCount})`)
                                            ) : el('span', { className: 'cosas-amazon-no-rating' }, 'Sin valoración')
                                        ),
                                        showPrice && el('td', { className: 'cosas-amazon-table-price' },
                                            (normalizePriceStringJS(productData.price) && extractNumericPriceJS(productData.price) > 0) ? el('span', { className: 'cosas-amazon-price', style: { color: priceColor } }, normalizePriceStringJS(productData.price)) : el('span', { className: 'cosas-amazon-no-price' }, 'N/A')
                                        ),
                                        showDiscount && el('td', { className: 'cosas-amazon-table-discount' }, (() => {
                                            // Usar discount válido o calcular a partir de price y original_price
                                            const normalizePrice = (s) => {
                                                if (!s) return null;
                                                // Reutiliza normalizador existente
                                                try { return normalizePriceStringJS(String(s)); } catch(e) { return String(s); }
                                            };
                                            const parsePrice = (s) => {
                                                s = normalizePrice(s);
                                                if (!s) return null;
                                                const clean = s.replace(/[^0-9.,]/g, '');
                                                if (!clean) return null;
                                                const lastComma = clean.lastIndexOf(',');
                                                const lastDot = clean.lastIndexOf('.');
                                                let val = clean;
                                                if (lastComma !== -1 && lastDot !== -1) {
                                                    // último separador como decimal
                                                    if (lastComma > lastDot) {
                                                        val = val.replace(/\./g, '');
                                                        val = val.replace(',', '.');
                                                    } else {
                                                        val = val.replace(/,/g, '');
                                                    }
                                                } else if (lastComma !== -1) {
                                                    if (/,[0-9]{1,2}$/.test(val)) {
                                                        val = val.replace(/\./g, '');
                                                        val = val.replace(',', '.');
                                                    } else {
                                                        val = val.replace(/,/g, '');
                                                    }
                                                } else if (lastDot !== -1) {
                                                    if (/\.[0-9]{1,2}$/.test(val)) {
                                                        val = val.replace(/,/g, '');
                                                    } else {
                                                        val = val.replace(/[.,]/g, '');
                                                    }
                                                } else {
                                                    val = val.replace(/[.,]/g, '');
                                                }
                                                const num = parseFloat(val);
                                                return isNaN(num) ? null : num;
                                            };
                                            const coerceDiscount = (d) => {
                                                if (d === undefined || d === null) return null;
                                                const digits = String(d).replace(/[^0-9]/g, '');
                                                if (!digits) return null;
                                                const n = parseInt(digits, 10);
                                                if (n <= 0 || n >= 100) return null;
                                                return n;
                                            };
                                            const title = (productData.title || '').toLowerCase();
                                            const looksLikeUnitPrice = [/\b\d+\s?(ml|l|litro|litros|kg|g)\b/i, /\bpor\s?(100|1l|1 kg|kg|l)\b/i, /\bpack\b/i, /\b\d+\s?x\b/i, /\b\d+\s?(unidades|unidad|capsulas|cápsulas|tabletas)\b/i, /\b\d+\s?(ml|g)\s?(cada|c\/u)\b/i]
                                                .some(re => re.test(title));
                                            let d = coerceDiscount(productData.discount);
                                            if (d === null) {
                                                const price = parsePrice(productData.price);
                                                const original = parsePrice(productData.original_price || productData.originalPrice);
                                                if (price !== null && original !== null && original > price && price > 0) {
                                                    const ratio = original / price;
                                                    // Heurística de ratio sospechoso (p.ej., 1L vs 250ml)
                                                    const suspiciousRatio = (ratio >= 3.5 && ratio <= 4.5) || ratio >= 8;
                                                    const suppressedByUnitHeuristic = looksLikeUnitPrice && suspiciousRatio && !(productData.savings || productData.hasSavings);
                                                    if (suppressedByUnitHeuristic) {
                                                        d = null;
                                                    } else {
                                                        d = Math.round((1 - (price / original)) * 100);
                                                        if (d <= 0 || d >= 100 || !isFinite(d)) d = null;
                                                    }
                                                }
                                            }
                                            if (d !== null) {
                                                return [
                                                    el('span', { className: 'cosas-amazon-discount', key: 'discount', style: { backgroundColor: discountColor } }, `-${d}%`),
                                                    (productData.original_price || productData.originalPrice) && el('span', { className: 'cosas-amazon-original-price', key: 'original', style: { color: originalPriceColor } }, productData.original_price || productData.originalPrice)
                                                ];
                                            }
                                            // Mensaje diagnóstico opcional
                                            if (debugMode) {
                                                let reason = 'Sin descuento';
                                                if (looksLikeUnitPrice) reason = 'Sin descuento (posible precio por unidad)';
                                                return el('span', { className: 'cosas-amazon-no-discount' }, reason);
                                            }
                                            return el('span', { className: 'cosas-amazon-no-discount' }, 'Sin descuento');
                                        })()),
                                        showButton && el('td', { className: 'cosas-amazon-table-button' },
                                            el('a', { href: url, target: '_blank', rel: 'noopener noreferrer', className: 'cosas-amazon-btn', style: { backgroundColor: buttonColor } }, buttonText || 'Ver en Amazon')
                                        )
                                    );
                                })
                            )
                        )
                    );
                }

                // Vista previa de carousel en el editor (estructura igual a PHP)
                if (displayStyle === 'carousel' && productsData && productsData.length > 0) {
                    const items = productsData.map((p, idx) => el('div', { key: idx, className: 'cosas-amazon-carousel-item' },
                        el('div', { className: 'cosas-amazon-content' },
                            p.image && el('div', { className: 'cosas-amazon-image' },
                                el('img', { src: p.image, alt: p.title || 'Producto de Amazon' })
                            ),
                            (showSpecialOffer && (specialOfferText || p.specialOffer)) && el('div', { className: 'cosas-amazon-special-offer' },
                                el('span', { style: { backgroundColor: specialOfferColor } }, specialOfferText || p.specialOffer || 'Oferta')
                            ),
                            p.title && el('h3', { className: 'cosas-amazon-title' }, p.title),
                            (areRatingsEnabled() && (p.rating || p.reviewCount)) && el('div', { className: 'cosas-amazon-rating' },
                                p.rating && generateRatingStars(p.rating),
                                p.rating && el('span', { className: 'cosas-amazon-rating-number' }, p.rating),
                                p.reviewCount && el('span', { className: 'cosas-amazon-review-count' }, formatReviewCount(p.reviewCount))
                            ),
                            showPrice && normalizePriceStringJS(p.price) && extractNumericPriceJS(p.price) > 0 && el('div', { className: 'cosas-amazon-price', style: { color: priceColor } }, normalizePriceStringJS(p.price)),
                            showDiscount && p.discount && el('div', { className: 'cosas-amazon-discount', style: { backgroundColor: discountColor } }, `-${p.discount}%`),
                            normalizePriceStringJS(p.originalPrice) && extractNumericPriceJS(p.originalPrice) > 0 && el('div', { className: 'cosas-amazon-original-price', style: { color: originalPriceColor } }, normalizePriceStringJS(p.originalPrice)),
                            (() => {
                                if (!showDescription) return null;
                                const d = getEffectiveDescription(p, descriptionLength || 150);
                                return d ? el('div', { className: 'cosas-amazon-description' }, d) : null;
                            })(),
                            showButton && el('a', {
                                href: (amazonUrls && amazonUrls[idx]) || amazonUrl || '#',
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                className: 'cosas-amazon-btn',
                                style: { backgroundColor: buttonColor }
                            }, buttonText || 'Ver en Amazon')
                        )
                    ));

                    // Llamar init tras montar
                    setTimeout(() => { if (window.cosasAmazonCarousel && typeof window.cosasAmazonCarousel.init === 'function') { window.cosasAmazonCarousel.init(); } }, 50);

                    return el('div', { style: { display: 'flex', justifyContent: alignment === 'left' ? 'flex-start' : alignment === 'right' ? 'flex-end' : 'center', width: '100%' } },
                        el('div', { className: `cosas-amazon-products-carousel cosas-amazon-align-${alignment}` },
                            el('div', { className: `cosas-amazon-carousel-container cosas-amazon-carousel cosas-amazon-size-${blockSize}` }, items),
                            el('div', { className: 'cosas-amazon-carousel-controls' },
                                el('button', { type: 'button', className: 'cosas-amazon-carousel-prev', onClick: (e) => window.cosasAmazonCarousel && window.cosasAmazonCarousel.prev && window.cosasAmazonCarousel.prev(e.target) }, '‹'),
                                el('button', { type: 'button', className: 'cosas-amazon-carousel-next', onClick: (e) => window.cosasAmazonCarousel && window.cosasAmazonCarousel.next && window.cosasAmazonCarousel.next(e.target) }, '›')
                            )
                        )
                    );
                }

                // Si estamos en modo múltiples productos (para estilos NO tabla), renderizar grid
                if (multipleProductsMode && productsData && productsData.length > 0) {
                    return renderMultipleProductsGrid();
                }

                const product = productData;
                const containerClass = `cosas-amazon-product cosas-amazon-${displayStyle} cosas-amazon-size-${blockSize} cosas-amazon-align-${alignment}`;

                // Estilos base del contenedor con tamaño dinámico
                const containerStyles = {
                    backgroundColor: backgroundColor,
                    color: color,
                    fontSize: fontSize || currentSizeStyles.fontSize,
                    border: borderStyle !== 'none' ? `1px ${borderStyle} ${borderColor}` : 'none',
                    textAlign: alignment
                };

                // Estilos del wrapper con alineación
                const wrapperStyles = {
                    display: 'flex',
                    justifyContent: alignment === 'left' ? 'flex-start' : alignment === 'right' ? 'flex-end' : 'center',
                    width: '100%'
                };

                // Ajustes específicos por estilo de display
                const getDisplayStyles = (style) => {
                    switch(style) {
                        case 'vertical':
                            return {
                                ...containerStyles,
                                textAlign: 'center',
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center'
                            };
                        case 'minimal':
                            return {
                                ...containerStyles,
                                display: 'flex',
                                flexDirection: 'column',
                                padding: '10px',
                                maxWidth: '280px',
                                minHeight: '120px',
                                fontSize: '12px',
                                overflow: 'hidden',
                                boxSizing: 'border-box',
                                position: 'relative'
                            };
                        case 'compact':
                            return {
                                ...containerStyles,
                                display: 'flex',
                                flexDirection: 'row',
                                alignItems: 'center',
                                gap: '15px'
                            };
                        case 'featured':
                            return {
                                ...containerStyles,
                                background: (featuredBackgroundGradient && featuredBackgroundGradient.trim()) ? featuredBackgroundGradient : ((featuredBackgroundColor && featuredBackgroundColor.trim()) ? featuredBackgroundColor : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'),
                                color: 'white',
                                textAlign: 'center'
                            };
                        default:
                            return {
                                ...containerStyles,
                                display: 'flex',
                                flexDirection: 'row',
                                alignItems: 'flex-start',
                                gap: '20px'
                            };
                    }
                }

                // (Rama 'table' consolidada más arriba; eliminada duplicidad)

                const finalContainerStyles = getDisplayStyles(displayStyle);

                // Renderizado para estilos de tarjeta normal
                if (displayStyle === 'minimal') {
                    // Aplicar wrapper de alineación igual que otros estilos
                    const alignmentWrapperStyles = {
                        ...wrapperStyles,
                        display: 'flex',
                        justifyContent: alignment === 'left' ? 'flex-start' : alignment === 'right' ? 'flex-end' : 'center'
                    };
                    
                    return el('div', { style: alignmentWrapperStyles },
                        el('div', { 
                            className: containerClass, 
                            style: {
                                ...finalContainerStyles,
                                display: 'flex',
                                flexDirection: 'column',
                                padding: '10px',
                                maxWidth: '280px',
                                minHeight: '120px',
                                fontSize: '12px',
                                overflow: 'hidden',
                                boxSizing: 'border-box',
                                position: 'relative'
                            }
                        },
                            // Título del producto en la parte superior
                            product.title && el('h3', { 
                                className: 'cosas-amazon-title',
                                style: { 
                                    fontSize: '17px', 
                                    fontWeight: 'bold', 
                                    margin: '0 0 10px 0', 
                                    lineHeight: '1.3',
                                    color: '#333',
                                    order: 1
                                } 
                            }, product.title.length > 50 ? product.title.substring(0, 50) + '...' : product.title),
                            
                            // Contenedor principal con imagen a la izquierda
                            el('div', { 
                                className: 'cosas-amazon-main-content',
                                style: { 
                                    display: 'flex', 
                                    gap: '10px', 
                                    flex: 1, 
                                    order: 2 
                                } 
                            },
                                // Imagen a la izquierda
                                product.image && el('div', { 
                                    className: 'cosas-amazon-image',
                                    style: { 
                                        width: '60px', 
                                        height: '60px', 
                                        flexShrink: 0, 
                                        overflow: 'hidden', 
                                        borderRadius: '4px' 
                                    } 
                                },
                                    el('img', {
                                        src: product.image,
                                        alt: product.title || 'Producto de Amazon',
                                        style: { 
                                            width: '100%', 
                                            height: '100%', 
                                            objectFit: 'contain', 
                                            borderRadius: '4px' 
                                        }
                                    })
                                ),
                                
                                // Contenido a la derecha
                                el('div', { 
                                    className: 'cosas-amazon-content',
                                    style: { 
                                        flex: 1, 
                                        display: 'flex', 
                                        flexDirection: 'column', 
                                        gap: '6px', 
                                        overflow: 'hidden' 
                                    } 
                                },
                                    // Precio (normalizado)
                                    showPrice && normalizePriceStringJS(product.price) && extractNumericPriceJS(product.price) > 0 && el('div', { 
                                        className: 'cosas-amazon-price',
                                        style: { 
                                            fontSize: '19px', 
                                            color: priceColor || '#B12704', 
                                            fontWeight: 'bold', 
                                            margin: '0', 
                                            order: 1 
                                        }
                                    }, normalizePriceStringJS(product.price)),
                                    
                                    // Línea de descuento y precio anterior
                                    (showDiscount && product.discount && product.discount > 0 && product.discount < 100) || product.originalPrice ? 
                                        el('div', { 
                                            className: 'cosas-amazon-pricing-line',
                                            style: { 
                                                display: 'flex', 
                                                alignItems: 'center', 
                                                gap: '8px', 
                                                margin: '0', 
                                                order: 2 
                                            } 
                                        },
                                            showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                                className: 'cosas-amazon-discount',
                                                style: { 
                                                    fontSize: '11px', 
                                                    padding: '2px 6px', 
                                                    borderRadius: '3px', 
                                                    background: discountColor, 
                                                    color: 'white', 
                                                    fontWeight: 'bold' 
                                                } 
                                            }, `-${product.discount}%`),
                                            normalizePriceStringJS(product.originalPrice) && extractNumericPriceJS(product.originalPrice) > 0 && el('span', { 
                                                className: 'cosas-amazon-original-price',
                                                style: { 
                                                    fontSize: '12px', 
                                                    color: originalPriceColor || '#999', 
                                                    textDecoration: 'line-through' 
                                                } 
                                            }, normalizePriceStringJS(product.originalPrice))
                                        ) : null,
                                    
                                    // Etiqueta de oferta especial
                                    showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', { 
                                        className: 'cosas-amazon-special-offer',
                                        style: { margin: '0', order: 3 } 
                                    },
                                        el('span', { 
                                            style: { 
                                                fontSize: '9px', 
                                                padding: '2px 6px', 
                                                borderRadius: '10px', 
                                                background: specialOfferColor, 
                                                color: 'white', 
                                                fontWeight: 'bold',
                                                textTransform: 'uppercase'
                                            } 
                                        }, specialOfferText || (product.specialOffer ? product.specialOffer.substring(0, 8) : 'Oferta'))
                                    ),
                                    
                                    // Botón en la parte inferior
                                    showButton && el('a', {
                                        href: amazonUrl,
                                        target: '_blank',
                                        rel: 'noopener noreferrer',
                                        className: 'cosas-amazon-btn',
                                        style: {
                                            fontSize: '11px',
                                            padding: '8px 12px',
                                            marginTop: 'auto',
                                            width: 'auto',
                                            boxSizing: 'border-box',
                                            order: 5,
                                            borderRadius: '4px',
                                            minHeight: '32px',
                                            display: 'inline-block',
                                            background: buttonColor,
                                            color: 'white',
                                            fontWeight: '600',
                                            textDecoration: 'none',
                                            textAlign: 'center'
                                        }
                                    }, (buttonText || 'Ver en Amazon').substring(0, 15))
                                )
                            )
                        )
                    );
                }

                // Render específico para estilo destacado (featured) con estructura igual al PHP
                if (displayStyle === 'featured') {
                    const parseHex = (c) => {
                        if (!c || typeof c !== 'string') return null;
                        c = c.trim();
                        if (/^#([0-9a-fA-F]{3})$/.test(c)) {
                            const r=c[1], g=c[2], b=c[3];
                            return `#${r}${r}${g}${g}${b}${b}`;
                        }
                        if (/^#([0-9a-fA-F]{6})$/.test(c)) return c;
                        return null;
                    };
                    const contrastText = (bg) => {
                        const hex = parseHex(bg);
                        if (!hex) return '#ffffff';
                        const r = parseInt(hex.substr(1,2),16)/255;
                        const g = parseInt(hex.substr(3,2),16)/255;
                        const b = parseInt(hex.substr(5,2),16)/255;
                        const luma = 0.2126*r + 0.7152*g + 0.0722*b;
                        return luma > 0.5 ? '#000000' : '#ffffff';
                    };
                    const computeBtnStyles = () => {
                        const userModified = (attributes.modifiedAttributes||[]).includes('buttonColor');
                        if (userModified && buttonColor) {
                            return { backgroundColor: buttonColor, color: contrastText(buttonColor) };
                        }
                        const bg = featuredBackgroundGradient?.trim() ? featuredBackgroundGradient : (featuredBackgroundColor?.trim() ? featuredBackgroundColor : null);
                        if (bg && /gradient/i.test(bg)) {
                            return { background: 'rgba(255,255,255,0.2)', color: '#fff', border: '2px solid #fff' };
                        }
                        const hex = parseHex(bg || backgroundColor);
                        if (hex) {
                            const text = contrastText(hex);
                            return { background: hex, color: text, border: text === '#ffffff' ? '2px solid rgba(255,255,255,0.8)' : '2px solid rgba(0,0,0,0.6)' };
                        }
                        return { background: 'rgba(255,255,255,0.2)', color: '#fff', border: '2px solid #fff' };
                    };
                    const btnAutoStyles = computeBtnStyles();
                    return el('div', { style: wrapperStyles },
                        el('div', {
                            className: containerClass,
                            style: finalContainerStyles
                        },
                            el('div', { className: 'cosas-amazon-content-wrapper' },
                                // Imagen en la parte superior
                                product.image && el('div', { className: 'cosas-amazon-image' },
                                    el('img', { src: product.image, alt: product.title || 'Producto de Amazon' })
                                ),
                                // Etiqueta de oferta especial bajo la imagen
                                showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', { className: 'cosas-amazon-special-offer' },
                                    el('span', { style: { backgroundColor: specialOfferColor } }, specialOfferText || product.specialOffer || 'Oferta')
                                ),
                                // Título centrado
                                product.title && el('h3', { className: 'cosas-amazon-title' }, product.title),
                                // Rating alineado a la derecha
                                areRatingsEnabled() && (product.rating || product.reviewCount) && el('div', { className: 'cosas-amazon-rating' },
                                    product.rating && generateRatingStars(product.rating),
                                    product.rating && el('span', { className: 'cosas-amazon-rating-number' }, product.rating),
                                    product.reviewCount && el('span', { className: 'cosas-amazon-review-count' }, formatReviewCount(product.reviewCount))
                                ),
                                // Descripción centrada (con fallback por ASIN y límite)
                                (() => {
                                    if (!showDescription) return null;
                                    const d = getEffectiveDescription(product, descriptionLength || 150);
                                    return d ? el('div', { className: 'cosas-amazon-description' }, el('p', {}, d)) : null;
                                })(),
                                // Precios centrados
                                (showPrice || showDiscount) && el('div', { className: 'cosas-amazon-pricing' },
                                    showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { className: 'cosas-amazon-discount', style: { backgroundColor: discountColor } }, `-${product.discount}%`),
                                    showPrice && normalizePriceStringJS(product.price) && extractNumericPriceJS(product.price) > 0 && el('span', { className: 'cosas-amazon-price', style: { color: priceColor } }, normalizePriceStringJS(product.price)),
                                    showPrice && normalizePriceStringJS(product.originalPrice) && extractNumericPriceJS(product.originalPrice) > 0 && el('span', { className: 'cosas-amazon-original-price', style: { color: originalPriceColor } }, normalizePriceStringJS(product.originalPrice))
                                )
                            ),
                            // Botón centrado inferior fuera del content-wrapper
                            showButton && el('div', { className: 'cosas-amazon-button' },
                                el('a', {
                                    href: amazonUrl,
                                    target: '_blank',
                                    rel: 'noopener noreferrer',
                                    className: 'cosas-amazon-btn',
                                    style: btnAutoStyles
                                }, buttonText || 'Ver en Amazon')
                            )
                        )
                    );
                }

                return el('div', { style: wrapperStyles },
                    el('div', { 
                        className: containerClass, 
                        style: finalContainerStyles 
                    },
                        // Para horizontal + small/medium/large/xlarge, estructura especial con contenedor de contenido
                        ...(displayStyle === 'horizontal' && (blockSize === 'small' || blockSize === 'medium' || blockSize === 'large' || blockSize === 'xlarge') ? [
                            // Imagen del producto (columna izquierda)
                            product.image && el('div', { 
                                className: 'cosas-amazon-image'
                            },
                                el('img', {
                                    src: product.image,
                                    alt: product.title || 'Producto de Amazon'
                                })
                            ),
                            
                            // Contenedor de contenido (columna derecha)
                            el('div', { 
                                className: 'cosas-amazon-content'
                            },
                                product.title && el('h3', { 
                                    className: 'cosas-amazon-title'
                                }, product.title),
                                
                                // Valoraciones del producto
                                areRatingsEnabled() && (product.rating || product.reviewCount) && el('div', { 
                                    className: 'cosas-amazon-rating'
                                },
                                    product.rating && generateRatingStars(product.rating),
                                    product.rating && el('span', { 
                                        className: 'cosas-amazon-rating-number'
                                    }, product.rating),
                                    product.reviewCount && el('span', { 
                                        className: 'cosas-amazon-review-count'
                                    }, formatReviewCount(product.reviewCount))
                                ),
                                
                                (showPrice || showDiscount) && el('div', { 
                                    className: 'cosas-amazon-pricing'
                                },
                                    showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                        className: 'cosas-amazon-discount',
                                        style: { backgroundColor: discountColor }
                                    }, `-${product.discount}%`),
                                    showPrice && normalizePriceStringJS(product.price) && extractNumericPriceJS(product.price) > 0 && el('span', { 
                                        className: 'cosas-amazon-price',
                                        style: { color: priceColor }
                                    }, normalizePriceStringJS(product.price)),
                                    showPrice && normalizePriceStringJS(product.originalPrice) && extractNumericPriceJS(product.originalPrice) > 0 && el('span', { 
                                        className: 'cosas-amazon-original-price',
                                        style: { color: originalPriceColor }
                                    }, normalizePriceStringJS(product.originalPrice))
                                ),
                                
                                // Etiqueta de oferta especial (DENTRO del contenido)
                                showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', {
                                    className: 'cosas-amazon-special-offer'
                                }, el('span', {
                                    style: {
                                        backgroundColor: specialOfferColor
                                    }
                                }, specialOfferText || product.specialOffer || 'Oferta')),
                                (() => {
                                    if (!showDescription) return null;
                                    const d = getEffectiveDescription(product, descriptionLength || 150);
                                    return d ? el('div', { className: 'cosas-amazon-description' }, d) : null;
                                })(),
                                
                                // Botón "Ver en Amazon"
                                showButton && el('div', { 
                                    className: 'cosas-amazon-button'
                                },
                                    el('a', {
                                        href: amazonUrl,
                                        target: '_blank',
                                        rel: 'noopener noreferrer',
                                        className: 'cosas-amazon-btn',
                                        style: {
                                            backgroundColor: buttonColor
                                        }
                                    }, buttonText || 'Ver en Amazon')
                                )
                            )
                        ] : [
                            // Para otros estilos, mantener estructura original
                            // Imagen del producto
                            product.image && el('div', { 
                                className: 'cosas-amazon-image'
                            },
                                el('img', {
                                    src: product.image,
                                    alt: product.title || 'Producto de Amazon'
                                })
                            ),
                            // Etiqueta de oferta especial (entre imagen y contenido)
                            showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', {
                                className: 'cosas-amazon-special-offer'
                            }, el('span', {
                                style: {
                                    backgroundColor: specialOfferColor
                                }
                            }, specialOfferText || product.specialOffer || 'Oferta')),
                            
                            el('div', { 
                                className: 'cosas-amazon-content'
                            },
                                product.title && el('h3', { 
                                    className: 'cosas-amazon-title'
                                }, product.title),
                                
                                // Valoraciones del producto
                                areRatingsEnabled() && (product.rating || product.reviewCount) && el('div', { 
                                    className: 'cosas-amazon-rating'
                                },
                                    product.rating && generateRatingStars(product.rating),
                                    product.rating && el('span', { 
                                        className: 'cosas-amazon-rating-number'
                                    }, product.rating),
                                    product.reviewCount && el('span', { 
                                        className: 'cosas-amazon-review-count'
                                    }, formatReviewCount(product.reviewCount))
                                ),
                                
                                // En estilos no-horizontales del fallback, descripción antes de precios
                                (displayStyle !== 'horizontal' && displayStyle !== 'compact') && (() => {
                                    if (!showDescription) return null;
                                    const d = getEffectiveDescription(product, descriptionLength || 150);
                                    return d ? el('div', { className: 'cosas-amazon-description' }, d) : null;
                                })(),
                                
                                (showPrice || showDiscount) && el('div', { 
                                    className: 'cosas-amazon-pricing'
                                },
                                    showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                        className: 'cosas-amazon-discount',
                                        style: { backgroundColor: discountColor }
                                    }, `-${product.discount}%`),
                                    showPrice && normalizePriceStringJS(product.price) && extractNumericPriceJS(product.price) > 0 && el('span', { 
                                        className: 'cosas-amazon-price',
                                        style: { color: priceColor }
                                    }, normalizePriceStringJS(product.price)),
                                    showPrice && normalizePriceStringJS(product.originalPrice) && extractNumericPriceJS(product.originalPrice) > 0 && el('span', { 
                                        className: 'cosas-amazon-original-price',
                                        style: { color: originalPriceColor }
                                    }, normalizePriceStringJS(product.originalPrice))
                                ),
                                
                                // En horizontal (p.ej. xlarge en fallback), descripción después de precios
                                (displayStyle === 'horizontal') && (() => {
                                    if (!showDescription) return null;
                                    const d = getEffectiveDescription(product, descriptionLength || 150);
                                    return d ? el('div', { className: 'cosas-amazon-description' }, d) : null;
                                })(),
                                
                                // Botón "Ver en Amazon"
                                showButton && el('div', { 
                                    className: 'cosas-amazon-button'
                                },
                                    el('a', {
                                        href: amazonUrl,
                                        target: '_blank',
                                        rel: 'noopener noreferrer',
                                        className: 'cosas-amazon-btn',
                                        style: {
                                            backgroundColor: buttonColor
                                        }
                                    }, buttonText || 'Ver en Amazon')
                                )
                            )
                        ])
                    )
                );
            };
            
            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Configuración del Producto', initialOpen: true },
                        el(ToggleControl, {
                            label: 'Modo múltiples productos',
                            checked: multipleProductsMode,
                            disabled: displayStyle === 'carousel',
                            onChange: (value) => {
                                const prev = attributes.modifiedAttributes || [];
                                const next = [...new Set([...prev, 'multipleProductsMode'])];
                                setAttributes({ multipleProductsMode: value, modifiedAttributes: next });
                                // Limpiar datos cuando se cambia de modo
                                if (!value) {
                                    setAttributes({ productsData: [] });
                                } else {
                                    setAttributes({ productData: {} });
                                }
                            },
                            help: displayStyle === 'carousel' ? 
                                'Esta opción no está disponible para el estilo carousel' : 
                                'Activa esta opción para mostrar varios productos en la misma línea'
                        }),
                        
                        multipleProductsMode && el(SelectControl, {
                            label: 'Productos por fila',
                            value: productsPerRow,
                            options: (() => {
                                // COMPORTAMIENTO ORIGINAL PARA ESTILO HORIZONTAL: máximo 2 productos
                                if (displayStyle === 'horizontal') {
                                    // Todos los tamaños horizontales: máximo 2 productos (ORIGINAL)
                                    return [
                                        { label: '1 producto', value: 1 },
                                        { label: '2 productos', value: 2 }
                                    ];
                                }
                                // LIMITACIONES PROGRESIVAS PARA VISTA COMPACTA
                                else if (displayStyle === 'compact') {
                                    switch(blockSize) {
                                        case 'xlarge':
                                        case 'large':
                                            // Grande y Extragrande: máximo 2 productos
                                            return [
                                                { label: '1 producto', value: 1 },
                                                { label: '2 productos', value: 2 }
                                            ];
                                        case 'medium':
                                        case 'small':
                                            // Medio y Pequeño: máximo 3 productos
                                            return [
                                                { label: '1 producto', value: 1 },
                                                { label: '2 productos', value: 2 },
                                                { label: '3 productos', value: 3 }
                                            ];
                                        default:
                                            // Otros tamaños compactos: máximo 3
                                            return [
                                                { label: '1 producto', value: 1 },
                                                { label: '2 productos', value: 2 },
                                                { label: '3 productos', value: 3 }
                                            ];
                                    }
                                }
                                // LIMITACIONES PROGRESIVAS PARA VISTA VERTICAL (IGUAL QUE COMPACTA)
                                else if (displayStyle === 'vertical') {
                                    switch(blockSize) {
                                        case 'xlarge':
                                        case 'large':
                                            // Grande y Extragrande: máximo 2 productos
                                            return [
                                                { label: '1 producto', value: 1 },
                                                { label: '2 productos', value: 2 }
                                            ];
                                        case 'medium':
                                        case 'small':
                                            // Medio y Pequeño: máximo 3 productos
                                            return [
                                                { label: '1 producto', value: 1 },
                                                { label: '2 productos', value: 2 },
                                                { label: '3 productos', value: 3 }
                                            ];
                                        default:
                                            // Otros tamaños verticales: máximo 3
                                            return [
                                                { label: '1 producto', value: 1 },
                                                { label: '2 productos', value: 2 },
                                                { label: '3 productos', value: 3 }
                                            ];
                                    }
                                }
                                // LIMITACIONES PARA VISTA MINIMAL: siempre 3 productos máximo
                                else if (displayStyle === 'minimal') {
                                    // Todos los tamaños minimal: máximo 3 productos
                                    return [
                                        { label: '1 producto', value: 1 },
                                        { label: '2 productos', value: 2 },
                                        { label: '3 productos', value: 3 }
                                    ];
                                }
                                // Para estilos SIN limitaciones: opciones completas incluyendo 5
                                return [
                                    { label: '2 productos', value: 2 },
                                    { label: '3 productos', value: 3 },
                                    { label: '4 productos', value: 4 },
                                    { label: '5 productos', value: 5 }
                                ];
                            })(),
                            onChange: (value) => markModified('productsPerRow', parseInt(value)),
                            help: (() => {
                                if (displayStyle === 'horizontal') {
                                    switch(blockSize) {
                                        case 'xlarge':
                                            return 'EXTRAGRANDE HORIZONTAL: Máximo 2 productos por fila permitidos';
                                        case 'large':
                                            return 'GRANDE HORIZONTAL: Máximo 2 productos por fila permitidos';
                                        case 'medium':
                                            return 'MEDIO HORIZONTAL: Máximo 3 productos por fila permitidos';
                                        case 'small':
                                            return 'PEQUEÑO HORIZONTAL: Máximo 4 productos por fila permitidos';
                                        default:
                                            return 'HORIZONTAL: Máximo 4 productos por fila (sin opción de 5)';
                                    }
                                } else if (displayStyle === 'compact') {
                                    switch(blockSize) {
                                        case 'xlarge':
                                        case 'large':
                                            return `${blockSize.toUpperCase()} COMPACTA: Máximo 2 productos por fila permitidos`;
                                        case 'medium':
                                        case 'small':
                                            return `${blockSize.toUpperCase()} COMPACTA: Máximo 3 productos por fila permitidos`;
                                        default:
                                            return 'COMPACTA: Limitado según tamaño del bloque';
                                    }
                                } else if (displayStyle === 'vertical') {
                                    switch(blockSize) {
                                        case 'xlarge':
                                        case 'large':
                                            return `${blockSize.toUpperCase()} VERTICAL: Máximo 2 productos por fila permitidos`;
                                        case 'medium':
                                        case 'small':
                                            return `${blockSize.toUpperCase()} VERTICAL: Máximo 3 productos por fila permitidos`;
                                        default:
                                            return 'VERTICAL: Limitado según tamaño del bloque';
                                    }
                                } else if (displayStyle === 'minimal') {
                                    // Minimal siempre 3 productos máximo
                                    return `${blockSize.toUpperCase()} MINIMAL: Máximo 3 productos por fila permitidos`;
                                }
                                return 'Selecciona cuántos productos mostrar por fila';
                            })()
                        }),
                        
                        el(TextControl, {
                            label: multipleProductsMode ? 'URL principal de Amazon' : 'URL de Amazon',
                            value: amazonUrl,
                            onChange: (value) => markModified('amazonUrl', value),
                            placeholder: 'https://www.amazon.es/dp/...',
                            help: multipleProductsMode ? 
                                'URL del primer producto (usa el campo de abajo para más productos)' : 
                                (displayStyle === 'carousel' || displayStyle === 'table') ? 
                                'URL principal del producto (para estilos múltiples usa el campo de abajo)' : 
                                'Introduce la URL completa del producto de Amazon'
                        }),
                        
                        (multipleProductsMode || displayStyle === 'carousel' || displayStyle === 'table') && (() => {
                            const labelStr = multipleProductsMode ? 'URLs adicionales (una por línea)' : 'URLs adicionales (una por línea)';
                            const placeholderStr = 'https://amzn.to/abc123\nhttps://amzn.to/def456\nhttps://amzn.to/ghi789';
                            const helpStr = multipleProductsMode ? (() => {
                                if (displayStyle === 'horizontal') {
                                    return `HORIZONTAL: Solo 1 URL adicional permitida (2 productos máximo). URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                                } else if (displayStyle === 'compact' || displayStyle === 'vertical') {
                                    switch(blockSize) {
                                        case 'xlarge':
                                        case 'large':
                                            return `${blockSize.toUpperCase()} ${displayStyle.toUpperCase()}: Solo 1 URL adicional permitida (2 productos máximo). URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                                        case 'medium':
                                        case 'small':
                                            return `${blockSize.toUpperCase()} ${displayStyle.toUpperCase()}: Máximo 2 URLs adicionales permitidas (3 productos máximo). URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                                        default:
                                            return `${displayStyle.toUpperCase()}: Máximo 2 URLs adicionales permitidas (3 productos máximo). URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                                    }
                                }
                                return `Introduce cada URL en una línea nueva. URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                            })() : (displayStyle === 'carousel'
                                ? `Para carousel se recomienda añadir al menos 2-3 URLs adicionales. URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`
                                : (() => {
                                    if (displayStyle === 'horizontal') {
                                        return `HORIZONTAL: Solo 1 URL adicional permitida (2 productos máximo). URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                                    } else if (displayStyle === 'compact' || displayStyle === 'vertical') {
                                        switch(blockSize) {
                                            case 'xlarge':
                                            case 'large':
                                                return `${blockSize.toUpperCase()} ${displayStyle.toUpperCase()}: Solo 1 URL adicional permitida (2 productos máximo). URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                                            case 'medium':
                                            case 'small':
                                                return `${blockSize.toUpperCase()} ${displayStyle.toUpperCase()}: Máximo 2 URLs adicionales permitidas (3 productos máximo). URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                                            default:
                                                return `${displayStyle.toUpperCase()}: Máximo 2 URLs adicionales permitidas (3 productos máximo). URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}`;
                                        }
                                    }
                                    return 'Introduce múltiples URLs, una en cada línea';
                                })()
                            );

                            const processInput = (raw) => {
                                console.log('📝 URLs input recibido:', raw);
                                // Actualizar texto crudo para que el textarea conserve saltos de línea
                                setUrlsTextarea(String(raw));
                                let urls = String(raw)
                                    .split('\n')
                                    .map((u) => u.trim())
                                    .filter((u) => u.length > 0);
                                if (displayStyle === 'horizontal') {
                                    const maxUrls = 1;
                                    if (urls.length > maxUrls) {
                                        urls = urls.slice(0, maxUrls);
                                        console.log(`⚠️ HORIZONTAL: Limitado a ${maxUrls} URL adicional (2 productos total) - COMPORTAMIENTO ORIGINAL`);
                                    }
                                } else if (displayStyle === 'compact' || displayStyle === 'vertical') {
                                    let maxUrls;
                                    switch (blockSize) {
                                        case 'xlarge':
                                        case 'large':
                                            maxUrls = 1;
                                            break;
                                        case 'medium':
                                        case 'small':
                                            maxUrls = 2;
                                            break;
                                        default:
                                            maxUrls = 2;
                                    }
                                    if (urls.length > maxUrls) {
                                        urls = urls.slice(0, maxUrls);
                                        console.log(`⚠️ ${blockSize.toUpperCase()} ${displayStyle.toUpperCase()}: Limitado a ${maxUrls} URLs adicionales (${maxUrls + 1} productos total)`);
                                    }
                                }
                                console.log('📝 URLs procesadas:', urls);
                                setAttributes({ amazonUrls: urls, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[])])] });
                            };

                            const handleKey = (e) => {
                                if (!e) return;
                                const isEnter = e.key === 'Enter' || e.keyCode === 13;
                                if (isEnter) {
                                    // Evitar que el editor intercepte Enter; no prevenimos el default para que inserte salto de línea
                                    try { e.stopPropagation(); } catch(_) {}
                                    try { if (e.nativeEvent && typeof e.nativeEvent.stopImmediatePropagation === 'function') e.nativeEvent.stopImmediatePropagation(); } catch(_) {}
                                }
                            };

                            return el('div', { className: 'components-base-control' },
                                el('div', { className: 'components-base-control__field' },
                                    el('label', { className: 'components-base-control__label' }, labelStr),
                                    el('textarea', {
                                        className: 'components-textarea-control__input',
                                        rows: 6,
                                        value: urlsTextarea,
                                        placeholder: placeholderStr,
                                        spellCheck: false,
                                        autoComplete: 'off',
                                        onChange: (e) => processInput(e && e.target ? e.target.value : ''),
                                        onKeyDown: handleKey,
                                        onKeyDownCapture: handleKey,
                                        onKeyUp: handleKey,
                                        onKeyUpCapture: handleKey,
                                        onKeyPress: handleKey,
                                        onKeyPressCapture: handleKey
                                    })
                                ),
                                el('p', { className: 'components-base-control__help' }, helpStr)
                            );
                        })(),
                        
                        el('div', { style: { margin: '15px 0' } },
                            el(Button, {
                                isPrimary: true,
                                isBusy: isLoading,
                                onClick: (multipleProductsMode || displayStyle === 'carousel') ? fetchMultipleProducts : fetchProductData,
                                disabled: !amazonUrl || isLoading,
                                style: { width: '100%' }
                            }, isLoading ? 'Obteniendo datos...' : 
                                (multipleProductsMode || displayStyle === 'carousel') ? 'Obtener Múltiples Productos' : 'Obtener Datos del Producto')
                        ),
                        
                        (hasProductData || (multipleProductsMode && productsData && productsData.length > 0) || (displayStyle === 'carousel' && productsData && productsData.length > 0)) && el(Button, {
                            isSecondary: true,
                            onClick: () => {
                                setAttributes({ productData: {}, productsData: [] });
                                setError('');
                            },
                            style: { width: '100%', marginTop: '10px' }
                        }, 'Limpiar Datos')
                    ),
                    
                    el(PanelBody, { title: 'Estilo y Visualización', initialOpen: false },
                        el(SelectControl, {
                            label: 'Estilo de la tarjeta',
                            value: displayStyle,
                            options: [
                                { label: 'Horizontal', value: 'horizontal' },
                                { label: 'Vertical', value: 'vertical' },
                                { label: 'Compacta', value: 'compact' },
                                { label: 'Destacada', value: 'featured' },
                                { label: 'Muestra mínima', value: 'minimal' },
                                { label: 'Carousel', value: 'carousel' },
                                { label: 'Tabla comparativa', value: 'table' }
                            ],
                            onChange: (value) => {
                                const prev = attributes.modifiedAttributes || [];
                                const next = [...new Set([...prev, 'displayStyle'])];
                                const update = { displayStyle: value, modifiedAttributes: next };
                                if (value === 'carousel' && multipleProductsMode) {
                                    update.multipleProductsMode = false;
                                }
                                setAttributes(update);
                            },
                            help: 'Selecciona el estilo de presentación del producto'
                        }),
                        
                        el(SelectControl, {
                            label: 'Tamaño del bloque',
                            value: blockSize,
                            options: [
                                { label: 'Pequeño', value: 'small' },
                                { label: 'Mediano', value: 'medium' },
                                { label: 'Grande', value: 'large' },
                                { label: 'Extra Grande', value: 'xlarge' }
                            ],
                            onChange: (value) => markModified('blockSize', value),
                            help: 'Ajusta el tamaño general del bloque'
                        }),
                        
                        el(SelectControl, {
                            label: 'Alineación del bloque',
                            value: alignment,
                            options: [
                                { label: 'Izquierda', value: 'left' },
                                { label: 'Centro', value: 'center' },
                                { label: 'Derecha', value: 'right' }
                            ],
                            onChange: (value) => markModified('alignment', value),
                            help: 'Selecciona la alineación del bloque en la página'
                        }),
                        
                        el(ToggleControl, {
                            label: 'Mostrar precio',
                            checked: showPrice,
                            onChange: (value) => markModified('showPrice', value)
                        }),
                        
                        el(ToggleControl, {
                            label: 'Mostrar descuento',
                            checked: showDiscount,
                            onChange: (value) => markModified('showDiscount', value)
                        }),
                        
                        el(ToggleControl, {
                            label: 'Mostrar descripción',
                            checked: showDescription,
                            onChange: (value) => markModified('showDescription', value)
                        }),
                        
                        showDescription && el(RangeControl, {
                            label: 'Límite de caracteres en descripción',
                            value: descriptionLength || 150,
                            onChange: (value) => markModified('descriptionLength', value),
                            min: 50,
                            max: 500,
                            step: 10,
                            help: 'Número máximo de caracteres para la descripción (0 = usar configuración global)'
                        }),
                        
                        // Controles avanzados de personalización
                        el(ColorPalette, {
                            label: 'Color texto general (título, descripción)',
                            value: color,
                            onChange: (newColor) => setAttributes({ color: newColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'color'])] }),
                            help: 'Afecta a títulos, descripciones y texto base'
                        }),
                        el(FontSizePicker, {
                            label: 'Tamaño de fuente',
                            value: fontSize,
                            onChange: (newFontSize) => markModified('fontSize', newFontSize),
                        }),
                        el(SelectControl, {
                            label: 'Estilo de borde',
                            value: borderStyle,
                            options: [
                                { label: 'Sólido', value: 'solid' },
                                { label: 'Punteado', value: 'dotted' },
                                { label: 'Rayado', value: 'dashed' },
                            ],
                            onChange: (newBorderStyle) => markModified('borderStyle', newBorderStyle),
                        }),
                        el(ColorPalette, {
                            label: 'Color del borde',
                            value: borderColor,
                            onChange: (newBorderColor) => setAttributes({ borderColor: newBorderColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'borderColor'])] }),
                            help: 'Borde externo de la tarjeta'
                        }),
                        el(ColorPalette, {
                            label: 'Color de fondo',
                            value: backgroundColor,
                            onChange: (newBackgroundColor) => setAttributes({ backgroundColor: newBackgroundColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'backgroundColor'])] }),
                            help: 'Fondo del contenedor del producto'
                        }),
                        displayStyle === 'featured' && el('div', {
                            style: { marginTop: '10px', padding: '10px', background: '#f6f7f7', borderRadius: '6px' }
                        },
                            el('strong', { style: { display: 'block', marginBottom: '6px' } }, 'Fondo para tarjetas Destacadas'),
                            el('p', { style: { margin: 0, fontSize: '12px', color: '#555' } }, 'Puedes elegir un degradado predefinido, pegar uno personalizado o usar un color sólido. Prioridad: Degradado > Color sólido > Degradado por defecto.')
                        ),
                        displayStyle === 'featured' && el('div', { style: { marginTop: '10px' } },
                            el('label', { style: { display: 'block', fontSize: '12px', marginBottom: '6px' } }, 'Degradados predefinidos'),
                            el(SelectControl, {
                                label: undefined,
                                value: featuredBackgroundGradient || '',
                                options: [
                                    { label: '— Sin degradado (usar color o por defecto)', value: '' },
                                    { label: 'Azul → Morado (por defecto)', value: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
                                    { label: 'Naranja → Rojo', value: 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)' },
                                    { label: 'Verde → Azul', value: 'linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)' },
                                    { label: 'Rosa → Violeta', value: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' },
                                    { label: 'Oscuro', value: 'linear-gradient(135deg, #232526 0%, #414345 100%)' }
                                ],
                                onChange: (v) => setAttributes({ featuredBackgroundGradient: v, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'featuredBackgroundGradient'])] })
                            })
                        ),
                        displayStyle === 'featured' && el('div', { style: { marginTop: '10px' } },
                            el(TextControl, {
                                label: 'Degradado personalizado (CSS)',
                                value: featuredBackgroundGradient || '',
                                placeholder: 'p.ej. linear-gradient(135deg, #667eea, #764ba2)',
                                onChange: (v) => setAttributes({ featuredBackgroundGradient: v, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'featuredBackgroundGradient'])] }),
                                help: 'Pega cualquier valor CSS válido para background (linear-gradient, radial-gradient, etc.)'
                            })
                        ),
                        displayStyle === 'featured' && el(ColorPalette, {
                            label: 'Fondo tarjeta destacada',
                            value: featuredBackgroundColor,
                            onChange: (newColor) => setAttributes({ featuredBackgroundColor: newColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'featuredBackgroundColor'])] }),
                            help: 'Solo para estilo Destacado. Si lo dejas vacío, se usa el degradado por defecto.'
                        }),
                        displayStyle === 'featured' && el(Button, {
                            isSecondary: true,
                            onClick: () => setAttributes({ featuredBackgroundColor: '', featuredBackgroundGradient: '' }),
                            style: { marginTop: '8px' }
                        }, 'Restablecer degradado por defecto'),
                        showPrice && el(ColorPalette, {
                            label: 'Color del precio principal',
                            value: priceColor,
                            onChange: (newPriceColor) => setAttributes({ priceColor: newPriceColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'priceColor'])] }),
                            help: 'Solo el precio actual (no original ni descuento)'
                        }),
                        showDiscount && el(ColorPalette, {
                            label: 'Color fondo descuento (%)',
                            value: discountColor,
                            onChange: (newDiscountColor) => setAttributes({ discountColor: newDiscountColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'discountColor'])] }),
                            help: 'Fondo de la etiqueta de descuento'
                        }),
                        showPrice && el(ColorPalette, {
                            label: 'Color del precio original (tachado)',
                            value: originalPriceColor,
                            onChange: (newOriginalColor) => setAttributes({ originalPriceColor: newOriginalColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'originalPriceColor'])] }),
                            help: 'Color del precio anterior'
                        })
                    ),
                    
                    el(PanelBody, { title: 'Personalización del Botón', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostrar botón "Ver en Amazon"',
                            checked: showButton,
                            onChange: (value) => markModified('showButton', value)
                        }),
                        
                        showButton && el(TextControl, {
                            label: 'Texto del botón',
                            value: buttonText,
                            onChange: (value) => markModified('buttonText', value),
                            placeholder: 'Ver en Amazon',
                            help: 'Personaliza el texto del botón'
                        }),
                        
                        showButton && el(ColorPalette, {
                            label: 'Color del botón (fondo)',
                            value: buttonColor,
                            onChange: (newButtonColor) => setAttributes({ buttonColor: newButtonColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'buttonColor'])] }),
                            help: 'Fondo del botón “Ver en Amazon”'
                        })
                    ),
                    
                    el(PanelBody, { title: 'Etiquetas de Oferta', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostrar etiquetas de oferta',
                            checked: showSpecialOffer,
                            onChange: (value) => markModified('showSpecialOffer', value)
                        }),
                        
                        showSpecialOffer && el(TextControl, {
                            label: 'Texto de la etiqueta',
                            value: specialOfferText,
                            onChange: (value) => setAttributes({ specialOfferText: value }),
                            placeholder: 'Oferta, PrimeDay, Black Friday...',
                            help: 'Personaliza el texto de la etiqueta. Si está vacío, se usará automáticamente el texto de oferta detectado por Amazon o "Oferta" por defecto.'
                        }),
                        
                        showSpecialOffer && el(ColorPalette, {
                            label: 'Color de la etiqueta de oferta',
                            value: specialOfferColor,
                            onChange: (newOfferColor) => setAttributes({ specialOfferColor: newOfferColor, modifiedAttributes: [...new Set([...(attributes.modifiedAttributes||[]), 'specialOfferColor'])] }),
                            help: 'Fondo de la insignia de oferta'
                        })
                    )
                    ,
                    el(PanelBody, { title: 'Diagnóstico', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Modo diagnóstico (editor)',
                            checked: debugMode,
                            onChange: (value) => setAttributes({ debugMode: value }),
                            help: 'Muestra mensajes en la tabla cuando el descuento se oculta por heurísticas (p.ej., precio por unidad).'
                        })
                    )
                ),
                
                el('div', { className: 'cosas-amazon-block-editor' },
                    error && el(Notice, {
                        status: 'error',
                        isDismissible: true,
                        onRemove: () => setError('')
                    }, error),
                    
                    renderProductPreview()
                )
            );
        },
        
        save: function() {
            // El rendering se hace en PHP
            return null;
        }
    });
    
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.data,
    window.wp.compose,
    window.wp.apiFetch
);
