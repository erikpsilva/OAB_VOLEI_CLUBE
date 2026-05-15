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
if (!$dataTreino) {
    echo json_encode(['ok' => false, 'msg' => 'Data inválida']);
    exit;
}

$stmt = $pdo->prepare("SELECT estado_json FROM sorteio_estado WHERE data_treino = ? LIMIT 1");
$stmt->execute([$dataTreino]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'Treino não encontrado']);
    exit;
}

$estado = json_decode($row['estado_json'], true);
$estado['encerrado'] = true;

$pdo->prepare("UPDATE sorteio_estado SET estado_json = ? WHERE data_treino = ?")
    ->execute([json_encode($estado), $dataTreino]);

echo json_encode(['ok' => true]);
