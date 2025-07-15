(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Manejar clicks en botones de Amazon
        $('.cosas-de-amazon-block .amazon-button').on('click', function(e) {
            e.preventDefault();
            
            const $block = $(this).closest('.cosas-de-amazon-block');
            const url = $block.data('amazon-url');
            
            if (url) {
                // Tracking del click (opcional)
                trackAmazonClick($block);
                
                // Abrir en nueva ventana
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        });
        
        // Lazy loading de imágenes
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.getAttribute('data-src');
                        
                        if (src) {
                            img.setAttribute('src', src);
                            img.removeAttribute('data-src');
                            img.classList.remove('lazy');
                            img.classList.add('loaded');
                        }
                        
                        observer.unobserve(img);
                    }
                });
            });
            
            $('.cosas-de-amazon-block img.lazy').each(function() {
                imageObserver.observe(this);
            });
        }
        
        // Animación de entrada para bloques
        $('.cosas-de-amazon-block').each(function(index) {
            const $block = $(this);
            
            setTimeout(function() {
                $block.addClass('animate-in');
            }, index * 100);
        });
        
        // Tooltip para información adicional
        $('.product-info-icon').on('mouseenter', function() {
            const $tooltip = $(this).find('.tooltip');
            $tooltip.fadeIn(200);
        }).on('mouseleave', function() {
            const $tooltip = $(this).find('.tooltip');
            $tooltip.fadeOut(200);
        });
        
        // Actualizar precios dinámicamente (opcional)
        function updatePrices() {
            $('.cosas-de-amazon-block[data-auto-update="true"]').each(function() {
                const $block = $(this);
                const asin = $block.data('asin');
                
                if (asin) {
                    updateProductPrice($block, asin);
                }
            });
        }
        
        // Función para actualizar precio de un producto
        function updateProductPrice($block, asin) {
            $.ajax({
                url: cosasDeAmazonAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_product_price',
                    asin: asin,
                    nonce: cosasDeAmazonAjax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.price) {
                        const $priceContainer = $block.find('.product-price');
                        const $currentPrice = $priceContainer.find('.current-price');
                        
                        if ($currentPrice.text() !== response.data.price) {
                            $currentPrice.addClass('price-updated');
                            $currentPrice.text(response.data.price);
                            
                            setTimeout(function() {
                                $currentPrice.removeClass('price-updated');
                            }, 2000);
                        }
                    }
                }
            });
        }
        
        // Función de tracking (opcional)
        function trackAmazonClick($block) {
            const data = {
                action: 'track_amazon_click',
                asin: $block.data('asin'),
                url: $block.data('amazon-url'),
                display_style: $block.data('display-style'),
                nonce: cosasDeAmazonAjax.nonce
            };
            
            $.ajax({
                url: cosasDeAmazonAjax.ajaxurl,
                type: 'POST',
                data: data,
                async: true
            });
        }
        
        // Función para cargar más productos relacionados
        $('.load-related-products').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $container = $button.closest('.related-products-container');
            const asin = $button.data('asin');
            
            $button.addClass('loading').text('Cargando...');
            
            $.ajax({
                url: cosasDeAmazonAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_related_products',
                    asin: asin,
                    nonce: cosasDeAmazonAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.find('.related-products-grid').append(response.data.html);
                        $button.remove();
                    }
                },
                error: function() {
                    $button.removeClass('loading').text('Error al cargar');
                }
            });
        });
        
        // Comparador de precios (si está habilitado)
        $('.price-comparison-toggle').on('click', function() {
            const $block = $(this).closest('.cosas-de-amazon-block');
            const $comparison = $block.find('.price-comparison');
            
            if ($comparison.hasClass('visible')) {
                $comparison.removeClass('visible').slideUp();
                $(this).text('Comparar precios');
            } else {
                $comparison.addClass('visible').slideDown();
                $(this).text('Ocultar comparación');
                
                // Cargar datos de comparación si no están cargados
                if (!$comparison.hasClass('loaded')) {
                    loadPriceComparison($block);
                }
            }
        });
        
        // Función para cargar comparación de precios
        function loadPriceComparison($block) {
            const asin = $block.data('asin');
            const $comparison = $block.find('.price-comparison');
            
            $.ajax({
                url: cosasDeAmazonAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_price_comparison',
                    asin: asin,
                    nonce: cosasDeAmazonAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $comparison.html(response.data.html).addClass('loaded');
                    }
                }
            });
        }
        
        // Responsive handling para diferentes tamaños
        function handleResponsive() {
            $('.cosas-de-amazon-block').each(function() {
                const $block = $(this);
                const width = $block.width();
                
                $block.removeClass('size-small size-medium size-large');
                
                if (width < 300) {
                    $block.addClass('size-small');
                } else if (width < 600) {
                    $block.addClass('size-medium');
                } else {
                    $block.addClass('size-large');
                }
            });
        }
        
        // Ejecutar al cargar y redimensionar
        handleResponsive();
        $(window).on('resize', debounce(handleResponsive, 250));
        
        // Función debounce para optimizar eventos
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = function() {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Actualizar precios cada 30 minutos (opcional)
        // setInterval(updatePrices, 30 * 60 * 1000);
    });
    
})(jQuery);
