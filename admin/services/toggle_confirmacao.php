<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Não autorizado']);
    exit;
}

$nivel = $_SESSION['usuario']['nivel_acesso'];
if ($nivel !== 'admin' && $nivel !== 'editor') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sem permissão']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
$pdo = getDbConnection();

$jogadorId  = (int)($_POST['jogador_id'] ?? 0);
$dataTreino = trim($_POST['data_treino'] ?? '');

if ($jogadorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataTreino)) {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos']);
    exit;
}

// Verifica se já está confirmado
$stmtChk = $pdo->prepare("SELECT id FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino = ? LIMIT 1");
$stmtChk->execute([$jogadorId, $dataTreino]);
$jaConfirmado = $stmtChk->fetch();

if ($jaConfirmado) {
    // Remove da lista
    $pdo->prepare("DELETE FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino = ?")
        ->execute([$jogadorId, $dataTreino]);
    echo json_encode(['ok' => true, 'acao' => 'removido']);
} else {
    // Busca dados do jogador
    $stmtJ = $pdo->prepare("SELECT nome_completo, cpf, telefone, email FROM jogadores WHERE id = ? LIMIT 1");
    $stmtJ->execute([$jogadorId]);
    $j = $stmtJ->fetch(PDO::FETCH_ASSOC);

    if (!$j) {
        echo json_encode(['ok' => false, 'msg' => 'Jogador não encontrado']);
        exit;
    }

    $pdo->prepare("
        INSERT IGNORE INTO confirmacoes_treino (jogador_id, data_treino, nome_completo, cpf, telefone, email)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$jogadorId, $dataTreino, $j['nome_completo'], $j['cpf'], $j['telefone'], $j['email']]);

    echo json_encode(['ok' => true, 'acao' => 'confirmado']);
}
