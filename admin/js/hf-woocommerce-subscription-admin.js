// subscription product JS
jQuery(document).ready(function ($) {
    'use strict';

    if ("undefined" == typeof HFSubscriptions_OBJ)
        return!1;
    var obj = function () {
        $(document).on("click", ".post-type-hf_shop_subscription .wp-list-table tbody td", this.TableRowClick)
    };
    
    obj.prototype.TableRowClick = function (obj) {
        if ($(obj.target).filter("a, a *, .no-link, .no-link *").length)
            return!0;
        var a = $(this).closest("tr").find("a.subscription-view").attr("href");
        a.length && (obj.preventDefault(), obj.metaKey || obj.ctrlKey ? window.open(a, "_blank") : window.location = a)
    },
    
    new obj

    /*
    $( document ).on( 'click', '.notice-dismiss', function () {
        
        // Make an AJAX call
        // Since WP 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        $.ajax( ajaxurl,
          {
            type: 'POST',
            data: {
              action: 'dismissed_notice_handler',
              type: true,
            }
          } );
      } );
      */

    $.extend({
        showHideSubscriptionMetaData: function () {
            if ($('select#product-type').val() == HFSubscriptions_OBJ.ProductType) {
                $('.show_if_simple').show();
                $('.grouping_options').hide();
                $('.options_group.pricing ._regular_price_field').hide();
                $('#sale-price-period').show();
                $('.hide_if_subscription').hide();
                $('input#_manage_stock').change();

                if ('day' == $('#_subscription_period').val()) {
                    $('.subscription_sync').hide();
                }
            } else {
                $('.options_group.pricing ._regular_price_field').show();
                $('#sale-price-period').hide();
            }
        },

        setSubscriptionLengths: function () {
            $('[name^="_subscription_length"]').each(function () {
                var $lengthElement = $(this),
                        selectedLength = $lengthElement.val(),
                        hasSelectedLength = false,
                        periodSelector;
                var billingInterval;
                    periodSelector = '#_subscription_period';
                    billingInterval = parseInt($('#_subscription_period_interval').val());

                $lengthElement.empty();

                $.each(HFSubscriptions_OBJ.LocalizedSubscriptionLengths[ $(periodSelector).val() ], function (length, description) {
                    if (parseInt(length) == 0 || 0 == (parseInt(length) % billingInterval)) {
                        $lengthElement.append($('<option></option>').attr('value', length).text(description));
                    }
                });

                $lengthElement.children('option').each(function () {
                    if (this.value == selectedLength) {
                        hasSelectedLength = true;
                        return false;
                    }
                });

                if (hasSelectedLength) {
                    $lengthElement.val(selectedLength);
                } else {
                    $lengthElement.val(0);
                }

            });
        },

        setSalePeriod: function () {
            $('#sale-price-period').fadeOut(80, function () {
                $('#sale-price-period').text($('#_subscription_period_interval option:selected').text() + ' ' + $('#_subscription_period option:selected').text());
                $('#sale-price-period').fadeIn(180);
            });
        },

        showHideSubscriptionsPanels: function () {
            var tab = $('div.panel-wrap').find('ul.wc-tabs li').eq(0).find('a');
            var panel = tab.attr('href');
            var visible = $(panel).children('.options_group').filter(function () {
                return 'none' != $(this).css('display');
            });
            if (0 != visible.length) {
                tab.click().parent().show();
            }
        },

        getParamByName: function (name) {
            name = name.replace(/[\[]/, '\\\[').replace(/[\]]/, '\\\]');
            var regexS = '[\\?&]' + name + '=([^&#]*)';
            var regex = new RegExp(regexS);
            var results = regex.exec(window.location.search);
            if (results == null) {
                return '';
            } else {
                return decodeURIComponent(results[1].replace(/\+/g, ' '));
            }
        },
    });

    $('.options_group.pricing ._sale_price_field .description').prepend('<span id="sale-price-period" style="display: none;"></span>');
    $('.options_group.subscription_pricing').not('.variable_subscription_pricing .options_group.subscription_pricing').insertBefore($('.options_group.pricing:first'));
    $('.show_if_subscription.clear').insertAfter($('.options_group.subscription_pricing'));


    if ($('.options_group.pricing').length > 0) {
        $.setSalePeriod();
        $.showHideSubscriptionMetaData();

        $.setSubscriptionLengths();
        $.showHideSubscriptionsPanels();
    }

    $('#woocommerce-product-data').on('change', '[name^="_subscription_period"], [name^="_subscription_period_interval"]', function () {
        $.setSubscriptionLengths();
        $.setSalePeriod();
    });

    $('body').bind('woocommerce-product-type-change', function () {
        $.showHideSubscriptionMetaData();

        $.showHideSubscriptionsPanels();
    });

    $('input#_downloadable, input#_virtual').change(function () {
        $.showHideSubscriptionMetaData();

    });


    if ($.getParamByName('select_subscription') == 'true') {
        $('select#product-type option[value="' + HFSubscriptions_OBJ.ProductType + '"]').attr('selected', 'selected');
        $('select#product-type').select().change();
    }

    $('#posts-filter').submit(function () {
        if ($('[name="post_type"]').val() == 'shop_order' && ($('[name="action"]').val() == 'trash' || $('[name="action2"]').val() == 'trash')) {
            var containsSubscription = false;
            $('[name="post[]"]:checked').each(function () {
                if (true === $('.contains_subscription', $('#post-' + $(this).val())).data('contains_subscription')) {
                    containsSubscription = true;
                }
                return (false === containsSubscription);
            });
            if (containsSubscription) {
                return confirm(HFSubscriptions_OBJ.BulkTrashWarning);
            }
        }
    });

    $('.order_actions .submitdelete').click(function () {
        if ($('[name="contains_subscription"]').val() == 'true') {
            return confirm(HFSubscriptions_OBJ.TrashWarning);
        }
    });

    $('.row-actions .submitdelete').click(function () {
        var order = $(this).closest('.type-shop_order').attr('id');
        if (true === $('.contains_subscription', $('#' + order)).data('contains_subscription')) {
            return confirm(HFSubscriptions_OBJ.TrashWarning);
        }
    });

    $(window).load(function () {
        if ($('[name="contains_subscription"]').length > 0 && $('[name="contains_subscription"]').val() == 'true') {
            $('#woocommerce-order-totals').show();
        } else {
            $('#woocommerce-order-totals').hide();
        }
    });


    $('#general_product_data').on('change', '[name^="_hf_subscription_price"]', function () {
        $('[name="_regular_price"]').val($(this).val());
    });

    $('.users-php .submitdelete').on('click', function () {
        return confirm(HFSubscriptions_OBJ.DeleteUserWarning);
    });


    $('.hf_payment_method_selector').on('change', function () {
        var payment_method = $(this).val();
        $('.hf_payment_method_meta_fields').hide();
        $('#hf_' + payment_method + '_fields').show();
    });


});