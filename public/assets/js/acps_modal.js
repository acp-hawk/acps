//*********************************************************************//
// AlleyCat PhotoStation - Modal System (AJAX-based)
// Handles all modal interactions for cart, images, and checkout
//*********************************************************************//

(function($) {
    'use strict';

    // ===================================================================
    // MODAL CONFIGURATION
    // ===================================================================
    
    var ModalConfig = {
        cart: {
            maxWidth: '95vw',
            maxHeight: '96vh',
            width: '95vw',
            height: '90vh' 
        },
        checkout: {
            fullScreen: true  // Full viewport overlay for checkout
        }
    };

    // ===================================================================
    // GLOBAL MODAL INSTANCE STORAGE
    // ===================================================================
    
    window.ACPS = window.ACPS || {};
    window.ACPS.modals = {};

    // ===================================================================
    // CREATE MODAL HTML
    // ===================================================================
    
    function createModal(content, type) {
        var config = ModalConfig[type] || ModalConfig.cart;
        var modalHtml;
        
        if (type === 'checkout' && config.fullScreen) {
            // Full-screen checkout overlay
            modalHtml = 
                '<div class="acps-checkout-overlay">' +
                    '<button class="acps-checkout-close" onclick="closeCheckoutModal(); return false;">×</button>' +
                    '<div class="acps-checkout-content">' +
                        content +
                    '</div>' +
                '</div>';
        } else {
            // Standard modal for cart
            modalHtml = 
                '<div class="acps-modal-overlay">' +
                    '<div class="acps-modal-container" style="max-width:' + config.maxWidth + '; width:' + config.width + '; max-height:' + config.maxHeight + ';">' +
                        '<button class="acps-modal-close" onclick="closeCartModal(); return false;">×</button>' +
                        '<div class="acps-modal-content">' +
                            content +
                        '</div>' +
                    '</div>' +
                '</div>';
        }
        
        return $(modalHtml);
    }

    // ===================================================================
    // CART MODAL FUNCTIONS
    // ===================================================================
    
    /**
     * Opens the cart add/edit modal
     * @param {string} url - URL to cart_add.php with photo parameter
     */
    window.openCartModal = function(url) {
        console.log('[ACPS Modal] Opening cart modal:', url);
        
        // Always use top window's jQuery and body
        var topWindow = window.top || window;
        var $ = topWindow.jQuery;
        
        // Destroy existing modal if present
        if ($('.acps-modal-overlay').length) {
            topWindow.closeCartModal();
        }

        // Show loading state
        var $loadingModal = createModal('<div class="acps-modal-loading">Loading...</div>', 'cart');
        $(topWindow.document.body).append($loadingModal);
        $loadingModal.fadeIn(200);

        // Load content via AJAX
        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                // Remove loading modal
                $loadingModal.remove();
                
                // Create modal with loaded content
                var $modal = createModal(response, 'cart');
                $(topWindow.document.body).append($modal);
                $modal.fadeIn(300);
                
                // Store reference
                topWindow.ACPS.modals.cart = $modal;
                
                // Ensure prices are updated after modal loads
                setTimeout(function() {
                    if (typeof topWindow.updateSubtotal === 'function') {
                        topWindow.updateSubtotal();
                    }
                }, 100);
                
                console.log('[ACPS Modal] Cart modal opened');
            },
            error: function(xhr, status, error) {
                console.error('[ACPS Modal] Failed to load cart content:', error);
                $loadingModal.remove();
                alert('Failed to load cart. Please try again.');
            }
        });
    };

    /**
     * Closes the cart modal
     */
    window.closeCartModal = function() {
        console.log('[ACPS Modal] Closing cart modal');
        
        // Always use top window
        var topWindow = window.top || window;
        var $ = topWindow.jQuery;
        
        $('.acps-modal-overlay').fadeOut(300, function() {
            $(this).remove();
            
            // Reload cart sidebar to reflect changes
            if (topWindow.frames && topWindow.frames['cart']) {
                topWindow.frames['cart'].location.reload();
            }
            
            if (topWindow.ACPS && topWindow.ACPS.modals) {
                delete topWindow.ACPS.modals.cart;
            }
        });
    };

    // ===================================================================
    // CHECKOUT MODAL FUNCTIONS
    // ===================================================================
    
    /**
     * Opens the checkout modal (pay.php)
     * @param {number} amount - Total amount to charge
     */
    window.openCheckoutModal = function(amount) {
        console.log('[ACPS Modal] Opening checkout modal for amount:', amount);
        
        // Always use top window
        var topWindow = window.top || window;
        var $ = topWindow.jQuery;
        
        // Destroy existing modal if present
        if ($('.acps-checkout-overlay').length) {
            topWindow.closeCheckoutModal();
        }

        var url = 'pay.php?amt=' + amount;

        // Show loading state
        var $loadingModal = createModal('<div class="acps-modal-loading">Loading checkout...</div>', 'checkout');
        $(topWindow.document.body).append($loadingModal);
        $loadingModal.fadeIn(200);

        // Load content via AJAX
        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                // Remove loading modal
                $loadingModal.remove();
                
                // Create modal with loaded content
                var $modal = createModal(response, 'checkout');
                $(topWindow.document.body).append($modal);
                $modal.fadeIn(300);
                
                // Store reference
                topWindow.ACPS.modals.checkout = $modal;
                
                console.log('[ACPS Modal] Checkout modal opened');
            },
            error: function(xhr, status, error) {
                console.error('[ACPS Modal] Failed to load checkout content:', error);
                $loadingModal.remove();
                alert('Failed to load checkout. Please try again.');
            }
        });
    };

    /**
     * Closes the checkout modal
     */
    window.closeCheckoutModal = function() {
        console.log('[ACPS Modal] Closing checkout modal');
        
        // Always use top window
        var topWindow = window.top || window;
        var $ = topWindow.jQuery;
        
        $('.acps-checkout-overlay').fadeOut(300, function() {
            $(this).remove();
            
            // Reload cart sidebar to reflect any updates
            if (topWindow.frames && topWindow.frames['cart']) {
                topWindow.frames['cart'].location.reload();
            }
            
            if (topWindow.ACPS && topWindow.ACPS.modals) {
                delete topWindow.ACPS.modals.checkout;
            }
        });
    };

    // ===================================================================
    // BACKDROP CLICK TO CLOSE
    // ===================================================================
    
    $(document).on('click', '.acps-modal-overlay', function(e) {
        // Only close if clicking the backdrop itself (not the container)
        if (e.target === this) {
            window.closeCartModal();
        }
    });

    // ===================================================================
    // GALLERY IMAGE CLICK HANDLER
    // ===================================================================
    
    /**
     * Handles large image click to open cart modal
     * Attached to gallery images with class .gallery-image-clickable
     */
    $(document).on('click', '.gallery-image-clickable', function(e) {
        e.preventDefault();
        var cartUrl = $(this).data('cart-url');
        if (cartUrl) {
            openCartModal(cartUrl);
        }
    });

    // ===================================================================
    // CART SIDEBAR EDIT HANDLER
    // ===================================================================
    
    /**
     * Legacy function for cart sidebar edit links
     * Maintains compatibility with existing cart.php onclick handlers
     */
    window.editCart = function(url) {
        openCartModal(url);
    };

    // ===================================================================
    // INITIALIZATION
    // ===================================================================
    
    $(document).ready(function() {
        console.log('[ACPS Modal System] Initialized');
        console.log('[ACPS Modal System] jQuery version:', $.fn.jquery);
    });

})(jQuery);
