(function($){


    jQuery(document).on('change', '.acf-field[data-name="product_item_facture"] select', function(){

        const productId = $(this).val();

        const container = $(this).closest('tr')

        if (!productId) return; 



        $.post(ajaxurl, {

            action: 'acf_get_product_data',

            product_id: productId

        }, function(response) {

            if(response.success){

                $(container).find('.acf-field[data-name="current_price_product_item_facture"] input').val(response.data.price);

                $(container).find('.acf-field[data-name="stock_product_item_facture"] input').val(response.data.stock_quantity);

                setDisabledVat()

            }

        });

    });

    setDisabledVat()

})(jQuery); 

function setDisabledVat() {
    jQuery("td[data-name='vat_rate_product_item_facture']").each(function() {
        jQuery(this).find('input').prop('disabled', true)
    })
}