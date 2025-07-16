/**
 * Bloque de Gutenberg para Cosas de Amazon
 * Version: 1.3.0
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
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { amazonUrl, amazonUrls, displayStyle, blockSize, productData, productsData, showPrice, showDiscount, showDescription, descriptionLength, color, fontSize, borderStyle, borderColor, backgroundColor, alignment, showButton, buttonText, buttonColor, showSpecialOffer, specialOfferText, specialOfferColor, multipleProductsMode, productsPerRow } = attributes;
            
            const [isLoading, setIsLoading] = useState(false);
            const [error, setError] = useState('');
            const [hasProductData, setHasProductData] = useState(false);
            const [isInitialized, setIsInitialized] = useState(false);
            
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
                        setAttributes({ productData: data });
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
                    }).catch(err => {
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
                const gridStyle = {
                    display: 'grid',
                    gridTemplateColumns: `repeat(${productsPerRow}, 1fr)`,
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
                    
                    return el('div', {
                        key: `product-${index}`,
                        className: `cosas-amazon-product cosas-amazon-${displayStyle} cosas-amazon-size-${blockSize} cosas-amazon-align-${alignment}`,
                        style: cardStyle
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
                        product.title && el('h4', { 
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
                        
                        // Precios
                        (showPrice || showDiscount) && el('div', { 
                            className: 'cosas-amazon-pricing'
                        },
                            showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                className: 'cosas-amazon-discount'
                            }, `-${product.discount}%`),
                            showPrice && product.price && el('div', { 
                                className: 'cosas-amazon-price'
                            }, product.price),
                            showPrice && product.originalPrice && el('div', { 
                                className: 'cosas-amazon-original-price'
                            }, product.originalPrice)
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
                    className: 'cosas-amazon-multiple-products',
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

                if (!hasProductData && (!multipleProductsMode || !productsData || productsData.length === 0)) {
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

                // Si estamos en modo múltiples productos, renderizar grid
                if (multipleProductsMode && productsData && productsData.length > 0) {
                    return renderMultipleProductsGrid();
                }

                // Si estamos en modo carousel con múltiples productos, renderizar directamente
                if (displayStyle === 'carousel' && productsData && productsData.length > 0) {
                    // El carousel ya está manejado más abajo en el código
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
                                flexDirection: 'row',
                                alignItems: 'center',
                                gap: '10px'
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
                                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
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
                };

                // Renderizado específico para carousel y table
                if (displayStyle === 'carousel') {
                    // Para carousel, mostrar múltiples productos si están disponibles
                    if (productsData && productsData.length > 0) {
                        return el('div', { style: wrapperStyles },
                            el('div', { 
                                className: `${containerClass} cosas-amazon-carousel`, 
                                style: containerStyles 
                            },
                                // Renderizar todos los productos del carousel
                                ...productsData.map((product, index) => {
                                    const productUrl = index === 0 ? amazonUrl : (amazonUrls[index - 1] || '');
                                    return el('div', { 
                                        key: index,
                                        className: 'cosas-amazon-carousel-item' 
                                    },
                                        // Imagen en la parte superior
                                        product.image && el('div', { className: 'cosas-amazon-image' },
                                            el('img', {
                                                src: product.image,
                                                alt: product.title || 'Producto de Amazon',
                                                style: { maxWidth: '100%', height: 'auto' }
                                            })
                                        ),
                                        // Contenido del carousel item
                                        el('div', { className: 'cosas-amazon-content' },
                                            // Etiqueta de oferta especial
                                            showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', {
                                                className: 'cosas-amazon-special-offer'
                                            }, el('span', {
                                                style: {
                                                    backgroundColor: specialOfferColor
                                                }
                                            }, specialOfferText || product.specialOffer || 'Oferta')),
                                            // Título
                                            el('h3', { className: 'cosas-amazon-title' }, product.title),
                                            // Rating
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
                                            // Precio
                                            showPrice && product.price && el('div', { className: 'cosas-amazon-price' }, product.price),
                                            // Descuento
                                            showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('div', { className: 'cosas-amazon-discount' }, `-${product.discount}%`),
                                            // Precio original
                                            product.originalPrice && el('div', { className: 'cosas-amazon-original-price' }, product.originalPrice),
                                            // Botón
                                            showButton && el('a', {
                                                href: productUrl,
                                                target: '_blank',
                                                rel: 'noopener noreferrer',
                                                className: 'cosas-amazon-btn',
                                                style: {
                                                    display: 'inline-block',
                                                    backgroundColor: buttonColor,
                                                    color: 'white',
                                                    padding: '8px 12px',
                                                    borderRadius: '4px',
                                                    textDecoration: 'none',
                                                    fontSize: '12px',
                                                    fontWeight: 'bold',
                                                    marginTop: 'auto',
                                                    width: '100%',
                                                    textAlign: 'center',
                                                    boxSizing: 'border-box'
                                                }
                                            }, buttonText || 'Ver en Amazon')
                                        )
                                    );
                                })
                            )
                        );
                    } else {
                        // Fallback para cuando no hay productos múltiples
                        return el('div', { style: wrapperStyles },
                            el('div', { 
                                className: `${containerClass} cosas-amazon-carousel`, 
                                style: containerStyles 
                            },
                                el('div', { className: 'cosas-amazon-carousel-item' },
                                    // Imagen en la parte superior
                                    product.image && el('div', { className: 'cosas-amazon-image' },
                                        el('img', {
                                            src: product.image,
                                            alt: product.title || 'Producto de Amazon',
                                            style: { maxWidth: '100%', height: 'auto' }
                                        })
                                    ),
                                    // Contenido del carousel item
                                    el('div', { className: 'cosas-amazon-content' },
                                        // Etiqueta de oferta especial
                                        showSpecialOffer && (specialOfferText || product.specialOffer) && el('div', {
                                            className: 'cosas-amazon-special-offer'
                                        }, el('span', {
                                            style: {
                                                backgroundColor: specialOfferColor
                                            }
                                        }, specialOfferText || product.specialOffer || 'Oferta')),
                                        // Título
                                        el('h3', { className: 'cosas-amazon-title' }, product.title),
                                        // Rating
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
                                        // Precio
                                        showPrice && product.price && el('div', { className: 'cosas-amazon-price' }, product.price),
                                        // Descuento
                                        showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('div', { className: 'cosas-amazon-discount' }, `-${product.discount}%`),
                                        // Precio original
                                        product.originalPrice && el('div', { className: 'cosas-amazon-original-price' }, product.originalPrice),
                                        // Botón
                                        showButton && el('a', {
                                            href: amazonUrl,
                                            target: '_blank',
                                            rel: 'noopener noreferrer',
                                            className: 'cosas-amazon-btn',
                                            style: {
                                                display: 'inline-block',
                                                backgroundColor: buttonColor,
                                                color: 'white',
                                                padding: '8px 12px',
                                                borderRadius: '4px',
                                                textDecoration: 'none',
                                                fontSize: '12px',
                                                fontWeight: 'bold',
                                                marginTop: 'auto',
                                                width: '100%',
                                                textAlign: 'center',
                                                boxSizing: 'border-box'
                                            }
                                        }, buttonText || 'Ver en Amazon')
                                    )
                                )
                            )
                        );
                    }
                }

                if (displayStyle === 'table') {
                    // Para tabla, usar múltiples productos si están disponibles
                    const tableProducts = productsData && productsData.length > 0 ? productsData : [product];
                    const tableUrls = [];
                    
                    // Construir array de URLs para la tabla
                    if (amazonUrl) {
                        tableUrls.push(amazonUrl);
                    }
                    if (amazonUrls && amazonUrls.length > 0) {
                        tableUrls.push(...amazonUrls);
                    }
                    
                    return el('div', { style: wrapperStyles },
                        el('div', { 
                            className: `${containerClass} cosas-amazon-table-container`, 
                            style: containerStyles 
                        },
                            el('table', { className: 'cosas-amazon-table' },
                                el('thead', {},
                                    el('tr', {},
                                        el('th', { className: 'cosas-amazon-table-header' }, 'Imagen'),
                                        el('th', { className: 'cosas-amazon-table-header' }, 'Producto'),
                                        el('th', { className: 'cosas-amazon-table-header' }, 'Valoración'),
                                        showPrice && el('th', { className: 'cosas-amazon-table-header' }, 'Precio'),
                                        showDiscount && el('th', { className: 'cosas-amazon-table-header' }, 'Descuento'),
                                        showButton && el('th', { className: 'cosas-amazon-table-header' }, 'Acción')
                                    )
                                ),
                                el('tbody', {},
                                    tableUrls.map((url, index) => {
                                        const productData = tableProducts[index] || {};
                                        return el('tr', { key: index },
                                            // Columna de imagen
                                            el('td', { className: 'cosas-amazon-table-image' },
                                                productData.image ? el('img', {
                                                    src: productData.image,
                                                    alt: productData.title || 'Producto de Amazon'
                                                }) : el('div', { className: 'cosas-amazon-placeholder-image' }, '📦')
                                            ),
                                            // Columna de título
                                            el('td', { className: 'cosas-amazon-table-title' },
                                                el('h4', {}, productData.title || 'Producto de Amazon'),
                                                showDescription && productData.description && el('p', { className: 'cosas-amazon-table-description' }, productData.description)
                                            ),
                                            // Columna de valoración
                                            el('td', { className: 'cosas-amazon-table-rating' },
                                                productData.rating ? el('div', { className: 'cosas-amazon-rating' },
                                                    el('div', { className: 'cosas-amazon-stars' },
                                                        Array.from({ length: 5 }, (_, i) => {
                                                            const starClass = i < Math.floor(productData.rating) ? 'cosas-amazon-star filled' : 'cosas-amazon-star';
                                                            return el('span', { key: i, className: starClass }, '★');
                                                        })
                                                    ),
                                                    el('span', { className: 'cosas-amazon-rating-number' }, productData.rating),
                                                    productData.review_count && el('span', { className: 'cosas-amazon-review-count' }, `(${productData.review_count})`)
                                                ) : el('span', { className: 'cosas-amazon-no-rating' }, 'Sin valoración')
                                            ),
                                            // Columna de precio
                                            showPrice && el('td', { className: 'cosas-amazon-table-price' },
                                                productData.price ? el('span', { className: 'cosas-amazon-price' }, productData.price) : el('span', { className: 'cosas-amazon-no-price' }, 'N/A')
                                            ),
                                            // Columna de descuento
                                            showDiscount && el('td', { className: 'cosas-amazon-table-discount' },
                                                productData.discount && productData.discount > 0 ? [
                                                    el('span', { className: 'cosas-amazon-discount', key: 'discount' }, `-${productData.discount}%`),
                                                    productData.original_price && el('span', { className: 'cosas-amazon-original-price', key: 'original' }, productData.original_price)
                                                ] : el('span', { className: 'cosas-amazon-no-discount' }, 'Sin descuento')
                                            ),
                                            // Columna de botón
                                            showButton && el('td', { className: 'cosas-amazon-table-button' },
                                                el('a', {
                                                    href: url,
                                                    target: '_blank',
                                                    rel: 'noopener noreferrer',
                                                    className: 'cosas-amazon-btn'
                                                }, buttonText || 'Ver en Amazon')
                                            )
                                        );
                                    })
                                )
                            )
                        )
                    );
                }

                const finalContainerStyles = getDisplayStyles(displayStyle);

                // Renderizado para estilos de tarjeta normal
                if (displayStyle === 'minimal') {
                    return el('div', { style: wrapperStyles },
                        el('div', { 
                            className: containerClass, 
                            style: finalContainerStyles 
                        },
                            // Imagen pequeña para estilo minimal
                            product.image && el('img', {
                                src: product.image,
                                alt: product.title || 'Producto de Amazon',
                                style: { width: '50px', height: '50px', objectFit: 'cover', borderRadius: '4px' }
                            }),
                            el('div', { style: { flex: 1 } },
                                product.title && el('div', { 
                                    style: { fontWeight: 'bold', fontSize: '11px', marginBottom: '2px' } 
                                }, product.title.substring(0, 30) + '...'),
                                showPrice && product.price && el('div', { 
                                    style: { color: '#B12704', fontWeight: 'bold', fontSize: '12px' } 
                                }, product.price),
                                el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px', marginTop: '2px' } },
                                    showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                        style: { 
                                            background: '#B12704', 
                                            color: 'white', 
                                            padding: '1px 4px', 
                                            borderRadius: '2px', 
                                            fontSize: '10px' 
                                        } 
                                    }, `-${product.discount}%`),
                                    showSpecialOffer && (specialOfferText || product.specialOffer) && el('span', { 
                                        style: { 
                                            background: specialOfferColor, 
                                            color: 'white', 
                                            padding: '1px 4px', 
                                            borderRadius: '2px', 
                                            fontSize: '9px',
                                            textTransform: 'uppercase'
                                        } 
                                    }, specialOfferText || (product.specialOffer ? product.specialOffer.substring(0, 8) : 'Oferta'))
                                ),
                                showButton && el('a', {
                                    href: amazonUrl,
                                    target: '_blank',
                                    rel: 'noopener noreferrer',
                                    className: 'cosas-amazon-btn',
                                    style: {
                                        display: 'inline-block',
                                        background: buttonColor,
                                        color: 'white',
                                        padding: '3px 8px',
                                        borderRadius: '3px',
                                        fontSize: '10px',
                                        fontWeight: '600',
                                        textDecoration: 'none',
                                        marginTop: '4px'
                                    }
                                }, (buttonText || 'Ver en Amazon').substring(0, 12))
                            )
                        )
                    );
                }

                return el('div', { style: wrapperStyles },
                    el('div', { 
                        className: containerClass, 
                        style: finalContainerStyles 
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
                            
                            showDescription && product.description && displayStyle !== 'compact' && el('div', { 
                                className: 'cosas-amazon-description'
                            }, product.description),
                            
                            (showPrice || showDiscount) && el('div', { 
                                className: 'cosas-amazon-pricing'
                            },
                                showDiscount && product.discount && product.discount > 0 && product.discount < 100 && el('span', { 
                                    className: 'cosas-amazon-discount'
                                }, `-${product.discount}%`),
                                showPrice && product.price && el('span', { 
                                    className: 'cosas-amazon-price'
                                }, product.price),
                                showPrice && product.originalPrice && el('span', { 
                                    className: 'cosas-amazon-original-price'
                                }, product.originalPrice)
                            ),
                            
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
                                setAttributes({ multipleProductsMode: value });
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
                            options: [
                                { label: '2 productos', value: 2 },
                                { label: '3 productos', value: 3 },
                                { label: '4 productos', value: 4 },
                                { label: '5 productos', value: 5 }
                            ],
                            onChange: (value) => setAttributes({ productsPerRow: parseInt(value) }),
                            help: 'Selecciona cuántos productos mostrar por fila'
                        }),
                        
                        el(TextControl, {
                            label: multipleProductsMode ? 'URL principal de Amazon' : 'URL de Amazon',
                            value: amazonUrl,
                            onChange: (value) => setAttributes({ amazonUrl: value }),
                            placeholder: 'https://www.amazon.es/dp/...',
                            help: multipleProductsMode ? 
                                'URL del primer producto (usa el campo de abajo para más productos)' : 
                                (displayStyle === 'carousel' || displayStyle === 'table') ? 
                                'URL principal del producto (para estilos múltiples usa el campo de abajo)' : 
                                'Introduce la URL completa del producto de Amazon'
                        }),
                        
                        (multipleProductsMode || displayStyle === 'carousel' || displayStyle === 'table') && el(TextareaControl, {
                            label: multipleProductsMode ? 'URLs adicionales (una por línea)' : 'URLs adicionales (una por línea)',
                            value: Array.isArray(amazonUrls) ? amazonUrls.join('\n') : '',
                            onChange: (value) => {
                                // Debug: mostrar el valor recibido en la entrada
                                console.log('📝 URLs input recibido:', value);
                                console.log('📝 Tipo de valor:', typeof value);
                                
                                // Procesar las URLs separadas por líneas
                                if (typeof value === 'string') {
                                    // Dividir por saltos de línea, limpiar espacios y filtrar URLs vacías
                                    const urls = value
                                        .split('\n')
                                        .map(url => url.trim())
                                        .filter(url => url.length > 0);
                                    
                                    console.log('📝 URLs procesadas:', urls);
                                    console.log('📝 Número de URLs:', urls.length);
                                    
                                    // Actualizar los atributos
                                    setAttributes({ amazonUrls: urls });
                                } else {
                                    console.warn('⚠️ Valor recibido no es string:', value);
                                }
                            },
                            placeholder: 'https://amzn.to/abc123\nhttps://amzn.to/def456\nhttps://amzn.to/ghi789',
                            help: multipleProductsMode ? 
                                `Introduce cada URL en una línea nueva. URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}` :
                                displayStyle === 'carousel' ? 
                                    `Para carousel se recomienda añadir al menos 2-3 URLs adicionales. URLs actuales: ${Array.isArray(amazonUrls) ? amazonUrls.length : 0}` :
                                    'Introduce múltiples URLs, una en cada línea',
                            rows: 4,
                            spellCheck: false,
                            autoComplete: 'off'
                        }),
                        
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
                                setAttributes({ displayStyle: value });
                                // Deshabilitar modo múltiples productos si se selecciona carousel
                                if (value === 'carousel' && multipleProductsMode) {
                                    setAttributes({ multipleProductsMode: false });
                                }
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
                            onChange: (value) => setAttributes({ blockSize: value }),
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
                            onChange: (value) => setAttributes({ alignment: value }),
                            help: 'Selecciona la alineación del bloque en la página'
                        }),
                        
                        el(ToggleControl, {
                            label: 'Mostrar precio',
                            checked: showPrice,
                            onChange: (value) => setAttributes({ showPrice: value })
                        }),
                        
                        el(ToggleControl, {
                            label: 'Mostrar descuento',
                            checked: showDiscount,
                            onChange: (value) => setAttributes({ showDiscount: value })
                        }),
                        
                        el(ToggleControl, {
                            label: 'Mostrar descripción',
                            checked: showDescription,
                            onChange: (value) => setAttributes({ showDescription: value })
                        }),
                        
                        showDescription && el(RangeControl, {
                            label: 'Límite de caracteres en descripción',
                            value: descriptionLength || 150,
                            onChange: (value) => setAttributes({ descriptionLength: value }),
                            min: 50,
                            max: 500,
                            step: 10,
                            help: 'Número máximo de caracteres para la descripción (0 = usar configuración global)'
                        }),
                        
                        // Controles avanzados de personalización
                        el(ColorPalette, {
                            label: 'Color del texto',
                            value: color,
                            onChange: (newColor) => setAttributes({ color: newColor }),
                        }),
                        el(FontSizePicker, {
                            label: 'Tamaño de fuente',
                            value: fontSize,
                            onChange: (newFontSize) => setAttributes({ fontSize: newFontSize }),
                        }),
                        el(SelectControl, {
                            label: 'Estilo de borde',
                            value: borderStyle,
                            options: [
                                { label: 'Sólido', value: 'solid' },
                                { label: 'Punteado', value: 'dotted' },
                                { label: 'Rayado', value: 'dashed' },
                            ],
                            onChange: (newBorderStyle) => setAttributes({ borderStyle: newBorderStyle }),
                        }),
                        el(ColorPalette, {
                            label: 'Color del borde',
                            value: borderColor,
                            onChange: (newBorderColor) => setAttributes({ borderColor: newBorderColor }),
                        }),
                        el(ColorPalette, {
                            label: 'Color de fondo',
                            value: backgroundColor,
                            onChange: (newBackgroundColor) => setAttributes({ backgroundColor: newBackgroundColor }),
                        })
                    ),
                    
                    el(PanelBody, { title: 'Personalización del Botón', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostrar botón "Ver en Amazon"',
                            checked: showButton,
                            onChange: (value) => setAttributes({ showButton: value })
                        }),
                        
                        showButton && el(TextControl, {
                            label: 'Texto del botón',
                            value: buttonText,
                            onChange: (value) => setAttributes({ buttonText: value }),
                            placeholder: 'Ver en Amazon',
                            help: 'Personaliza el texto del botón'
                        }),
                        
                        showButton && el(ColorPalette, {
                            label: 'Color del botón',
                            value: buttonColor,
                            onChange: (newButtonColor) => setAttributes({ buttonColor: newButtonColor }),
                        })
                    ),
                    
                    el(PanelBody, { title: 'Etiquetas de Oferta', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostrar etiquetas de oferta',
                            checked: showSpecialOffer,
                            onChange: (value) => setAttributes({ showSpecialOffer: value })
                        }),
                        
                        showSpecialOffer && el(TextControl, {
                            label: 'Texto de la etiqueta',
                            value: specialOfferText,
                            onChange: (value) => setAttributes({ specialOfferText: value }),
                            placeholder: 'Oferta, PrimeDay, Black Friday...',
                            help: 'Personaliza el texto de la etiqueta. Si está vacío, se usará automáticamente el texto de oferta detectado por Amazon o "Oferta" por defecto.'
                        }),
                        
                        showSpecialOffer && el(ColorPalette, {
                            label: 'Color de la etiqueta',
                            value: specialOfferColor,
                            onChange: (newOfferColor) => setAttributes({ specialOfferColor: newOfferColor }),
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
