$(document).ready(function () {

    let selectedDate = null;

    // Abre o modal ao clicar em card disponível
    $(document).on('click', '.calendarioBox.--clicavel', function () {
        selectedDate = $(this).data('date');
        const label  = $(this).data('label');

        $('#confirmDate').text(label);
        $('#confirmCheck').prop('checked', false);
        $('#confirmModal').addClass('--open');
    });

    // Fecha o modal
    $('#btnCancelar').on('click', function () {
        $('#confirmModal').removeClass('--open');
        selectedDate = null;
    });

    // Fecha clicando fora do box
    $('#confirmModal').on('click', function (e) {
        if ($(e.target).is('#confirmModal')) {
            $('#confirmModal').removeClass('--open');
            selectedDate = null;
        }
    });

    // Enviar confirmação
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
        }, 'json').fail(function (xhr) {
            $('.overlayForm').remove();
            const msg = xhr.responseJSON?.message || 'Erro ao confirmar presença.';
            alert(msg);
        });
    });

});
