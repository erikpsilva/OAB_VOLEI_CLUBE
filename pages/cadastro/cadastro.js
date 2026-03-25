// ── VALIDAÇÕES ───────────────────────────────────────────────

const setFieldState = (selector, isValid) => {
    const parent = $(selector).closest('.formGroup__item');
    if (isValid) {
        parent.removeClass('error');
        parent.find('.errorText').removeClass('show');
    } else {
        parent.addClass('error');
        parent.find('.errorText').addClass('show');
    }
    return isValid;
};

const validateNome = () =>
    setFieldState('#userName', $('#userName').val().trim().length >= 3);

const validateSobrenome = () =>
    setFieldState('#userLastName', $('#userLastName').val().trim().length >= 3);

const isValidCPF = (cpf) => {
    cpf = cpf.replace(/[^\d]/g, '');
    if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
    const calcDigit = (cpf, factor) => {
        let total = 0;
        for (let i = 0; i < factor - 1; i++) total += cpf[i] * (factor - i);
        const rem = (total * 10) % 11;
        return rem === 10 ? 0 : rem;
    };
    return calcDigit(cpf, 10) === parseInt(cpf[9]) && calcDigit(cpf, 11) === parseInt(cpf[10]);
};

const validateCpf = () =>
    setFieldState('#userCpf', isValidCPF($('#userCpf').val()));

const validateBirthdate = () =>
    setFieldState('#userBirthdate', $('#userBirthdate').val().trim() !== '');

const validatePhone = () => {
    const digits = $('#userPhone').val().replace(/[^\d]/g, '');
    return setFieldState('#userPhone', digits.length === 11);
};

const validateEmail = () => {
    const regex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
    return setFieldState('#userEmail', regex.test($('#userEmail').val().trim()));
};

const validateSenha = () => {
    const val = $('#userPassword').val();
    const valid = val.length >= 6 && val.length <= 20;
    const result = setFieldState('#userPassword', valid);
    if ($('#userConfirmPassword').val() !== '') validateConfirmSenha();
    return result;
};

const validateConfirmSenha = () => {
    const match = $('#userPassword').val() === $('#userConfirmPassword').val() && $('#userConfirmPassword').val() !== '';
    return setFieldState('#userConfirmPassword', match);
};

const validateAll = () => {
    const results = [
        validateNome(),
        validateSobrenome(),
        validateCpf(),
        validateBirthdate(),
        validatePhone(),
        validateEmail(),
        validateSenha(),
        validateConfirmSenha(),
    ];
    return results.every(r => r === true);
};

// ── ENVIO ────────────────────────────────────────────────────

const sendCadastro = () => {
    $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');

    const payload = {
        userNameVal:      $('#userName').val().trim(),
        userLastNameVal:  $('#userLastName').val().trim(),
        userCpfVal:       $('#userCpf').val(),
        userBirthdateVal: $('#userBirthdate').val(),
        userPhoneVal:     $('#userPhone').val(),
        userEmailVal:     $('#userEmail').val().trim(),
        userPasswordVal:  $('#userPassword').val(),
    };

    $.post(BASE_URL + '/services/cadastrar_jogador.php', payload, function (res) {
        $('.overlayForm').remove();

        if (res.success) {
            $('.formGroup').hide();
            $('#cadastroSuccess').fadeIn();

            let seconds = 5;
            $('#countdown').text(seconds);

            const timer = setInterval(() => {
                seconds--;
                $('#countdown').text(seconds);
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = BASE_URL + '/login';
                }
            }, 1000);
        }
    }, 'json').fail(function (xhr) {
        $('.overlayForm').remove();
        const msg = xhr.responseJSON?.message || 'Erro ao realizar o cadastro.';
        alert(msg);
    });
};

// ── INICIALIZAÇÃO ────────────────────────────────────────────

const insertMask = () => {
    $('#userCpf').mask('999.999.999-99');
    $('#userPhone').mask('(99) 99999-9999');
};

const bindKeyup = () => {
    $('#userName').on('keyup',            validateNome);
    $('#userLastName').on('keyup',        validateSobrenome);
    $('#userCpf').on('keyup input',       validateCpf);
    $('#userBirthdate').on('change',      validateBirthdate);
    $('#userPhone').on('keyup input',     validatePhone);
    $('#userEmail').on('keyup',           validateEmail);
    $('#userPassword').on('keyup',        validateSenha);
    $('#userConfirmPassword').on('keyup', validateConfirmSenha);
};

const bindSubmit = () => {
    $('#btnCadastrar').on('click', function (e) {
        e.preventDefault();
        if (validateAll()) sendCadastro();
    });
};

$(document).ready(() => {
    insertMask();
    bindKeyup();
    bindSubmit();
});
