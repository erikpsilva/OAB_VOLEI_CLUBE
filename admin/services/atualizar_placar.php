<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
$pdo = getDbConnection();

$dataTreino = trim($_POST['data_treino'] ?? '');
$numero     = (int)($_POST['numero'] ?? 0);
$placarCasa = max(0, (int)($_POST['placar_casa'] ?? 0));
$placarVis  = max(0, (int)($_POST['placar_visitante'] ?? 0));

if (!$dataTreino || $numero <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE sorteio_partidas
    SET placar_casa = ?, placar_visitante = ?
    WHERE data_treino = ? AND numero = ? AND idx_vencedor IS NULL
");
$stmt->execute([$placarCasa, $placarVis, $dataTreino, $numero]);

echo json_encode(['ok' => true]);
