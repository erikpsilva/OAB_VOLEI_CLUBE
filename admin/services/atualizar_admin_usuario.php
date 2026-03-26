<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso restrito a administradores.']);
    exit;
}

$id          = (int) ($_POST['id']           ?? 0);
$nome        = trim($_POST['nome']            ?? '');
$sobrenome   = trim($_POST['sobrenome']       ?? '');
$email       = trim($_POST['email']           ?? '');
$cpf         = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$nivelAcesso = trim($_POST['nivel_acesso']    ?? '');
$senha       = $_POST['senha'] ?? '';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

if (mb_strlen($nome) < 2 || mb_strlen($sobrenome) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome e sobrenome devem ter pelo menos 2 caracteres.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
    exit;
}

if (!in_array($nivelAcesso, ['admin', 'editor', 'leitor'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nível de acesso inválido.']);
    exit;
}

if ($senha !== '' && (strlen($senha) < 6 || strlen($senha) > 20)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A senha deve ter entre 6 e 20 caracteres.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT id FROM admin_usuarios WHERE (email = ? OR cpf = ?) AND id != ? LIMIT 1");
$stmt->execute([$email, $cpf, $id]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'E-mail ou CPF já utilizado por outro usuário.']);
    exit;
}

$nomeCompleto = $nome . ' ' . $sobrenome;

if ($senha !== '') {
    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE admin_usuarios SET nome_completo=?, email=?, cpf=?, nivel_acesso=?, senha=? WHERE id=?");
    $stmt->execute([$nomeCompleto, $email, $cpf, $nivelAcesso, $hash, $id]);
} else {
    $stmt = $pdo->prepare("UPDATE admin_usuarios SET nome_completo=?, email=?, cpf=?, nivel_acesso=? WHERE id=?");
    $stmt->execute([$nomeCompleto, $email, $cpf, $nivelAcesso, $id]);
}

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso!']);
