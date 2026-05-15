<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

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

$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT id, jogador_id, nome_completo, cpf, telefone, email, inscrito_em
    FROM fila_espera
    WHERE data_treino = ?
    ORDER BY inscrito_em ASC
");
$stmt->execute([$data]);
$rows = $stmt->fetchAll();

$resultado = [];
foreach ($rows as $i => $row) {
    $digits = preg_replace('/\D/', '', $row['cpf'] ?? '');
    $cpfMasked = strlen($digits) === 11
        ? $digits[0] . '**.***.***-' . substr($digits, -2)
        : $row['cpf'];
    $resultado[] = [
        'posicao'       => $i + 1,
        'nome_completo' => $row['nome_completo'],
        'cpf_masked'    => $cpfMasked,
        'telefone'      => $row['telefone'],
        'email'         => $row['email'],
        'inscrito_em'   => $row['inscrito_em'],
    ];
}

echo json_encode($resultado);
