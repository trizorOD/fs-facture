jQuery(document).ready(function ($) {
    var btn = $('#fs-facture-download-csv');
    var msg = $('#fs-facture-csv-message');

    if (!btn.length) {
        return;
    }

    btn.on('click', function () {
        btn.prop('disabled', true).text('Reading stock...');
        msg.hide().text('').css('color', '');

        $.ajax({
            url: fsDotyposCSV.ajaxUrl,
            type: 'POST',
            data: {
                action: fsDotyposCSV.action,
                nonce: fsDotyposCSV.nonce,
                facture_id: btn.data('facture-id'),
            },
            success: function (response) {
                btn.prop('disabled', false).text('Download CSV');

                if (!response.success) {
                    msg.css('color', '#cc0000').text(response.data.message).show();
                    return;
                }

                if (response.data.warnings && response.data.warnings.length) {
                    msg.css('color', '#b26a00')
                        .text('Skipped ' + response.data.warnings.length + ' product(s): ' + response.data.warnings.join('; '))
                        .show();
                }

                var blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', response.data.filename);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            },
            error: function () {
                btn.prop('disabled', false).text('Download CSV');
                msg.css('color', '#cc0000').text('Server error. Please try again.').show();
            },
        });
    });
});
