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

$emailAdmin   = trim($_POST['email_admin']        ?? '');
$emailEsperia = trim($_POST['email_esperia']       ?? '');
$disparoDia   = trim($_POST['disparo_dia_semana']  ?? '');
$disparoHora  = trim($_POST['disparo_hora']        ?? '');

if (!filter_var($emailAdmin, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail da Coordenação inválido.']);
    exit;
}

if (!filter_var($emailEsperia, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail do Clube Esperia inválido.']);
    exit;
}

if (!in_array($disparoDia, ['1','2','3','4','5','6','7'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dia da semana inválido.']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}$/', $disparoHora)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Horário inválido. Use o formato HH:MM.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$sql = "INSERT INTO app_configuracoes (chave, valor) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
$stmt = $pdo->prepare($sql);

$stmt->execute(['email_admin',        $emailAdmin]);
$stmt->execute(['email_esperia',       $emailEsperia]);
$stmt->execute(['disparo_dia_semana',  $disparoDia]);
$stmt->execute(['disparo_hora',        $disparoHora]);

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
