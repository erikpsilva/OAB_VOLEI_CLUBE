<header class="header">
    <div class="container">
        <div class="row">
            <div class="col-6">
                <div class="header__logo">
                    <a href="<?= BASE_URL ?>">
                        <img src="<?= BASE_URL ?>/images/logo.png" alt="OAB Vôlei Clube" />
                    </a>
                </div>
            </div>
            <div class="col-6">
                <div class="header__actions">
                    <?php if (!empty($_SESSION['jogador'])): ?>
                        <?php $primeiroNome = explode(' ', $_SESSION['jogador']['nome_completo'])[0]; ?>
                        <span class="header__user">Olá, <?= htmlspecialchars($primeiroNome) ?></span>
                        <a href="<?= BASE_URL ?>/logout" class="header__btn header__btn--sair">Sair</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login" class="header__btn">Entrar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>