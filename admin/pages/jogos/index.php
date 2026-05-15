<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

function fmtData(string $dt, array $meses): string {
    $d = DateTime::createFromFormat('Y-m-d', $dt);
    return $d->format('d') . ' de ' . $meses[$d->format('m')] . ' de ' . $d->format('Y');
}

// Datas com confirmações
$stmtD = $pdo->query("SELECT DISTINCT data_treino FROM confirmacoes_treino ORDER BY data_treino DESC");
$datas = $stmtD->fetchAll(PDO::FETCH_COLUMN);

$dataSel = $_GET['data'] ?? ($datas[0] ?? null);

$times    = [];
$estado   = null;
$partidas = [];
$hasSorteio = false;
$isEncerrado = false;

$nivelLabels = [1=>'Iniciante 1',2=>'Iniciante 2',3=>'Médio',4=>'Avançado',5=>'Profissional'];
$nivelColors = [1=>'#6c757d',2=>'#17a2b8',3=>'#28a745',4=>'#fd7e14',5=>'#ffc107'];

$_coresMap = [
    'azul'=>'#0b3c75','vermelho'=>'#e30613','verde'=>'#155724',
    'amarelo'=>'#ffc300','laranja'=>'#e67e22','roxo'=>'#6f42c1',
    'rosa'=>'#e91e8c','preto'=>'#212529','cinza'=>'#6c757d',
];
function hexCor(string $c): string {
    global $_coresMap;
    return ($c !== '' && $c[0] === '#') ? $c : ($_coresMap[$c] ?? '#6c757d');
}

