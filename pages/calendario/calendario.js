$(document).ready(function () {

    let selectedDate = null;

    // ── Filtro de treinos passados ────────────────────────────
    $('#filtroPassados').on('change', function () {
        if ($(this).is(':checked')) {
            $('#calendarioGrid').addClass('--mostrar-passados');
        } else {
            $('#calendarioGrid').removeClass('--mostrar-passados');
        }
    });

    // ── Modal de CONFIRMAÇÃO ──────────────────────────────────
    $(document).on('click', '.calendarioBox.--clicavel', function () {
        selectedDate = $(this).data('date');
        const label  = $(this).data('label');
        $('#confirmDate').text(label);
        $('#confirmCheck').prop('checked', false);
        $('#confirmModal').addClass('--open');
    });

    $('#btnCancelar').on('click', function () {
        $('#confirmModal').removeClass('--open');
        selectedDate = null;
    });

    $('#confirmModal').on('click', function (e) {
        if ($(e.target).is('#confirmModal')) {
            $('#confirmModal').removeClass('--open');
            selectedDate = null;
        }
    });

    $('#btnConfirmar').on('click', function () {
        if (!$('#confirmCheck').is(':checked')) {
            alert('Marque o checkbox para confirmar sua presença.');
            return;
        }

        $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');

        $.post(BASE_URL + '/services/confirmar_presenca.php', { data_treino: selectedDate }, function (res) {
            $('.overlayForm').remove();
            $('#confirmModal').removeClass('--open');
            alert(res.message);
            if (res.success) location.reload();
        }, 'json').fail(function (xhr) {
            $('.overlayForm').remove();
            const msg = xhr.responseJSON?.message || 'Erro ao confirmar presença.';
            alert(msg);
        });
    });

    // ── Modal de CANCELAMENTO ─────────────────────────────────
    $(document).on('click', '.calendarioBox.--cancelavel', function () {
        selectedDate = $(this).data('date');
        const label  = $(this).data('label');
        $('#cancelDate').text(label);
        $('#cancelModal').addClass('--open');
    });

    $('#btnFecharCancel').on('click', function () {
        $('#cancelModal').removeClass('--open');
        selectedDate = null;
    });

    $('#cancelModal').on('click', function (e) {
        if ($(e.target).is('#cancelModal')) {
            $('#cancelModal').removeClass('--open');
            selectedDate = null;
        }
    });

    $('#btnConfirmarCancel').on('click', function () {
        $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');

        $.post(BASE_URL + '/services/cancelar_presenca.php', { data_treino: selectedDate }, function (res) {
            $('.overlayForm').remove();
            $('#cancelModal').removeClass('--open');
            alert(res.message);
            if (res.success) location.reload();
        }, 'json').fail(function (xhr) {
            $('.overlayForm').remove();
            const msg = xhr.responseJSON?.message || 'Erro ao cancelar confirmação.';
            alert(msg);
        });
    });

});
