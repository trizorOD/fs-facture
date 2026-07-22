(function($){

    let currentOrganizationId = null;

    function clearBuyerFields(container) {
        container.find('.acf-field[data-name="buyer_organization"] textarea').val('');
        container.find('.acf-field[data-name="buyer_nip"] input').val('');
        container.find('.acf-field[data-name="buyer_country_code"] input').val('');
        container.find('.acf-field[data-name="buyer_street"] input').val('');
        container.find('.acf-field[data-name="buyer_city"] input').val('');
        container.find('.acf-field[data-name="buyer_postal_code"] input').val('');
    }

    jQuery(document).on('change', '.acf-field[data-name="buyer_organization_ref"] select', function(){

        const organizationId = $(this).val();

        const container = $(this).closest('.acf-field[data-name="buyer_group"]');

        currentOrganizationId = organizationId;

        if (!organizationId) {
            clearBuyerFields(container);
            return;
        }

        $.post(fsFactureOrganization.ajaxUrl, {

            action: fsFactureOrganization.action,

            nonce: fsFactureOrganization.nonce,

            organization_id: organizationId

        }, function(response) {

            // A later selection may have already fired and changed
            // currentOrganizationId while this request was in flight —
            // discard a stale response so it can't overwrite newer data.
            if (organizationId !== currentOrganizationId) {
                return;
            }

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
