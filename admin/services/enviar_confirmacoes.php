<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (empty($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$nivel = $_SESSION['usuario']['nivel_acesso'];
if ($nivel !== 'admin' && $nivel !== 'editor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas admin e editor podem enviar confirmações.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$data = trim($_POST['data_treino'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

if (!defined('ROOT')) define('ROOT', dirname(__DIR__, 2));
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/envio_helper.php';

$pdo    = getDbConnection();
$result = enviarConfirmacoes($data, $pdo, false);

http_response_code($result['success'] ? 200 : 500);
echo json_encode($result);
