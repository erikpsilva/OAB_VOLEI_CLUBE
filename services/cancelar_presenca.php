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
require_once dirname(__FILE__, 2) . '/config/envio_helper.php';

$dataTreino = trim($_POST['data_treino'] ?? '');

if (!$dataTreino || !DateTime::createFromFormat('Y-m-d', $dataTreino)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

$pdo    = getDbConnection();
$config = getAppConfig($pdo);

// Bloqueia se o email já foi enviado (treino encerrado)
$stmtEnc = $pdo->prepare("SELECT 1 FROM treinos_encerrados WHERE data_treino = ? LIMIT 1");
$stmtEnc->execute([$dataTreino]);
if ($stmtEnc->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Não é possível cancelar após o envio da lista para o clube.']);
    exit;
}

// Bloqueia se é hoje e o horário de disparo já passou
$agora = new DateTime();
if ($dataTreino === $agora->format('Y-m-d')) {
    $disparoHora = $config['disparo_hora'] ?? '13:00';
    if ($agora->format('H:i') >= $disparoHora) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'O prazo para cancelamento já encerrou.']);
        exit;
    }
}

$jogadorId = $_SESSION['jogador']['id'];

// Verifica se o jogador realmente está confirmado
$stmtCheck = $pdo->prepare("SELECT id FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino = ? LIMIT 1");
$stmtCheck->execute([$jogadorId, $dataTreino]);
if (!$stmtCheck->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Você não está confirmado neste treino.']);
    exit;
}

// Remove a confirmação
$stmtDel = $pdo->prepare("DELETE FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino = ?");
$stmtDel->execute([$jogadorId, $dataTreino]);

echo json_encode(['success' => true, 'message' => 'Sua confirmação foi cancelada. A vaga foi liberada para outros participantes.']);
