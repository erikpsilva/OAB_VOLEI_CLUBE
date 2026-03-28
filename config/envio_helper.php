<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/*
 * Helper de envio de confirmações de treino.
 *
 * SQL para criar a tabela necessária (execute uma vez no banco oab_bd):
 *
 *   CREATE TABLE IF NOT EXISTS `treinos_encerrados` (
 *     `data_treino`   DATE NOT NULL,
 *     `encerrado_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     `auto_enviado`  TINYINT(1) NOT NULL DEFAULT 0,
 *     PRIMARY KEY (`data_treino`)
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

define('LIMITE_VAGAS',    30);
define('EMAIL_FROM_NAME', 'OAB Santana Vôlei Clube');
define('EMAIL_FROM_ADDR', 'noreply@oabvoleiclube.com.br'); // e-mail criado no cPanel

/**
 * Lê as configurações do sistema do banco de dados,
 * com fallback para valores padrão caso a tabela ainda não exista.
 */
function getAppConfig(PDO $pdo): array
{
    $defaults = [
        'emails_admin'         => '["erikprimao@gmail.com"]',
        'emails_esperia'       => '["erikpsilva@gmail.com"]',
        'email_remetente'      => 'noreply@oabvoleiclube.com.br',
        'mensagem_email'       => '',
        'smtp_ativo'           => '0',
        'smtp_host'            => '',
        'smtp_porta'           => '587',
        'smtp_usuario'         => '',
        'smtp_senha'           => '',
        'smtp_encryption'      => 'tls',
        'disparo_dia_semana'   => '4',
        'disparo_hora'         => '19:00',
        'max_vagas'            => '30',
        'modo_abertura_agenda' => 'automatico',
        'agenda_liberada_data' => '',
    ];
    try {
        $stmt = $pdo->query("SELECT chave, valor FROM app_configuracoes");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $defaults[$row['chave']] = $row['valor'];
        }
    } catch (Exception $e) {
        // tabela ainda não criada — usa defaults
    }
    return $defaults;
}

/**
 * Envia os emails de confirmação para advogada e clube,
 * e marca o treino como encerrado na base de dados.
 *
 * @param  string $data       Data no formato Y-m-d
 * @param  PDO    $pdo
 * @param  bool   $autoEnvio  true quando disparado automaticamente pela cron
 * @return array  ['success' => bool, 'message' => string]
 */
function enviarConfirmacoes(string $data, PDO $pdo, bool $autoEnvio = false): array
{
    // ── Lê configurações do banco ────────────────────────────────
    $config          = getAppConfig($pdo);
    $emailsAdmin     = json_decode($config['emails_admin']   ?? '[]', true) ?: [];
    $emailsEsperia   = json_decode($config['emails_esperia'] ?? '[]', true) ?: [];
    $emailRemetente  = $config['email_remetente'] ?: EMAIL_FROM_ADDR;
    $mensagemEmail   = $config['mensagem_email']  ?? '';
    $smtpConfig      = [
        'ativo'      => ($config['smtp_ativo']     ?? '0') === '1',
        'host'       => $config['smtp_host']       ?? '',
        'porta'      => $config['smtp_porta']      ?? '587',
        'usuario'    => $config['smtp_usuario']    ?? '',
        'senha'      => $config['smtp_senha']      ?? '',
        'encryption' => $config['smtp_encryption'] ?? 'tls',
    ];

    // ── Busca confirmados ─────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT j.nome_completo, j.cpf
        FROM confirmacoes_treino ct
        JOIN jogadores j ON j.id = ct.jogador_id
        WHERE ct.data_treino = ?
        ORDER BY j.nome_completo
    ");
    $stmt->execute([$data]);
    $jogadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Formata data longa ─────────────────────────────────────────
    $meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',
              '05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',
              '09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
    $dt        = DateTime::createFromFormat('Y-m-d', $data);
    $dataLonga = $dt->format('d') . ' de ' . $meses[$dt->format('m')] . ' de ' . $dt->format('Y');

    // ── Email para a advogada (CPF completo) ──────────────────────
    $listaAdvogada = array_map(function ($j) {
        return [
            'nome_completo' => $j['nome_completo'],
            'cpf'           => _formatCpf($j['cpf'] ?? ''),
        ];
    }, $jogadores);

    $htmlAdv     = _buildEmail($dataLonga, $listaAdvogada, 'advogada', $mensagemEmail);
    $subjectAdv  = 'OAB Santana Vôlei Clube — Confirmações de Presença — ' . $dataLonga;
    $okAdv       = true;
    $primeiroAdv = true;
    foreach ($emailsAdmin as $addr) {
        $bcc = $primeiroAdv ? $emailRemetente : '';
        if (!_sendEmail($addr, $subjectAdv, $htmlAdv, $emailRemetente, $bcc, $smtpConfig)) $okAdv = false;
        $primeiroAdv = false;
    }
    if (empty($emailsAdmin)) $okAdv = false;

    // ── Email para o Clube Esperia (CPF completo) ─────────────────
    $listaEsperia = array_map(function ($j) {
        return [
            'nome_completo' => $j['nome_completo'],
            'cpf'           => _formatCpf($j['cpf'] ?? ''),
        ];
    }, $jogadores);

    $htmlEsp      = _buildEmail($dataLonga, $listaEsperia, 'esperia', $mensagemEmail);
    $subjectEsp   = 'OAB Santana Vôlei Clube — Lista Oficial de Presença — ' . $dataLonga;
    $okEsp        = true;
    $primeiroEsp  = true;
    foreach ($emailsEsperia as $addr) {
        $bcc = $primeiroEsp ? $emailRemetente : '';
        if (!_sendEmail($addr, $subjectEsp, $htmlEsp, $emailRemetente, $bcc, $smtpConfig)) $okEsp = false;
        $primeiroEsp = false;
    }
    if (empty($emailsEsperia)) $okEsp = false;

    // ── Marca como encerrado ──────────────────────────────────────
    $ins = $pdo->prepare("
        INSERT INTO treinos_encerrados (data_treino, encerrado_at, auto_enviado)
        VALUES (?, NOW(), ?)
        ON DUPLICATE KEY UPDATE encerrado_at = encerrado_at
    ");
    $ins->execute([$data, $autoEnvio ? 1 : 0]);

    if ($okAdv && $okEsp) {
        return ['success' => true,  'message' => 'E-mails enviados e treino encerrado com sucesso.'];
    }
    return ['success' => false, 'message' => 'Treino encerrado, mas houve falha no envio de e-mail(s).'];
}

// ── Helpers internos ──────────────────────────────────────────────

function _maskCpf(string $cpf): string
{
    $d = preg_replace('/\D/', '', $cpf);
    return strlen($d) === 11 ? $d[0] . '**.***.***-' . substr($d, -2) : $cpf;
}

function _formatTelefone(string $tel): string
{
    $d = preg_replace('/\D/', '', $tel);
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
    }
    return $tel;
}

