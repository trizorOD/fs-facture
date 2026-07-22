(function($){

    jQuery(document).on('change', '.acf-field[data-name="buyer_organization_ref"] select', function(){

        const organizationId = $(this).val();

        const container = $(this).closest('.acf-field[data-name="buyer_group"]');

        if (!organizationId) return;

        $.post(fsFactureOrganization.ajaxUrl, {

            action: fsFactureOrganization.action,

            nonce: fsFactureOrganization.nonce,

            organization_id: organizationId

        }, function(response) {

            if (response.success) {

                container.find('.acf-field[data-name="buyer_organization"] textarea').val(response.data.organization);
                container.find('.acf-field[data-name="buyer_nip"] input').val(response.data.nip);
                container.find('.acf-field[data-name="buyer_country_code"] input').val(response.data.country_code);
                container.find('.acf-field[data-name="buyer_street"] input').val(response.data.street);
                container.find('.acf-field[data-name="buyer_city"] input').val(response.data.city);
                container.find('.acf-field[data-name="buyer_postal_code"] input').val(response.data.postal_code);

            }

        });

    });

})(jQuery);
