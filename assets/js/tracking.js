/**
 * Script de tracking para Cosas de Amazon
 * Version: 1.4.0
 */

(function($) {
    'use strict';

    // Configuración
    const tracking = {
        init: function() {
            this.bindEvents();
            this.trackPageView();
        },

        bindEvents: function() {
            // Trackear clicks en botones de Amazon
            $(document).on('click', '.cosas-amazon-btn', this.trackClick.bind(this));
            
            // Trackear clicks en imágenes de productos
            $(document).on('click', '.cosas-amazon-product img', this.trackImageClick.bind(this));
        },

        trackPageView: function() {
            // Solo trackear si hay productos de Amazon en la página
            if ($('.cosas-amazon-product').length > 0) {
                // Opcional: trackear visualización de página
                this.sendTracking('view', {
                    post_id: this.getPostId(),
                    product_count: $('.cosas-amazon-product').length,
                    timestamp: Date.now()
                });
            }
        },

        trackClick: function(event) {
            const $button = $(event.currentTarget);
            const $product = $button.closest('.cosas-amazon-product');
            const productUrl = $button.attr('href');
            
            // Datos del tracking
            const data = {
                action: 'track_amazon_click',
                nonce: cosasAmazonTracking.nonce,
                product_url: productUrl,
                post_id: this.getPostId(),
                style: this.getProductStyle($product),
                timestamp: Date.now()
            };

            // Enviar tracking de forma asíncrona
            this.sendTracking('click', data);
        },

        trackImageClick: function(event) {
            const $image = $(event.currentTarget);
            const $product = $image.closest('.cosas-amazon-product');
            const $button = $product.find('.cosas-amazon-btn');
            
            if ($button.length > 0) {
                const productUrl = $button.attr('href');
                
                // Datos del tracking
                const data = {
                    action: 'track_amazon_click',
                    nonce: cosasAmazonTracking.nonce,
                    product_url: productUrl,
                    post_id: this.getPostId(),
                    style: this.getProductStyle($product),
                    type: 'image_click',
                    timestamp: Date.now()
                };

                // Enviar tracking de forma asíncrona
                this.sendTracking('click', data);
            }
        },

        sendTracking: function(type, data) {
            // Enviar datos de tracking sin bloquear la navegación
            if (typeof cosasAmazonTracking !== 'undefined') {
                $.ajax({
                    url: cosasAmazonTracking.ajaxurl,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (window.console && console.log) {
                            console.log('Tracking enviado:', type, response);
                        }
                    },
                    error: function(xhr, status, error) {
                        if (window.console && console.error) {
                            console.error('Error en tracking:', error);
                        }
                    }
                });
            }
        },

        getPostId: function() {
            // Intentar obtener el ID del post desde el body
            const postId = $('body').attr('class').match(/postid-(\d+)/);
            return postId ? postId[1] : 0;
        },

        getProductStyle: function($product) {
            // Extraer el estilo del producto desde las clases CSS
            const classes = $product.attr('class').split(' ');
            
            for (let i = 0; i < classes.length; i++) {
                if (classes[i].startsWith('cosas-amazon-')) {
                    const style = classes[i].replace('cosas-amazon-', '');
                    if (['horizontal', 'vertical', 'compact', 'featured', 'minimal', 'carousel', 'grid'].includes(style)) {
                        return style;
                    }
                }
            }
            
            return 'unknown';
        }
    };

    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        tracking.init();
    });

})(jQuery);
