<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once dirname(__FILE__, 2) . '/config/api_security.php';

validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['jogador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

require_once dirname(__FILE__, 2) . '/config/database.php';
require_once dirname(__FILE__, 2) . '/config/envio_helper.php';

$dataTreino = trim($_POST['data_treino'] ?? '');

if (!$dataTreino || !DateTime::createFromFormat('Y-m-d', $dataTreino)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

$jogador  = $_SESSION['jogador'];
$pdo      = getDbConnection();
$config   = getAppConfig($pdo);
$maxVagas = (int) $config['max_vagas'];

// Treino encerrado não aceita fila
$stmtEnc = $pdo->prepare("SELECT 1 FROM treinos_encerrados WHERE data_treino = ? LIMIT 1");
$stmtEnc->execute([$dataTreino]);
if ($stmtEnc->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'As confirmações para este treino foram encerradas.']);
    exit;
}

// Não pode entrar na fila se já está confirmado
$stmtConf = $pdo->prepare("SELECT id FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino = ? LIMIT 1");
$stmtConf->execute([$jogador['id'], $dataTreino]);
if ($stmtConf->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Você já está confirmado neste treino.']);
    exit;
}

// Verifica se o treino realmente está lotado
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM confirmacoes_treino WHERE data_treino = ?");
$stmtCount->execute([$dataTreino]);
if ((int) $stmtCount->fetchColumn() < $maxVagas) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Ainda há vagas disponíveis. Confirme sua presença normalmente.']);
    exit;
}

// Verifica se já está na fila
$stmtFila = $pdo->prepare("SELECT id FROM fila_espera WHERE jogador_id = ? AND data_treino = ? LIMIT 1");
$stmtFila->execute([$jogador['id'], $dataTreino]);
if ($stmtFila->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Você já está na fila de espera para este treino.']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO fila_espera (jogador_id, data_treino, nome_completo, cpf, telefone, email)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $jogador['id'],
    $dataTreino,
    $jogador['nome_completo'],
    $jogador['cpf'],
    $jogador['telefone'] ?? '',
    $jogador['email'],
]);

// Retorna posição na fila
$stmtPos = $pdo->prepare("
    SELECT COUNT(*) FROM fila_espera
    WHERE data_treino = ? AND inscrito_em <= (
        SELECT inscrito_em FROM fila_espera WHERE jogador_id = ? AND data_treino = ?
    )
");
$stmtPos->execute([$dataTreino, $jogador['id'], $dataTreino]);
$posicao = (int) $stmtPos->fetchColumn();

http_response_code(201);
echo json_encode([
    'success'  => true,
    'message'  => "Você entrou para a fila de espera! Sua posição é #{$posicao}.",
    'posicao'  => $posicao,
]);
