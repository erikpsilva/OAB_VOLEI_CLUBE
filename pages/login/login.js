$(document).ready(function () {

    $('#btnLogin').on('click', function () {
        var email = $('#loginEmail').val().trim();
        var senha = $('#loginPassword').val().trim();

        if (!email || !senha) {
            showError('Preencha e-mail e senha.');
            return;
        }

        $.ajax({
            url: BASE_URL + '/services/login.php',
            method: 'POST',
            data: { email: email, senha: senha },
            success: function (res) {
                if (res.success) {
                    window.location.href = BASE_URL;
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON?.message || 'E-mail ou senha incorretos.';
                showError(msg);
            }
        });
    });

    function showError(msg) {
        var $error = $('.login__box__error');
        if ($error.length === 0) {
            $error = $('<p class="login__box__error"></p>');
            $('.login__box__form').prepend($error);
        }
        $error.text(msg);
    }

});
