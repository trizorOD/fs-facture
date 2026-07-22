(function($){

    $(document).on('click', '#fs-facture-import-organizations', function(e){

        e.preventDefault();

        const $button = $(this);
        const $result = $('#fs-facture-import-result');

        $button.prop('disabled', true);
        $result.text(fsFactureOrganizationImport.i18n.loading);

        $.post(fsFactureOrganizationImport.ajaxUrl, {

            action: fsFactureOrganizationImport.action,

            nonce: fsFactureOrganizationImport.nonce

        }, function(response) {

            $button.prop('disabled', false);

            if (response.success) {
                $result.text(
                    fsFactureOrganizationImport.i18n.done
                        .replace('%created%', response.data.created)
                        .replace('%skipped%', response.data.skipped)
                );
            } else {
                $result.text(fsFactureOrganizationImport.i18n.error);
            }

        });

    });

})(jQuery);
