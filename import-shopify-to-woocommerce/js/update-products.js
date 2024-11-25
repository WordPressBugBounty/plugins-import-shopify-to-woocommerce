jQuery(document).ready(function ($) {
    'use strict';

    $('.s2w-shopify-update-product').on('click', function () {
        s2w_update_product_options_show();
        $('.s2w-update-product-options-button-update').removeClass('s2w-hidden');
        $('.s2w-update-product-options-button-save').removeClass('s2w-hidden');
    });


    $('.s2w-update-product-options-button-cancel').on('click', function () {
        s2w_update_product_options_hide();
    });
    $('.s2w-update-product-options-close').on('click', function () {
        s2w_update_product_options_hide();
    });
    $('.s2w-overlay').on('click', function () {
        s2w_update_product_options_hide();
    });
    /*Metafields*/
    $('#s2w-update-product-options-metafields').on('change', function () {
        if ($(this).prop('checked')) {
            $('.s2w-product-metafields-mapping').removeClass('s2w-hidden');
        } else {
            $('.s2w-product-metafields-mapping').addClass('s2w-hidden');
        }
    }).trigger('change');
    $(document).on('click', '.s2w-product-metafields-duplicate', function () {
        let $button = $(this), $row = $button.closest('tr'),
            $new_row = $row.clone();
        $new_row.insertAfter($row)
    });
    $(document).on('click', '.s2w-product-metafields-remove', function () {
        let $button = $(this), $container = $button.closest('tbody'), $rows = $container.find('tr'),
            $row = $button.closest('tr');
        if ($rows.length > 1) {
            $row.fadeOut(300);
            setTimeout(function () {
                $row.remove();
            }, 300)
        } else {
            $row.find('.s2w-update_product_metafields_from').val('');
            $row.find('.s2w-update_product_metafields_to').val('');
        }
    });

    function s2w_update_product_options_hide() {
        $('.s2w-button-edit').removeClass('s2w-button-editing');
        $('.s2w-update-product-options-container').addClass('s2w-hidden');
        $('.s2w-update-product-options-content-footer').find('.button-primary').addClass('s2w-hidden');
    }

    function s2w_update_product_options_show() {
        $('.s2w-update-product-options-container').removeClass('s2w-hidden');
    }
});
