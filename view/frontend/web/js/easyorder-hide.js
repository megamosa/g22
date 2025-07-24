define([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';
    
    return {
        init: function(shouldHideOptions) {
            if (shouldHideOptions) {
                // إخفاء زر أضف للسلة الأساسي
                $('.product-add-form .box-tocart').hide();
                
                // إخفاء أي عناصر أخرى متعلقة بخيارات المنتج
                $('.product-options-wrapper').hide();
                $('.product-options-bottom').hide();
                
                // إخفاء خيارات المنتج في EasyOrder
                $('.easyorder-product-options').addClass('easyorder-hidden-field');
            }
        }
    };
});