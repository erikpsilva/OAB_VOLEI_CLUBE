<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

if ($_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso restrito a administradores.']);
    exit;
}

$ativar   = isset($_POST['ativar']) ? (bool)(int)$_POST['ativar'] : null;
$flagFile = dirname(__FILE__, 3) . '/config/maintenance.flag';

if ($ativar === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetro inválido.']);
    exit;
}

if ($ativar) {
    file_put_contents($flagFile, date('Y-m-d H:i:s'));
    echo json_encode(['success' => true, 'ativo' => true, 'message' => 'Modo de manutenção ativado.']);
} else {
    if (file_exists($flagFile)) {
        unlink($flagFile);
    }
    echo json_encode(['success' => true, 'ativo' => false, 'message' => 'Modo de manutenção desativado.']);
}
