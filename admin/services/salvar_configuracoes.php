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

$emailsAdminRaw   = $_POST['emails_admin']    ?? [];
$emailsEsperiaRaw = $_POST['emails_esperia']  ?? [];
$emailRemetente   = trim($_POST['email_remetente']      ?? '');
$mensagemEmail    = trim($_POST['mensagem_email']        ?? '');
$smtpAtivo        = ($_POST['smtp_ativo'] ?? '0') === '1' ? '1' : '0';
$smtpHost         = trim($_POST['smtp_host']            ?? '');
$smtpPorta        = (int) ($_POST['smtp_porta']         ?? 587);
$smtpEncryption   = trim($_POST['smtp_encryption']      ?? 'tls');
$smtpUsuario      = trim($_POST['smtp_usuario']         ?? '');
$smtpSenha        = $_POST['smtp_senha']                ?? '';
$disparoDia       = trim($_POST['disparo_dia_semana']   ?? '');
$disparoHora      = trim($_POST['disparo_hora']         ?? '');
$maxVagas         = (int) ($_POST['max_vagas']          ?? 0);
$modoAbertura     = trim($_POST['modo_abertura_agenda'] ?? '');
$exibirListaHome  = ($_POST['exibir_lista_home'] ?? '0') === '1' ? '1' : '0';

// Normaliza para array e filtra vazios
$emailsAdmin   = array_values(array_filter(array_map('trim', (array) $emailsAdminRaw)));
$emailsEsperia = array_values(array_filter(array_map('trim', (array) $emailsEsperiaRaw)));

if (empty($emailsAdmin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe ao menos um e-mail da Coordenação.']);
    exit;
}
if (count($emailsAdmin) > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Máximo de 5 e-mails para a Coordenação.']);
    exit;
}
foreach ($emailsAdmin as $em) {
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-mail inválido na Coordenação: ' . $em]);
        exit;
    }
}

if (empty($emailsEsperia)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe ao menos um e-mail do Clube Esperia.']);
    exit;
}
if (count($emailsEsperia) > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Máximo de 5 e-mails para o Clube Esperia.']);
    exit;
}
foreach ($emailsEsperia as $em) {
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-mail inválido no Clube Esperia: ' . $em]);
        exit;
    }
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

if ($maxVagas < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'O número de vagas deve ser no mínimo 1.']);
    exit;
}

if (!in_array($modoAbertura, ['automatico', 'manual'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Modo de abertura inválido.']);
    exit;
}

if ($emailRemetente !== '' && !filter_var($emailRemetente, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail do remetente inválido.']);
    exit;
}

if ($smtpUsuario !== '' && !filter_var($smtpUsuario, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuário SMTP deve ser um e-mail válido.']);
    exit;
}

if (!in_array($smtpEncryption, ['tls', 'ssl', 'none'])) {
    $smtpEncryption = 'tls';
}

if ($smtpPorta < 1 || $smtpPorta > 65535) {
    $smtpPorta = 587;
}

// Mensagem: strip de tags HTML, máx 2000 chars
$mensagemEmail = substr(strip_tags($mensagemEmail), 0, 2000);

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$sql = "INSERT INTO app_configuracoes (chave, valor) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
$stmt = $pdo->prepare($sql);

$stmt->execute(['emails_admin',        json_encode($emailsAdmin,   JSON_UNESCAPED_UNICODE)]);
$stmt->execute(['emails_esperia',      json_encode($emailsEsperia, JSON_UNESCAPED_UNICODE)]);
$stmt->execute(['email_remetente',     $emailRemetente]);
$stmt->execute(['mensagem_email',      $mensagemEmail]);
$stmt->execute(['smtp_ativo',          $smtpAtivo]);
$stmt->execute(['smtp_host',           $smtpHost]);
$stmt->execute(['smtp_porta',          (string) $smtpPorta]);
$stmt->execute(['smtp_encryption',     $smtpEncryption]);
$stmt->execute(['smtp_usuario',        $smtpUsuario]);
// Senha: só atualiza se foi preenchida
if (trim($smtpSenha) !== '') {
    $stmt->execute(['smtp_senha', $smtpSenha]);
}
$stmt->execute(['disparo_dia_semana',  $disparoDia]);
$stmt->execute(['disparo_hora',        $disparoHora]);
$stmt->execute(['max_vagas',           (string) $maxVagas]);
$stmt->execute(['exibir_lista_home',   $exibirListaHome]);
$stmt->execute(['modo_abertura_agenda', $modoAbertura]);

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
