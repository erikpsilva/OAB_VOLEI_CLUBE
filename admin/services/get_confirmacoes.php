<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$data = $_GET['data'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Data inválida']);
    exit;
}

if (!defined('ROOT')) define('ROOT', dirname(__DIR__, 2));
require_once ROOT . '/config/database.php';

$pdo  = getDbConnection();
$stmt = $pdo->prepare("
    SELECT j.nome_completo, j.cpf, j.telefone
    FROM confirmacoes_treino ct
    JOIN jogadores j ON j.id = ct.jogador_id
    WHERE ct.data_treino = ?
    ORDER BY j.nome_completo
");
$stmt->execute([$data]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $digits = preg_replace('/\D/', '', $row['cpf'] ?? '');
    if (strlen($digits) === 11) {
        $row['cpf_masked'] = $digits[0] . '**.***.***-' . substr($digits, -2);
    } else {
        $row['cpf_masked'] = $row['cpf'];
    }
    unset($row['cpf']);
}

header('Content-Type: application/json');
echo json_encode($rows);
