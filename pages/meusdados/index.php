<?php
if (empty($_SESSION['jogador'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}
$partes    = explode(' ', $_SESSION['jogador']['nome_completo'], 2);
$nome      = $partes[0];
$sobrenome = $partes[1] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Vôlei Clube - Meus Dados</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/includes/header/header.php'; ?>
<?php include ROOT . '/includes/nav/nav.php'; ?>

<div class="meusDadosLayout">
    <div class="container">

        <section class="meusDados">
            <div class="row">
                <div class="col-md-12">
                    <h2>Meus <span>Dados</span></h2>
                </div>
            </div>
            <div class="formGroup">
                <div class="row">

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Dados <span>pessoais</span></h3>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Nome</label>
                            <input class="input" type="text" id="userName" name="userName"
                                   value="<?= htmlspecialchars($nome) ?>" placeholder="Seu primeiro nome" />
                            <span class="errorText">Digite um nome válido</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Sobrenome</label>
                            <input class="input" type="text" id="userLastName" name="userLastName"
                                   value="<?= htmlspecialchars($sobrenome) ?>" placeholder="Seu sobrenome" />
                            <span class="errorText">Digite um sobrenome válido</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>CPF</label>
                            <input class="input" type="text" id="userCpf" name="userCpf"
                                   value="<?= htmlspecialchars($_SESSION['jogador']['cpf']) ?>"
                                   placeholder="___.___.___-__" disabled />
                            <span class="errorText">Digite um CPF válido</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Data de nascimento</label>
                            <input class="input" type="date" id="userBirthdate" name="userBirthdate"
                                   value="<?= htmlspecialchars($_SESSION['jogador']['data_nascimento'] ?? '') ?>" />
                            <span class="errorText">Digite uma data válida</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Telefone</label>
                            <input class="input" type="text" id="userPhone" name="userPhone"
                                   value="<?= htmlspecialchars($_SESSION['jogador']['telefone'] ?? '') ?>"
                                   placeholder="(__) _____-____" />
                            <span class="errorText">Digite um telefone válido</span>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="formGroup__item">
                            <label>E-mail</label>
                            <input class="input" type="text" id="userEmail" name="userEmail"
                                   value="<?= htmlspecialchars($_SESSION['jogador']['email']) ?>"
                                   placeholder="Seu e-mail" />
                            <span class="errorText">Digite um e-mail válido</span>
                        </div>
                    </div>

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Senha de <span>acesso</span></h3>
                    </div>

                    <div class="col-md-12">
                        <p class="meusDados__senhaAviso">Preencha somente se desejar alterar sua senha.</p>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Nova senha</label>
                            <input class="input" type="password" id="userPassword" name="userPassword"
                                   placeholder="Entre 6 e 20 caracteres" />
                            <span class="errorText">A senha deve ter entre 6 e 20 caracteres</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Confirmar nova senha</label>
                            <input class="input" type="password" id="userConfirmPassword" name="userConfirmPassword"
                                   placeholder="Repita a nova senha" />
                            <span class="errorText">As senhas não são iguais</span>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <button class="btn btn--primary" id="salvarMeusDados">Salvar</button>
                    </div>

                </div>
            </div>
        </section>

    </div>
</div>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
<script>var BASE_URL = "<?= BASE_URL ?>";</script>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/meusdados/meusdados.js?v' . $version . '"></script>';
?>

</body>
</html>
