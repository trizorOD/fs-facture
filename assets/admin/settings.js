(function($) {
    const $select = $('#fs-settings-product-search');
    const $add = $('#fs-settings-add-product');
    const $message = $('#fs-settings-message');
    const $tbody = $('#fs-settings-products');
    const $count = $('#fs-settings-import-count');

    function renderMessage(text, isError) {
        $message.text(text).toggleClass('is-error', Boolean(isError)).prop('hidden', false);
    }

    function clearMessage() {
        $message.prop('hidden', true).removeClass('is-error').text('');
    }

    function selectedProductId() {
        return Number($select.val() || 0);
    }

    function initProductSelect() {
        const selectPlugin = $.fn.select2 ? 'select2' : ($.fn.selectWoo ? 'selectWoo' : null);

        if (!selectPlugin) {
            return;
        }

        $select[selectPlugin]({
            width: 'resolve',
            minimumInputLength: 2,
            placeholder: fsFactureSettings.i18n.placeholder,
            ajax: {
                url: fsFactureSettings.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: fsFactureSettings.actions.search,
                        nonce: fsFactureSettings.nonce,
                        term: params.term || '',
                        page: params.page || 1,
                    };
                },
                processResults: function(data) {
                    return data || { results: [] };
                },
            },
            language: {
                searching: function() {
                    return fsFactureSettings.i18n.searching;
                },
                inputTooShort: function() {
                    return fsFactureSettings.i18n.inputTooShort;
                },
                noResults: function() {
                    return fsFactureSettings.i18n.empty;
                },
            },
        });
    }

    $select.on('change', function() {
        $add.prop('disabled', !selectedProductId());
    });

    $add.on('click', function() {
        const productId = selectedProductId();
        if (!productId) {
            return;
        }

        clearMessage();
        $add.prop('disabled', true);

        $.post(fsFactureSettings.ajaxUrl, {
            action: fsFactureSettings.actions.add,
            nonce: fsFactureSettings.nonce,
            product_id: productId,
        }).done(function(response) {
            if (!response || !response.success) {
                renderMessage((response && response.data && response.data.message) || fsFactureSettings.i18n.addError, true);
                return;
            }

            $tbody.find('.fs-settings-empty-row').remove();
            $tbody.append(response.data.row);
            $count.text(response.data.count);
            $select.val(null).trigger('change');
        }).fail(function() {
            renderMessage(fsFactureSettings.i18n.addError, true);
        }).always(function() {
            $add.prop('disabled', !selectedProductId());
        });
    });

    $tbody.on('click', '.fs-settings-remove-product', function() {
        const $button = $(this);
        const productId = Number($button.data('product-id') || 0);
        if (!productId || !window.confirm(fsFactureSettings.i18n.confirmRemove)) {
            return;
        }

        clearMessage();
        $button.prop('disabled', true);

        $.post(fsFactureSettings.ajaxUrl, {
            action: fsFactureSettings.actions.remove,
            nonce: fsFactureSettings.nonce,
            product_id: productId,
        }).done(function(response) {
            if (!response || !response.success) {
                renderMessage((response && response.data && response.data.message) || fsFactureSettings.i18n.removeError, true);
                $button.prop('disabled', false);
                return;
            }

            $tbody.find('tr[data-product-id="' + productId + '"]').remove();
            $count.text(response.data.count);

            if (!$tbody.find('tr').length) {
                $tbody.append(
                    $('<tr class="fs-settings-empty-row"></tr>').append(
                        $('<td></td>').attr('colspan', 4).text(fsFactureSettings.i18n.emptyList)
                    )
                );
            }
        }).fail(function() {
            renderMessage(fsFactureSettings.i18n.removeError, true);
            $button.prop('disabled', false);
        });
    });

    initProductSelect();
})(jQuery);
