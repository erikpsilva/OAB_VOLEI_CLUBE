$(function () {

    $('#btnSalvarConfig').on('click', function () {
        var $btn      = $(this);
        var $feedback = $('#configFeedback');

        var emailAdmin   = $.trim($('#emailAdmin').val());
        var emailEsperia = $.trim($('#emailEsperia').val());
        var disparoDia   = $('#disparoDia').val();
        var disparoHora  = $.trim($('#disparoHora').val());

        // Validações básicas
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailAdmin)) {
            return mostrarFeedback('E-mail da Coordenação inválido.', 'error');
        }
        if (!emailRegex.test(emailEsperia)) {
            return mostrarFeedback('E-mail do Clube Esperia inválido.', 'error');
        }
        if (!disparoHora) {
            return mostrarFeedback('Informe o horário de disparo.', 'error');
        }

        $btn.prop('disabled', true).text('Salvando...');
        $feedback.hide();

        $.ajax({
            url:    ADMIN_BASE_URL + '/services/salvar_configuracoes.php',
            method: 'POST',
            data: {
                email_admin:        emailAdmin,
                email_esperia:      emailEsperia,
                disparo_dia_semana: disparoDia,
                disparo_hora:       disparoHora,
            },
            success: function (res) {
                mostrarFeedback(res.message, 'success');
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'Erro ao salvar configurações.';
                mostrarFeedback(msg, 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Salvar configurações');
            }
        });
    });

    function mostrarFeedback(msg, tipo) {
        $('#configFeedback')
            .removeClass('--success --error')
            .addClass('--' + tipo)
            .text(msg)
            .show();
    }

});
