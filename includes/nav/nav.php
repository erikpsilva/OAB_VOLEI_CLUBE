<?php
$_navAgora = new DateTime();
$_navAoVivo = ($_navAgora->format('N') == 5 && $_navAgora->format('H:i') >= '21:00');
?>
<?php if (!empty($_SESSION['jogador'])): ?>
<div class="nav__overlay" id="navOverlay"></div>
<nav class="nav">
    <div class="container">
        <button class="nav__hamburger" id="navHamburger" aria-label="Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <ul class="nav__list" id="navList">
            <li><a href="<?= BASE_URL ?>" class="nav__item <?= ($mainRoute === 'inicio' || $mainRoute === '') ? 'active' : '' ?>">Início</a></li>
            <li><a href="<?= BASE_URL ?>/calendario" class="nav__item <?= $mainRoute === 'calendario' ? 'active' : '' ?>">Calendário de treinos</a></li>
            <li><a href="<?= BASE_URL ?>/historico" class="nav__item <?= $mainRoute === 'historico' ? 'active' : '' ?>">Histórico de treinos</a></li>
            <li><a href="<?= BASE_URL ?>/meusdados" class="nav__item <?= $mainRoute === 'meusdados' ? 'active' : '' ?>">Meus dados</a></li>
            <?php if ($_navAoVivo): ?>
            <li><a href="<?= BASE_URL ?>/treino" class="nav__item nav__item--live <?= $mainRoute === 'treino' ? 'active' : '' ?>">&#9679; Ao Vivo</a></li>
            <?php else: ?>
            <li><a href="<?= BASE_URL ?>/treino" class="nav__item <?= $mainRoute === 'treino' ? 'active' : '' ?>">Jogos</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
<script>
(function () {
    var btn     = document.getElementById('navHamburger');
    var list    = document.getElementById('navList');
    var overlay = document.getElementById('navOverlay');

    function toggle() {
        btn.classList.toggle('--open');
        list.classList.toggle('--open');
        overlay.classList.toggle('--open');
    }

    btn.addEventListener('click', toggle);
    overlay.addEventListener('click', toggle);
})();
</script>
<?php endif; ?>