function _formatCpf(string $cpf): string
{
    $d = preg_replace('/\D/', '', $cpf);
    return strlen($d) === 11
        ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2)
        : $cpf;
}

function _sendEmail(string $to, string $subject, string $html, string $fromAddr = '', string $bcc = '', array $smtpConfig = []): bool
{
    if ($fromAddr === '') $fromAddr = EMAIL_FROM_ADDR;

    // ── SMTP via PHPMailer (somente se explicitamente ativado) ──
    if (!empty($smtpConfig['ativo']) && !empty($smtpConfig['host']) && !empty($smtpConfig['usuario']) && !empty($smtpConfig['senha'])) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtpConfig['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpConfig['usuario'];
            $mail->Password   = $smtpConfig['senha'];
            $mail->Port       = (int) ($smtpConfig['porta'] ?? 587);

            $enc = strtolower($smtpConfig['encryption'] ?? 'tls');
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure  = false;
            }

            $mail->setFrom($fromAddr, EMAIL_FROM_NAME);
            $mail->addReplyTo($fromAddr, EMAIL_FROM_NAME);
            $mail->addAddress($to);
            if ($bcc !== '') $mail->addBCC($bcc);

            $mail->CharSet  = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject  = $subject;
            $mail->Body     = $html;

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Fallback: mail() nativo ───────────────────────────────
    // From: deve ser um e-mail do domínio do servidor (SPF).
    // email_remetente (ex: gmail) vai só no Reply-To.
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDR . ">\r\n";
    if ($fromAddr !== '' && $fromAddr !== EMAIL_FROM_ADDR) {
        $headers .= 'Reply-To: ' . $fromAddr . "\r\n";
    }
    if ($bcc !== '' && $bcc !== EMAIL_FROM_ADDR) {
        $headers .= 'Bcc: ' . $bcc . "\r\n";
    }
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    error_clear_last();
    $ok = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
    if (!$ok) {
        $err = error_get_last();
        error_log('mail() falhou para ' . $to . ': ' . ($err['message'] ?? 'sem detalhe'));
    }
    return $ok;
}

