<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Login</title>

<?php include ROOT . '/includes/assets.php';?>

</head>

<body>

<?php include ROOT . '/includes/header/header.php';?>

<section class="login">
    <div class="login__box">
        <h2 class="login__box__title">Acesse sua <span>conta</span></h2>

        <div class="login__box__form">
            <div class="formItem">
                <label>E-mail</label>
                <input class="input" type="email" id="loginEmail" placeholder="Digite seu e-mail" />
            </div>
            <div class="formItem">
                <label>Senha</label>
                <input class="input" type="password" id="loginPassword" placeholder="Digite sua senha" />
            </div>
            <button class="login__box__btn" id="btnLogin">Entrar</button>
        </div>

        <p class="login__box__register">
            Ainda não é cadastrado?
            <a href="<?= BASE_URL ?>/cadastro" class="login__box__register__link">Cadastre-se aqui</a>
        </p>
    </div>
</section>

<?php include ROOT . '/includes/footer/footer.php';?>
<?php include ROOT . '/includes/scripts.php';?>
<script>var BASE_URL = "<?= BASE_URL ?>";</script>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/login/login.js?' . $version . '"></script>';
?>

</body>
</html>
