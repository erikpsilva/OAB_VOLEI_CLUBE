<?php
if (empty($_SESSION['jogador'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$now   = new DateTime(); // hora atual
$today = clone $now;
$today->setTime(0, 0, 0);
$year  = (int) $today->format('Y');

// ── CONTAGEM DE CONFIRMAÇÕES E TREINOS ENCERRADOS ────────────
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/envio_helper.php';
$pdo      = getDbConnection();
$config   = getAppConfig($pdo);
$maxVagas           = (int) $config['max_vagas'];
$modoAbertura       = $config['modo_abertura_agenda'] ?? 'automatico';
$agendaLiberadaData = $config['agenda_liberada_data'] ?? '';
$disparoHora        = $config['disparo_hora'] ?? '13:00'; // horário configurado de disparo

// Verifica se o jogador logado é favorito
$isFavoritoLogado = false;
if (!empty($_SESSION['jogador'])) {
    $stmtFav = $pdo->prepare("SELECT favorito FROM jogadores WHERE id = ? LIMIT 1");
    $stmtFav->execute([$_SESSION['jogador']['id']]);
    $favRow = $stmtFav->fetch();
    $isFavoritoLogado = $favRow && (int)$favRow['favorito'] === 1;
}

$stmt  = $pdo->query("SELECT data_treino, COUNT(*) as total FROM confirmacoes_treino GROUP BY data_treino");
$counts = [];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['data_treino']] = (int) $row['total'];
}

$stmtEnc  = $pdo->query("SELECT data_treino FROM treinos_encerrados");
$encerrados = array_flip($stmtEnc->fetchAll(PDO::FETCH_COLUMN));

// Datas que o jogador logado já confirmou
$confirmacoesUsuario = [];
if (!empty($_SESSION['jogador'])) {
    $stmtMinha = $pdo->prepare("SELECT data_treino FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino LIKE ?");
    $stmtMinha->execute([$_SESSION['jogador']['id'], "$year-%"]);
    $confirmacoesUsuario = array_flip($stmtMinha->fetchAll(PDO::FETCH_COLUMN));
}

// Datas em que o jogador está na fila de espera e posição
$filaUsuario = [];
if (!empty($_SESSION['jogador'])) {
    $stmtFila = $pdo->prepare("SELECT data_treino FROM fila_espera WHERE jogador_id = ? AND data_treino LIKE ?");
    $stmtFila->execute([$_SESSION['jogador']['id'], "$year-%"]);
    foreach ($stmtFila->fetchAll(PDO::FETCH_COLUMN) as $dtFila) {
        $filaUsuario[$dtFila] = true;
    }
}

// Posição na fila por data (para as datas em que está na fila)
$posicaoFila = [];
if (!empty($filaUsuario)) {
    foreach (array_keys($filaUsuario) as $dtFila) {
        $stmtPos = $pdo->prepare("
            SELECT COUNT(*) FROM fila_espera
            WHERE data_treino = ? AND inscrito_em <= (
                SELECT inscrito_em FROM fila_espera WHERE jogador_id = ? AND data_treino = ?
            )
        ");
        $stmtPos->execute([$dtFila, $_SESSION['jogador']['id'], $dtFila]);
        $posicaoFila[$dtFila] = (int) $stmtPos->fetchColumn();
    }
}

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
// $now   = DateTime com hora atual
// $disparoHora = string "HH:MM" do horário configurado de disparo
function getStatus(DateTime $friday, DateTime $today, DateTime $now, string $disparoHora): string {
    if ($friday < $today) return 'concluido';

    if ($friday == $today) {
        // Em curso somente se o horário de disparo já passou
        // (o encerramento por email enviado é tratado no loop abaixo)
        if ($now->format('H:i') >= $disparoHora) return 'em_curso';
        // Antes do disparo: ainda no período de confirmação
        return 'disponivel';
    }

    $monday = (clone $friday)->modify('-4 days');
    if ($today >= $monday && $today < $friday) return 'disponivel';

    return 'indisponivel';
}

function statusLabel(string $status): string {
    return match($status) {
        'concluido'  => 'Concluído',
        'em_curso'   => 'Em curso',
        'disponivel' => 'Disponível',
        'lotado'     => 'Lotado',
        'encerrado'  => 'Encerrado',
        'aguardando' => 'Aguardando abertura',
        default      => 'Indisponível',
    };
}
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Calendário de Treinos</title>
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
                <span class="calendario__legenda__item --fila-espera">Fila de espera</span>
                <span class="calendario__legenda__item --encerrado">Encerrado</span>
                <span class="calendario__legenda__item --aguardando">Aguardando abertura</span>
                <span class="calendario__legenda__item --indisponivel">Indisponível</span>
            </div>
        </div>

        <div class="calendario__filtro">
            <label class="calendario__filtro__toggle">
                <input type="checkbox" id="filtroPassados" />
                <span class="calendario__filtro__slider"></span>
            </label>
            <span class="calendario__filtro__label">Exibir treinos já realizados</span>
        </div>

        <p class="calendario__dica">Clique no dia do treino com status <strong>Disponível</strong> para confirmar sua presença.</p>

        <div class="calendario__grid" id="calendarioGrid">
            <?php foreach ($fridays as $friday):
                $key    = $friday->format('Y-m-d');
                $isHoje = ($friday == $today);
                $status = getStatus($friday, $today, $now, $disparoHora);
                if (isset($encerrados[$key]) && $status !== 'concluido') {
                    // Email enviado hoje (admin ou cron) → em curso
                    // Email enviado para treino futuro (encerramento antecipado) → encerrado
                    $status = $isHoje ? 'em_curso' : 'encerrado';
                } elseif ($status === 'disponivel' && ($counts[$key] ?? 0) >= $maxVagas) {
                    $status = 'lotado';
                } elseif ($status === 'disponivel' && $modoAbertura === 'manual' && !$isFavoritoLogado && $agendaLiberadaData !== $key) {
                    $status = 'aguardando';
                }
                $dia   = $friday->format('d');
                $mes   = $meses[$friday->format('m')];
                $label = $dia . '/' . $friday->format('m') . '/' . $friday->format('Y');

                $usuarioConfirmou = isset($confirmacoesUsuario[$key]);
                $usuarioNaFila    = isset($filaUsuario[$key]);
                // Pode cancelar se confirmou e o treino ainda não está em curso/encerrado/concluido
                $podeCancelar = $usuarioConfirmou && !in_array($status, ['em_curso', 'concluido', 'encerrado']);
                // Pode confirmar se disponível e ainda não confirmou
                $podeConfirmar = ($status === 'disponivel' && !$usuarioConfirmou);
                // Pode entrar na fila se lotado, não confirmou e não está na fila
                $podeEntrarFila = ($status === 'lotado' && !$usuarioConfirmou && !$usuarioNaFila);
                // Pode sair da fila se está na fila e treino não encerrado/concluido
                $podeSairFila = $usuarioNaFila && !in_array($status, ['em_curso', 'concluido', 'encerrado']);

                $extraClasses = '';
                if ($podeConfirmar)  $extraClasses .= ' --clicavel';
                if ($podeCancelar)   $extraClasses .= ' --cancelavel';
                if ($podeEntrarFila) $extraClasses .= ' --fila-clicavel';
                if ($podeSairFila)   $extraClasses .= ' --fila-sair';
                if ($usuarioConfirmou && !in_array($status, ['concluido'])) $extraClasses .= ' --tem-confirmacao';
                if ($usuarioNaFila)  $extraClasses .= ' --na-fila';

                $dataAttrs = "data-date=\"{$key}\" data-label=\"{$label}\"";
            ?>
            <div class="calendarioBox --<?= $status ?><?= $extraClasses ?>"
                 <?= $dataAttrs ?>
                 <?= $status === 'aguardando' ? 'title="As confirmações para este treino ainda não foram abertas pelo administrador."' : '' ?>>

                <div class="calendarioBox__date">
                    <span class="calendarioBox__date__day"><?= $dia ?></span>
                    <span class="calendarioBox__date__month"><?= $mes ?></span>
                </div>
                <p class="calendarioBox__weekday">Sexta-feira</p>
                <span class="calendarioBox__status"><?= statusLabel($status) ?></span>
                <?php if ($usuarioConfirmou && !in_array($status, ['concluido'])): ?>
                    <span class="calendarioBox__badge">&#10003; Confirmado<?= $podeCancelar ? ' · Cancelar' : '' ?></span>
                <?php elseif ($usuarioNaFila): ?>
                    <span class="calendarioBox__badge --fila">&#9201; Fila #<?= $posicaoFila[$key] ?? '?' ?><?= $podeSairFila ? ' · Sair' : '' ?></span>
                <?php elseif ($podeEntrarFila): ?>
                    <span class="calendarioBox__badge --entrar-fila">Entrar na fila</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- MODAL ENTRAR NA FILA -->
<div class="confirmModal" id="filaEntrarModal">
    <div class="confirmModal__box">
        <h3 class="confirmModal__title">Entrar na fila de espera</h3>
        <p class="confirmModal__text">O treino do dia <strong id="filaEntrarDate"></strong> está lotado.</p>
        <p class="confirmModal__text">Deseja entrar na fila de espera? Se algum confirmado desistir, você será chamado automaticamente por ordem de chegada.</p>
        <div class="confirmModal__actions">
            <button class="confirmModal__btn --cancelar" id="btnFecharFilaEntrar">Cancelar</button>
            <button class="confirmModal__btn --enviar" id="btnConfirmarFilaEntrar">Entrar na fila</button>
        </div>
    </div>
</div>

<!-- MODAL SAIR DA FILA -->
<div class="confirmModal" id="filaSairModal">
    <div class="confirmModal__box">
        <h3 class="confirmModal__title">Sair da fila de espera</h3>
        <p class="confirmModal__text">Deseja sair da fila de espera do treino do dia <strong id="filaSairDate"></strong>?</p>
        <p class="confirmModal__text --aviso">Você perderá sua posição na fila.</p>
        <div class="confirmModal__actions">
            <button class="confirmModal__btn --cancelar" id="btnFecharFilaSair">Voltar</button>
            <button class="confirmModal__btn --excluir" id="btnConfirmarFilaSair">Sim, sair da fila</button>
        </div>
    </div>
</div>

<!-- MODAL CANCELAMENTO -->
<div class="confirmModal" id="cancelModal">
    <div class="confirmModal__box">
        <h3 class="confirmModal__title">Cancelar confirmação</h3>
        <p class="confirmModal__text">Tem certeza que deseja cancelar sua confirmação para o treino do dia <strong id="cancelDate"></strong>?</p>
        <p class="confirmModal__text --aviso">Sua vaga será liberada para outros participantes.</p>
        <div class="confirmModal__actions">
            <button class="confirmModal__btn --cancelar" id="btnFecharCancel">Voltar</button>
            <button class="confirmModal__btn --excluir" id="btnConfirmarCancel">Sim, cancelar</button>
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