function _buildEmail(string $dataLonga, array $jogadores, string $destinatario, string $mensagem = ''): string
{
    $corPrimary   = '#0B3C75';
    $corSecundary = '#FFC300';
    $total        = count($jogadores);

    if ($destinatario === 'advogada') {
        $rodape = 'Esta lista é destinada ao controle de presença pela assessoria jurídica.';
    } else {
        $rodape = 'Lista oficial enviada pelo OAB Santana Vôlei Clube para controle de acesso às instalações do Clube Esperia — Zona Norte, São Paulo.';
    }

    $linhas = '';
    foreach ($jogadores as $i => $j) {
        $bg = ($i % 2 === 0) ? '#ffffff' : '#f8f9fa';
        $linhas .= '
        <tr style="background-color:' . $bg . ';">
          <td style="padding:10px 14px;border-bottom:1px solid #e9ecef;font-size:13px;color:#6c757d;text-align:center;width:36px;">' . ($i + 1) . '</td>
          <td style="padding:10px 14px;border-bottom:1px solid #e9ecef;font-size:13px;color:#212529;font-weight:600;">' . htmlspecialchars($j['nome_completo']) . '</td>
          <td style="padding:10px 14px;border-bottom:1px solid #e9ecef;font-size:13px;color:#495057;font-family:monospace;">' . htmlspecialchars($j['cpf']) . '</td>
        </tr>';
    }

    if ($total === 0) {
        $linhas = '<tr><td colspan="3" style="padding:20px;text-align:center;color:#6c757d;font-size:13px;">Nenhum jogador confirmado para este treino.</td></tr>';
    }

    return '<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>OAB Santana Vôlei Clube</title></head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- CABEÇALHO -->
  <tr>
    <td style="background-color:' . $corPrimary . ';padding:28px 32px;border-radius:10px 10px 0 0;">
      <table width="100%" cellpadding="0" cellspacing="0"><tr>
        <td>
          <div style="font-size:22px;font-weight:bold;color:#ffffff;letter-spacing:0.5px;">OAB Santana Vôlei Clube</div>
          <div style="font-size:12px;color:rgba(255,255,255,0.65);margin-top:5px;letter-spacing:0.3px;">LISTA DE CONFIRMAÇÕES DE PRESENÇA</div>
        </td>
        <td align="right" style="vertical-align:top;">
          <span style="background-color:' . $corSecundary . ';color:' . $corPrimary . ';font-size:10px;font-weight:bold;padding:5px 14px;border-radius:20px;text-transform:uppercase;letter-spacing:0.8px;">Treino</span>
        </td>
      </tr></table>
    </td>
  </tr>

  <!-- FAIXA DA DATA -->
  <tr>
    <td style="background-color:' . $corSecundary . ';padding:14px 32px;">
      <span style="font-size:15px;font-weight:bold;color:' . $corPrimary . ';">&#128197; Sexta-feira, ' . $dataLonga . '</span>
    </td>
  </tr>

  <!-- CORPO -->
  <tr>
    <td style="background-color:#ffffff;padding:30px 32px;">
      ' . ($mensagem ? '<p style="margin:0 0 26px;font-size:14px;color:#212529;line-height:1.75;white-space:pre-line;">' . nl2br(htmlspecialchars(str_replace('{{data}}', $dataLonga, $mensagem))) . '</p>' : '') . '

      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
        <thead>
          <tr style="background-color:' . $corPrimary . ';">
            <th style="padding:10px 14px;text-align:center;font-size:11px;color:rgba(255,255,255,0.8);font-weight:bold;letter-spacing:0.5px;text-transform:uppercase;width:36px;">#</th>
            <th style="padding:10px 14px;text-align:left;font-size:11px;color:rgba(255,255,255,0.8);font-weight:bold;letter-spacing:0.5px;text-transform:uppercase;">Nome Completo</th>
            <th style="padding:10px 14px;text-align:left;font-size:11px;color:rgba(255,255,255,0.8);font-weight:bold;letter-spacing:0.5px;text-transform:uppercase;">CPF</th>
          </tr>
        </thead>
        <tbody>' . $linhas . '</tbody>
      </table>

      <!-- TOTAL -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
        <tr>
          <td style="background-color:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:12px 16px;">
            <span style="font-size:13px;color:#6c757d;">Total confirmado: </span>
            <span style="font-size:17px;font-weight:bold;color:' . $corPrimary . ';">' . $total . '</span>
            <span style="font-size:12px;color:#adb5bd;"> / ' . LIMITE_VAGAS . ' vagas</span>
          </td>
        </tr>
      </table>

      <p style="margin:24px 0 0;font-size:12px;color:#adb5bd;line-height:1.6;">' . $rodape . '</p>
    </td>
  </tr>

  <!-- RODAPÉ -->
  <tr>
    <td style="background-color:#f8f9fa;border-top:1px solid #dee2e6;padding:16px 32px;border-radius:0 0 10px 10px;text-align:center;">
      <p style="margin:0;font-size:11px;color:#adb5bd;">OAB Santana Vôlei Clube &mdash; Este é um e-mail automático, por favor não responda.</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}
