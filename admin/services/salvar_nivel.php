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

$jogadorId = (int)($_POST['jogador_id'] ?? 0);
$nivel     = (int)($_POST['nivel'] ?? 0);

if ($jogadorId <= 0 || $nivel < 1 || $nivel > 5) {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos']);
    exit;
}

$stmt = $pdo->prepare("UPDATE jogadores SET nivel_jogo = ? WHERE id = ?");
$stmt->execute([$nivel, $jogadorId]);

echo json_encode(['ok' => true]);
