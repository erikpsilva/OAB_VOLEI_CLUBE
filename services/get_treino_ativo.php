<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 2) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['jogador'])) {
    http_response_code(403);
    echo json_encode(['ativo' => false]);
    exit;
}

require_once dirname(__FILE__, 2) . '/config/database.php';

$pdo = getDbConnection();

$dataParam = $_GET['data'] ?? null;

if ($dataParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataParam)) {
    // Busca a data específica solicitada
    $stmtE = $pdo->prepare("SELECT data_treino, estado_json FROM sorteio_estado WHERE data_treino = ? LIMIT 1");
    $stmtE->execute([$dataParam]);
    $estadoRow = $stmtE->fetch();
    if ($estadoRow) {
        $estado = json_decode($estadoRow['estado_json'], true);
    }
} else {
    // Busca o sorteio mais recente que não foi encerrado
    $stmt = $pdo->query("SELECT data_treino, estado_json FROM sorteio_estado ORDER BY data_treino DESC LIMIT 5");
    $rows = $stmt->fetchAll();

    $estadoRow = null;
    foreach ($rows as $row) {
        $est = json_decode($row['estado_json'], true);
        if (!($est['encerrado'] ?? true)) {
            $estadoRow = $row;
            $estado    = $est;
            break;
        }
    }
}

if (!$estadoRow) {
    echo json_encode(['ativo' => false]);
    exit;
}

$dataTreino = $estadoRow['data_treino'];

// Times
$stmtT = $pdo->prepare("SELECT times_json FROM sorteio_times WHERE data_treino = ? LIMIT 1");
$stmtT->execute([$dataTreino]);
$timesRow = $stmtT->fetch();
$times = $timesRow ? json_decode($timesRow['times_json'], true) : [];

$coresMap = [
    'azul'     => '#0b3c75', 'vermelho' => '#e30613', 'verde'  => '#155724',
    'amarelo'  => '#ffc300', 'laranja'  => '#e67e22', 'roxo'   => '#6f42c1',
    'rosa'     => '#e91e8c', 'preto'    => '#212529', 'cinza'  => '#6c757d',
];
function normCor(string $c, array $m): string {
    return ($c !== '' && $c[0] === '#') ? $c : ($m[$c] ?? '#6c757d');
}
// Normaliza todas as cores para hex
foreach ($times as $idx => $t) {
    $times[$idx]['color'] = normCor($t['color'] ?? '', $coresMap);
}

// Partidas
$stmtP = $pdo->prepare("SELECT * FROM sorteio_partidas WHERE data_treino = ? ORDER BY numero ASC");
$stmtP->execute([$dataTreino]);
$partidas = $stmtP->fetchAll();

// Formata data
$meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',
          '05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',
          '09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$dt = DateTime::createFromFormat('Y-m-d', $dataTreino);
$dataLonga = $dt->format('d') . ' de ' . $meses[$dt->format('m')] . ' de ' . $dt->format('Y');

// Monta resposta
$emQuadra = array_map(function($item) use ($times) {
    $t = $times[$item['idx']] ?? null;
    return $t ? ['idx' => $item['idx'], 'name' => $t['name'], 'color' => $t['color']] : null;
}, $estado['em_quadra'] ?? []);

$fila = array_map(function($item) use ($times) {
    $t = $times[$item['idx']] ?? null;
    return $t ? ['idx' => $item['idx'], 'name' => $t['name'], 'color' => $t['color']] : null;
}, $estado['fila'] ?? []);

$partidasFormatadas = array_map(function($p) use ($times) {
    $casa      = $times[$p['idx_casa']]      ?? null;
    $visitante = $times[$p['idx_visitante']] ?? null;
    $vencedor  = $p['idx_vencedor'] !== null ? ($times[$p['idx_vencedor']] ?? null) : null;
    return [
        'numero'          => (int) $p['numero'],
        'time_casa'       => $casa      ? ['idx' => $p['idx_casa'],      'name' => $casa['name'],      'color' => $casa['color']]      : null,
        'time_visitante'  => $visitante ? ['idx' => $p['idx_visitante'], 'name' => $visitante['name'], 'color' => $visitante['color']] : null,
        'placar_casa'     => $p['placar_casa']     !== null ? (int)$p['placar_casa']     : null,
        'placar_visitante'=> $p['placar_visitante'] !== null ? (int)$p['placar_visitante'] : null,
        'encerrada'       => $p['idx_vencedor'] !== null,
        'vencedor_idx'    => $p['idx_vencedor'] !== null ? (int)$p['idx_vencedor'] : null,
        'vencedor_nome'   => $vencedor ? $vencedor['name'] : null,
    ];
}, $partidas);

echo json_encode([
    'ativo'          => true,
    'data_treino'    => $dataTreino,
    'data_longa'     => $dataLonga,
    'encerrado'      => (bool)($estado['encerrado'] ?? false),
    'times'          => $times,
    'em_quadra'      => array_values(array_filter($emQuadra)),
    'fila'           => array_values(array_filter($fila)),
    'partidas'       => $partidasFormatadas,
    'partida_atual'  => (int)($estado['partida_atual'] ?? 0),
]);
