$(function () {

    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // ── Adicionar campo de e-mail ──────────────────────────────
    $(document).on('click', '.emailList__add', function () {
        var targetId = $(this).data('target');
        var max      = parseInt($(this).data('max'), 10) || 5;
        var $list    = $('#' + targetId);

        if ($list.find('.emailList__item').length >= max) return;

        var $item = $('<div class="emailList__item">' +
            '<input class="input emailList__input" type="email" placeholder="email@exemplo.com.br" value="">' +
            '<button type="button" class="emailList__remove" title="Remover">&times;</button>' +
            '</div>');
        $list.append($item);
        $item.find('input').focus();
        atualizarBotaoAdd(targetId, max);
    });

    // ── Remover campo de e-mail ────────────────────────────────
    $(document).on('click', '.emailList__remove', function () {
        var $list    = $(this).closest('.emailList');
        var targetId = $list.attr('id');
        var max      = parseInt($list.siblings('.emailList__add').data('max'), 10) || 5;

        // Manter sempre ao menos 1 campo
        if ($list.find('.emailList__item').length <= 1) {
            $(this).siblings('input').val('');
            return;
        }
        $(this).closest('.emailList__item').remove();
        atualizarBotaoAdd(targetId, max);
    });

    function atualizarBotaoAdd(targetId, max) {
        var count = $('#' + targetId).find('.emailList__item').length;
        $('[data-target="' + targetId + '"]').prop('disabled', count >= max);
    }

    // Inicializa estado dos botões ao carregar
    ['emailsAdmin', 'emailsEsperia'].forEach(function (id) {
        atualizarBotaoAdd(id, 5);
    });

    // ── Coletar e-mails de uma lista ───────────────────────────
    function coletarEmails(listId) {
        var emails = [];
        $('#' + listId + ' .emailList__input').each(function () {
            var v = $.trim($(this).val());
            if (v) emails.push(v);
        });
        return emails;
    }

    $('#btnSalvarConfig').on('click', function () {
        var $btn      = $(this);
        var $feedback = $('#configFeedback');

        var emailsAdmin   = coletarEmails('emailsAdmin');
        var emailsEsperia = coletarEmails('emailsEsperia');
        var disparoDia    = $('#disparoDia').val();
        var disparoHora   = $.trim($('#disparoHora').val());
        var maxVagas      = parseInt($('#maxVagas').val(), 10);
        var modoAbertura  = $('#modoAbertura').val();

        if (emailsAdmin.length === 0) {
            return mostrarFeedback('Informe ao menos um e-mail da Coordenação.', 'error');
        }
        for (var i = 0; i < emailsAdmin.length; i++) {
            if (!emailRegex.test(emailsAdmin[i])) {
                return mostrarFeedback('E-mail inválido na Coordenação: ' + emailsAdmin[i], 'error');
            }
        }
        if (emailsEsperia.length === 0) {
            return mostrarFeedback('Informe ao menos um e-mail do Clube Esperia.', 'error');
        }
        for (var j = 0; j < emailsEsperia.length; j++) {
            if (!emailRegex.test(emailsEsperia[j])) {
                return mostrarFeedback('E-mail inválido no Clube Esperia: ' + emailsEsperia[j], 'error');
            }
        }
        if (!disparoHora) {
            return mostrarFeedback('Informe o horário de disparo.', 'error');
        }
        if (isNaN(maxVagas) || maxVagas < 1) {
            return mostrarFeedback('Informe um número válido de vagas (mínimo 1).', 'error');
        }

        $btn.prop('disabled', true).text('Salvando...');
        $feedback.hide();

        var emailRemetente = $.trim($('#emailRemetente').val());
        var mensagemEmail  = $('#mensagemEmail').val();
        var smtpAtivo      = $('#smtpAtivo').is(':checked') ? '1' : '0';
        var smtpHost       = $.trim($('#smtpHost').val());
        var smtpPorta      = $.trim($('#smtpPorta').val()) || '587';
        var smtpEncryption = $('#smtpEncryption').val();
        var smtpUsuario    = $.trim($('#smtpUsuario').val());
        var smtpSenha      = $('#smtpSenha').val();

        if (emailRemetente && !emailRegex.test(emailRemetente)) {
            return mostrarFeedback('E-mail do remetente inválido.', 'error');
        }
        if (smtpUsuario && !emailRegex.test(smtpUsuario)) {
            return mostrarFeedback('Usuário SMTP deve ser um e-mail válido.', 'error');
        }

        $.ajax({
            url:    ADMIN_BASE_URL + '/services/salvar_configuracoes.php',
            method: 'POST',
            data: {
                emails_admin:         emailsAdmin,
                emails_esperia:       emailsEsperia,
                email_remetente:      emailRemetente,
                mensagem_email:       mensagemEmail,
                smtp_ativo:           smtpAtivo,
                smtp_host:            smtpHost,
                smtp_porta:           smtpPorta,
                smtp_encryption:      smtpEncryption,
                smtp_usuario:         smtpUsuario,
                smtp_senha:           smtpSenha,
                disparo_dia_semana:   disparoDia,
                disparo_hora:         disparoHora,
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

    // ── Toggle SMTP ────────────────────────────────────────────
    $('#smtpAtivo').on('change', function () {
        var ativo = $(this).is(':checked');
        $('.configSmtp__campos').toggle(ativo);
        $('.configSmtp__toggleStatus')
            .removeClass('--ativo --inativo')
            .addClass(ativo ? '--ativo' : '--inativo')
            .text(ativo ? 'Ativo — usando SMTP configurado abaixo' : 'Inativo — usando mail() nativo do servidor');
    });

    // ── Inserir tag no cursor do textarea ─────────────────────
    $(document).on('click', '.configPage__tag', function () {
        var tag      = $(this).data('tag');
        var $ta      = $('#mensagemEmail')[0];
        var start    = $ta.selectionStart;
        var end      = $ta.selectionEnd;
        var val      = $ta.value;
        $ta.value    = val.substring(0, start) + tag + val.substring(end);
        $ta.selectionStart = $ta.selectionEnd = start + tag.length;
        $ta.focus();
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

    // ── Testar envio ───────────────────────────────────────────
    $('#btnTestarEnvio').on('click', function () {
        var $btn      = $(this);
        var $feedback = $('#testarEnvioFeedback');

        var emailsAdmin = coletarEmails('emailsAdmin');
        if (emailsAdmin.length === 0) {
            $feedback.removeClass('--success --error').addClass('--error')
                     .text('Cadastre ao menos um e-mail da Coordenação antes de testar.').show();
            return;
        }

        $btn.prop('disabled', true).text('Enviando...');
        $feedback.hide();

        $.ajax({
            url:    ADMIN_BASE_URL + '/services/testar_envio.php',
            method: 'POST',
            success: function (res) {
                $feedback.removeClass('--success --error')
                         .addClass(res.success ? '--success' : '--error')
                         .text(res.message).show();
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message : 'Erro ao disparar e-mail de teste.';
                $feedback.removeClass('--success --error').addClass('--error').text(msg).show();
            },
            complete: function () {
                $btn.prop('disabled', false).html('&#9993; Testar envio (somente Coordena&ccedil;&atilde;o)');
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
