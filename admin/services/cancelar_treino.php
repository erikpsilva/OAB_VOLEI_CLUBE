<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sem permissão']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
$pdo = getDbConnection();

$dataTreino = trim($_POST['data_treino'] ?? '');
$acao       = trim($_POST['acao'] ?? ''); // 'cancelar' | 'reativar'

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataTreino) || !in_array($acao, ['cancelar', 'reativar'])) {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos']);
    exit;
}

if ($acao === 'cancelar') {
    $pdo->prepare("INSERT IGNORE INTO treinos_cancelados (data_treino) VALUES (?)")->execute([$dataTreino]);
    echo json_encode(['ok' => true, 'cancelado' => true]);
} else {
    $pdo->prepare("DELETE FROM treinos_cancelados WHERE data_treino = ?")->execute([$dataTreino]);
    echo json_encode(['ok' => true, 'cancelado' => false]);
}
