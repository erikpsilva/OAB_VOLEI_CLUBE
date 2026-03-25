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
    setFieldState('#userName', $('#userName').val().trim().length >= 2);

const validateSobrenome = () =>
    setFieldState('#userLastName', $('#userLastName').val().trim().length >= 2);

const validateEmail = () => {
    const regex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
    return setFieldState('#userEmail', regex.test($('#userEmail').val().trim()));
};

const validatePhone = () => {
    const digits = $('#userPhone').val().replace(/[^\d]/g, '');
    return setFieldState('#userPhone', digits.length === 11);
};

const validateSenha = () => {
    const val = $('#userPassword').val();
    if (val === '') {
        setFieldState('#userPassword', true);
        setFieldState('#userConfirmPassword', true);
        return true;
    }
    const valid = val.length >= 6 && val.length <= 20;
    const result = setFieldState('#userPassword', valid);
    if ($('#userConfirmPassword').val() !== '') validateConfirmSenha();
    return result;
};

const validateConfirmSenha = () => {
    if ($('#userPassword').val() === '') {
        return setFieldState('#userConfirmPassword', true);
    }
    const match = $('#userPassword').val() === $('#userConfirmPassword').val();
    return setFieldState('#userConfirmPassword', match);
};

const validateAll = () => {
    const results = [
        validateNome(),
        validateSobrenome(),
        validateEmail(),
        validatePhone(),
        validateSenha(),
        validateConfirmSenha(),
    ];
    return results.every(r => r === true);
};

// ── INICIALIZAÇÃO ────────────────────────────────────────────

const insertMask = () => {
    $('#userCpf').mask('999.999.999-99');
    $('#userPhone').mask('(99) 99999-9999');
};

const bindKeyup = () => {
    $('#userName').on('keyup',            validateNome);
    $('#userLastName').on('keyup',        validateSobrenome);
    $('#userEmail').on('keyup',           validateEmail);
    $('#userPhone').on('keyup input',     validatePhone);
    $('#userPassword').on('keyup',        validateSenha);
    $('#userConfirmPassword').on('keyup', validateConfirmSenha);
};

// ── ENVIO ────────────────────────────────────────────────────

const sendMeusDados = () => {
    $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');

    const payload = {
        userNameVal:      $('#userName').val().trim(),
        userLastNameVal:  $('#userLastName').val().trim(),
        userEmailVal:     $('#userEmail').val().trim(),
        userPhoneVal:     $('#userPhone').val(),
        userBirthdateVal: $('#userBirthdate').val(),
        userPasswordVal:  $('#userPassword').val(),
    };

    $.post(BASE_URL + '/services/update_jogador.php', payload, function (res) {
        $('.overlayForm').remove();
        alert(res.message);
    }, 'json').fail(function (xhr) {
        $('.overlayForm').remove();
        const msg = xhr.responseJSON?.message || 'Erro ao salvar os dados.';
        alert(msg);
    });
};

const bindSubmit = () => {
    $('#salvarMeusDados').on('click', function (e) {
        e.preventDefault();
        if (validateAll()) sendMeusDados();
    });
};

$(document).ready(() => {
    insertMask();
    bindKeyup();
    bindSubmit();
});
