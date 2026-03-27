<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Cadastro</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/includes/header/header.php'; ?>

<div class="cadastroLayout">
    <div class="container">

        <section class="cadastro">
            <div class="row">
                <div class="col-md-12">
                    <h2>Criar <span>cadastro</span></h2>
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
                            <input class="input" type="text" id="userName" placeholder="Seu primeiro nome" />
                            <span class="errorText">Nome deve ter no mínimo 3 caracteres</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Sobrenome</label>
                            <input class="input" type="text" id="userLastName" placeholder="Seu sobrenome" />
                            <span class="errorText">Sobrenome deve ter no mínimo 3 caracteres</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>CPF</label>
                            <input class="input" type="text" id="userCpf" placeholder="___.___.___-__" />
                            <span class="errorText">Digite um CPF válido</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Data de nascimento</label>
                            <input class="input" type="date" id="userBirthdate" />
                            <span class="errorText">Informe a data de nascimento</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Telefone</label>
                            <input class="input" type="text" id="userPhone" placeholder="(__) _____-____" />
                            <span class="errorText">Digite um telefone válido</span>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="formGroup__item">
                            <label>E-mail</label>
                            <input class="input" type="text" id="userEmail" placeholder="Seu e-mail" />
                            <span class="errorText">Digite um e-mail válido</span>
                        </div>
                    </div>

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Senha de <span>acesso</span></h3>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Senha</label>
                            <input class="input" type="password" id="userPassword" placeholder="Entre 6 e 20 caracteres" />
                            <span class="errorText">A senha deve ter entre 6 e 20 caracteres</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Confirmar senha</label>
                            <input class="input" type="password" id="userConfirmPassword" placeholder="Repita a senha" />
                            <span class="errorText">As senhas não são iguais</span>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <button class="btn btn--primary" id="btnCadastrar">Cadastrar</button>
                    </div>

                </div>
            </div>

            <div class="cadastro__success" id="cadastroSuccess">
                <p class="cadastro__success__msg">Cadastro realizado com sucesso!</p>
                <p class="cadastro__success__redirect">Você será redirecionado para a tela de acesso em <span id="countdown">5</span> segundo(s)...</p>
            </div>

        </section>
    </div>
</div>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
<script>var BASE_URL = "<?= BASE_URL ?>";</script>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/cadastro/cadastro.js?v' . $version . '"></script>';
?>

</body>
</html>
