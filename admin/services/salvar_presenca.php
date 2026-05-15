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

$jogadorId  = (int)($_POST['jogador_id'] ?? 0);
$dataTreino = trim($_POST['data_treino'] ?? '');
$status     = trim($_POST['status'] ?? '');

$validos = ['presente', 'espectador', 'falta_justificada', 'falta_injustificada'];
if ($jogadorId <= 0 || !$dataTreino || !in_array($status, $validos)) {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO presenca_treino (jogador_id, data_treino, status)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status), registrado_em = CURRENT_TIMESTAMP
");
$stmt->execute([$jogadorId, $dataTreino, $status]);

echo json_encode(['ok' => true]);
