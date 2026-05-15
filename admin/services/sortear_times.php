<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Não autorizado']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
$pdo = getDbConnection();

$dataTreino = trim($_POST['data_treino'] ?? '');
if (!$dataTreino || !DateTime::createFromFormat('Y-m-d', $dataTreino)) {
    echo json_encode(['ok' => false, 'msg' => 'Data inválida']);
    exit;
}

// Busca jogadores presentes na data (com fallback para todos os confirmados)
$stmt = $pdo->prepare("
    SELECT j.id, j.nome_completo, COALESCE(j.nivel_jogo, 3) AS nivel_jogo
    FROM presenca_treino pt
    JOIN jogadores j ON j.id = pt.jogador_id
    WHERE pt.data_treino = ? AND pt.status = 'presente'
    ORDER BY j.nivel_jogo DESC, j.nome_completo ASC
");
$stmt->execute([$dataTreino]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback: se não há presença registrada, usa confirmados
if (empty($players)) {
    $stmt = $pdo->prepare("
        SELECT j.id, j.nome_completo, COALESCE(j.nivel_jogo, 3) AS nivel_jogo
        FROM confirmacoes_treino ct
        JOIN jogadores j ON j.id = ct.jogador_id
        WHERE ct.data_treino = ?
        ORDER BY j.nivel_jogo DESC, j.nome_completo ASC
    ");
    $stmt->execute([$dataTreino]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (count($players) < 6) {
    echo json_encode(['ok' => false, 'msg' => 'São necessários pelo menos 6 jogadores presentes para montar os times.']);
    exit;
}

// Calcula número de times (times de ~6 jogadores)
$total     = count($players);
$numTimes  = max(2, (int) round($total / 6));
if ($numTimes > 6) $numTimes = 6;

$paleta = [
    ['name' => 'Time Azul',     'color' => '#1565C0'],
    ['name' => 'Time Vermelho', 'color' => '#C62828'],
    ['name' => 'Time Verde',    'color' => '#2E7D32'],
    ['name' => 'Time Laranja',  'color' => '#E65100'],
    ['name' => 'Time Roxo',     'color' => '#6A1B9A'],
    ['name' => 'Time Amarelo',  'color' => '#F57F17'],
];

$times = [];
for ($i = 0; $i < $numTimes; $i++) {
    $times[$i] = [
        'name'      => $paleta[$i]['name'],
        'color'     => $paleta[$i]['color'],
        'jogadores' => [],
    ];
}

// Snake draft para distribuição equilibrada
foreach ($players as $pos => $player) {
    $round   = (int) floor($pos / $numTimes);
    $posRod  = $pos % $numTimes;
    $timeIdx = ($round % 2 === 0) ? $posRod : ($numTimes - 1 - $posRod);
    $times[$timeIdx]['jogadores'][] = [
        'id'          => (int) $player['id'],
        'nome'        => $player['nome_completo'],
        'nivel_jogo'  => (int) $player['nivel_jogo'],
    ];
}

// Salva times (sobrescreve se já existir)
$timesJson = json_encode($times);
$stmtT = $pdo->prepare("
    INSERT INTO sorteio_times (data_treino, times_json)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE times_json = VALUES(times_json)
");
$stmtT->execute([$dataTreino, $timesJson]);

// Estado inicial: primeiros dois times na quadra, resto na fila
$emQuadra = [
    ['idx' => 0, 'consecutivos' => 0],
    ['idx' => 1, 'consecutivos' => 0],
];
$fila = [];
for ($i = 2; $i < $numTimes; $i++) {
    $fila[] = ['idx' => $i];
}

$estado = [
    'encerrado'     => false,
    'em_quadra'     => $emQuadra,
    'fila'          => $fila,
    'partida_atual' => 1,
];

$stmtE = $pdo->prepare("
    INSERT INTO sorteio_estado (data_treino, estado_json)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE estado_json = VALUES(estado_json)
");
$stmtE->execute([$dataTreino, json_encode($estado)]);

// Remove partidas antigas desta data e cria a primeira
$pdo->prepare("DELETE FROM sorteio_partidas WHERE data_treino = ?")->execute([$dataTreino]);

$stmtP = $pdo->prepare("
    INSERT INTO sorteio_partidas (data_treino, numero, idx_casa, idx_visitante)
    VALUES (?, 1, 0, 1)
");
$stmtP->execute([$dataTreino]);

echo json_encode(['ok' => true, 'num_times' => $numTimes, 'total_jogadores' => $total]);
