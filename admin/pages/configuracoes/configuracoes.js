$(function () {

    $('#btnSalvarConfig').on('click', function () {
        var $btn      = $(this);
        var $feedback = $('#configFeedback');

        var emailAdmin   = $.trim($('#emailAdmin').val());
        var emailEsperia = $.trim($('#emailEsperia').val());
        var disparoDia   = $('#disparoDia').val();
        var disparoHora  = $.trim($('#disparoHora').val());
        var maxVagas     = parseInt($('#maxVagas').val(), 10);
        var modoAbertura = $('#modoAbertura').val();

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
        if (isNaN(maxVagas) || maxVagas < 1) {
            return mostrarFeedback('Informe um número válido de vagas (mínimo 1).', 'error');
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
                max_vagas:            maxVagas,
                modo_abertura_agenda: modoAbertura,
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

    // ── Mostrar/ocultar seção manual ao mudar modo ────────────
    $('#modoAbertura').on('change', function () {
        if ($(this).val() === 'manual') {
            $('.configAgendaManual').show();
        } else {
            $('.configAgendaManual').hide();
        }
    });

    // ── Liberar agenda manualmente ─────────────────────────────
    $('#btnLiberarAgenda').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Liberando...');

        $.ajax({
            url:    ADMIN_BASE_URL + '/services/liberar_agenda.php',
            method: 'POST',
            success: function (res) {
                if (res.success) {
                    $('#agendaStatus')
                        .removeClass('--bloqueada').addClass('--liberada')
                        .html('&#10003; Agenda liberada para ' + res.data_formatada);
                    $btn.removeClass('btn--primary').addClass('btn--gray')
                        .text('Já liberada').prop('disabled', true);
                    $('#agendaFeedback').removeClass('--success --error').addClass('--success')
                                       .text(res.message).show();
                    setTimeout(function () { $('#agendaFeedback').fadeOut(); }, 4000);
                }
            },
            error: function () {
                $('#agendaFeedback').removeClass('--success --error').addClass('--error')
                                   .text('Erro ao liberar agenda.').show();
                $btn.prop('disabled', false).text('Liberar para ' + $btn.data('fmt'));
            }
        });
    });

    // ── Toggle manutenção ──────────────────────────────────────
    $(document).on('click', '#btnAtivarManutencao, #btnDesativarManutencao', function () {
        var $btn   = $(this);
        var ativar = $btn.is('#btnAtivarManutencao') ? 1 : 0;
        var label  = ativar ? 'ativar' : 'desativar';

        if (ativar && !confirm('Tem certeza? O site ficará inacessível para os jogadores.')) {
            return;
        }

        $btn.prop('disabled', true).text('Aguarde...');

        $.ajax({
            url:    ADMIN_BASE_URL + '/services/toggle_manutencao.php',
            method: 'POST',
            data:   { ativar: ativar },
            success: function (res) {
                var $status   = $('#manutencaoStatus');
                var $wrapper  = $('.configManutencao');
                var $feedback = $('#manutencaoFeedback');

                if (res.ativo) {
                    $wrapper.addClass('--ativa');
                    $status.removeClass('--inativa').addClass('--ativa')
                           .html('&#9888; Site em manutenção — visitantes não conseguem acessar');
                    $btn.attr('id', 'btnDesativarManutencao')
                        .removeClass('btn--danger').addClass('btn--success')
                        .text('Desativar manutenção').prop('disabled', false);
                } else {
                    $wrapper.removeClass('--ativa');
                    $status.removeClass('--ativa').addClass('--inativa')
                           .html('&#10003; Site funcionando normalmente');
                    $btn.attr('id', 'btnAtivarManutencao')
                        .removeClass('btn--success').addClass('btn--danger')
                        .text('Ativar manutenção').prop('disabled', false);
                }

                $feedback.removeClass('--success --error').addClass('--success')
                         .text(res.message).show();
                setTimeout(function () { $feedback.fadeOut(); }, 4000);
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message : 'Erro ao alterar modo de manutenção.';
                $('#manutencaoFeedback').removeClass('--success --error').addClass('--error')
                                       .text(msg).show();
                $btn.prop('disabled', false).text(ativar ? 'Ativar manutenção' : 'Desativar manutenção');
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