if ($dataSel) {
    $stmtT = $pdo->prepare("SELECT times_json FROM sorteio_times WHERE data_treino = ? LIMIT 1");
    $stmtT->execute([$dataSel]);
    $timesRow = $stmtT->fetch();
    if ($timesRow) {
        $times = json_decode($timesRow['times_json'], true);
        $hasSorteio = true;
    }

    $stmtE = $pdo->prepare("SELECT estado_json FROM sorteio_estado WHERE data_treino = ? LIMIT 1");
    $stmtE->execute([$dataSel]);
    $estadoRow = $stmtE->fetch();
    if ($estadoRow) {
        $estado = json_decode($estadoRow['estado_json'], true);
        $isEncerrado = (bool)($estado['encerrado'] ?? false);
    }

    $stmtP = $pdo->prepare("SELECT * FROM sorteio_partidas WHERE data_treino = ? ORDER BY numero ASC");
    $stmtP->execute([$dataSel]);
    $partidas = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // Jogadores presentes para exibir caso não haja sorteio ainda
    if (!$hasSorteio) {
        $presentes = [];
        try {
            $stmtPr = $pdo->prepare("
                SELECT j.id, j.nome_completo, COALESCE(j.nivel_jogo,3) AS nivel_jogo
                FROM presenca_treino pt
                JOIN jogadores j ON j.id = pt.jogador_id
                WHERE pt.data_treino = ? AND pt.status = 'presente'
                ORDER BY j.nivel_jogo DESC, j.nome_completo
            ");
            $stmtPr->execute([$dataSel]);
            $presentes = $stmtPr->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { }

        if (empty($presentes)) {
            $stmtPr = $pdo->prepare("
                SELECT j.id, j.nome_completo, COALESCE(j.nivel_jogo,3) AS nivel_jogo
                FROM confirmacoes_treino ct
                JOIN jogadores j ON j.id = ct.jogador_id
                WHERE ct.data_treino = ?
                ORDER BY j.nivel_jogo DESC, j.nome_completo
            ");
            $stmtPr->execute([$dataSel]);
            $presentes = $stmtPr->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Partida atual (em andamento)
$partidaAtual = null;
if ($estado && !$isEncerrado) {
    $numAtual = (int)($estado['partida_atual'] ?? 1);
    foreach ($partidas as $p) {
        if ((int)$p['numero'] === $numAtual && $p['idx_vencedor'] === null) {
            $partidaAtual = $p;
            break;
        }
    }
}

// Normaliza cores para hex
foreach ($times as $idx => $t) {
    $times[$idx]['color'] = hexCor($t['color'] ?? '');
}

// Partidas encerradas
$partidasEncerradas = array_filter($partidas, fn($p) => $p['idx_vencedor'] !== null);
$totalTimes = count($times);
$totalJogadores = array_reduce($times, function ($acc, $time) {
    return $acc + count($time['jogadores'] ?? []);
}, 0);
$totalPartidas = count($partidas);
$totalResultados = count($partidasEncerradas);
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Admin - Jogos do Treino</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<style>
.jogos-page h2 { font-size: 1.5rem; font-weight: 700; color: #0b3c75; margin-bottom: 4px; }
.jogos-page h2 span { color: #f5a623; }
.jogos-page__sub { color: #777; font-size: .9rem; margin-bottom: 24px; }

.jogos-controls { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
.jogos-controls select { padding: 8px 14px; border: 1.5px solid #ddd; border-radius: 8px; font-size: .9rem; }

/* Times grid */
.times-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 28px; }
.time-card { border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); background: #fff; }
.time-card__header { padding: 10px 16px; color: #fff; font-weight: 700; font-size: .95rem; text-transform: uppercase; letter-spacing: .5px; }
.time-card__body { padding: 10px 0; }
.time-card__jogador { display: flex; align-items: center; justify-content: space-between; padding: 7px 16px; border-bottom: 1px solid #f5f5f5; font-size: .85rem; }
.time-card__jogador:last-child { border-bottom: none; }
.time-card__jogador__nome { color: #222; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
.time-card__jogador__right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.time-card__jogador__stars { color: #f5a623; font-size: .8rem; }
.time-card__jogador__badge { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 10px; color: #fff; white-space: nowrap; }
.time-card__footer { padding: 8px 16px; font-size: 11px; color: #999; text-align: right; border-top: 1px solid #f0f0f0; }

/* Partida atual */
.partida-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.1); padding: 24px; margin-bottom: 20px; }
.partida-card__label { font-size: 12px; font-weight: 700; color: #777; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 16px; }
.partida-card__times { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.partida-card__time {
    flex: 1; padding: 14px; border-radius: 10px; text-align: center;
    font-weight: 700; font-size: 1rem; color: #fff;
}
.partida-card__vs { font-size: 1.2rem; font-weight: 700; color: #555; flex-shrink: 0; }
.partida-card__placar { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.partida-card__placar label { font-size: .9rem; font-weight: 600; color: #333; min-width: 90px; }
.partida-card__input {
    width: 72px; padding: 8px; border: 2px solid #ddd; border-radius: 8px;
    font-size: 1.3rem; font-weight: 700; text-align: center; color: #0b3c75;
}
.partida-card__input:focus { border-color: #0b3c75; outline: none; }
.partida-card__x { font-size: 1.2rem; font-weight: 700; color: #999; }
.btn-registrar {
    background: #28a745; color: #fff; border: none; border-radius: 8px;
    padding: 11px 28px; font-size: .95rem; font-weight: 700; cursor: pointer;
    letter-spacing: .5px; text-transform: uppercase; transition: background .2s;
}
.btn-registrar:hover { background: #218838; }
.btn-encerrar {
    background: #6c757d; color: #fff; border: none; border-radius: 8px;
    padding: 9px 22px; font-size: .85rem; font-weight: 600; cursor: pointer;
    margin-left: 12px; transition: background .2s;
}
.btn-encerrar:hover { background: #545b62; }

/* Fila */
.fila-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.1); padding: 16px 24px; margin-bottom: 24px; }
.fila-card__label { font-size: 12px; font-weight: 700; color: #777; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; }
.fila-card__times { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.fila-badge { padding: 6px 16px; border-radius: 8px; color: #fff; font-weight: 700; font-size: .85rem; }
.fila-arrow { color: #999; font-size: 1rem; }

/* Resultados */
.resultados-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.resultados-table th { background: #0b3c75; color: #fff; font-size: 12px; padding: 10px 14px; text-align: left; letter-spacing: .5px; text-transform: uppercase; }
.resultados-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; font-size: .88rem; }
.resultados-table tr:last-child td { border-bottom: none; }
.resultados-table__venc { font-weight: 700; color: #28a745; }

/* Banner sem sorteio */
.sorteio-cta { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.1); padding: 32px; text-align: center; margin-bottom: 24px; }
.sorteio-cta h3 { font-size: 1.1rem; color: #0b3c75; margin-bottom: 8px; }
.sorteio-cta p { color: #777; margin-bottom: 20px; font-size: .9rem; }
.btn-sortear { background: #0b3c75; color: #fff; border: none; border-radius: 8px; padding: 12px 32px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .2s; }
.btn-sortear:hover { background: #0a3367; }
.presentes-preview { margin-top: 16px; font-size: .85rem; color: #555; }

/* Encerrado badge */
.badge-encerrado { display: inline-block; background: #6c757d; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-left: 10px; vertical-align: middle; }

/* Visual inspirado na tela pública de treino */
.jogos-page { max-width: 1120px; margin: 0 auto; }
.jogos-hero { position: relative; overflow: hidden; background: linear-gradient(135deg, #072950 0%, #0b3c75 58%, #0f5a9d 100%); border-radius: 16px; padding: 26px; margin-bottom: 22px; box-shadow: 0 16px 42px rgba(11,60,117,.18); border: 1px solid rgba(255,255,255,.10); }
.jogos-hero::after { content: ''; position: absolute; top: -70px; right: -48px; width: 210px; height: 210px; border-radius: 50%; background: rgba(255,195,0,.16); pointer-events: none; }
.jogos-hero::before { content: ''; position: absolute; right: -70px; bottom: -110px; width: 260px; height: 260px; border-radius: 50%; background: rgba(255,255,255,.07); pointer-events: none; }
.jogos-hero__top { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
.jogos-page .jogos-hero h2 { color: #fff; font-size: 28px; line-height: 1.15; margin: 0 0 7px; }
.jogos-page .jogos-hero h2 span { color: #ffc300; }
.jogos-page__sub { color: rgba(255,255,255,.76); margin: 0; }
.jogos-status { display: inline-flex; align-items: center; gap: 7px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: .8px; padding: 7px 13px; text-transform: uppercase; white-space: nowrap; }
.jogos-status.--live { background: #e30613; color: #fff; box-shadow: 0 8px 18px rgba(227,6,19,.28); }
.jogos-status.--live::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #fff; box-shadow: 0 0 0 0 rgba(255,255,255,.75); animation: jogosPulse 1.4s infinite; }
.jogos-status.--done { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.22); color: #fff; }
.jogos-status.--idle { background: rgba(255,195,0,.18); border: 1px solid rgba(255,195,0,.28); color: #ffc300; }
.jogos-stats { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 10px; }
.jogos-stats__item { background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.14); border-radius: 12px; padding: 12px 14px; backdrop-filter: blur(8px); }
.jogos-stats__num { display: block; color: #ffc300; font-size: 22px; font-weight: 700; line-height: 1; }
.jogos-stats__label { display: block; color: rgba(255,255,255,.68); font-size: 11px; font-weight: 600; margin-top: 6px; text-transform: uppercase; letter-spacing: .4px; }
@keyframes jogosPulse { 0%{box-shadow:0 0 0 0 rgba(255,255,255,.75)} 70%{box-shadow:0 0 0 8px rgba(255,255,255,0)} 100%{box-shadow:0 0 0 0 rgba(255,255,255,0)} }

.jogos-controls { background: #fff; border: 1px solid #e8edf3; border-radius: 12px; padding: 12px 14px; box-shadow: 0 2px 12px rgba(11,60,117,.06); }
.jogos-controls label { color: #0b3c75!important; }
.jogos-controls select { flex: 1; min-width: 240px; min-height: 42px; border-color: #dfe7f0; color: #555; background: #fff; }
.jogos-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.jogos-actions:empty { display: none; }
.admin-section-title { display: flex; align-items: center; gap: 8px; color: #0b3c75; font-size: 12px; font-weight: 700; letter-spacing: .8px; margin: 28px 0 12px; text-transform: uppercase; }

.partida-card { position: relative; overflow: hidden; border: 1px solid #e9eef5; border-radius: 16px; box-shadow: 0 10px 30px rgba(11,60,117,.12); padding: 22px; }
.partida-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(11,60,117,.035), transparent 35%, transparent 65%, rgba(11,60,117,.035)); pointer-events: none; }
.partida-card__label { color: #0b3c75; margin-bottom: 14px; }
.partida-card__times { display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); gap: 14px; align-items: stretch; margin-bottom: 20px; }
.partida-card__time { position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 112px; border-radius: 14px; padding: 18px 12px; box-shadow: inset 0 -3px 0 rgba(0,0,0,.12); }
.partida-card__vs { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; align-self: center; border-radius: 50%; background: #eef3f8; color: #9aa8b8; font-size: 15px; }
.partida-card__placar { justify-content: center; gap: 12px; flex-wrap: wrap; background: #f8fafc; border-radius: 12px; padding: 14px; margin-bottom: 16px; }
.partida-card__placar label { min-width: auto; color: #0b3c75; }
.partida-card__input { width: 76px; height: 52px; border-color: #dfe7f0; font-size: 1.45rem; }
.partida-card__x { color: #9aa8b8; }
.btn-registrar, .btn-sortear, .btn-encerrar { box-shadow: 0 4px 12px rgba(11,60,117,.10); }
.btn-registrar { display: block; width: fit-content; margin: 0 auto; }

.fila-card { border: 1px solid #edf1f6; border-radius: 14px; box-shadow: 0 4px 16px rgba(11,60,117,.08); }
.fila-card__label { color: #0b3c75; }
.fila-badge { display: inline-flex; align-items: center; gap: 7px; border-radius: 10px; box-shadow: 0 2px 10px rgba(11,60,117,.08); }
.fila-badge::before { content: ''; width: 9px; height: 9px; border-radius: 50%; background: rgba(255,255,255,.85); }
.fila-arrow { display: none; }

.resultados-table { border: 1px solid #edf1f6; border-radius: 14px; box-shadow: 0 4px 16px rgba(11,60,117,.08); }
.resultados-table th { background: #0b3c75; }
.resultados-table td { color: #555; }
.resultados-table__venc { color: #155724; }
.resultados-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 7px; vertical-align: middle; }

.times-grid { grid-template-columns: repeat(auto-fill,minmax(240px,1fr)); align-items: start; }
.time-card { border: 1px solid #edf1f6; border-radius: 14px; box-shadow: 0 4px 16px rgba(11,60,117,.08); transition: .2s transform, .2s box-shadow; }
.time-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(11,60,117,.12); }
.time-card__header { min-height: 44px; display: flex; align-items: center; }
.time-card__jogador { gap: 12px; padding: 9px 16px; }
.time-card__jogador__nome { max-width: none; min-width: 0; }
.time-card__jogador__stars { display: none; }
.time-card__jogador__badge { background: #eef3f8!important; color: #647287!important; }

.sorteio-cta { border: 1px solid #edf1f6; border-radius: 16px; box-shadow: 0 4px 16px rgba(11,60,117,.08); }

.admin-accordion { margin-top: 24px; }
.admin-accordion__header { margin: 0 0 12px; }
.admin-accordion__toggle { width: 100%; border: 0; background: transparent; padding: 0; text-align: left; }
.admin-accordion__icon { display: none; }
.admin-accordion__body { display: block; }

/* ── Modal Administrar Partida ───────────────────────── */
.placar-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.55); backdrop-filter: blur(3px);
    z-index: 9999; align-items: center; justify-content: center;
}
.placar-modal-overlay.--open { display: flex; }
.placar-modal {
    background: #f4f7fb; border-radius: 20px;
    width: 100%; max-width: 520px; max-height: 92vh;
    overflow-y: auto; box-shadow: 0 32px 80px rgba(0,0,0,.28);
    display: flex; flex-direction: column;
}
.placar-modal__header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; background: #fff;
    border-radius: 20px 20px 0 0; border-bottom: 1px solid #eef3f8;
}
.placar-modal__header h3 { margin: 0; font-size: 1rem; font-weight: 700; color: #0b3c75; }
.placar-modal__close {
    width: 34px; height: 34px; border: none; background: #eef3f8;
    border-radius: 50%; font-size: 20px; cursor: pointer; color: #555;
    display: flex; align-items: center; justify-content: center; line-height: 1;
    flex-shrink: 0;
}
.placar-modal__close:hover { background: #dce6f0; }
.placar-modal__team-headers {
    display: grid; grid-template-columns: 1fr 1fr;
}
.placar-modal__team-hd {
    padding: 12px 10px; text-align: center;
    font-weight: 700; font-size: .95rem; color: #fff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.placar-modal__scores {
    display: grid; grid-template-columns: 1fr 36px 1fr;
    align-items: center; gap: 8px;
    padding: 28px 20px; background: #fff; margin: 0; flex: 1;
}
.placar-modal__side { display: flex; flex-direction: column; align-items: center; gap: 12px; }
.placar-modal__btn {
    width: 68px; height: 68px; border: none; border-radius: 50%;
    font-size: 30px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: transform .1s, box-shadow .1s; user-select: none;
    -webkit-user-select: none; touch-action: manipulation;
    box-shadow: 0 4px 14px rgba(0,0,0,.15);
}
.placar-modal__btn:active { transform: scale(.88); box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.placar-modal__btn--plus  { background: #28a745; color: #fff; }
.placar-modal__btn--minus { background: #dc3545; color: #fff; }
.placar-modal__num {
    font-size: 80px; font-weight: 900; color: #0b3c75;
    line-height: 1; min-width: 90px; text-align: center;
    font-variant-numeric: tabular-nums;
}
.placar-modal__x { font-size: 26px; font-weight: 700; color: #c8d3df; text-align: center; }
.placar-modal__footer {
    padding: 14px 20px 20px; background: #fff;
    border-top: 1px solid #eef3f8; border-radius: 0 0 20px 20px;
    display: flex; flex-direction: column; gap: 10px;
}
.placar-modal__sync {
    text-align: center; font-size: 12px; color: #aaa; min-height: 16px;
    transition: color .3s;
}
.placar-modal__sync.--saved { color: #28a745; }
.placar-modal__sync.--error { color: #dc3545; }
.btn-admin-partida {
    display: block; width: 100%; padding: 14px;
    background: #0b3c75; color: #fff; border: none; border-radius: 10px;
    font-size: .95rem; font-weight: 700; cursor: pointer; letter-spacing: .5px;
    text-transform: uppercase; transition: background .2s;
    box-shadow: 0 4px 12px rgba(11,60,117,.2);
}
.btn-admin-partida:hover { background: #0a3367; }
.btn-registrar-final {
    width: 100%; padding: 16px; border: none; border-radius: 12px;
    background: #28a745; color: #fff; font-size: 1rem; font-weight: 700;
    cursor: pointer; letter-spacing: .5px; text-transform: uppercase;
    box-shadow: 0 4px 14px rgba(40,167,69,.3); transition: background .2s;
}
.btn-registrar-final:hover { background: #218838; }
@media (max-width: 600px) {
    .placar-modal-overlay { align-items: stretch; padding: 0; }
    .placar-modal { border-radius: 0; max-height: 100vh; height: 100vh; max-width: 100%; }
    .placar-modal__header { border-radius: 0; }
    .placar-modal__footer { border-radius: 0; padding-bottom: env(safe-area-inset-bottom, 20px); }
    .placar-modal__scores { flex: 1; padding: 0 20px; }
    .placar-modal__btn { width: 76px; height: 76px; font-size: 34px; }
    .placar-modal__num { font-size: 88px; }
}

@media only screen and (max-width: 991px) {
    .jogos-page { max-width: none; }
}
@media only screen and (max-width: 767px) {
    .jogos-hero { border-radius: 0; margin-left: -15px; margin-right: -15px; padding: 20px 15px 18px; }
    .jogos-hero__top { flex-direction: column; gap: 12px; padding-right: 82px; }
    .jogos-page .jogos-hero h2 { font-size: 24px; }
    .jogos-page__sub { font-size: 13px; line-height: 1.45; }
    .jogos-status { position: absolute; top: 0; right: 0; padding: 6px 10px; font-size: 10px; }
    .jogos-stats { grid-template-columns: repeat(4,minmax(0,1fr)); gap: 8px; }
    .jogos-stats__item { min-height: 58px; padding: 9px 8px; text-align: center; }
    .jogos-stats__num { font-size: 19px; }
    .jogos-stats__label { font-size: 9px; letter-spacing: .25px; }
    .jogos-controls { align-items: stretch; flex-direction: column; gap: 10px; }
    .jogos-controls select { width: 100%; min-width: 0; }
    .jogos-actions { width: 100%; }
    .jogos-actions button { flex: 1; margin: 0; white-space: nowrap; }
    .admin-section-title { margin-top: 24px; }
    .partida-card { padding: 12px; border-radius: 14px; }
    .partida-card__times { grid-template-columns: 1fr; gap: 10px; }
    .partida-card__time { min-height: 104px; }
    .partida-card__vs { justify-self: center; width: 34px; height: 34px; }
    .partida-card__placar { display: grid; grid-template-columns: 1fr auto 1fr; gap: 8px; padding: 12px; }
    .partida-card__placar label { display: none; }
    .partida-card__input { width: 100%; height: 52px; }
    .btn-registrar { width: 100%; }
    .fila-card { padding: 14px; overflow: hidden; }
    .fila-card__label { margin-bottom: 10px; }
    .fila-card__times { display: block; overflow: visible; padding-bottom: 0; }
    .fila-card__times:not(.slick-initialized) { display: flex; flex-wrap: nowrap; gap: 8px; overflow: hidden; }
    .fila-card__times.slick-initialized { position: relative; }
    .fila-card__times .slick-list { overflow: hidden; margin: 0 -5px; }
    .fila-card__times .slick-slide { padding: 0 5px; }
    .fila-card__times .slick-dots { position: static; display: flex!important; justify-content: center; gap: 6px; margin: 10px 0 0; padding: 0; list-style: none; }
    .fila-card__times .slick-dots li { width: auto; height: auto; margin: 0; }
    .fila-card__times .slick-dots button { display: block; width: 6px; height: 6px; border: 0; border-radius: 50%; padding: 0; background: #c8d3df; font-size: 0; line-height: 0; }
    .fila-card__times .slick-dots .slick-active button { width: 16px; border-radius: 999px; background: #0b3c75; }
    .fila-badge { width: 100%; justify-content: center; min-height: 42px; padding: 9px 12px; }
    .fila-arrow { display: none; }
    .admin-accordion { margin-top: 18px; border: 1px solid #edf1f6; border-radius: 14px; background: #fff; box-shadow: 0 4px 16px rgba(11,60,117,.08); overflow: hidden; }
    .admin-accordion__header { margin: 0; }
    .admin-accordion__toggle { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 52px; padding: 0 14px; cursor: pointer; }
    .admin-accordion__toggle .admin-section-title { margin: 0; }
    .admin-accordion__icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #eef3f8; color: #0b3c75; font-size: 18px; line-height: 1; transition: .2s transform; flex: 0 0 auto; }
    .admin-accordion.is-open .admin-accordion__icon { transform: rotate(45deg); }
    .admin-accordion__body { display: none; padding: 0 12px 14px; }
    .admin-accordion.is-open .admin-accordion__body { display: block; }
    .admin-accordion .row { margin-left: 0; margin-right: 0; }
    .admin-accordion .col-md-12 { padding-left: 0; padding-right: 0; }
    .resultados-table, .resultados-table tbody, .resultados-table td, .resultados-table tr { display: block; width: 100%; }
    .resultados-table { background: transparent; border: 0; box-shadow: none; border-radius: 0; }
    .resultados-table thead { display: none; }
    .resultados-table tbody { display: grid; gap: 10px; }
    .resultados-table tr { background: #fff; border: 1px solid #edf1f6; border-radius: 12px; box-shadow: 0 2px 10px rgba(11,60,117,.07); overflow: hidden; }
    .resultados-table td { display: flex; align-items: center; justify-content: space-between; gap: 14px; min-height: 42px; padding: 11px 13px; text-align: right; }
    .resultados-table td::before { content: attr(data-label); color: #7d7d7d; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; text-align: left; flex-shrink: 0; }
    .resultados-table td:nth-child(2), .resultados-table td:nth-child(4) { align-items: flex-start; flex-direction: column; text-align: left; gap: 6px; }
    .resultados-table td:nth-child(2)::before, .resultados-table td:nth-child(4)::before { width: 100%; }
    .times-grid { grid-template-columns: 1fr; gap: 12px; }
    .time-card { box-shadow: 0 2px 10px rgba(11,60,117,.07); }
    .time-card:hover { transform: none; box-shadow: 0 2px 10px rgba(11,60,117,.07); }
    .time-card__jogador__badge { font-size: 9px; }
    .presentes-preview { max-height: 150px; overflow-y: auto; text-align: left; }
}
@media only screen and (max-width: 420px) {
    .jogos-stats { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .jogos-stats__item { text-align: left; }
    .jogos-hero__top { padding-right: 0; }
    .jogos-status { position: static; }
}
</style>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="jogos-page">
            <div class="jogos-hero">
                <div class="jogos-hero__top">
                    <div>
                        <h2>Jogos do <span>Treino</span></h2>
                        <p class="jogos-page__sub"><?= $dataSel ? fmtData($dataSel, $meses) . ' — ' : '' ?>Monte os times, registre os placares e acompanhe os jogos.</p>
                    </div>
                    <?php if ($hasSorteio && $isEncerrado): ?>
                    <span class="jogos-status --done">Encerrado</span>
                    <?php elseif ($hasSorteio): ?>
                    <span class="jogos-status --live">Ao vivo</span>
                    <?php else: ?>
                    <span class="jogos-status --idle">Aguardando sorteio</span>
                    <?php endif; ?>
                </div>
                <div class="jogos-stats">
                    <div class="jogos-stats__item">
                        <span class="jogos-stats__num"><?= $totalTimes ?></span>
                        <span class="jogos-stats__label">Times</span>
                    </div>
                    <div class="jogos-stats__item">
                        <span class="jogos-stats__num"><?= $totalJogadores ?></span>
                        <span class="jogos-stats__label">Jogadores</span>
                    </div>
                    <div class="jogos-stats__item">
                        <span class="jogos-stats__num"><?= $totalPartidas ?></span>
                        <span class="jogos-stats__label">Jogos</span>
                    </div>
                    <div class="jogos-stats__item">
                        <span class="jogos-stats__num"><?= $totalResultados ?></span>
                        <span class="jogos-stats__label">Resultados</span>
                    </div>
                </div>
            </div>

            <!-- Seletor de data -->
            <div class="jogos-controls">
                <label style="font-weight:600;color:#333;">Treino:</label>
                <select onchange="window.location.href='<?= BASE_URL ?>/admin/jogos?data='+this.value">
                    <?php foreach ($datas as $dt): ?>
                    <option value="<?= $dt ?>" <?= $dt === $dataSel ? 'selected' : '' ?>>
                        <?= fmtData($dt, $meses) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="jogos-actions">
                <?php if ($hasSorteio && !$isEncerrado): ?>
                <button class="btn-encerrar" onclick="encerrarTreino()">Encerrar treino</button>
                <?php endif; ?>
                <?php if ($hasSorteio && !$isEncerrado): ?>
                <button class="btn-sortear" style="padding:9px 20px;font-size:.85rem;" onclick="refazerSorteio()">Refazer sorteio</button>
                <?php endif; ?>
                </div>
            </div>

            <?php if (!$dataSel): ?>
            <p style="color:#777;text-align:center;padding:40px 0;">Nenhum treino encontrado.</p>

            <?php elseif (!$hasSorteio): ?>
            <!-- Sem sorteio: mostra botão para montar times -->
            <div class="sorteio-cta">
                <h3>Times não foram sorteados ainda para este treino</h3>
                <p>
                    O sistema usará os jogadores marcados como <strong>Presentes</strong> na Lista de Presença.
                    <?php if (!empty($presentes)): ?>
                    Há <strong><?= count($presentes) ?></strong> jogadores disponíveis.
                    <?php else: ?>
                    Nenhum jogador marcado como presente ainda.
                    <?php endif; ?>
                </p>
                <?php if (!empty($presentes)): ?>
                <button class="btn-sortear" onclick="sortearTimes()">Montar Times</button>
                <div class="presentes-preview">
                    <?php foreach ($presentes as $pr): ?>
                    <span style="margin:0 4px;">• <?= htmlspecialchars(explode(' ', $pr['nome_completo'])[0]) ?> (<?= str_repeat('★', $pr['nivel_jogo']) ?>)</span>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:#dc3545;">Registre a presença dos jogadores primeiro na <a href="<?= BASE_URL ?>/admin/presenca?data=<?= $dataSel ?>">Lista de Presença</a>.</p>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- Tem sorteio: exibe tudo -->

            <!-- Partida atual -->
            <?php if ($partidaAtual && !$isEncerrado): ?>
            <div class="admin-section-title">&#9917; Jogo em andamento</div>
            <div class="partida-card">
                <div class="partida-card__label">Partida <?= $partidaAtual['numero'] ?></div>
                <div class="partida-card__times">
                    <div class="partida-card__time" style="background:<?= hexCor($times[$partidaAtual['idx_casa']]['color'] ?? '') ?>">
                        <?= htmlspecialchars($times[$partidaAtual['idx_casa']]['name'] ?? 'Time ?') ?>
                        <div style="font-size:.75rem;font-weight:400;margin-top:4px;opacity:.85;">
                            <?php
                                $cons = 0;
                                foreach ($estado['em_quadra'] as $eq) {
                                    $eqIdx = is_array($eq) ? (int)($eq['idx'] ?? -1) : (int)$eq;
                                    if ($eqIdx === (int)$partidaAtual['idx_casa']) { $cons = (int)($eq['consecutivos'] ?? 0); break; }
                                }
                                echo $cons > 0 ? $cons . 'ª partida na quadra' : '1ª partida na quadra';
                            ?>
                        </div>
                    </div>
                    <div class="partida-card__vs">VS</div>
                    <div class="partida-card__time" style="background:<?= hexCor($times[$partidaAtual['idx_visitante']]['color'] ?? '') ?>">
                        <?= htmlspecialchars($times[$partidaAtual['idx_visitante']]['name'] ?? 'Time ?') ?>
                        <div style="font-size:.75rem;font-weight:400;margin-top:4px;opacity:.85;">
                            <?php
                                $cons = 0;
                                foreach ($estado['em_quadra'] as $eq) {
                                    $eqIdx = is_array($eq) ? (int)($eq['idx'] ?? -1) : (int)$eq;
                                    if ($eqIdx === (int)$partidaAtual['idx_visitante']) { $cons = (int)($eq['consecutivos'] ?? 0); break; }
                                }
                                echo $cons > 0 ? $cons . 'ª partida na quadra' : '1ª partida na quadra';
                            ?>
                        </div>
                    </div>
                </div>
                <button class="btn-admin-partida" onclick="abrirAdministrar()">&#9881; Administrar Partida</button>
            </div>
            <?php elseif ($isEncerrado): ?>
            <div style="background:#f8f9fa;border-radius:10px;padding:20px;text-align:center;margin-bottom:20px;color:#6c757d;font-weight:600;">
                Treino encerrado — confira os resultados abaixo.
            </div>
            <?php endif; ?>

            <!-- Fila de espera -->
            <?php if (!$isEncerrado && !empty($estado['fila'])): ?>
            <div class="admin-section-title">&#9201; Próximos na fila</div>
            <div class="fila-card">
                <div class="fila-card__label">Fila de espera</div>
                <div class="fila-card__times" id="adminFilaSlider">
                    <?php $filaShown = 0; foreach ($estado['fila'] as $fi):
                        $fiIdx = is_array($fi) ? (int)($fi['idx'] ?? -1) : (int)$fi;
                        $t = $times[$fiIdx] ?? null;
                        if (!$t) continue;
                    ?>
                    <?php if ($filaShown > 0): ?><span class="fila-arrow">&#8594;</span><?php endif; ?>
                    <span class="fila-badge" style="background:<?= hexCor($t['color'] ?? '') ?>"><?= htmlspecialchars($t['name']) ?></span>
                    <?php $filaShown++; endforeach; ?>
                    <?php if ($filaShown === 0): ?><span style="color:#aaa;font-size:.85rem;">Fila vazia</span><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Times -->
            <div id="secAdminTimes" class="admin-accordion is-open" data-admin-accordion>
            <div class="admin-accordion__header">
                <button type="button" class="admin-accordion__toggle" aria-expanded="true">
                    <span class="admin-section-title">&#128101; Times do treino</span>
                    <span class="admin-accordion__icon" aria-hidden="true">+</span>
                </button>
            </div>
            <div class="admin-accordion__body">
            <div class="times-grid">
                <?php foreach ($times as $idx => $time): ?>
                <div class="time-card">
                    <div class="time-card__header" style="background:<?= hexCor($time['color'] ?? '') ?>">
                        <?= htmlspecialchars($time['name']) ?>
                    </div>
                    <div class="time-card__body">
                        <?php foreach ($time['jogadores'] ?? [] as $jg):
                            $nv = (int)($jg['nivel_jogo'] ?? 3);
                            $nLabel = $nivelLabels[$nv] ?? 'Médio';
                            $nColor = $nivelColors[$nv] ?? '#28a745';
                        ?>
                        <div class="time-card__jogador">
                            <span class="time-card__jogador__nome" title="<?= htmlspecialchars($jg['nome'] ?? $jg['nome_completo'] ?? '') ?>">
                                <?= htmlspecialchars($jg['nome'] ?? $jg['nome_completo'] ?? '') ?>
                            </span>
                            <div class="time-card__jogador__right">
                                <span class="time-card__jogador__stars"><?= str_repeat('★', $nv) ?><?= str_repeat('☆', 5-$nv) ?></span>
                                <span class="time-card__jogador__badge" style="background:<?= $nColor ?>"><?= $nLabel ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($time['jogadores'])): ?>
                        <div style="padding:10px 16px;color:#aaa;font-size:.82rem;font-style:italic;">Sem jogadores registrados</div>
                        <?php endif; ?>
                    </div>
                    <div class="time-card__footer"><?= count($time['jogadores'] ?? []) ?> jogadores</div>
                </div>
                <?php endforeach; ?>
            </div>
            </div>
            </div>

            <!-- Resultados -->
            <?php if (!empty($partidasEncerradas)): ?>
            <div id="secAdminResultados" class="admin-accordion is-open" data-admin-accordion>
            <div class="admin-accordion__header">
                <button type="button" class="admin-accordion__toggle" aria-expanded="true">
                    <span class="admin-section-title">&#128202; Resultados</span>
                    <span class="admin-accordion__icon" aria-hidden="true">+</span>
                </button>
            </div>
            <div class="admin-accordion__body">
                    <table class="resultados-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Time A</th>
                                <th>Placar</th>
                                <th>Time B</th>
                                <th>Vencedor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidasEncerradas as $p):
                                $tCasa = $times[$p['idx_casa']] ?? null;
                                $tVisi = $times[$p['idx_visitante']] ?? null;
                                $tVenc = $p['idx_vencedor'] !== null ? ($times[$p['idx_vencedor']] ?? null) : null;
                            ?>
                            <tr>
                                <td data-label="Jogo">#<?= $p['numero'] ?></td>
                                <td data-label="Time A"><?php if ($tCasa): ?><span><i class="resultados-dot" style="background:<?= hexCor($tCasa['color'] ?? '') ?>"></i><?= htmlspecialchars($tCasa['name']) ?></span><?php endif; ?></td>
                                <td data-label="Placar" style="font-weight:700;text-align:center;"><?= $p['placar_casa'] ?> × <?= $p['placar_visitante'] ?></td>
                                <td data-label="Time B"><?php if ($tVisi): ?><span><i class="resultados-dot" style="background:<?= hexCor($tVisi['color'] ?? '') ?>"></i><?= htmlspecialchars($tVisi['name']) ?></span><?php endif; ?></td>
                                <td data-label="Vencedor" class="resultados-table__venc"><?= $tVenc ? htmlspecialchars($tVenc['name']) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>
            </div>
            <?php endif; ?>

            <?php endif; // hasSorteio ?>

        </section>
    </main>
</div>

<!-- Modal Administrar Partida -->
<div class="placar-modal-overlay" id="placarModalOverlay" onclick="if(event.target===this)fecharModal()">
    <div class="placar-modal">
        <div class="placar-modal__header">
            <h3>&#9881; Administrar Partida</h3>
            <button class="placar-modal__close" onclick="fecharModal()">&#10005;</button>
        </div>
        <div class="placar-modal__team-headers">
            <div class="placar-modal__team-hd" id="modalHdCasa">Casa</div>
            <div class="placar-modal__team-hd" id="modalHdVisit">Visitante</div>
        </div>
        <div class="placar-modal__scores">
            <div class="placar-modal__side">
                <button class="placar-modal__btn placar-modal__btn--plus" onclick="addPonto('casa',1)">+</button>
                <div class="placar-modal__num" id="modalNumCasa">0</div>
                <button class="placar-modal__btn placar-modal__btn--minus" onclick="addPonto('casa',-1)">&#8722;</button>
            </div>
            <div class="placar-modal__x">&#215;</div>
            <div class="placar-modal__side">
                <button class="placar-modal__btn placar-modal__btn--plus" onclick="addPonto('visitante',1)">+</button>
                <div class="placar-modal__num" id="modalNumVisit">0</div>
                <button class="placar-modal__btn placar-modal__btn--minus" onclick="addPonto('visitante',-1)">&#8722;</button>
            </div>
        </div>
        <div class="placar-modal__footer">
            <div class="placar-modal__sync" id="modalSync"></div>
            <button class="btn-registrar-final" onclick="registrarFinal()">&#127942; Registrar Placar Final</button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var BASE_URL       = "<?= BASE_URL ?>";
var DATA_TREINO    = "<?= $dataSel ?>";
var PARTIDA_NUM         = <?= $partidaAtual ? (int)$partidaAtual['numero'] : 0 ?>;
var PARTIDA_IDX_CASA    = <?= $partidaAtual ? (int)$partidaAtual['idx_casa'] : -1 ?>;
var PARTIDA_IDX_VISIT   = <?= $partidaAtual ? (int)$partidaAtual['idx_visitante'] : -1 ?>;
var PARTIDA_PLACAR_CASA = <?= $partidaAtual ? (int)$partidaAtual['placar_casa'] : 0 ?>;
var PARTIDA_PLACAR_VISIT= <?= $partidaAtual ? (int)$partidaAtual['placar_visitante'] : 0 ?>;
var TIMES_DATA          = <?= json_encode($times) ?>;

$(function () {
    var $resultados = $('#secAdminResultados');
    var $times = $('#secAdminTimes');
    if ($resultados.length && $times.length && $resultados.index() > $times.index()) {
        $resultados.insertBefore($times);
    }

    configurarAdminFilaSlider();
    configurarAdminAccordions();
    $(window).on('resize orientationchange', function () {
        configurarAdminFilaSlider();
        configurarAdminAccordions();
    });
});

function configurarAdminFilaSlider() {
    var $fila = $('#adminFilaSlider');
    if (!$fila.length || typeof $.fn.slick !== 'function') return;

    if ($(window).width() <= 767) {
        if (!$fila.hasClass('slick-initialized') && $fila.find('.fila-badge').length > 1) {
            $fila.slick({
                arrows: false,
                dots: true,
                infinite: false,
                slidesToShow: 2,
                slidesToScroll: 1,
                adaptiveHeight: false,
                slide: '.fila-badge',
                responsive: [
                    { breakpoint: 480, settings: { slidesToShow: 1.35 } }
                ]
            });
        }
    } else if ($fila.hasClass('slick-initialized')) {
        $fila.slick('unslick');
    }
}

function configurarAdminAccordions() {
    var isMobile = $(window).width() <= 767;
    var $accordions = $('[data-admin-accordion]');
    if (!$accordions.length) return;

    $accordions.each(function () {
        var $accordion = $(this);
        var $toggle = $accordion.find('.admin-accordion__toggle').first();

        if (!isMobile) {
            $accordion.addClass('is-open');
            $toggle.attr('aria-expanded', 'true');
            return;
        }

        if (!$accordion.data('mobileAccordionReady')) {
            var deveAbrir = $accordion.is('#secAdminResultados') || !$('#secAdminResultados').length;
            $accordion.toggleClass('is-open', deveAbrir);
            $toggle.attr('aria-expanded', deveAbrir ? 'true' : 'false');
            $accordion.data('mobileAccordionReady', true);
        }
    });
}

$(document).on('click', '.admin-accordion__toggle', function () {
    if ($(window).width() > 767) return;
    var $accordion = $(this).closest('[data-admin-accordion]');
    var abrir = !$accordion.hasClass('is-open');
    $accordion.toggleClass('is-open', abrir);
    $(this).attr('aria-expanded', abrir ? 'true' : 'false');
    if (abrir) configurarAdminFilaSlider();
});

function sortearTimes() {
    if (!confirm('Montar os times? Isso irá criar um novo sorteio para ' + DATA_TREINO + '.')) return;
    $.post(ADMIN_BASE_URL + '/services/sortear_times.php', { data_treino: DATA_TREINO }, function(res) {
        if (res.ok) {
            window.location.reload();
        } else {
            alert(res.msg || 'Erro ao sortear times.');
        }
    }).fail(function() { alert('Erro na requisição.'); });
}

function refazerSorteio() {
    if (!confirm('Isso vai APAGAR o sorteio atual e todos os placares. Confirma?')) return;
    sortearTimes();
}

var _modalPlacarCasa  = 0;
var _modalPlacarVisit = 0;
var _saveTimer = null;

function abrirAdministrar() {
    if (!PARTIDA_NUM) return;
    _modalPlacarCasa  = PARTIDA_PLACAR_CASA;
    _modalPlacarVisit = PARTIDA_PLACAR_VISIT;
    $('#modalNumCasa').text(_modalPlacarCasa);
    $('#modalNumVisit').text(_modalPlacarVisit);
    $('#modalSync').removeClass('--saved --error').text('');

    var timeCasa  = TIMES_DATA[PARTIDA_IDX_CASA]  || {};
    var timeVisit = TIMES_DATA[PARTIDA_IDX_VISIT] || {};
    $('#modalHdCasa').text(timeCasa.name  || 'Casa').css('background', timeCasa.color  || '#0b3c75');
    $('#modalHdVisit').text(timeVisit.name || 'Visitante').css('background', timeVisit.color || '#6c757d');

    $('#placarModalOverlay').addClass('--open');
    document.body.style.overflow = 'hidden';
}

function fecharModal() {
    $('#placarModalOverlay').removeClass('--open');
    document.body.style.overflow = '';
}

function addPonto(lado, delta) {
    if (lado === 'casa') {
        _modalPlacarCasa = Math.max(0, _modalPlacarCasa + delta);
        $('#modalNumCasa').text(_modalPlacarCasa);
    } else {
        _modalPlacarVisit = Math.max(0, _modalPlacarVisit + delta);
        $('#modalNumVisit').text(_modalPlacarVisit);
    }
    salvarPlacarParcial();
}

function salvarPlacarParcial() {
    clearTimeout(_saveTimer);
    _saveTimer = setTimeout(function () {
        $('#modalSync').removeClass('--saved --error').text('Salvando...');
        $.post(ADMIN_BASE_URL + '/services/atualizar_placar.php', {
            data_treino:      DATA_TREINO,
            numero:           PARTIDA_NUM,
            placar_casa:      _modalPlacarCasa,
            placar_visitante: _modalPlacarVisit
        }, function(res) {
            if (res.ok) {
                $('#modalSync').addClass('--saved').text('Salvo ✓');
            } else {
                $('#modalSync').addClass('--error').text('Erro ao salvar');
            }
        }).fail(function() {
            $('#modalSync').addClass('--error').text('Erro de conexão');
        });
    }, 300);
}

function registrarFinal() {
    if (_modalPlacarCasa === _modalPlacarVisit) {
        alert('Não pode ser empate. Um time deve ter mais pontos.');
        return;
    }
    var timeCasa  = TIMES_DATA[PARTIDA_IDX_CASA]  || {};
    var timeVisit = TIMES_DATA[PARTIDA_IDX_VISIT] || {};
    var msg = 'Registrar placar final?\n\n' +
              (timeCasa.name  || 'Casa')      + ': ' + _modalPlacarCasa  + '\n' +
              (timeVisit.name || 'Visitante') + ': ' + _modalPlacarVisit;
    if (!confirm(msg)) return;

    $.post(ADMIN_BASE_URL + '/services/registrar_placar.php', {
        data_treino:      DATA_TREINO,
        numero:           PARTIDA_NUM,
        placar_casa:      _modalPlacarCasa,
        placar_visitante: _modalPlacarVisit
    }, function(res) {
        if (res.ok) {
            fecharModal();
            window.location.reload();
        } else {
            alert(res.msg || 'Erro ao registrar placar.');
        }
    }).fail(function() { alert('Erro na requisição.'); });
}

$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('#placarModalOverlay').hasClass('--open')) fecharModal();
});

function encerrarTreino() {
    if (!confirm('Encerrar o treino? Nenhuma nova partida poderá ser registrada.')) return;
    $.post(ADMIN_BASE_URL + '/services/encerrar_jogos.php', { data_treino: DATA_TREINO }, function(res) {
        if (res.ok) window.location.reload();
        else alert(res.msg || 'Erro ao encerrar.');
    });
}
</script>
</body>
</html>
