<?php
if (empty($_SESSION['jogador'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$today = new DateTime();
$today->setTime(0, 0, 0);
$year  = (int) $today->format('Y');

// ── SEXTAS-FEIRAS DO ANO ATÉ HOJE + SEXTA DA SEMANA ATUAL ───
$fridays = [];
$date = new DateTime("$year-01-01");
while ($date->format('N') != 5) {
    $date->modify('+1 day');
}
while ((int) $date->format('Y') === $year) {
    $friday = clone $date;

    // Inclui apenas sextas até hoje OU a sexta da semana atual (seg a qui)
    $monday = (clone $friday)->modify('-4 days');
    if ($friday <= $today || ($today >= $monday && $today < $friday)) {
        $fridays[] = $friday;
    }

    $date->modify('+7 days');
}

// Inverte para exibir do mais recente ao mais antigo
$fridays = array_reverse($fridays);

// ── CONFIRMAÇÕES DO JOGADOR ───────────────────────────────────
require_once ROOT . '/config/database.php';
$pdo  = getDbConnection();
$stmt = $pdo->prepare("SELECT data_treino FROM confirmacoes_treino WHERE jogador_id = ?");
$stmt->execute([$_SESSION['jogador']['id']]);
$confirmados = array_column($stmt->fetchAll(), 'data_treino');
$confirmados = array_flip($confirmados); // para busca O(1)

// ── SEXTA DA SEMANA ATUAL (para destaque) ────────────────────
$proximaSexta = new DateTime();
while ($proximaSexta->format('N') != 5) {
    $proximaSexta->modify('+1 day');
}
$proximaSexta->setTime(0, 0, 0);
$semanaAtualKey = ($today <= $proximaSexta) ? $proximaSexta->format('Y-m-d') : null;

$mesesFull = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
              '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Histórico de Treinos</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/includes/header/header.php'; ?>
<?php include ROOT . '/includes/nav/nav.php'; ?>

<div class="historicoLayout">
    <div class="container">

        <div class="historico__header">
            <h2>Histórico de <span>Treinos</span></h2>
            <p class="historico__header__sub">Seus treinos de <?= $year ?> — confirmados e não confirmados.</p>
        </div>

        <?php if (empty($fridays)): ?>
            <p class="historico__vazio">Nenhum treino registrado ainda neste ano.</p>
        <?php else: ?>

        <div class="historico__filtro">
            <label class="historico__filtro__toggle">
                <input type="checkbox" id="filtroPassados" />
                <span class="historico__filtro__slider"></span>
            </label>
            <span class="historico__filtro__label">Exibir treinos já realizados</span>
        </div>

        <div class="historico__lista" id="historicoLista">
            <?php foreach ($fridays as $friday):
                $key        = $friday->format('Y-m-d');
                $confirmou  = isset($confirmados[$key]);
                $isDestaque = ($key === $semanaAtualKey);
                $isPassado  = !$isDestaque;
                $dia        = $friday->format('d');
                $mes        = $mesesFull[$friday->format('m')];
                $ano        = $friday->format('Y');
            ?>
            <div class="historicoItem <?= $confirmou ? '--confirmado' : '--nao-confirmado' ?> <?= $isDestaque ? '--destaque' : '' ?> <?= $isPassado ? '--passado' : '' ?>">
                <div class="historicoItem__data">
                    <span class="historicoItem__data__dia"><?= $dia ?></span>
                    <span class="historicoItem__data__mes"><?= $mes ?> <?= $ano ?></span>
                    <?php if ($isDestaque): ?>
                        <span class="historicoItem__badge">Semana atual</span>
                    <?php endif; ?>
                </div>
                <div class="historicoItem__info">
                    <p class="historicoItem__info__dia">Sexta-feira</p>
                </div>
                <div class="historicoItem__status">
                    <?php if ($confirmou): ?>
                        <span class="historicoItem__status__tag --confirmado">Confirmado</span>
                    <?php else: ?>
                        <span class="historicoItem__status__tag --nao-confirmado">Não confirmado</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
<script>
$(document).ready(function () {
    $('#filtroPassados').on('change', function () {
        if ($(this).is(':checked')) {
            $('#historicoLista').addClass('--mostrar-passados');
        } else {
            $('#historicoLista').removeClass('--mostrar-passados');
        }
    });
});
</script>

</body>
</html>
