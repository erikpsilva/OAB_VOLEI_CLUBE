<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$data = trim($_GET['data'] ?? '');
if (!$data || !DateTime::createFromFormat('Y-m-d', $data)) {
    echo '<p style="font-family:Arial;padding:40px;color:red;">Data inválida.</p>';
    exit;
}

$dt     = DateTime::createFromFormat('Y-m-d', $data);
$meses  = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
           '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$dataLonga = $dt->format('d') . ' de ' . $meses[$dt->format('m')] . ' de ' . $dt->format('Y');

$stmt = $pdo->prepare("
    SELECT j.nome_completo, j.cpf, j.telefone
    FROM confirmacoes_treino ct
    JOIN jogadores j ON j.id = ct.jogador_id
    WHERE ct.data_treino = ?
    ORDER BY j.nome_completo
");
$stmt->execute([$data]);
$jogadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

function _formatCpfPrint(string $cpf): string {
    $d = preg_replace('/\D/', '', $cpf);
    return strlen($d) === 11
        ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2)
        : $cpf;
}

$total = count($jogadores);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Confirmações — <?= htmlspecialchars($dataLonga) ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 13px;
        color: #212529;
        background: #fff;
        padding: 32px 40px;
    }

    .print-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        border-bottom: 3px solid #0B3C75;
        padding-bottom: 16px;
        margin-bottom: 24px;
    }

    .print-header__org {
        font-size: 18px;
        font-weight: bold;
        color: #0B3C75;
    }

    .print-header__sub {
        font-size: 12px;
        color: #6c757d;
        margin-top: 4px;
    }

    .print-header__date {
        text-align: right;
    }

    .print-header__date__label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
    }

    .print-header__date__value {
        font-size: 15px;
        font-weight: bold;
        color: #0B3C75;
        margin-top: 4px;
    }

    .print-title {
        font-size: 14px;
        font-weight: bold;
        color: #0B3C75;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 16px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead tr {
        background-color: #0B3C75;
    }

    thead th {
        padding: 9px 12px;
        text-align: left;
        font-size: 11px;
        color: rgba(255,255,255,0.9);
        font-weight: bold;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    tbody tr:nth-child(even) { background-color: #f8f9fa; }
    tbody tr:nth-child(odd)  { background-color: #ffffff; }

    tbody td {
        padding: 9px 12px;
        border-bottom: 1px solid #e9ecef;
        font-size: 13px;
        color: #212529;
    }

    tbody td:first-child {
        color: #6c757d;
        text-align: center;
        width: 36px;
    }

    .print-footer {
        margin-top: 24px;
        padding-top: 14px;
        border-top: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .print-footer__total {
        font-size: 13px;
        color: #495057;
    }

    .print-footer__total strong {
        font-size: 16px;
        color: #0B3C75;
    }

    .print-footer__assinatura {
        text-align: right;
        font-size: 11px;
        color: #adb5bd;
    }

    .print-footer__assinatura__linha {
        border-top: 1px solid #adb5bd;
        width: 200px;
        margin: 0 0 6px auto;
    }

    .btn-print {
        display: inline-block;
        background-color: #0B3C75;
        color: #fff;
        border: none;
        padding: 10px 24px;
        font-size: 13px;
        font-weight: bold;
        border-radius: 6px;
        cursor: pointer;
        margin-bottom: 24px;
    }

    .btn-print:hover { background-color: #092e5a; }

    @media print {
        .btn-print { display: none; }
        body { padding: 0; }
    }
</style>
</head>
<body>

<button class="btn-print" onclick="window.print()">&#128438; Imprimir</button>

<div class="print-header">
    <div>
        <div class="print-header__org">OAB Santana Vôlei Clube</div>
        <div class="print-header__sub">Clube Esperia — Zona Norte, São Paulo</div>
    </div>
    <div class="print-header__date">
        <div class="print-header__date__label">Data do treino</div>
        <div class="print-header__date__value">Sexta-feira, <?= htmlspecialchars($dataLonga) ?></div>
    </div>
</div>

<div class="print-title">Lista de Confirmações de Presença</div>

<?php if ($total === 0): ?>
    <p style="color:#6c757d;font-size:13px;padding:20px 0;">Nenhum jogador confirmado para este treino.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Nome Completo</th>
            <th>CPF</th>
            <th>Telefone</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($jogadores as $i => $j): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($j['nome_completo']) ?></td>
            <td style="font-family:monospace;"><?= htmlspecialchars(_formatCpfPrint($j['cpf'] ?? '')) ?></td>
            <td><?= htmlspecialchars($j['telefone'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="print-footer">
    <div class="print-footer__total">
        Total confirmado: <strong><?= $total ?></strong> / 30 vagas
    </div>
    <div class="print-footer__assinatura">
        <div class="print-footer__assinatura__linha"></div>
        Coordenação OAB Santana Vôlei Clube
    </div>
</div>

<script>
    // Auto-print ao abrir (pode comentar se preferir manual)
    window.addEventListener('load', function () { window.print(); });
</script>

</body>
</html>
