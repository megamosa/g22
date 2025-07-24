/**
 * MagoArab_EasYorder JavaScript
 *
 * @category    MagoArab
 * @package     MagoArab_EasYorder
 * @author      MagoArab Development Team
 * @copyright   Copyright (c) 2025 MagoArab
 * @license     https://opensource.org/licenses/MIT MIT License
 */

define([
    'jquery',
    'mage/url',
    'mage/translate'
], function ($, url, $t) {
    'use strict';

    return {
        /**
         * Initialize EasyOrder functionality
         * @param {Object} config - Configuration object
         */
        init: function(config) {
            this.config = config;
            this.bindEvents();
            this.initializeForm();
        },

        /**
         * Bind form events
         */
        bindEvents: function() {
            var self = this;
            
            // Quantity controls
            $(document).on('click', '.qty-btn', function() {
                self.handleQuantityChange($(this));
            });
            
            // Country change
            $(document).on('change', '#country_id', function() {
                self.loadShippingMethods();
            });
            
            // Address field changes
            $(document).on('change', '#city, #address, #region, #postcode, #qty', function() {
                if ($('#country_id').val()) {
                    self.loadShippingMethods();
                }
            });
            
            // Shipping method change
            $(document).on('change', 'input[name="shipping_method"]', function() {
                self.validateForm();
                self.updateCalculation();
            });
            
            // Payment method change
            $(document).on('change', 'input[name="payment_method"]', function() {
                self.validateForm();
            });
            
            // Form field validation
            $(document).on('change blur', '#easyorder-form input[required], #easyorder-form select[required], #easyorder-form textarea[required]', function() {
                self.validateForm();
            });
            
            // Form submission
            $(document).on('submit', '#easyorder-form', function(e) {
                e.preventDefault();
                self.submitOrder();
            });
        },

        /**
         * Initialize form state
         */
        initializeForm: function() {
            this.validateForm();
            
            // Auto-load shipping methods if country is pre-selected
            if ($('#country_id').val()) {
                this.loadShippingMethods();
            }
        },

        /**
         * Handle quantity button clicks
         * @param {jQuery} button - The clicked button
         */
        handleQuantityChange: function(button) {
            var action = button.data('action');
            var qtyInput = $('#qty');
            var currentQty = parseInt(qtyInput.val()) || 1;
            
            if (action === 'plus') {
                qtyInput.val(currentQty + 1);
            } else if (action === 'minus' && currentQty > 1) {
                qtyInput.val(currentQty - 1);
            }
            
            this.updateCalculation();
        },

        /**
         * Load available shipping methods
         */
        // Update loadShippingMethods function
        loadShippingMethods: function() {
            var self = this;
            var formData = this.getFormData();
            
            // Show loading state
            $('#magoarab-shipping-methods-container').html('<div class="loading-message">' + $t('Loading shipping methods...') + '</div>');
            
            // Enhanced request data (like checkout)
            var requestData = {
                product_id: formData.product_id,
                country_id: formData.country_id,
                region_id: formData.region_id || '',
                region: formData.region || '',
                city: formData.city || '',
                postcode: formData.postcode || '',
                phone: formData.phone || '',
                qty: formData.qty || 1,
                timestamp: Date.now()
            };
            
            $.ajax({
                url: this.options.shippingUrl,
                type: 'POST',
                dataType: 'json',
                data: requestData,
                timeout: 15000, // 15 seconds timeout
                success: function(response) {
                    console.log('Shipping methods response:', response);
                    
                    if (response.success && response.shipping_methods && response.shipping_methods.length > 0) {
                        self.renderShippingMethods(response.shipping_methods);
                        
                        // Log success for debugging
                        if (response.debug) {
                            console.log('Shipping debug info:', response.debug);
                        }
                    } else {
                        var errorMessage = response.message || $t('No shipping methods available for this location.');
                        $('#magoarab-shipping-methods-container').html('<div class="error-message">' + errorMessage + '</div>');
                        
                        // Show suggestions if available
                        if (response.suggestions && response.suggestions.length > 0) {
                            var suggestionsHtml = '<div class="suggestions"><h4>' + $t('Suggestions:') + '</h4><ul>';
                            response.suggestions.forEach(function(suggestion) {
                                suggestionsHtml += '<li>' + suggestion + '</li>';
                            });
                            suggestionsHtml += '</ul></div>';
                            $('#magoarab-shipping-methods-container').append(suggestionsHtml);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Shipping methods AJAX error:', error);
                    $('#magoarab-shipping-methods-container').html('<div class="error-message">' + $t('Error loading shipping methods. Please try again.') + '</div>');
                }
            });
        },
        
        // Enhanced shipping methods rendering
        renderShippingMethods: function(methods) {
            const container = $('#magoarab-shipping-methods-container');
            
            if (!methods || methods.length === 0) {
                container.html(
                    '<div class="message error">' +
                    '<span>No shipping methods available. Please check your address details or contact support.</span>' +
                    '</div>'
                );
                return;
            }
            
            let html = '<div class="shipping-methods">';
            
            methods.forEach(function(method, index) {
                const priceDisplay = method.price > 0 ? method.price_formatted : 'Free';
                const isChecked = index === 0 ? 'checked="checked"' : '';
                
                html += `
                    <label class="shipping-method-option">
                        <input type="radio" name="shipping_method" value="${method.code}" ${isChecked} />
                        <span class="shipping-info">
                            <span class="shipping-title">${method.carrier_title} - ${method.title}</span>
                            <span class="shipping-price">${priceDisplay}</span>
                        </span>
                    </label>
                `;
            });
            
            html += '</div>';
            container.html(html);
            
            // Trigger calculation update
            this.updateCalculation();
        },
        
        loadShippingMethods: function() {
            var self = this;
            var requestData = {
                product_id: this.config.productId,
                country_id: $('#country').val(),
                region_id: $('#region_id').val(),
                region: $('#region').val(),
                city: $('#city').val(),
                postcode: $('#postcode').val(),
                phone: $('#phone').val(),
                qty: parseInt($('#qty').val()) || 1
            };
            
            // إضافة معرف فريد للطلب
            var requestId = 'frontend_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            requestData.request_id = requestId;
            
            console.log('=== EasyOrder Frontend: Loading shipping methods ===', {
                request_id: requestId,
                timestamp: new Date().toISOString(),
                request_data: requestData
            });
            
            $('#magoarab-shipping-methods-container').html(
                '<div class="loading-message">جاري تحميل طرق الشحن...</div>'
            );
            
            $.ajax({
                url: this.config.urls.shipping,
                type: 'POST',
                dataType: 'json',
                data: requestData,
                timeout: 30000, // زيادة المهلة الزمنية
                beforeSend: function() {
                    console.log('Shipping request sent', {
                        request_id: requestId,
                        url: self.config.urls.shipping
                    });
                },
                success: function(response) {
                    console.log('=== EasyOrder Frontend: Shipping response received ===', {
                        request_id: requestId,
                        response: response,
                        processing_time: response.debug_info ? response.debug_info.processing_time : 'unknown'
                    });
                    
                    if (response.success && response.shipping_methods && response.shipping_methods.length > 0) {
                        self.renderShippingMethods(response.shipping_methods);
                        
                        console.log('Shipping methods rendered successfully', {
                            request_id: requestId,
                            methods_count: response.shipping_methods.length
                        });
                    } else {
                        var errorMessage = response.message || $t('لا توجد طرق شحن متاحة لهذا الموقع.');
                        $('#magoarab-shipping-methods-container').html(
                            '<div class="error-message">' + errorMessage + '</div>'
                        );
                        
                        console.error('No shipping methods available', {
                            request_id: requestId,
                            response: response
                        });
                        
                        // عرض الاقتراحات إذا كانت متوفرة
                        if (response.suggestions && response.suggestions.length > 0) {
                            var suggestionsHtml = '<div class="suggestions"><strong>اقتراحات:</strong><ul>';
                            response.suggestions.forEach(function(suggestion) {
                                suggestionsHtml += '<li>' + suggestion + '</li>';
                            });
                            suggestionsHtml += '</ul></div>';
                            $('#magoarab-shipping-methods-container').append(suggestionsHtml);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('=== EasyOrder Frontend: Shipping request failed ===', {
                        request_id: requestId,
                        status: status,
                        error: error,
                        response_text: xhr.responseText,
                        status_code: xhr.status
                    });
                    
                    var errorMessage = 'خطأ في تحميل طرق الشحن. يرجى المحاولة مرة أخرى.';
                    
                    if (xhr.status === 0) {
                        errorMessage = 'فشل في الاتصال بالخادم. يرجى التحقق من الاتصال بالإنترنت.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'خطأ في الخادم. يرجى المحاولة لاحقاً.';
                    } else if (status === 'timeout') {
                        errorMessage = 'انتهت مهلة الطلب. يرجى المحاولة مرة أخرى.';
                    }
                    
                    $('#magoarab-shipping-methods-container').html(
                        '<div class="error-message">' + errorMessage + '</div>' +
                        '<div class="debug-info">معرف الطلب: ' + requestId + '</div>'
                    );
                }
            });
        }
        
        /**
         * Handle shipping methods response
         * @param {Object} response - AJAX response
         */
        handleShippingMethodsResponse: function(response) {
            if (response.success && response.shipping_methods && response.shipping_methods.length > 0) {
                var html = '<div class="shipping-methods">';
                
                $.each(response.shipping_methods, function(index, method) {
                    html += '<label class="shipping-method">';
                    html += '<input type="radio" name="shipping_method" value="' + method.code + '" class="shipping-radio">';
                    html += '<span class="shipping-label">' + method.carrier_title + ' - ' + method.title + '</span>';
                    html += '<span class="shipping-price">' + this.formatPrice(method.price) + '</span>';
                    html += '</label>';
                }.bind(this));
                
                html += '</div>';
                
                $('#shipping-methods-container').html(html);
                
                // Auto-select first method
                $('#shipping-methods-container input[name="shipping_method"]:first').prop('checked', true);
                
                this.validateForm();
                this.updateCalculation();
            } else {
                $('#shipping-methods-container').html('<div class="error-message">' + (response.message || $t('No shipping methods available')) + '</div>');
            }
        },

        /**
         * Update order calculation
         */
        updateCalculation: function() {
            var postData = {
                product_id: this.getProductId(),
                qty: this.getQuantity(),
                shipping_method: this.getShippingMethod(),
                country_id: this.getCountryId(),
                region: this.getRegion(),
                postcode: this.getPostcode(),
                form_key: $('input[name="form_key"]').val() // إضافة form_key
            };
            
            $.ajax({
                url: this.config.urls.calculate,
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        this.updateOrderSummaryWithCalculation(response.data);
                    } else {
                        console.error('Calculation failed:', response.message);
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: xhr.status,
                        statusText: status,
                        responseText: xhr.responseText,
                        error: error
                    });
                }
            });
        }

        /**
         * Update order summary display
         * @param {Object} calculation - Calculation data
         */
        updateOrderSummary: function(calculation) {
            $('#product-subtotal').text(calculation.formatted.subtotal);
            $('#shipping-cost').text(calculation.formatted.shipping_cost);
            $('#order-total').text(calculation.formatted.total);
            $('#order-summary-section').show();
        },

        /**
         * Validate form and enable/disable submit button
         */
        validateForm: function() {
            var isValid = true;
            var form = $('#easyorder-form');
            
            // Check required fields
            form.find('input[required], select[required], textarea[required]').each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    return false;
                }
            });
            
            // Check shipping method selection
            if (!$('input[name="shipping_method"]:checked').length) {
                isValid = false;
            }
            
            // Check payment method selection
            if (!$('input[name="payment_method"]:checked').length) {
                isValid = false;
            }
            
            $('#easyorder-submit-btn').prop('disabled', !isValid);
        },

        /**
         * Submit order
         */
        submitOrder: function() {
            var self = this;
            var submitBtn = $('#easyorder-submit-btn');
            var loadingOverlay = $('#loading-overlay');
            var form = $('#easyorder-form');
            
            if (submitBtn.prop('disabled')) {
                return;
            }
            
            submitBtn.prop('disabled', true);
            loadingOverlay.show();
            this.hideMessages();
            
            $.post(this.config.urls.submit, form.serialize())
                .done(function(response) {
                    if (response.success) {
                        self.showSuccessMessage(response.message, response.increment_id);
                        form.hide();
                    } else {
                        self.showErrorMessage(response.message || $t('Error creating order'));
                        submitBtn.prop('disabled', false);
                    }
                })
                .fail(function() {
                    self.showErrorMessage($t('Connection error. Please try again.'));
                    submitBtn.prop('disabled', false);
                })
                .always(function() {
                    loadingOverlay.hide();
                });
        },

        /**
         * Show success message
         * @param {string} message - Success message
         * @param {string} orderNumber - Order increment ID
         */
        showSuccessMessage: function(message, orderNumber) {
            $('#success-text').text(message);
            $('#order-number').text(orderNumber);
            $('#success-message').show();
            this.scrollToElement($('#success-message'));
        },

        /**
         * Show error message
         * @param {string} message - Error message
         */
        showErrorMessage: function(message) {
            $('#error-text').text(message);
            $('#error-message').show();
            this.scrollToElement($('#error-message'));
        },

        /**
         * Hide all messages
         */
        hideMessages: function() {
            $('#success-message, #error-message').hide();
        },

        /**
         * Scroll to element
         * @param {jQuery} element - Element to scroll to
         */
        scrollToElement: function(element) {
            $('html, body').animate({
                scrollTop: element.offset().top - 50
            }, 500);
        },

        /**
         * Format price
         * @param {number} price - Price to format
         * @return {string} Formatted price
         */
        formatPrice: function(price) {
            // الحصول على إعدادات العملة من window.easyorderConfig إذا كانت متوفرة
            var currencySymbol = (window.easyorderConfig && window.easyorderConfig.currency) 
                ? window.easyorderConfig.currency.symbol 
                : '$'; // fallback
            var precision = (window.easyorderConfig && window.easyorderConfig.currency) 
                ? window.easyorderConfig.currency.precision 
                : 2;
            
            var formattedPrice = parseFloat(price).toFixed(precision);
            return currencySymbol + formattedPrice;
        },

        /**
         * Check form validity and update submit button state
         */
        checkFormValidity: function() {
            var form = $('#easyorder-form');
            var isValid = true;
            
            // Check required fields
            form.find('input[required], select[required], textarea[required]').each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    return false;
                }
            });
            
            // Check shipping method selection
            if (!$('input[name="shipping_method"]:checked').length) {
                isValid = false;
            }
            
            // Check payment method selection
            if (!$('input[name="payment_method"]:checked').length) {
                isValid = false;
            }
            
            $('#easyorder-submit-btn').prop('disabled', !isValid);
            return isValid;
        }
    };
});

// Initialize form validation when document is ready
$(document).ready(function() {
    // تحديث التحقق من صحة النموذج عند تغيير أي حقل
    var form = $('#easyorder-form');
    if (form.length) {
        form.on('change keyup', 'input, select, textarea', function() {
            // استخدام الدالة من الكائن الرئيسي
            if (window.easyOrderWidget && typeof window.easyOrderWidget.checkFormValidity === 'function') {
                window.easyOrderWidget.checkFormValidity();
            }
        });
    }
});