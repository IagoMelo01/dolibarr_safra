jQuery(function ($) {
    function loadLinkedProducts(productId) {
        if (!productId) {
            $('#safra-linked-tecnico').empty();
            $('#safra-linked-formulado').empty();
            return;
        }

        $.getJSON(dolibarr_safra_aplicacao.ajax_url, { action: 'linkedoptions', product_id: productId })
            .done(function (response) {
                var tecnicoSelect = $('#safra-linked-tecnico');
                var formuladoSelect = $('#safra-linked-formulado');

                tecnicoSelect.empty();
                formuladoSelect.empty();

                tecnicoSelect.append($('<option>', { value: '', text: tecnicoSelect.data('placeholder') }));
                formuladoSelect.append($('<option>', { value: '', text: formuladoSelect.data('placeholder') }));

                if (response && $.isArray(response.tecnico)) {
                    $.each(response.tecnico, function (_, item) {
                        tecnicoSelect.append($('<option>', {
                            value: item.id,
                            text: item.ref + ' - ' + item.label
                        }));
                    });
                }

                if (response && $.isArray(response.formulado)) {
                    $.each(response.formulado, function (_, item) {
                        formuladoSelect.append($('<option>', {
                            value: item.id,
                            text: item.ref + ' - ' + item.label
                        }));
                    });
                }

                tecnicoSelect.val('').trigger('change.select2');
                formuladoSelect.val('').trigger('change.select2');
                updateProductType();
            });
    }

    function updateTotals() {
        var dose = parseFloat($('#safra-dose').val());
        var area = parseFloat($('#safra-area').val());
        if (isNaN(dose) || isNaN(area)) {
            $('#safra-total').val('');
            return;
        }
        var total = dose * area;
        $('#safra-total').val(total.toFixed(4));
    }

    function updateProductType() {
        var tecnicoVal = $('#safra-linked-tecnico').val();
        var formuladoVal = $('#safra-linked-formulado').val();
        var type = 'product';

        if (tecnicoVal) {
            type = 'tecnico';
            if (formuladoVal) {
                $('#safra-linked-formulado').val('').trigger('change.select2');
                formuladoVal = '';
            }
        } else if (formuladoVal) {
            type = 'formulado';
            $('#safra-linked-tecnico').val('').trigger('change.select2');
        }

        $('#safra-product-type').val(type);
    }

    function openCaldaDialog() {
        var dialog = $('#safra-calc-calda');
        dialog.dialog({
            modal: true,
            width: 500,
            buttons: [
                {
                    text: dialog.data('save-label'),
                    class: 'butAction',
                    click: function () {
                        var taxa = parseFloat($('#calc-taxa').val()) || 0;
                        var tanque = parseFloat($('#calc-tanque').val()) || 0;

                        var resultadoArea = 0;
                        if (taxa > 0) {
                            resultadoArea = tanque / taxa;
                        }
                        $('#calc-area-por-tanque').text(resultadoArea.toFixed(2));

                        var list = $('#calc-products');
                        list.empty();
                        if (resultadoArea > 0 && Array.isArray(dolibarr_safra_aplicacao.lines)) {
                            $.each(dolibarr_safra_aplicacao.lines, function (_, item) {
                                var dose = parseFloat(item.dose) || 0;
                                var qty = dose * resultadoArea;
                                var unit = item.dose_unit ? item.dose_unit.replace('/ha', '') : '';
                                var label = item.label + ': ' + qty.toFixed(2) + ' ' + unit;
                                list.append($('<li>').text(label));
                            });
                        }

                        $(this).dialog('close');
                    }
                },
                {
                    text: dialog.data('cancel-label'),
                    class: 'butActionRefused',
                    click: function () {
                        $(this).dialog('close');
                    }
                }
            ]
        });
    }

    $('#safra-product').on('change', function () {
        $('#safra-product-type').val('product');
        loadLinkedProducts($(this).val());
    });

    $('#safra-dose, #safra-area').on('input change', updateTotals);

    $('#safra-linked-tecnico').on('change', function () {
        if ($(this).val()) {
            $('#safra-linked-formulado').val('').trigger('change.select2');
        }
        updateProductType();
    });

    $('#safra-linked-formulado').on('change', function () {
        if ($(this).val()) {
            $('#safra-linked-tecnico').val('').trigger('change.select2');
        }
        updateProductType();
    });

    $('#safra-open-calc-calda').on('click', function (e) {
        e.preventDefault();
        openCaldaDialog();
    });

    if ($('#safra-product').val()) {
        loadLinkedProducts($('#safra-product').val());
    }

    updateProductType();
});
