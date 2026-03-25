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

$dataTreino = trim($_POST['data_treino'] ?? '');

if (!$dataTreino || !DateTime::createFromFormat('Y-m-d', $dataTreino)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

$jogador = $_SESSION['jogador'];
$pdo     = getDbConnection();

// Verifica se o treino foi encerrado
$stmtEnc = $pdo->prepare("SELECT 1 FROM treinos_encerrados WHERE data_treino = ? LIMIT 1");
$stmtEnc->execute([$dataTreino]);
if ($stmtEnc->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'As confirmações para este treino foram encerradas.']);
    exit;
}

// Verifica se já confirmou presença nesta data
$stmt = $pdo->prepare("SELECT id FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino = ? LIMIT 1");
$stmt->execute([$jogador['id'], $dataTreino]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Você já confirmou presença neste treino.']);
    exit;
}

// Verifica limite de vagas
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM confirmacoes_treino WHERE data_treino = ?");
$stmtCount->execute([$dataTreino]);
if ((int) $stmtCount->fetchColumn() >= 30) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este treino já atingiu o limite de 30 confirmações.']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO confirmacoes_treino (jogador_id, data_treino, nome_completo, cpf, telefone, email)
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

http_response_code(201);
echo json_encode(['success' => true, 'message' => 'Presença confirmada com sucesso!']);
