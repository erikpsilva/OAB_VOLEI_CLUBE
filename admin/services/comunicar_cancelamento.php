<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario']) || !in_array($_SESSION['usuario']['nivel_acesso'], ['admin', 'editor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

if (!defined('ROOT')) define('ROOT', dirname(__DIR__, 2));
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/envio_helper.php';

$pdo    = getDbConnection();
$config = getAppConfig($pdo);

$dataTreino  = trim($_POST['data_treino'] ?? '');
$mensagemExtra = trim($_POST['mensagem'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataTreino)) {
    echo json_encode(['success' => false, 'message' => 'Data inválida']);
    exit;
}

// Formata data longa
$meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
          '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$dt = DateTime::createFromFormat('Y-m-d', $dataTreino);
$dataLonga = $dt->format('d') . ' de ' . $meses[$dt->format('m')] . ' de ' . $dt->format('Y');

// Busca todos os jogadores confirmados para enviar email individual
$stmtJ = $pdo->prepare("
    SELECT j.nome_completo, j.email
    FROM confirmacoes_treino ct
    JOIN jogadores j ON j.id = ct.jogador_id
    WHERE ct.data_treino = ? AND j.email != ''
    ORDER BY j.nome_completo
");
$stmtJ->execute([$dataTreino]);
$jogadores = $stmtJ->fetchAll(PDO::FETCH_ASSOC);

// Config de envio
$emailRemetente = $config['email_remetente'] ?: EMAIL_FROM_ADDR;
$smtpConfig = [
    'ativo'      => ($config['smtp_ativo']     ?? '0') === '1',
    'host'       => $config['smtp_host']       ?? '',
    'porta'      => $config['smtp_porta']      ?? '587',
    'usuario'    => $config['smtp_usuario']    ?? '',
    'senha'      => $config['smtp_senha']      ?? '',
    'encryption' => $config['smtp_encryption'] ?? 'tls',
];

// Monta HTML do email de cancelamento
$msgCancelamento = $mensagemExtra ?: 'O treino desta semana foi cancelado. Até a próxima sexta!';
$html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <tr><td style="background:#0B3C75;padding:24px 32px;">
    <h1 style="margin:0;color:#fff;font-size:20px;">OAB Santana Vôlei Clube</h1>
    <p style="margin:4px 0 0;color:#b0c4de;font-size:13px;">Comunicado importante</p>
  </td></tr>
  <tr><td style="padding:32px;">
    <div style="background:#fce8e8;border-left:4px solid #dc3545;padding:16px 20px;border-radius:6px;margin-bottom:24px;">
      <strong style="color:#dc3545;font-size:16px;">&#9888; Treino Cancelado</strong>
      <p style="margin:6px 0 0;color:#555;font-size:14px;">Sexta-feira, ' . $dataLonga . '</p>
    </div>
    <p style="color:#333;font-size:15px;line-height:1.6;">' . nl2br(htmlspecialchars($msgCancelamento)) . '</p>
    <p style="color:#888;font-size:13px;margin-top:24px;">— Coordenação OAB Santana Vôlei Clube</p>
  </td></tr>
</table>
</td></tr></table></body></html>';

$subject = 'OAB Santana Vôlei Clube — Treino Cancelado — ' . $dataLonga;

$enviados = 0;
$erros    = 0;

foreach ($jogadores as $j) {
    if (_sendEmail($j['email'], $subject, $html, $emailRemetente, '', $smtpConfig)) {
        $enviados++;
    } else {
        $erros++;
    }
}

if ($enviados > 0) {
    echo json_encode([
        'success' => true,
        'message' => "Comunicado enviado para {$enviados} jogador(es)" . ($erros > 0 ? " ({$erros} falha(s))" : '') . '.',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $erros > 0 ? 'Falha ao enviar os e-mails. Verifique as configurações de SMTP.' : 'Nenhum jogador confirmado com e-mail cadastrado.',
    ]);
}
