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

if (empty($_SESSION['jogador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

require_once dirname(__FILE__, 2) . '/config/database.php';

$jogadorId = $_SESSION['jogador']['id'];
$nome      = trim($_POST['userNameVal']      ?? '');
$sobrenome = trim($_POST['userLastNameVal']  ?? '');
$email     = trim($_POST['userEmailVal']     ?? '');
$telefone  = preg_replace('/[^\d]/', '', $_POST['userPhoneVal'] ?? '');
$birthdate = trim($_POST['userBirthdateVal'] ?? '');
$senha     = $_POST['userPasswordVal']       ?? '';

// Validações
if (mb_strlen($nome) < 2 || mb_strlen($sobrenome) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome e sobrenome devem ter no mínimo 2 caracteres.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

if (strlen($telefone) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Telefone inválido.']);
    exit;
}

if ($senha !== '' && (strlen($senha) < 6 || strlen($senha) > 20)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A senha deve ter entre 6 e 20 caracteres.']);
    exit;
}

$pdo = getDbConnection();

// Verifica duplicidade de e-mail em outro jogador
$stmt = $pdo->prepare("SELECT id FROM jogadores WHERE email = ? AND id != ? LIMIT 1");
$stmt->execute([$email, $jogadorId]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'E-mail já utilizado por outro jogador.']);
    exit;
}

$nomeCompleto = $nome . ' ' . $sobrenome;

if ($senha !== '') {
    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("
        UPDATE jogadores
        SET nome_completo = ?, email = ?, telefone = ?, data_nascimento = ?, senha = ?
        WHERE id = ?
    ");
    $stmt->execute([$nomeCompleto, $email, $telefone, $birthdate, $senhaHash, $jogadorId]);
} else {
    $stmt = $pdo->prepare("
        UPDATE jogadores
        SET nome_completo = ?, email = ?, telefone = ?, data_nascimento = ?
        WHERE id = ?
    ");
    $stmt->execute([$nomeCompleto, $email, $telefone, $birthdate, $jogadorId]);
}

// Atualiza a sessão
$_SESSION['jogador']['nome_completo']   = $nomeCompleto;
$_SESSION['jogador']['email']           = $email;
$_SESSION['jogador']['telefone']        = $telefone;
$_SESSION['jogador']['data_nascimento'] = $birthdate;

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso!']);
