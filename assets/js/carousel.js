/**
 * JavaScript para el plugin Cosas de Amazon
 * Version: 1.3.0
 */

// Funcionalidad del carousel
window.cosasAmazonCarousel = {
    // Navegar a la siguiente diapositiva
    next: function(button) {
        const carousel = button.closest('.cosas-amazon-products-carousel');
        const container = carousel.querySelector('.cosas-amazon-carousel-container');
        const items = container.querySelectorAll('.cosas-amazon-carousel-item');
        if (!carousel || !container || items.length === 0) return;

    const styles = window.getComputedStyle(container);
        const gap = parseFloat(styles.gap || styles.columnGap || 20) || 20;
        const itemW = items[0].getBoundingClientRect().width;
        const step = itemW + gap;
    const viewport = carousel.clientWidth; // ancho visible del wrapper
    const totalItemsWidth = (items.length * itemW) + ((items.length - 1) * gap);
    const maxScroll = Math.max(0, totalItemsWidth - viewport);

        const current = this.getCurrentTransform(container);
        let newTransform;
        // Si ya estamos al final (o muy cerca), la siguiente hace wrap al inicio
        if (Math.abs(current) >= maxScroll) {
            newTransform = 0;
        } else {
            newTransform = Math.max(-maxScroll, current - step);
        }

        container.style.transform = `translateX(${newTransform}px)`;
    },
    
    // Navegar a la diapositiva anterior
    prev: function(button) {
        const carousel = button.closest('.cosas-amazon-products-carousel');
        const container = carousel.querySelector('.cosas-amazon-carousel-container');
        const items = container.querySelectorAll('.cosas-amazon-carousel-item');
        if (!carousel || !container || items.length === 0) return;

    const styles = window.getComputedStyle(container);
        const gap = parseFloat(styles.gap || styles.columnGap || 20) || 20;
        const itemW = items[0].getBoundingClientRect().width;
        const step = itemW + gap;
    const viewport = carousel.clientWidth;
    const totalItemsWidth = (items.length * itemW) + ((items.length - 1) * gap);
    const maxScroll = Math.max(0, totalItemsWidth - viewport);

        const current = this.getCurrentTransform(container);
        let newTransform;
        // Si estamos al inicio (o por encima), la anterior hace wrap al final
        if (current >= 0) {
            newTransform = -maxScroll;
        } else {
            newTransform = Math.min(0, current + step);
        }

        container.style.transform = `translateX(${newTransform}px)`;
    },
    
    // Obtener la transformación actual
    getCurrentTransform: function(element) {
        const style = window.getComputedStyle(element);
        const matrix = style.transform;
        
        if (matrix === 'none') {
            return 0;
        }
        
        const values = matrix.split('(')[1].split(')')[0].split(',');
        return parseInt(values[4]) || 0;
    },
    
    // Inicializar carousels
    init: function() {
        const carousels = document.querySelectorAll('.cosas-amazon-products-carousel');
        
        carousels.forEach(carousel => {
            const container = carousel.querySelector('.cosas-amazon-carousel-container');
            const items = container.querySelectorAll('.cosas-amazon-carousel-item');
            
            if (items.length <= 1) {
                // Ocultar controles si hay solo un item
                const controls = carousel.querySelector('.cosas-amazon-carousel-controls');
                if (controls) {
                    controls.style.display = 'none';
                }
            }
            
            // Añadir soporte para touch/swipe en móviles
            this.addTouchSupport(carousel, container);
        });
    },
    
    // Soporte para gestos táctiles
    addTouchSupport: function(carousel, container) {
        let startX = 0;
        let currentX = 0;
        let isDragging = false;
        
        const handleStart = (e) => {
            startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX;
            currentX = this.getCurrentTransform(container);
            isDragging = true;
            
            container.style.transition = 'none';
        };
        
        const handleMove = (e) => {
            if (!isDragging) return;
            
            e.preventDefault();
            const clientX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
            const diffX = clientX - startX;
            const newX = currentX + diffX;
            
            container.style.transform = `translateX(${newX}px)`;
        };
        
        const handleEnd = () => {
            if (!isDragging) return;
            
            isDragging = false;
            container.style.transition = 'transform 0.3s ease';
            
            const transform = this.getCurrentTransform(container);
            const items = container.querySelectorAll('.cosas-amazon-carousel-item');
            const styles = window.getComputedStyle(container);
            const gap = parseFloat(styles.gap || styles.columnGap || 20) || 20;
            const itemW = items[0].getBoundingClientRect().width;
            const step = itemW + gap;
            const viewport = carousel.clientWidth;
            const totalItemsWidth = (items.length * itemW) + ((items.length - 1) * gap);
            const maxScroll = Math.max(0, totalItemsWidth - viewport);
            
            // Snap al item más cercano dentro de límites
            const nearestIndex = Math.round(Math.abs(transform) / step);
            let newTransform = -nearestIndex * step;
            if (Math.abs(newTransform) > maxScroll) newTransform = -maxScroll;
            if (newTransform > 0) newTransform = 0;
            // Evitar pequeños desbordes por redondeos cuando estamos al final
            if (Math.abs(maxScroll + newTransform) < 1) newTransform = -maxScroll;
            
            container.style.transform = `translateX(${newTransform}px)`;
        };
        
        // Mouse events
        carousel.addEventListener('mousedown', handleStart);
        document.addEventListener('mousemove', handleMove);
        document.addEventListener('mouseup', handleEnd);
        
        // Touch events
        carousel.addEventListener('touchstart', handleStart);
        carousel.addEventListener('touchmove', handleMove);
        carousel.addEventListener('touchend', handleEnd);
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    cosasAmazonCarousel.init();
});

// Reinicializar cuando se cargue contenido dinámico
document.addEventListener('DOMNodeInserted', function(e) {
    if (e.target.classList && e.target.classList.contains('cosas-amazon-products-carousel')) {
        setTimeout(() => {
            cosasAmazonCarousel.init();
        }, 100);
    }
});

// Manejar redimensionamiento de ventana
window.addEventListener('resize', function() {
    cosasAmazonCarousel.init();
});

// Utilidades adicionales
window.cosasAmazonUtils = {
    // Función para actualizar un producto específico
    updateProduct: function(url, blockId) {
        if (typeof wp !== 'undefined' && wp.data) {
            // Forzar actualización del bloque en el editor
            const blocks = wp.data.select('core/block-editor').getBlocks();
            // Implementar lógica específica del editor si es necesario
        }
    },
    
    // Función para mostrar estado de carga
    showLoading: function(element) {
        element.classList.add('cosas-amazon-loading');
    },
    
    // Función para ocultar estado de carga
    hideLoading: function(element) {
        element.classList.remove('cosas-amazon-loading');
    },
    
    // Función para formatear precios
    formatPrice: function(price) {
        if (typeof price === 'number') {
            return price.toLocaleString('es-ES', {
                style: 'currency',
                currency: 'EUR'
            });
        }
        return price;
    }
};
