<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once dirname(__FILE__, 2) . '/config/api_security.php';

validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

require_once dirname(__FILE__, 2) . '/config/database.php';

$nome      = trim($_POST['userNameVal']      ?? '');
$sobrenome = trim($_POST['userLastNameVal']  ?? '');
$cpf       = preg_replace('/[^\d]/', '', $_POST['userCpfVal']    ?? '');
$birthdate = trim($_POST['userBirthdateVal'] ?? '');
$telefone  = preg_replace('/[^\d]/', '', $_POST['userPhoneVal']  ?? '');
$email     = trim($_POST['userEmailVal']     ?? '');
$senha     = $_POST['userPasswordVal']       ?? '';

// Validações
if (mb_strlen($nome) < 3 || mb_strlen($sobrenome) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome e sobrenome devem ter no mínimo 3 caracteres.']);
    exit;
}

if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
    exit;
}

if (empty($birthdate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe a data de nascimento.']);
    exit;
}

if (strlen($telefone) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Telefone inválido.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

if (strlen($senha) < 6 || strlen($senha) > 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A senha deve ter entre 6 e 20 caracteres.']);
    exit;
}

$pdo = getDbConnection();

// Verifica duplicidade de e-mail ou CPF
$stmt = $pdo->prepare("SELECT id FROM jogadores WHERE email = ? OR cpf = ? LIMIT 1");
$stmt->execute([$email, $cpf]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'E-mail ou CPF já cadastrado.']);
    exit;
}

$nomeCompleto = $nome . ' ' . $sobrenome;
$senhaHash    = password_hash($senha, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("
    INSERT INTO jogadores (nome_completo, email, cpf, data_nascimento, telefone, senha)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$nomeCompleto, $email, $cpf, $birthdate, $telefone, $senhaHash]);

http_response_code(201);
echo json_encode(['success' => true, 'message' => 'Cadastro realizado com sucesso!']);
