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

$jogadorId = $_SESSION['jogador']['id'];
$pdo       = getDbConnection();

$stmtCheck = $pdo->prepare("SELECT id FROM fila_espera WHERE jogador_id = ? AND data_treino = ? LIMIT 1");
$stmtCheck->execute([$jogadorId, $dataTreino]);
if (!$stmtCheck->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Você não está na fila de espera para este treino.']);
    exit;
}

$stmtDel = $pdo->prepare("DELETE FROM fila_espera WHERE jogador_id = ? AND data_treino = ?");
$stmtDel->execute([$jogadorId, $dataTreino]);

echo json_encode(['success' => true, 'message' => 'Você saiu da fila de espera.']);
