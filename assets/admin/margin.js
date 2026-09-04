(function($) {
    const state = {
        csv: '',
    };

    const $run = $('#fs-margin-run');
    const $copy = $('#fs-margin-copy');
    const $organization = $('#fs-margin-organization');
    const $dateFrom = $('#fs-margin-date-from');
    const $dateTo = $('#fs-margin-date-to');
    const $importOnly = $('#fs-margin-import-only');
    const $excludeIds = $('#fs-margin-exclude-ids');
    const $loader = $('#fs-margin-loader');
    const $message = $('#fs-margin-message');
    const $summary = $('#fs-margin-summary');
    const $details = $('#fs-margin-details');
    const $tableWrap = $('#fs-margin-table-wrap');
    const $results = $('#fs-margin-results');
    const $total = $('#fs-margin-total');

    function formatMoney(value) {
        const number = Number(value || 0);
        return number.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' PLN';
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString(undefined, {
            maximumFractionDigits: 4,
        });
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setLoading(isLoading) {
        $run.prop('disabled', isLoading);
        $loader.prop('hidden', !isLoading);
        if (isLoading) {
            $message.prop('hidden', true).removeClass('is-error');
        }
    }

    function renderMessage(text, isError) {
        $message.text(text).toggleClass('is-error', Boolean(isError)).prop('hidden', false);
    }

    function initOrganizationSelect() {
        if (!$.fn.select2) {
            return;
        }

        $organization.select2({
            width: 'resolve',
            minimumResultsForSearch: 0,
            dropdownAutoWidth: true,
        });
    }

    function renderSummary(data) {
        const totals = data.totals || {};
        const meta = data.meta || {};
        const items = [
            ['Profit', formatMoney(totals.profit)],
            ['Purchase net total', formatMoney(totals.purchase_cost)],
            ['Revenue net', formatMoney(totals.revenue)],
            ['Revenue gross', formatMoney(totals.gross_revenue)],
            ['Margin', Number(totals.margin_percent || 0).toFixed(2) + '%'],
            ['Factures', formatNumber(meta.facture_count)],
            ['Products without purchase price', formatNumber(totals.inactive_products)],
        ];

        $summary.html(items.map(function(item) {
            return '<div class="fs-margin-card"><span>' + escapeHtml(item[0]) + '</span><strong>' + escapeHtml(item[1]) + '</strong></div>';
        }).join('')).prop('hidden', false);
    }

    function renderDetails(data) {
        const details = data.buyer_details || {};
        const organizations = details.organizations || [];
        const nips = details.nips || [];
        const countries = details.countries || [];
        const addresses = details.addresses || [];
        const factures = details.factures || [];
        const dateRange = data.date_range || {};

        if (!organizations.length && !factures.length && !dateRange.from && !dateRange.to) {
            $details.prop('hidden', true).empty();
            return;
        }

        const detailBlocks = [
            ['Organization variants', organizations],
            ['NIP', nips],
            ['Country', countries],
            ['Addresses', addresses],
        ].filter(function(block) {
            return block[1].length;
        }).map(function(block) {
            return '<div><span>' + escapeHtml(block[0]) + '</span><p>' + escapeHtml(block[1].join(' | ')) + '</p></div>';
        }).join('');

        const facturePreview = factures.slice(0, 8).map(function(item) {
            return escapeHtml(item.invoice + (item.date ? ' (' + item.date + ')' : ''));
        }).join(', ');
        const factureMore = factures.length > 8 ? ' +' + (factures.length - 8) : '';
        const dateText = (dateRange.from || dateRange.to)
            ? escapeHtml((dateRange.from || '...') + ' - ' + (dateRange.to || '...'))
            : 'All dates';

        $details.html(
            '<div><span>Date range</span><p>' + dateText + '</p></div>' +
            detailBlocks +
            (factures.length ? '<div><span>Included factures</span><p>' + facturePreview + escapeHtml(factureMore) + '</p></div>' : '')
        ).prop('hidden', false);
    }

    function renderRows(factures, totals) {
        $results.empty();

        factures.forEach(function(facture) {
            const subtotal = facture.subtotal || {};
            const subtotalProfitClass = Number(subtotal.profit) < 0 ? 'is-negative' : 'is-positive';
            const title = (facture.is_corrective ? 'Corrective - ' : '') + (facture.invoice || ('#' + facture.id)) + (facture.date ? ' - ' + facture.date : '');
            const buyer = facture.buyer ? '<small>' + escapeHtml(facture.buyer) + '</small>' : '';
            const invoiceClass = facture.is_corrective ? ' fs-margin-corrective-row' : '';

            $results.append(
                '<tr class="fs-margin-invoice-row' + invoiceClass + '">' +
                    '<td colspan="10"><strong>' + escapeHtml(title) + '</strong>' + buyer + '</td>' +
                '</tr>'
            );

            (facture.products || []).forEach(function(row) {
                const excluded = Boolean(row.excluded_from_calculation);
                const inactive = !row.has_purchase_price || excluded;
                const margin = excluded ? 'Excluded' : (row.margin_percent === null ? 'Missing price' : Number(row.margin_percent).toFixed(2) + '%');
                const purchase = row.has_purchase_price ? formatMoney(row.purchase_price) : 'No purchase price';
                const profitClass = Number(row.profit) < 0 ? 'is-negative' : 'is-positive';
                const rowClass = (inactive ? 'is-inactive' : '') + (excluded ? ' is-excluded' : '');
                const note = excluded ? '<small>Excluded from calculation: negative quantity</small>' : '';

                $results.append(
                    '<tr class="' + rowClass + '">' +
                        '<td><strong>' + escapeHtml(row.name) + '</strong><small>ID ' + escapeHtml(row.product_id) + '</small>' + note + '</td>' +
                        '<td>' + escapeHtml(row.sku || '-') + '</td>' +
                        '<td>' + escapeHtml(formatNumber(row.quantity)) + '</td>' +
                        '<td>' + escapeHtml(formatMoney(row.avg_sale_price)) + '</td>' +
                        '<td>' + escapeHtml(formatMoney(row.avg_sale_price_gross)) + '</td>' +
                        '<td>' + escapeHtml(purchase) + '</td>' +
                        '<td>' + escapeHtml(formatMoney(row.revenue)) + '</td>' +
                        '<td>' + escapeHtml(formatMoney(row.gross_revenue)) + '</td>' +
                        '<td class="' + profitClass + '">' + (inactive ? '-' : escapeHtml(formatMoney(row.profit))) + '</td>' +
                        '<td>' + escapeHtml(margin) + '</td>' +
                    '</tr>'
                );
            });

            $results.append(
                '<tr class="fs-margin-subtotal-row">' +
                    '<td colspan="2"><strong>Facture subtotal</strong><small>' + escapeHtml(formatNumber(subtotal.inactive_products || 0)) + ' products without purchase price, ' + escapeHtml(formatNumber(subtotal.excluded_products || 0)) + ' excluded</small></td>' +
                    '<td>' + escapeHtml(formatNumber(subtotal.quantity)) + '</td>' +
                    '<td></td>' +
                    '<td></td>' +
                    '<td>' + escapeHtml(formatMoney(subtotal.purchase_cost)) + '</td>' +
                    '<td>' + escapeHtml(formatMoney(subtotal.revenue)) + '</td>' +
                    '<td>' + escapeHtml(formatMoney(subtotal.gross_revenue)) + '</td>' +
                    '<td class="' + subtotalProfitClass + '">' + escapeHtml(formatMoney(subtotal.profit)) + '</td>' +
                    '<td>' + escapeHtml(Number(subtotal.margin_percent || 0).toFixed(2)) + '%</td>' +
                '</tr>'
            );
        });

        $total.html(
            '<tr>' +
                '<th colspan="2">Total calculated products</th>' +
                '<th>' + escapeHtml(formatNumber(totals.quantity)) + '</th>' +
                '<th></th>' +
                '<th></th>' +
                '<th>' + escapeHtml(formatMoney(totals.purchase_cost)) + '</th>' +
                '<th>' + escapeHtml(formatMoney(totals.revenue)) + '</th>' +
                '<th>' + escapeHtml(formatMoney(totals.gross_revenue)) + '</th>' +
                '<th>' + escapeHtml(formatMoney(totals.profit)) + '</th>' +
                '<th>' + escapeHtml(Number(totals.margin_percent || 0).toFixed(2)) + '%</th>' +
            '</tr>'
        );

        $tableWrap.prop('hidden', false);
    }

    function buildCsv(factures) {
        const header = ['Facture', 'Facture status', 'Facture date', 'Buyer', 'Product ID', 'Product', 'SKU', 'Quantity', 'Sale Net Price', 'Avg sale gross', 'Purchase net', 'Revenue net', 'Revenue gross', 'Profit', 'Margin', 'Calculation status'];
        const lines = [];

        factures.forEach(function(facture) {
            (facture.products || []).forEach(function(row) {
                lines.push([
                    facture.invoice || facture.id,
                    facture.is_corrective ? 'Corrective' : (facture.status || ''),
                    facture.date || '',
                    facture.buyer || '',
                    row.product_id,
                    row.name,
                    row.sku || '',
                    row.quantity,
                    row.avg_sale_price,
                    row.avg_sale_price_gross,
                    row.has_purchase_price ? row.purchase_price : '',
                    row.revenue,
                    row.gross_revenue,
                    row.has_purchase_price ? row.profit : '',
                    row.margin_percent === null ? '' : row.margin_percent,
                    row.excluded_from_calculation ? 'Excluded: negative quantity' : 'Calculated',
                ]);
            });
        });

        return [header].concat(lines).map(function(line) {
            return line.map(function(cell) {
                return '"' + String(cell === null || cell === undefined ? '' : cell).replace(/"/g, '""') + '"';
            }).join(',');
        }).join('\n');
    }

    function renderReport(data) {
        const factures = data.factures || [];

        if (!factures.length) {
            $summary.prop('hidden', true);
            $details.prop('hidden', true).empty();
            $tableWrap.prop('hidden', true);
            $copy.prop('disabled', true);
            renderMessage(fsFactureMargin.i18n.empty, false);
            return;
        }

        renderSummary(data);
        renderDetails(data);
        renderRows(factures, data.totals || {});
        state.csv = buildCsv(factures);
        $copy.prop('disabled', false);
        $message.prop('hidden', true);
    }

    $run.on('click', function() {
        setLoading(true);
        $summary.prop('hidden', true);
        $details.prop('hidden', true).empty();
        $tableWrap.prop('hidden', true);
        $copy.prop('disabled', true);

        $.post(fsFactureMargin.ajaxUrl, {
            action: fsFactureMargin.action,
            nonce: fsFactureMargin.nonce,
            organization: $organization.val(),
            date_from: $dateFrom.val(),
            date_to: $dateTo.val(),
            import_only: $importOnly.is(':checked') ? 1 : 0,
            exclude_ids: $excludeIds.val(),
        }).done(function(response) {
            if (!response || !response.success) {
                renderMessage((response && response.data && response.data.message) || fsFactureMargin.i18n.error, true);
                return;
            }

            renderReport(response.data);
        }).fail(function() {
            renderMessage(fsFactureMargin.i18n.error, true);
        }).always(function() {
            setLoading(false);
        });
    });

    initOrganizationSelect();

    $copy.on('click', function() {
        if (!state.csv || !navigator.clipboard) {
            return;
        }

        navigator.clipboard.writeText(state.csv);
        $copy.text('Copied');
        window.setTimeout(function() {
            $copy.text('Copy CSV');
        }, 1400);
    });
})(jQuery);
