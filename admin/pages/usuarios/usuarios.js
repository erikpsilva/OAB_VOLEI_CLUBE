$(function () {

    // ── LISTA: clique na linha ─────────────────────────────────
    $(document).on('click', '.usuariosTable__row', function (e) {
        if ($(e.target).is('a, button, .usuariosTable__star')) return;
        window.location.href = $(this).data('href');
    });

    // ── Toggle favorito ────────────────────────────────────────
    $(document).on('click', '.usuariosTable__star', function (e) {
        e.stopPropagation();
        var $btn = $(this);
        var id   = $btn.data('id');

        $btn.prop('disabled', true);

        $.post(ADMIN_BASE_URL + '/services/toggle_favorito.php', { id: id }, function (res) {
            if (res.success) {
                $btn.toggleClass('--ativo', res.favorito)
                    .html(res.favorito ? '&#9733;' : '&#9734;')
                    .attr('title', res.favorito ? 'Remover dos favoritos' : 'Marcar como favorito');
            }
        }, 'json').always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ── EDIÇÃO ─────────────────────────────────────────────────
    if (!$('#entityId').length) return;

    var tipo = $('#entityTipo').val();

    // Masks
    $('#entCpf').mask('999.999.999-99');
    if (tipo === 'jogador') $('#entTelefone').mask('(99) 99999-9999');

    // Helpers
    const setField = (sel, valid) => {
        const $p = $(sel).closest('.formGroup__item');
        $p.toggleClass('error', !valid);
        $p.find('.errorText').toggleClass('show', !valid);
        return valid;
    };

    const isValidCpf = (cpf) => {
        cpf = cpf.replace(/\D/g, '');
        if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
        const calc = (c, f) => { let s=0; for(let i=0;i<f-1;i++) s+=c[i]*(f-i); const r=(s*10)%11; return r===10?0:r; };
        return calc(cpf,10)===+cpf[9] && calc(cpf,11)===+cpf[10];
    };

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const validateAll = () => {
        const nome  = setField('#entNome',  $('#entNome').val().trim().length >= 2);
        const sobre = setField('#entSobre', $('#entSobre').val().trim().length >= 2);
        const email = setField('#entEmail', emailRegex.test($('#entEmail').val().trim()));
        const cpf   = setField('#entCpf',   isValidCpf($('#entCpf').val()));
        const senha = $('#entSenha').val();
        let senhaOk = true, confOk = true;
        if (senha !== '') {
            senhaOk = setField('#entSenha', senha.length >= 6 && senha.length <= 20);
            confOk  = setField('#entSenhaConfirm', senha === $('#entSenhaConfirm').val());
        }
        return nome && sobre && email && cpf && senhaOk && confOk;
    };

    const showFeedback = (msg, t) => {
        $('#editFeedback').removeClass('--success --error').addClass('--' + t).text(msg).show();
        $('html, body').animate({ scrollTop: $('#editFeedback').offset().top - 100 }, 300);
    };

    // ── Salvar ─────────────────────────────────────────────────
    $('#btnSalvar').on('click', function () {
        if (!validateAll()) return;
        var $btn = $(this).prop('disabled', true).text('Salvando...');

        var url     = tipo === 'admin'
            ? ADMIN_BASE_URL + '/services/atualizar_admin_usuario.php'
            : ADMIN_BASE_URL + '/services/atualizar_jogador.php';

        var payload = {
            id:        $('#entityId').val(),
            nome:      $('#entNome').val().trim(),
            sobrenome: $('#entSobre').val().trim(),
            email:     $('#entEmail').val().trim(),
            cpf:       $('#entCpf').val().replace(/\D/g, ''),
            senha:     $('#entSenha').val(),
        };

        if (tipo === 'admin') {
            payload.nivel_acesso = $('#entNivel').val();
        } else {
            payload.telefone        = $('#entTelefone').val().trim();
            payload.data_nascimento = $('#entNascimento').val();
        }

        $.post(url, payload, function (res) {
            showFeedback(res.message, res.success ? 'success' : 'error');
        }, 'json').fail(function () {
            showFeedback('Erro de comunicação com o servidor.', 'error');
        }).always(function () {
            $btn.prop('disabled', false).text('Salvar alterações');
        });
    });

    // ── Excluir ────────────────────────────────────────────────
    $('#btnExcluir').on('click', function () {
        $('#deleteModal').addClass('--open');
    });

    $('#btnCancelarExclusao').on('click', function () {
        $('#deleteModal').removeClass('--open');
    });

    $('#deleteModal').on('click', function (e) {
        if ($(e.target).is('#deleteModal')) $(this).removeClass('--open');
    });

    $('#btnConfirmarExclusao').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Excluindo...');

        var url = tipo === 'admin'
            ? ADMIN_BASE_URL + '/services/excluir_admin_usuario.php'
            : ADMIN_BASE_URL + '/services/excluir_jogador.php';

        var redirectParam = tipo === 'admin' ? '?deleted=admin' : '?deleted=jogador';

        $.post(url, { id: $('#entityId').val() }, function (res) {
            if (res.success) {
                window.location.href = BASE_URL + '/admin/usuarios' + redirectParam;
            } else {
                $('#deleteModal').removeClass('--open');
                showFeedback(res.message, 'error');
                $btn.prop('disabled', false).text('Confirmar exclusão');
            }
        }, 'json').fail(function () {
            $('#deleteModal').removeClass('--open');
            showFeedback('Erro ao excluir.', 'error');
            $btn.prop('disabled', false).text('Confirmar exclusão');
        });
    });

    // Validação inline
    $('#entNome').on('keyup',         () => setField('#entNome',  $('#entNome').val().trim().length >= 2));
    $('#entSobre').on('keyup',        () => setField('#entSobre', $('#entSobre').val().trim().length >= 2));
    $('#entEmail').on('keyup',        () => setField('#entEmail', emailRegex.test($('#entEmail').val().trim())));
    $('#entCpf').on('keyup input',    () => setField('#entCpf',   isValidCpf($('#entCpf').val())));
    $('#entSenha').on('keyup',        () => { if ($('#entSenha').val()) setField('#entSenha', $('#entSenha').val().length >= 6 && $('#entSenha').val().length <= 20); });
    $('#entSenhaConfirm').on('keyup', () => { if ($('#entSenha').val()) setField('#entSenhaConfirm', $('#entSenha').val() === $('#entSenhaConfirm').val()); });
});
