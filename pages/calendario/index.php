<?php
if (empty($_SESSION['jogador'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$today = new DateTime();
$today->setTime(0, 0, 0);
$year  = (int) $today->format('Y');

// ── CÁLCULO DE PÁSCOA (algoritmo anônimo gregoriano) ─────────
function getEaster(int $year): DateTime {
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day   = (($h + $l - 7 * $m + 114) % 31) + 1;
    return new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
}

// ── FERIADOS NACIONAIS + SÃO PAULO ───────────────────────────
function getHolidays(int $year): array {
    $easter = getEaster($year);

    // Feriados móveis baseados na Páscoa
    $sexta_santa   = (clone $easter)->modify('-2 days');
    $corpus        = (clone $easter)->modify('+60 days');
    $carnaval_seg  = (clone $easter)->modify('-48 days');
    $carnaval_ter  = (clone $easter)->modify('-47 days');

    $holidays = [
        // Nacionais fixos
        "$year-01-01" => 'Ano Novo',
        "$year-04-21" => 'Tiradentes',
        "$year-05-01" => 'Dia do Trabalhador',
        "$year-09-07" => 'Independência do Brasil',
        "$year-10-12" => 'Nossa Senhora Aparecida',
        "$year-11-02" => 'Finados',
        "$year-11-15" => 'Proclamação da República',
        "$year-11-20" => 'Consciência Negra',
        "$year-12-25" => 'Natal',
        // Nacionais móveis
        $sexta_santa->format('Y-m-d')  => 'Sexta-feira Santa',
        $corpus->format('Y-m-d')       => 'Corpus Christi',
        $carnaval_seg->format('Y-m-d') => 'Carnaval',
        $carnaval_ter->format('Y-m-d') => 'Carnaval',
        // São Paulo (estado e cidade)
        "$year-01-25" => 'Aniversário de São Paulo',
        "$year-07-09" => 'Revolução Constitucionalista',
    ];

    return $holidays;
}

$holidays = getHolidays($year);

// ── CONTAGEM DE CONFIRMAÇÕES E TREINOS ENCERRADOS ────────────
require_once ROOT . '/config/database.php';
$pdo   = getDbConnection();

$stmt  = $pdo->query("SELECT data_treino, COUNT(*) as total FROM confirmacoes_treino GROUP BY data_treino");
$counts = [];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['data_treino']] = (int) $row['total'];
}

$stmtEnc  = $pdo->query("SELECT data_treino FROM treinos_encerrados");
$encerrados = array_flip($stmtEnc->fetchAll(PDO::FETCH_COLUMN));

// ── GERA TODAS AS SEXTAS-FEIRAS DO ANO ───────────────────────
$fridays = [];
$date = new DateTime("$year-01-01");
while ($date->format('N') != 5) {
    $date->modify('+1 day');
}
while ((int) $date->format('Y') === $year) {
    $fridays[] = clone $date;
    $date->modify('+7 days');
}

$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

// ── LÓGICA DE STATUS ─────────────────────────────────────────
function getStatus(DateTime $friday, DateTime $today, array $holidays): string {
    $key = $friday->format('Y-m-d');

    if (isset($holidays[$key])) return 'feriado';
    if ($friday < $today)       return 'concluido';
    if ($friday == $today)      return 'em_curso';

    $monday = (clone $friday)->modify('-4 days');
    if ($today >= $monday && $today < $friday) return 'disponivel';

    return 'indisponivel';
}

function statusLabel(string $status): string {
    return match($status) {
        'concluido'   => 'Concluído',
        'em_curso'    => 'Em curso',
        'disponivel'  => 'Disponível',
        'lotado'      => 'Lotado',
        'encerrado'   => 'Encerrado',
        'feriado'     => 'Feriado',
        default       => 'Indisponível',
    };
}
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Vôlei Clube - Calendário de Treinos</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/includes/header/header.php'; ?>
<?php include ROOT . '/includes/nav/nav.php'; ?>

<div class="calendarioLayout">
    <div class="container">

        <div class="calendario__header">
            <h2>Calendário de <span>Treinos</span> <?= $year ?></h2>
            <div class="calendario__legenda">
                <span class="calendario__legenda__item --concluido">Concluído</span>
                <span class="calendario__legenda__item --em_curso">Em curso</span>
                <span class="calendario__legenda__item --disponivel">Disponível</span>
                <span class="calendario__legenda__item --lotado">Lotado</span>
                <span class="calendario__legenda__item --encerrado">Encerrado</span>
                <span class="calendario__legenda__item --indisponivel">Indisponível</span>
                <span class="calendario__legenda__item --feriado">Feriado</span>
            </div>
        </div>

        <p class="calendario__dica">Clique no dia do treino com status <strong>Disponível</strong> para confirmar sua presença.</p>

        <div class="calendario__grid">
            <?php foreach ($fridays as $friday):
                $key    = $friday->format('Y-m-d');
                $status = getStatus($friday, $today, $holidays);
                if (isset($encerrados[$key]) && !in_array($status, ['concluido', 'feriado'])) {
                    $status = 'encerrado';
                } elseif ($status === 'disponivel' && ($counts[$key] ?? 0) >= 30) {
                    $status = 'lotado';
                }
                $dia    = $friday->format('d');
                $mes    = $meses[$friday->format('m')];
                $nome   = $holidays[$key] ?? null;
                $label  = $dia . '/' . $friday->format('m') . '/' . $friday->format('Y');
            ?>
            <div class="calendarioBox --<?= $status ?><?= $status === 'disponivel' ? ' --clicavel' : '' ?>"
                 <?= $status === 'disponivel' ? "data-date=\"{$key}\" data-label=\"{$label}\"" : '' ?>>
                <div class="calendarioBox__date">
                    <span class="calendarioBox__date__day"><?= $dia ?></span>
                    <span class="calendarioBox__date__month"><?= $mes ?></span>
                </div>
                <p class="calendarioBox__weekday">Sexta-feira</p>
                <?php if ($nome): ?>
                    <p class="calendarioBox__holiday"><?= htmlspecialchars($nome) ?></p>
                <?php endif; ?>
                <span class="calendarioBox__status"><?= statusLabel($status) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- MODAL CONFIRMAÇÃO -->
<div class="confirmModal" id="confirmModal">
    <div class="confirmModal__box">
        <h3 class="confirmModal__title">Confirmar presença</h3>
        <p class="confirmModal__text">Você confirma que irá comparecer ao treino do dia <strong id="confirmDate"></strong>?</p>
        <label class="confirmModal__check">
            <input type="checkbox" id="confirmCheck" />
            Eu confirmo minha presença
        </label>
        <div class="confirmModal__actions">
            <button class="confirmModal__btn --cancelar" id="btnCancelar">Cancelar</button>
            <button class="confirmModal__btn --enviar" id="btnConfirmar">Enviar</button>
        </div>
    </div>
</div>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>
<script>var BASE_URL = "<?= BASE_URL ?>";</script>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/calendario/calendario.js?v' . $version . '"></script>';
?>

</body>
</html>
