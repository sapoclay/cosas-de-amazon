/**
 * Frontend JavaScript para Cosas de Amazon
 * Version: 2.0.0 - Sin dependencia de jQuery
 */
(function() {
    'use strict';
    
    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }
    
    ready(function() {
        
        function initializeAmazonButtons() {
            document.querySelectorAll('.cosas-de-amazon-block').forEach(function(block) {
                var button = block.querySelector('.amazon-button');
                var amazonUrl = block.dataset.amazonUrl || block.getAttribute('data-amazon-url');
                
                if (!button && amazonUrl) {
                    var buttonText = (window.cosasAmazonConfig && window.cosasAmazonConfig.buttonText) || 'Ver en Amazon';
                    var newButton = document.createElement('a');
                    newButton.className = 'amazon-button';
                    newButton.href = '#';
                    newButton.textContent = buttonText;
                    block.appendChild(newButton);
                }
            });
        }
        
        initializeAmazonButtons();
        
        if (window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                var shouldReinit = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) {
                                if (node.classList && node.classList.contains('cosas-de-amazon-block')) {
                                    shouldReinit = true;
                                } else if (node.querySelector && node.querySelector('.cosas-de-amazon-block')) {
                                    shouldReinit = true;
                                }
                            }
                        });
                    }
                });
                if (shouldReinit) initializeAmazonButtons();
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
        
        document.addEventListener('click', function(e) {
            var button = e.target.closest('.cosas-de-amazon-block .amazon-button');
            if (!button) return;
            e.preventDefault();
            var block = button.closest('.cosas-de-amazon-block');
            var url = block.dataset.amazonUrl || block.getAttribute('data-amazon-url');
            if (url) {
                trackAmazonClick(block);
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        });
        
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        var src = img.getAttribute('data-src');
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
            document.querySelectorAll('.cosas-de-amazon-block img.lazy').forEach(function(img) {
                imageObserver.observe(img);
            });
        }
        
        document.querySelectorAll('.cosas-de-amazon-block').forEach(function(block, index) {
            setTimeout(function() { block.classList.add('animate-in'); }, index * 100);
        });
        
        function trackAmazonClick(block) {
            if (typeof cosasAmazonConfig === 'undefined' || !cosasAmazonConfig.trackClicks) return;
            var data = new FormData();
            data.append('action', 'track_amazon_click');
            data.append('asin', block.dataset.asin || '');
            data.append('url', block.dataset.amazonUrl || '');
            data.append('display_style', block.dataset.displayStyle || '');
            data.append('nonce', cosasAmazonConfig.nonce || '');
            if (navigator.sendBeacon && cosasAmazonConfig.ajaxurl) {
                navigator.sendBeacon(cosasAmazonConfig.ajaxurl, data);
            } else if (cosasAmazonConfig.ajaxurl) {
                fetch(cosasAmazonConfig.ajaxurl, { method: 'POST', body: data, keepalive: true }).catch(function() {});
            }
        }
        
        function handleResponsive() {
            document.querySelectorAll('.cosas-de-amazon-block').forEach(function(block) {
                var width = block.offsetWidth;
                block.classList.remove('size-small', 'size-medium', 'size-large');
                if (width < 300) block.classList.add('size-small');
                else if (width < 600) block.classList.add('size-medium');
                else block.classList.add('size-large');
            });
        }
        
        function debounce(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() { func.apply(context, args); }, wait);
            };
        }
        
        handleResponsive();
        window.addEventListener('resize', debounce(handleResponsive, 250));
    });
})();
