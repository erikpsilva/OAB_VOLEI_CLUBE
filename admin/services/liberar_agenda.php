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

// Calcula a próxima sexta-feira
$hoje = new DateTime();
$hoje->setTime(0, 0, 0);
$proximaSexta = clone $hoje;
while ($proximaSexta->format('N') != 5) {
    $proximaSexta->modify('+1 day');
}
$dataSexta = $proximaSexta->format('Y-m-d');

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("INSERT INTO app_configuracoes (chave, valor) VALUES ('agenda_liberada_data', ?)
                        ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
$stmt->execute([$dataSexta]);

$meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
          '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$dataFormatada = $proximaSexta->format('d') . ' de ' . $meses[$proximaSexta->format('m')];

echo json_encode([
    'success'        => true,
    'data_liberada'  => $dataSexta,
    'data_formatada' => $dataFormatada,
    'message'        => 'Agenda liberada para ' . $dataFormatada . '.',
]);
