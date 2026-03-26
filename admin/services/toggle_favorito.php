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

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

// Lê o estado atual e inverte
$stmt = $pdo->prepare("SELECT favorito FROM jogadores WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Jogador não encontrado.']);
    exit;
}

$novoValor = $row['favorito'] ? 0 : 1;

$upd = $pdo->prepare("UPDATE jogadores SET favorito = ? WHERE id = ?");
$upd->execute([$novoValor, $id]);

echo json_encode([
    'success'  => true,
    'favorito' => (bool) $novoValor,
    'message'  => $novoValor ? 'Jogador marcado como favorito.' : 'Jogador removido dos favoritos.',
]);
