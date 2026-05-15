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

$dataTreino    = trim($_POST['data_treino'] ?? '');
$numerPartida  = (int)($_POST['numero'] ?? 0);
$placarCasa    = (int)($_POST['placar_casa'] ?? 0);
$placarVisit   = (int)($_POST['placar_visitante'] ?? 0);

if (!$dataTreino || $numerPartida <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos']);
    exit;
}

// Busca partida
$stmtP = $pdo->prepare("SELECT * FROM sorteio_partidas WHERE data_treino = ? AND numero = ? LIMIT 1");
$stmtP->execute([$dataTreino, $numerPartida]);
$partida = $stmtP->fetch(PDO::FETCH_ASSOC);

if (!$partida) {
    echo json_encode(['ok' => false, 'msg' => 'Partida não encontrada']);
    exit;
}

// Determina vencedor
if ($placarCasa > $placarVisit) {
    $idxVencedor  = (int) $partida['idx_casa'];
    $idxPerdedor  = (int) $partida['idx_visitante'];
} elseif ($placarVisit > $placarCasa) {
    $idxVencedor  = (int) $partida['idx_visitante'];
    $idxPerdedor  = (int) $partida['idx_casa'];
} else {
    echo json_encode(['ok' => false, 'msg' => 'Placar não pode ser empate']);
    exit;
}

// Atualiza partida com placar e vencedor
$stmtUpd = $pdo->prepare("
    UPDATE sorteio_partidas
    SET placar_casa = ?, placar_visitante = ?, idx_vencedor = ?
    WHERE data_treino = ? AND numero = ?
");
$stmtUpd->execute([$placarCasa, $placarVisit, $idxVencedor, $dataTreino, $numerPartida]);

// Busca estado atual
$stmtE = $pdo->prepare("SELECT estado_json FROM sorteio_estado WHERE data_treino = ? LIMIT 1");
$stmtE->execute([$dataTreino]);
$estadoRow = $stmtE->fetch(PDO::FETCH_ASSOC);

if (!$estadoRow) {
    echo json_encode(['ok' => false, 'msg' => 'Estado não encontrado']);
    exit;
}

$estado = json_decode($estadoRow['estado_json'], true);
$emQuadra = $estado['em_quadra'];
$fila     = $estado['fila'];

// Encontra os times em quadra com seus consecutivos
$vencedorEmQuadra = null;
$perdedorEmQuadra = null;
foreach ($emQuadra as $t) {
    if ((int)$t['idx'] === $idxVencedor) $vencedorEmQuadra = $t;
    if ((int)$t['idx'] === $idxPerdedor)  $perdedorEmQuadra = $t;
}

// Incrementa consecutivos do vencedor
$consVencedor = ((int)($vencedorEmQuadra['consecutivos'] ?? 0)) + 1;

if ($consVencedor < 2) {
    // Vencedor ainda não atingiu o limite: fica na quadra, perdedor vai para fila
    // e o próximo da fila entra como adversário
    $fila[] = ['idx' => $idxPerdedor];
    $proximo = array_shift($fila);
    $novaEmQuadra = [
        ['idx' => $idxVencedor, 'consecutivos' => $consVencedor],
        ['idx' => (int)$proximo['idx'], 'consecutivos' => 0],
    ];
} else {
    // Vencedor atingiu 2 jogos consecutivos: sai para a fila
    // O PERDEDOR FICA na quadra (consecutivos = 0) e enfrenta o próximo da fila
    $fila[] = ['idx' => $idxVencedor];
    if (!empty($fila)) {
        $proximo = array_shift($fila);
        $novaEmQuadra = [
            ['idx' => $idxPerdedor,          'consecutivos' => 0],
            ['idx' => (int)$proximo['idx'],  'consecutivos' => 0],
        ];
    } else {
        // Sem fila: os dois ficam (caso extremo de só 2 times)
        $novaEmQuadra = [
            ['idx' => $idxPerdedor,  'consecutivos' => 0],
            ['idx' => $idxVencedor,  'consecutivos' => 0],
        ];
        array_pop($fila); // remove o vencedor que acabou de ser adicionado
    }
}

$novaPartidaNum = $numerPartida + 1;

$estado['em_quadra']     = $novaEmQuadra;
$estado['fila']          = array_values($fila);
$estado['partida_atual'] = $novaPartidaNum;

$stmtSE = $pdo->prepare("UPDATE sorteio_estado SET estado_json = ? WHERE data_treino = ?");
$stmtSE->execute([json_encode($estado), $dataTreino]);

// Cria próxima partida
$stmtNP = $pdo->prepare("
    INSERT IGNORE INTO sorteio_partidas (data_treino, numero, idx_casa, idx_visitante)
    VALUES (?, ?, ?, ?)
");
$stmtNP->execute([$dataTreino, $novaPartidaNum, $novaEmQuadra[0]['idx'], $novaEmQuadra[1]['idx']]);

echo json_encode(['ok' => true, 'proxima_partida' => $novaPartidaNum]);
