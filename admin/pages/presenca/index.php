<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

// Datas com confirmações (ordenadas desc)
$stmtD = $pdo->query("
    SELECT DISTINCT data_treino FROM confirmacoes_treino
    ORDER BY data_treino DESC
");
$datas = $stmtD->fetchAll(PDO::FETCH_COLUMN);

$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

$dataSel = $_GET['data'] ?? ($datas[0] ?? null);
$jogadores = [];
$presencas = [];

if ($dataSel) {
    $stmtJ = $pdo->prepare("
        SELECT j.id, j.nome_completo, COALESCE(j.nivel_jogo, 3) AS nivel_jogo
        FROM confirmacoes_treino ct
        JOIN jogadores j ON j.id = ct.jogador_id
        WHERE ct.data_treino = ?
        ORDER BY j.nome_completo ASC
    ");
    $stmtJ->execute([$dataSel]);
    $jogadores = $stmtJ->fetchAll(PDO::FETCH_ASSOC);

    try {
        $stmtPr = $pdo->prepare("SELECT jogador_id, status FROM presenca_treino WHERE data_treino = ?");
        $stmtPr->execute([$dataSel]);
        foreach ($stmtPr->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $presencas[$p['jogador_id']] = $p['status'];
        }
    } catch (Exception $e) { }
}

function fmtData(string $dt, array $meses): string {
    $d = DateTime::createFromFormat('Y-m-d', $dt);
    return $d->format('d') . ' de ' . $meses[$d->format('m')] . ' de ' . $d->format('Y');
}

$totalPresente    = 0;
$totalEspectador  = 0;
$totalFalta       = 0;
foreach ($jogadores as $j) {
    $s = $presencas[$j['id']] ?? null;
    if ($s === 'presente')    $totalPresente++;
    elseif ($s === 'espectador') $totalEspectador++;
    elseif ($s)               $totalFalta++;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Admin - Lista de Presença</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<style>
.presenca-page h2 { font-size: 1.5rem; font-weight: 700; color: #0b3c75; margin-bottom: 4px; }
.presenca-page h2 span { color: #f5a623; }
.presenca-page__sub { color: #777; font-size: .9rem; margin-bottom: 24px; }

.presenca-controls { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
.presenca-controls select { padding: 8px 14px; border: 1.5px solid #ddd; border-radius: 8px; font-size: .9rem; color: #333; background: #fff; }

.presenca-stats { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.presenca-stat { padding: 10px 20px; border-radius: 8px; font-size: .85rem; font-weight: 600; }
.presenca-stat--total      { background: #e8f0fe; color: #1a56db; }
.presenca-stat--present    { background: #e6f4ea; color: #1e8a3a; }
.presenca-stat--espectador { background: #e8f4fd; color: #117a8b; }
.presenca-stat--falta      { background: #fce8e8; color: #c0392b; }
.presenca-stat--sem        { background: #f5f5f5; color: #777; }

.presenca-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.presenca-table th { background: #0b3c75; color: #fff; font-size: 12px; letter-spacing: .5px; text-transform: uppercase; padding: 12px 16px; text-align: left; }
.presenca-table td { padding: 11px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.presenca-table tr:last-child td { border-bottom: none; }
.presenca-table__nome { font-weight: 600; color: #222; }
.presenca-table__nivel { font-size: 12px; color: #f5a623; }

.presenca-btns { display: flex; gap: 8px; align-items: center; }
.btn-pres {
    padding: 6px 16px; border: none; border-radius: 6px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: opacity .2s; line-height: 1.4;
}
.btn-pres--presente    { background: #28a745; color: #fff; }
.btn-pres--espectador  { background: #17a2b8; color: #fff; }
.btn-pres--falta       { background: #dc3545; color: #fff; }
.btn-pres--ativo       { opacity: 1; }
.btn-pres--inativo     { opacity: .35; }
.presenca-status { font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 20px; color: #fff; }
.presenca-status--presente            { background: #28a745; }
.presenca-status--espectador          { background: #17a2b8; }
.presenca-status--falta_justificada   { background: #fd7e14; }
.presenca-status--falta_injustificada { background: #dc3545; }

/* Modal */
.presenca-modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 9999; align-items: center; justify-content: center;
}
.presenca-modal-overlay.--open { display: flex; }
.presenca-modal {
    background: #fff; border-radius: 14px; padding: 32px; max-width: 400px; width: 90%;
    box-shadow: 0 8px 32px rgba(0,0,0,.18);
}
.presenca-modal h3 { font-size: 1.1rem; font-weight: 700; color: #0b3c75; margin-bottom: 8px; }
.presenca-modal p  { font-size: .9rem; color: #555; margin-bottom: 20px; }
.presenca-modal__btns { display: flex; gap: 10px; }
.presenca-modal__btns button {
    flex: 1; padding: 10px; border: none; border-radius: 8px; font-size: .9rem;
    font-weight: 600; cursor: pointer;
}
.btn-justificada   { background: #fd7e14; color: #fff; }
.btn-injustificada { background: #dc3545; color: #fff; }
.btn-cancelar-modal { background: #f0f0f0; color: #555; }
</style>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="presenca-page">
            <div class="row">
                <div class="col-md-12">
                    <h2>Lista de <span>Presença</span></h2>
                    <p class="presenca-page__sub">Registre a presença dos jogadores confirmados em cada treino.</p>
                </div>
            </div>

            <!-- Seletor de data -->
            <div class="presenca-controls">
                <label style="font-weight:600;color:#333;">Treino:</label>
                <select id="seletorData" onchange="window.location.href='<?= BASE_URL ?>/admin/presenca?data='+this.value">
                    <?php foreach ($datas as $dt): ?>
                    <option value="<?= $dt ?>" <?= $dt === $dataSel ? 'selected' : '' ?>>
                        <?= fmtData($dt, $meses) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!$dataSel || empty($jogadores)): ?>
            <p style="color:#777;text-align:center;padding:40px 0;">Nenhum jogador confirmado para esta data.</p>
            <?php else: ?>

            <!-- Resumo -->
            <div class="presenca-stats">
                <span class="presenca-stat presenca-stat--total">
                    <?= count($jogadores) ?> confirmados
                </span>
                <span class="presenca-stat presenca-stat--present" id="statPresente">
                    <?= $totalPresente ?> presentes
                </span>
                <span class="presenca-stat presenca-stat--espectador" id="statEspectador">
                    <?= $totalEspectador ?> espectadores
                </span>
                <span class="presenca-stat presenca-stat--falta" id="statFalta">
                    <?= $totalFalta ?> faltas
                </span>
                <span class="presenca-stat presenca-stat--sem" id="statSem">
                    <?= count($jogadores) - $totalPresente - $totalEspectador - $totalFalta ?> sem registro
                </span>
            </div>

            <!-- Tabela -->
            <div class="row">
                <div class="col-md-12">
                    <table class="presenca-table">
                        <thead>
                            <tr>
                                <th>Jogador</th>
                                <th>Nível</th>
                                <th>Ação</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jogadores as $j):
                                $statusAtual = $presencas[$j['id']] ?? null;
                                $estrelas = str_repeat('★', (int)$j['nivel_jogo']) . str_repeat('☆', 5 - (int)$j['nivel_jogo']);
                            ?>
                            <tr id="row-<?= $j['id'] ?>">
                                <td class="presenca-table__nome"><?= htmlspecialchars($j['nome_completo']) ?></td>
                                <td class="presenca-table__nivel"><?= $estrelas ?></td>
                                <td>
                                    <div class="presenca-btns">
                                        <button class="btn-pres btn-pres--presente <?= $statusAtual === 'presente' ? 'btn-pres--ativo' : 'btn-pres--inativo' ?>"
                                                onclick="marcarPresente(<?= $j['id'] ?>)">
                                            &#10003; Presente
                                        </button>
                                        <button class="btn-pres btn-pres--espectador <?= $statusAtual === 'espectador' ? 'btn-pres--ativo' : 'btn-pres--inativo' ?>"
                                                onclick="marcarEspectador(<?= $j['id'] ?>)">
                                            &#128065; Espectador
                                        </button>
                                        <button class="btn-pres btn-pres--falta <?= ($statusAtual === 'falta_justificada' || $statusAtual === 'falta_injustificada') ? 'btn-pres--ativo' : 'btn-pres--inativo' ?>"
                                                onclick="abrirModalFalta(<?= $j['id'] ?>, '<?= htmlspecialchars($j['nome_completo'], ENT_QUOTES) ?>')">
                                            &#10005; Faltou
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($statusAtual):
                                        $labelMap = [
                                            'presente'            => 'Presente',
                                            'espectador'          => 'Espectador',
                                            'falta_justificada'   => 'Falta Justificada',
                                            'falta_injustificada' => 'Falta Injustificada',
                                        ];
                                    ?>
                                    <span class="presenca-status presenca-status--<?= $statusAtual ?> js-status-<?= $j['id'] ?>">
                                        <?= $labelMap[$statusAtual] ?? $statusAtual ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="js-status-<?= $j['id'] ?>" style="color:#aaa;font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

    </main>
</div>

<!-- Modal falta -->
<div class="presenca-modal-overlay" id="modalFalta">
    <div class="presenca-modal">
        <h3>Registrar Falta</h3>
        <p id="modalFaltaNome"></p>
        <div class="presenca-modal__btns">
            <button class="btn-justificada"   onclick="salvarFalta('falta_justificada')">Justificada</button>
            <button class="btn-injustificada" onclick="salvarFalta('falta_injustificada')">Injustificada</button>
            <button class="btn-cancelar-modal" onclick="fecharModal()">Cancelar</button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var DATA_TREINO    = "<?= $dataSel ?>";
var _jogadorFaltaId = null;

var statusLabels = {
    'presente':             'Presente',
    'espectador':           'Espectador',
    'falta_justificada':    'Falta Justificada',
    'falta_injustificada':  'Falta Injustificada',
};
var statusClasses = {
    'presente':             'presenca-status--presente',
    'espectador':           'presenca-status--espectador',
    'falta_justificada':    'presenca-status--falta_justificada',
    'falta_injustificada':  'presenca-status--falta_injustificada',
};

function salvar(jogadorId, status) {
    $.post(ADMIN_BASE_URL + '/services/salvar_presenca.php', {
        jogador_id:  jogadorId,
        data_treino: DATA_TREINO,
        status:      status
    }, function(res) {
        if (!res.ok) { alert(res.msg); return; }

        // Atualiza botões
        var row      = $('#row-' + jogadorId);
        var isPresente  = status === 'presente';
        var isEspect    = status === 'espectador';
        var isFalta     = status === 'falta_justificada' || status === 'falta_injustificada';
        row.find('.btn-pres--presente').toggleClass('btn-pres--ativo', isPresente).toggleClass('btn-pres--inativo', !isPresente);
        row.find('.btn-pres--espectador').toggleClass('btn-pres--ativo', isEspect).toggleClass('btn-pres--inativo', !isEspect);
        row.find('.btn-pres--falta').toggleClass('btn-pres--ativo', isFalta).toggleClass('btn-pres--inativo', !isFalta);

        // Atualiza badge de status
        var el = $('.js-status-' + jogadorId);
        el.attr('class', 'presenca-status ' + statusClasses[status] + ' js-status-' + jogadorId);
        el.text(statusLabels[status]);

        atualizarContadores();
    });
}

function marcarPresente(id)    { salvar(id, 'presente'); }
function marcarEspectador(id)  { salvar(id, 'espectador'); }

function abrirModalFalta(id, nome) {
    _jogadorFaltaId = id;
    document.getElementById('modalFaltaNome').textContent = 'Marcar falta para: ' + nome;
    document.getElementById('modalFalta').classList.add('--open');
}

function fecharModal() {
    document.getElementById('modalFalta').classList.remove('--open');
    _jogadorFaltaId = null;
}

function salvarFalta(tipo) {
    if (!_jogadorFaltaId) return;
    salvar(_jogadorFaltaId, tipo);
    fecharModal();
}

function atualizarContadores() {
    var presente = 0, espectador = 0, falta = 0, sem = 0;
    document.querySelectorAll('[id^="row-"]').forEach(function(row) {
        var badge = row.querySelector('[class*="js-status-"]');
        if (!badge) return;
        var cls = badge.className;
        if (cls.includes('--presente'))    presente++;
        else if (cls.includes('--espectador')) espectador++;
        else if (cls.includes('--falta'))  falta++;
        else sem++;
    });
    $('#statPresente').text(presente + ' presentes');
    $('#statEspectador').text(espectador + ' espectadores');
    $('#statFalta').text(falta + ' faltas');
    $('#statSem').text(sem + ' sem registro');
}

document.getElementById('modalFalta').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});
</script>
</body>
</html>
