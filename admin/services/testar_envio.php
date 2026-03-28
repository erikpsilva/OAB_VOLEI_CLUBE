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

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/envio_helper.php';

$pdo    = getDbConnection();
$config = getAppConfig($pdo);

$emailsAdmin    = json_decode($config['emails_admin'] ?? '[]', true) ?: [];
$emailRemetente = $config['email_remetente'] ?: EMAIL_FROM_ADDR;
$mensagemEmail  = $config['mensagem_email']  ?? '';
$smtpConfig     = [
    'ativo'      => ($config['smtp_ativo']     ?? '0') === '1',
    'host'       => $config['smtp_host']       ?? '',
    'porta'      => $config['smtp_porta']      ?? '587',
    'usuario'    => $config['smtp_usuario']    ?? '',
    'senha'      => $config['smtp_senha']      ?? '',
    'encryption' => $config['smtp_encryption'] ?? 'tls',
];

if (empty($emailsAdmin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum e-mail da Coordenação cadastrado.']);
    exit;
}

// ── Próxima sexta-feira ───────────────────────────────────────
$hoje = new DateTime();
while ($hoje->format('N') != 5) {
    $hoje->modify('+1 day');
}
$dataTreino = $hoje->format('Y-m-d');

$meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',
          '05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',
          '09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$dataLonga = $hoje->format('d') . ' de ' . $meses[$hoje->format('m')] . ' de ' . $hoje->format('Y');

// ── Busca confirmados atuais ───────────────────────────────────
$stmt = $pdo->prepare("
    SELECT j.nome_completo, j.cpf
    FROM confirmacoes_treino ct
    JOIN jogadores j ON j.id = ct.jogador_id
    WHERE ct.data_treino = ?
    ORDER BY j.nome_completo
");
$stmt->execute([$dataTreino]);
$jogadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lista = array_map(function ($j) {
    return [
        'nome_completo' => $j['nome_completo'],
        'cpf'           => _formatCpf($j['cpf'] ?? ''),
    ];
}, $jogadores);

// ── Monta e envia email de teste ──────────────────────────────
$subject = 'OAB Santana Vôlei Clube — Confirmações de Presença — ' . $dataLonga;
$html    = _buildEmail($dataLonga, $lista, 'advogada', $mensagemEmail);

$ok       = true;
$primeiro = true;
foreach ($emailsAdmin as $addr) {
    $bcc = $primeiro ? $emailRemetente : '';
    if (!_sendEmail($addr, $subject, $html, $emailRemetente, $bcc, $smtpConfig)) {
        $ok = false;
    }
    $primeiro = false;
}

$destinos = implode(', ', $emailsAdmin);

if ($ok) {
    echo json_encode([
        'success' => true,
        'message' => 'E-mail de teste enviado para: ' . $destinos . ' (' . count($lista) . ' confirmado(s) na lista atual).',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Falha ao enviar e-mail. Verifique as configurações de SMTP do servidor.',
    ]);
}
