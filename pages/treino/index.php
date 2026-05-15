<?php
if (empty($_SESSION['jogador'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',
          '05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',
          '09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];

// Todas as datas disponíveis (com sorteio de times)
$stmtDatas = $pdo->query("SELECT data_treino FROM sorteio_times ORDER BY data_treino DESC");
$datasDisponiveis = $stmtDatas->fetchAll(PDO::FETCH_COLUMN);

// Data selecionada via GET ou a mais recente
$dataParam = trim($_GET['data'] ?? '');
if ($dataParam && DateTime::createFromFormat('Y-m-d', $dataParam) && in_array($dataParam, $datasDisponiveis)) {
    $dataSelecionada = $dataParam;
} else {
    $dataSelecionada = !empty($datasDisponiveis) ? $datasDisponiveis[0] : null;
}

// Carrega dados da data selecionada
$times    = [];
$partidas = [];
$estado   = null;
$dataLonga = '';
$temDados  = false;

if ($dataSelecionada) {
    $dt = DateTime::createFromFormat('Y-m-d', $dataSelecionada);
    $dataLonga = $dt->format('d') . ' de ' . $meses[$dt->format('m')] . ' de ' . $dt->format('Y');

    $stmtT = $pdo->prepare("SELECT times_json FROM sorteio_times WHERE data_treino = ? LIMIT 1");
    $stmtT->execute([$dataSelecionada]);
    $timesRow = $stmtT->fetch();
    $times = $timesRow ? json_decode($timesRow['times_json'], true) : [];

    $stmtP = $pdo->prepare("SELECT * FROM sorteio_partidas WHERE data_treino = ? ORDER BY numero ASC");
    $stmtP->execute([$dataSelecionada]);
    $partidas = $stmtP->fetchAll();

    $stmtE = $pdo->prepare("SELECT estado_json FROM sorteio_estado WHERE data_treino = ? LIMIT 1");
    $stmtE->execute([$dataSelecionada]);
    $estadoRow = $stmtE->fetch();
    $estado = $estadoRow ? json_decode($estadoRow['estado_json'], true) : null;

    $temDados = !empty($times);
}

// Verifica se está ao vivo (estado existe e não encerrado)
$isAoVivo = $estado && !($estado['encerrado'] ?? true);
$totalTimes = count($times);
$totalJogadores = array_reduce($times, function ($acc, $time) {
    return $acc + count($time['jogadores'] ?? $time['players'] ?? []);
}, 0);
$totalPartidas = count($partidas);
$totalEncerradas = count(array_filter($partidas, fn($p) => $p['idx_vencedor'] !== null));

$cores = [
    'azul'     => ['bg' => '#0b3c75', 'text' => '#ffffff'],
    'vermelho' => ['bg' => '#e30613', 'text' => '#ffffff'],
    'verde'    => ['bg' => '#155724', 'text' => '#ffffff'],
    'amarelo'  => ['bg' => '#ffc300', 'text' => '#0b3c75'],
    'laranja'  => ['bg' => '#e67e22', 'text' => '#ffffff'],
    'roxo'     => ['bg' => '#6f42c1', 'text' => '#ffffff'],
    'rosa'     => ['bg' => '#e91e8c', 'text' => '#ffffff'],
    'preto'    => ['bg' => '#212529', 'text' => '#ffffff'],
    'cinza'    => ['bg' => '#6c757d', 'text' => '#ffffff'],
];

function getCor(string $color, array $cores): array {
    if ($color !== '' && $color[0] === '#') {
        return ['bg' => $color, 'text' => '#ffffff'];
    }
    return $cores[$color] ?? ['bg' => '#6c757d', 'text' => '#ffffff'];
}

function formatDataSelect(string $data, array $meses): string {
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    return $dt->format('d') . ' de ' . $meses[$dt->format('m')] . ' de ' . $dt->format('Y');
}
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Treino ao Vivo</title>
<?php include ROOT . '/includes/assets.php'; ?>
<style>
.treinoVivo { padding: 32px 0 60px; background: #f4f6f9; min-height: calc(100vh - 92px - 53px); }
.treinoVivo__header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
.treinoVivo__header h2 { font-size: 24px; font-weight: 700; color: #0b3c75; margin: 0 0 4px; }
.treinoVivo__header h2 span { color: #ffc300; }
.treinoVivo__date { font-size: 13px; color: #7d7d7d; }
.badge-live { display: inline-flex; align-items: center; gap: 6px; background: #e30613; color: #fff; font-size: 11px; font-weight: 700; padding: 5px 14px; border-radius: 20px; letter-spacing: 1px; text-transform: uppercase; flex-shrink: 0; }
.badge-live::before { content: ''; display: inline-block; width: 8px; height: 8px; background: #fff; border-radius: 50%; animation: pulse 1.2s infinite; }
.badge-encerrado { display: inline-flex; align-items: center; gap: 6px; background: #546e7a; color: #fff; font-size: 11px; font-weight: 700; padding: 5px 14px; border-radius: 20px; letter-spacing: 1px; flex-shrink: 0; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

/* Seletor de data */
.treinoSelector { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; flex-wrap: wrap; }
.treinoSelector label { font-size: 13px; font-weight: 600; color: #555; white-space: nowrap; }
.treinoSelector select { border: 1.5px solid #e0e0e0; border-radius: 8px; padding: 8px 14px; font-family: inherit; font-size: 14px; color: #555; background: #fff; cursor: pointer; outline: none; }
.treinoSelector select:focus { border-color: #0b3c75; }

/* Seção */
.tvSection { margin-bottom: 28px; }
.tvSection__title { font-size: 12px; font-weight: 700; color: #7d7d7d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }

/* Jogo atual */
.jogoAtual { background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,.10); padding: 32px 24px; display: flex; align-items: center; justify-content: center; gap: 20px; flex-wrap: wrap; }
.jogoAtual__time { display: flex; flex-direction: column; align-items: center; gap: 10px; min-width: 130px; }
.jogoAtual__badge { font-size: 12px; font-weight: 700; padding: 5px 18px; border-radius: 20px; text-transform: uppercase; letter-spacing: .5px; text-align: center; }
.jogoAtual__placar { font-size: 64px; font-weight: 900; color: #0b3c75; line-height: 1; min-width: 80px; text-align: center; }
.jogoAtual__vs { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.jogoAtual__vs__x { font-size: 28px; font-weight: 700; color: #d4d4d4; }
.jogoAtual__vs__num { font-size: 11px; color: #7d7d7d; font-weight: 600; }
.jogoAtual__vazio { text-align: center; color: #7d7d7d; font-size: 14px; padding: 24px; width: 100%; }

/* Fila */
.filaVivo { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.filaVivo__item { display: flex; align-items: center; gap: 8px; background: #fff; border-radius: 8px; padding: 10px 16px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.filaVivo__pos { font-size: 11px; font-weight: 700; color: #7d7d7d; width: 18px; }
.filaVivo__dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.filaVivo__nome { font-size: 13px; font-weight: 600; color: #555; }
.filaVivo__arrow { font-size: 20px; color: #d4d4d4; }

/* Grid de times */
.timesGrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px; }
.timeCard { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.07); }
.timeCard__header { padding: 12px 16px; }
.timeCard__header__nome { font-size: 14px; font-weight: 700; }
.timeCard__players { padding: 10px 16px; }
.timeCard__player { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #555; }
.timeCard__player:last-child { border-bottom: none; }
.timeCard__player__nivel { font-size: 10px; font-weight: 700; background: #f4f6f9; color: #7d7d7d; padding: 2px 8px; border-radius: 10px; }

/* Histórico */
.historicTable { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.07); font-size: 13px; }
.historicTable th { background: #0b3c75; color: #fff; padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.historicTable td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; color: #555; vertical-align: middle; }
.historicTable tr:last-child td { border-bottom: none; }
.historicTable tr:hover td { background: #f9f9f9; }
.historicTable .placar { font-size: 16px; font-weight: 700; color: #0b3c75; text-align: center; white-space: nowrap; }
.historicTable .vencedor-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; color: #fff; white-space: nowrap; }
.historicTable .jogo-num { font-weight: 700; color: #7d7d7d; }
.timeDot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; vertical-align: middle; flex-shrink: 0; }

/* Sem dados */
.semDados { text-align: center; padding: 60px 20px; background: #fff; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.07); }
.semDados__icon { font-size: 48px; margin-bottom: 16px; }
.semDados__title { font-size: 18px; font-weight: 700; color: #0b3c75; margin-bottom: 8px; }
.semDados__sub { font-size: 13px; color: #7d7d7d; }

/* Visual renovado e responsivo */
.treinoVivo { padding: 36px 0 64px; background: linear-gradient(180deg, #eef3f8 0%, #f7f9fc 42%, #f4f6f9 100%); }
.treinoHero { position: relative; overflow: hidden; background: linear-gradient(135deg, #072950 0%, #0b3c75 58%, #0f5a9d 100%); border-radius: 16px; padding: 26px; margin-bottom: 22px; box-shadow: 0 16px 42px rgba(11,60,117,.20); }
.treinoHero::after { content: ''; position: absolute; right: -48px; top: -70px; width: 210px; height: 210px; border-radius: 50%; background: rgba(255,195,0,.16); pointer-events: none; }
.treinoVivo__header { position: relative; z-index: 1; flex-wrap: nowrap; gap: 16px; margin-bottom: 22px; }
.treinoVivo__header h2 { color: #fff; font-size: 28px; line-height: 1.15; margin-bottom: 6px; }
.treinoVivo__date { display: flex; align-items: center; gap: 7px; color: rgba(255,255,255,.76); font-size: 14px; margin: 0; }
.badge-live { gap: 7px; padding: 7px 13px; letter-spacing: .8px; box-shadow: 0 8px 18px rgba(227,6,19,.28); }
.badge-live::before { box-shadow: 0 0 0 0 rgba(255,255,255,.75); animation: treinoPulse 1.4s infinite; }
.badge-encerrado { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.22); color: #fff; padding: 7px 13px; text-transform: uppercase; }
.treinoStats { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.treinoStats__item { background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.14); border-radius: 12px; padding: 12px 14px; }
.treinoStats__num { display: block; color: #ffc300; font-size: 22px; font-weight: 700; line-height: 1; }
.treinoStats__label { display: block; color: rgba(255,255,255,.68); font-size: 11px; font-weight: 600; margin-top: 6px; text-transform: uppercase; letter-spacing: .4px; }
@keyframes treinoPulse { 0%{box-shadow:0 0 0 0 rgba(255,255,255,.75)} 70%{box-shadow:0 0 0 8px rgba(255,255,255,0)} 100%{box-shadow:0 0 0 0 rgba(255,255,255,0)} }

.treinoSelector { margin-bottom: 24px; background: #fff; border: 1px solid #e8edf3; border-radius: 12px; padding: 12px 14px; box-shadow: 0 2px 12px rgba(11,60,117,.06); }
.treinoSelector label { color: #0b3c75; font-weight: 700; }
.treinoSelector select { flex: 1; min-width: 220px; padding: 10px 12px; }
.tvSection__title { display: flex; align-items: center; gap: 8px; color: #0b3c75; letter-spacing: .8px; margin-bottom: 12px; }

.jogoAtual { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); align-items: stretch; gap: 14px; border: 1px solid #e9eef5; border-radius: 16px; padding: 22px; box-shadow: 0 10px 30px rgba(11,60,117,.12); }
.jogoAtual__time { justify-content: center; min-width: 0; background: #f8fafc; border-radius: 14px; padding: 18px 12px; }
.jogoAtual__badge { max-width: 100%; padding: 7px 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.jogoAtual__placar { font-size: 70px; line-height: .95; min-width: 76px; }
.jogoAtual__vs { justify-content: center; min-width: 62px; }
.jogoAtual__vs__x { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; background: #eef3f8; border-radius: 50%; font-size: 25px; color: #9aa8b8; }
.jogoAtual__vs__num { font-weight: 700; white-space: nowrap; }
.jogoAtual__vazio { grid-column: 1 / -1; }

.filaVivo { flex-wrap: nowrap; overflow-x: auto; padding: 2px 2px 10px; scroll-snap-type: x proximity; -webkit-overflow-scrolling: touch; }
.filaVivo__item { border: 1px solid #edf1f6; border-radius: 10px; padding: 11px 14px; box-shadow: 0 2px 10px rgba(11,60,117,.08); flex: 0 0 auto; scroll-snap-align: start; }
.filaVivo__pos { min-width: 18px; width: auto; }
.filaVivo__arrow { color: #c6ced8; flex: 0 0 auto; }

.timesGrid { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
.timeCard { border: 1px solid #edf1f6; border-radius: 14px; box-shadow: 0 4px 16px rgba(11,60,117,.08); }
.timeCard__header__nome { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.timeCard__player { gap: 12px; padding: 9px 0; }
.timeCard__player span:first-child { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.timeCard__player__nivel { white-space: nowrap; flex-shrink: 0; }

.historicTable { border: 1px solid #edf1f6; border-radius: 14px; box-shadow: 0 4px 16px rgba(11,60,117,.08); }
.historicTable tbody tr:hover td { background: #f9f9f9; }
.semDados { border: 1px solid #edf1f6; border-radius: 16px; box-shadow: 0 4px 16px rgba(11,60,117,.08); }
.tvAccordion__head { width: 100%; display: block; padding: 0; background: none; border: 0; text-align: left; cursor: default; }
.tvAccordion__head .tvSection__title { margin-bottom: 12px; }
.tvAccordion__icon { display: none; }

@media only screen and (max-width: 767px) {
    .treinoVivo { padding: 18px 0 42px; }
    .treinoHero { border-radius: 0; margin-left: -15px; margin-right: -15px; margin-bottom: 18px; padding: 22px 15px; }
    .treinoHero::after { right: -88px; top: -94px; width: 190px; height: 190px; }
    .treinoVivo__header { flex-direction: column; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
    .treinoVivo__header h2 { font-size: 24px; }
    .treinoVivo__date { font-size: 13px; line-height: 1.4; }
    .treinoStats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .treinoStats__item { border-radius: 10px; padding: 10px 11px; }
    .treinoStats__num { font-size: 20px; }
    .treinoSelector { align-items: stretch; flex-direction: column; margin-bottom: 20px; padding: 12px; }
    .treinoSelector label { white-space: normal; }
    .treinoSelector select { width: 100%; min-width: 0; }
    .tvSection { margin-bottom: 24px; }
    .jogoAtual { grid-template-columns: minmax(0, 1fr) 44px minmax(0, 1fr); gap: 8px; padding: 10px; border-radius: 14px; }
    .jogoAtual__time { border-radius: 12px; padding: 14px 7px; }
    .jogoAtual__badge { width: 100%; padding: 7px 8px; font-size: 10px; letter-spacing: .2px; }
    .jogoAtual__placar { font-size: 48px; min-width: 0; }
    .jogoAtual__vs { min-width: 44px; }
    .jogoAtual__vs__x { width: 34px; height: 34px; font-size: 20px; }
    .jogoAtual__vs__num { font-size: 10px; text-align: center; white-space: normal; }
    .filaVivo { display: block; overflow: visible; padding: 0 22px 18px 0; }
    .filaVivo .slick-list { overflow: visible; }
    .filaVivo .slick-track { display: flex; align-items: stretch; }
    .filaVivo .slick-slide { height: auto; padding-right: 10px; }
    .filaVivo .slick-slide > div { height: 100%; }
    .filaVivo__item { min-height: 48px; width: 100%; }
    .filaVivo .slick-dots { display: flex !important; justify-content: center; gap: 6px; list-style: none; padding: 0; margin: 10px 0 0; }
    .filaVivo .slick-dots li { width: 6px; height: 6px; }
    .filaVivo .slick-dots button { display: block; width: 6px; height: 6px; padding: 0; border: 0; border-radius: 50%; background: #cbd5df; font-size: 0; line-height: 0; }
    .filaVivo .slick-dots .slick-active button { background: #0b3c75; }
    .timesGrid { grid-template-columns: 1fr; gap: 12px; }
    .timeCard { border-radius: 12px; }
    .tvAccordion { background: #fff; border: 1px solid #edf1f6; border-radius: 14px; box-shadow: 0 2px 10px rgba(11,60,117,.07); overflow: hidden; }
    .tvAccordion__head { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 15px 14px; cursor: pointer; }
    .tvAccordion__head .tvSection__title { margin: 0; }
    .tvAccordion__icon { position: relative; display: block; width: 18px; height: 18px; flex-shrink: 0; }
    .tvAccordion__icon::before,
    .tvAccordion__icon::after { content: ''; position: absolute; left: 4px; right: 4px; top: 8px; height: 2px; background: #0b3c75; border-radius: 2px; transition: .2s transform; }
    .tvAccordion__icon::after { transform: rotate(90deg); }
    .tvAccordion.--open .tvAccordion__icon::after { transform: rotate(0); }
    .tvAccordion__body { display: none; padding: 0 12px 14px; }
    .tvAccordion.--open .tvAccordion__body { display: block; }
    .tvAccordion .historicTable,
    .tvAccordion .timesGrid { margin-top: 0; }
    .historicTable { display: block; background: transparent; border: 0; box-shadow: none; border-radius: 0; overflow: visible; }
    .historicTable thead { display: none; }
    .historicTable tbody { display: grid; gap: 10px; }
    .historicTable tr { display: block; background: #fff; border: 1px solid #edf1f6; border-radius: 12px; box-shadow: 0 2px 10px rgba(11,60,117,.07); overflow: hidden; }
    .historicTable td { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 11px 13px; border-bottom: 1px solid #f0f0f0; text-align: right; }
    .historicTable td::before { content: attr(data-label); color: #7d7d7d; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; text-align: left; flex-shrink: 0; }
    .historicTable td:first-child { background: #f8fafc; }
    .historicTable .placar { font-size: 18px; }
    .historicTable .vencedor-badge { max-width: 150px; overflow: hidden; text-overflow: ellipsis; }
    .semDados { padding: 42px 18px; }
}

@media only screen and (max-width: 380px) {
    .jogoAtual { grid-template-columns: 1fr; }
    .jogoAtual__vs { flex-direction: row; width: 100%; min-width: 0; }
    .jogoAtual__vs__x { width: 32px; height: 32px; }
}

/* Acabamento visual da tela de treino */
.treinoVivo .container { max-width: 1120px; }
.treinoHero { border: 1px solid rgba(255,255,255,.10); }
.treinoHero::before { content: ''; position: absolute; inset: auto -70px -110px auto; width: 260px; height: 260px; border-radius: 50%; background: rgba(255,255,255,.07); pointer-events: none; }
.treinoVivo__header h2 { letter-spacing: 0; }
.treinoStats__item { backdrop-filter: blur(8px); transition: .2s border-color, .2s background-color; }
.treinoStats__item:hover { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.24); }

.treinoSelector { position: relative; align-items: center; gap: 14px; }
.treinoSelector label { display: inline-flex; align-items: center; gap: 7px; }
.treinoSelector select { min-height: 42px; border-color: #dfe7f0; box-shadow: inset 0 1px 0 rgba(255,255,255,.6); }

.tvSection { position: relative; }
.tvSection__title { line-height: 1.2; }
#secJogoAtual .tvSection__title,
#secFila .tvSection__title { margin-left: 2px; }

.jogoAtual { position: relative; overflow: hidden; }
.jogoAtual::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(11,60,117,.035), transparent 35%, transparent 65%, rgba(11,60,117,.035)); pointer-events: none; }
.jogoAtual__time { position: relative; overflow: hidden; }
.jogoAtual__time::after { content: ''; position: absolute; left: 16px; right: 16px; bottom: 0; height: 3px; background: rgba(11,60,117,.16); border-radius: 3px 3px 0 0; }
.jogoAtual__placar { font-variant-numeric: tabular-nums; text-shadow: 0 5px 18px rgba(11,60,117,.12); }
.jogoAtual__vs { position: relative; z-index: 1; }

.filaVivo__item { min-width: 144px; transition: .2s transform, .2s box-shadow; }
.filaVivo__item:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(11,60,117,.10); }
.filaVivo__nome { max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.tvAccordion { border-radius: 14px; }
.tvAccordion__head { border-radius: 14px 14px 0 0; }
.tvAccordion__body { width: 100%; }
.historicTable th:first-child { border-top-left-radius: 12px; }
.historicTable th:last-child { border-top-right-radius: 12px; }
.historicTable td[data-label="Times"] { line-height: 1.4; }
.historicTable .vencedor-badge { display: inline-flex; align-items: center; justify-content: center; min-height: 22px; }

.timesGrid { align-items: start; }
.timeCard { transition: .2s transform, .2s box-shadow; }
.timeCard:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(11,60,117,.12); }
.timeCard__header { min-height: 44px; display: flex; align-items: center; }
.timeCard__player__nivel { background: #eef3f8; color: #647287; }

@media only screen and (max-width: 767px) {
    .treinoVivo .container { padding-left: 14px; padding-right: 14px; }
    .treinoHero { margin-left: -14px; margin-right: -14px; padding: 20px 14px 18px; box-shadow: 0 12px 30px rgba(11,60,117,.18); }
    .treinoHero::before { width: 210px; height: 210px; right: -95px; bottom: -118px; }
    .treinoVivo__header { padding-right: 78px; }
    .badge-live,
    .badge-encerrado { position: absolute; top: 0; right: 0; padding: 6px 10px; font-size: 10px; }
    .treinoStats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .treinoStats__item { min-height: 58px; padding: 9px 8px; text-align: center; }
    .treinoStats__num { font-size: 19px; }
    .treinoStats__label { font-size: 9px; letter-spacing: .25px; }

    .treinoSelector { gap: 8px; border-radius: 14px; }
    .treinoSelector select { min-height: 44px; font-size: 13px; }
    #secJogoAtual { margin-bottom: 26px; }
    #secJogoAtual .tvSection__title,
    #secFila .tvSection__title { margin-left: 0; }

    .jogoAtual { grid-template-columns: 1fr; gap: 10px; padding: 12px; }
    .jogoAtual__time { min-height: 116px; padding: 14px 12px; }
    .jogoAtual__badge { width: auto; max-width: 100%; font-size: 11px; padding: 7px 12px; }
    .jogoAtual__placar { font-size: 52px; }
    .jogoAtual__vs { flex-direction: row; justify-content: center; min-width: 0; order: 2; }
    .jogoAtual__time:first-child { order: 1; }
    .jogoAtual__time:last-child { order: 3; }
    .jogoAtual__vs__x { width: 34px; height: 34px; }
    .jogoAtual__vs__num { white-space: nowrap; }

    .filaVivo { padding-right: 44px; }
    .filaVivo__item { min-width: 0; min-height: 52px; justify-content: flex-start; }
    .filaVivo__nome { max-width: none; }
    .filaVivo .slick-dots { margin-top: 8px; }

    .tvAccordion { margin-bottom: 16px; }
    .tvAccordion__head { min-height: 52px; }
    .tvAccordion__body { padding: 0 10px 12px; }
    .historicTable tbody { gap: 9px; }
    .historicTable tr { border-radius: 10px; }
    .historicTable td { min-height: 42px; }
    .historicTable td[data-label="Times"] { align-items: flex-start; flex-direction: column; text-align: left; gap: 6px; }
    .historicTable td[data-label="Times"]::before { width: 100%; }
    .historicTable .vencedor-badge { max-width: 100%; }

    .timeCard { box-shadow: none; }
    .timeCard:hover { transform: none; box-shadow: none; }
    .timeCard__header { min-height: 42px; padding: 11px 14px; }
    .timeCard__players { padding: 7px 14px 8px; }
    .timeCard__player { min-height: 36px; }
}

@media only screen and (max-width: 420px) {
    .treinoStats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .treinoStats__item { text-align: left; }
    .treinoVivo__header { padding-right: 0; }
    .badge-live,
    .badge-encerrado { position: static; }
}
</style>
</head>
<body>

<?php include ROOT . '/includes/header/header.php'; ?>
<?php include ROOT . '/includes/nav/nav.php'; ?>

<div class="treinoVivo">
<div class="container">

    <div class="treinoHero">
    <div class="treinoVivo__header">
        <div>
            <h2>Treino <span>ao Vivo</span></h2>
            <?php if ($dataSelecionada && $dataLonga): ?>
            <p class="treinoVivo__date">&#128197; Sexta-feira, <?= $dataLonga ?></p>
            <?php endif; ?>
        </div>
        <?php if ($isAoVivo): ?>
            <span class="badge-live">Ao vivo</span>
        <?php elseif ($temDados && $estado): ?>
            <span class="badge-encerrado">Encerrado</span>
        <?php endif; ?>
    </div>
    <div class="treinoStats">
        <div class="treinoStats__item">
            <span class="treinoStats__num" id="statTimes"><?= $totalTimes ?></span>
            <span class="treinoStats__label">Times</span>
        </div>
        <div class="treinoStats__item">
            <span class="treinoStats__num" id="statJogadores"><?= $totalJogadores ?></span>
            <span class="treinoStats__label">Jogadores</span>
        </div>
        <div class="treinoStats__item">
            <span class="treinoStats__num" id="statJogos"><?= $totalPartidas ?></span>
            <span class="treinoStats__label">Jogos</span>
        </div>
        <div class="treinoStats__item">
            <span class="treinoStats__num" id="statResultados"><?= $totalEncerradas ?></span>
            <span class="treinoStats__label">Resultados</span>
        </div>
    </div>
    </div>

    <!-- SELETOR DE DATA -->
    <?php if (!empty($datasDisponiveis)): ?>
    <div class="treinoSelector">
        <label for="seletorData">&#128197; Selecionar treino:</label>
        <select id="seletorData" onchange="window.location.href='<?= BASE_URL ?>/treino?data=' + this.value">
            <?php foreach ($datasDisponiveis as $d): ?>
            <option value="<?= $d ?>" <?= $d === $dataSelecionada ? 'selected' : '' ?>>
                <?= formatDataSelect($d, $meses) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <!-- SEM DADOS DISPONÍVEIS -->
    <?php if (empty($datasDisponiveis)): ?>
    <div class="semDados">
        <div class="semDados__icon">&#127944;</div>
        <h3 class="semDados__title">Nenhum treino disponível ainda</h3>
        <p class="semDados__sub">Quando o sorteio dos times for realizado, os dados aparecerão aqui.</p>
    </div>

    <!-- DATA SELECIONADA SEM DADOS -->
    <?php elseif (!$temDados): ?>
    <div class="semDados">
        <div class="semDados__icon">&#128202;</div>
        <h3 class="semDados__title">Sem dados para esta data</h3>
        <p class="semDados__sub">O sorteio dos times para este treino ainda não foi realizado.</p>
    </div>

    <?php else: ?>

    <!-- JOGO ATUAL (apenas quando ao vivo) -->
    <?php if ($isAoVivo): ?>
    <div class="tvSection" id="secJogoAtual">
        <p class="tvSection__title">&#9917; Jogo em andamento</p>
        <div class="jogoAtual" id="jogoAtual">
        <?php
        $emQuadra = $estado['em_quadra'] ?? [];
        if (count($emQuadra) >= 2):
            $eq0Idx = is_array($emQuadra[0]) ? (int)($emQuadra[0]['idx'] ?? -1) : (int)$emQuadra[0];
            $eq1Idx = is_array($emQuadra[1]) ? (int)($emQuadra[1]['idx'] ?? -1) : (int)$emQuadra[1];
            $t0 = $times[$eq0Idx] ?? null;
            $t1 = $times[$eq1Idx] ?? null;
            $c0 = getCor($t0['color'] ?? '', $cores);
            $c1 = getCor($t1['color'] ?? '', $cores);
            $partAtual = (int)($estado['partida_atual'] ?? 0);
            // Placar da partida atual
            $placarC = '—'; $placarV = '—';
            foreach ($partidas as $p) {
                if ((int)$p['numero'] === $partAtual) {
                    if ($p['placar_casa']      !== null) $placarC = $p['placar_casa'];
                    if ($p['placar_visitante'] !== null) $placarV = $p['placar_visitante'];
                    break;
                }
            }
        ?>
        <div class="jogoAtual__time">
            <span id="badgeCasa" class="jogoAtual__badge" style="background:<?= $c0['bg'] ?>;color:<?= $c0['text'] ?>;"><?= htmlspecialchars($t0['name'] ?? 'Time') ?></span>
            <div class="jogoAtual__placar" id="placarCasa"><?= $placarC ?></div>
        </div>
        <div class="jogoAtual__vs">
            <span class="jogoAtual__vs__x">×</span>
            <span class="jogoAtual__vs__num" id="jogoNum">Jogo #<?= $partAtual ?></span>
        </div>
        <div class="jogoAtual__time">
            <span id="badgeVisitante" class="jogoAtual__badge" style="background:<?= $c1['bg'] ?>;color:<?= $c1['text'] ?>;"><?= htmlspecialchars($t1['name'] ?? 'Time') ?></span>
            <div class="jogoAtual__placar" id="placarVisitante"><?= $placarV ?></div>
        </div>
        <?php else: ?>
        <p class="jogoAtual__vazio">Aguardando início do jogo...</p>
        <?php endif; ?>
        </div>
    </div>

    <!-- FILA -->
    <?php $fila = $estado['fila'] ?? []; if (!empty($fila)): ?>
    <div class="tvSection" id="secFila">
        <p class="tvSection__title">&#9201; Próximos na fila</p>
        <div class="filaVivo" id="filaVivo">
            <?php foreach ($fila as $i => $item):
                $fIdx = is_array($item) ? (int)($item['idx'] ?? -1) : (int)$item;
                $tf = $times[$fIdx] ?? null;
                if (!$tf) continue;
                $cf = getCor($tf['color'], $cores);
            ?>
            <div class="filaVivo__item">
                <span class="filaVivo__pos"><?= $i + 1 ?>º</span>
                <span class="filaVivo__dot" style="background:<?= $cf['bg'] ?>;"></span>
                <span class="filaVivo__nome"><?= htmlspecialchars($tf['name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; // fim isAoVivo ?>

    <!-- TIMES -->
    <div class="tvSection tvAccordion" id="secTimes">
        <button class="tvAccordion__head" type="button" aria-expanded="true">
            <span class="tvSection__title">&#128101; Times do treino</span>
            <span class="tvAccordion__icon"></span>
        </button>
        <div class="tvAccordion__body">
        <div class="timesGrid">
            <?php foreach ($times as $time):
                $ct = getCor($time['color'] ?? '', $cores);
            ?>
            <div class="timeCard">
                <div class="timeCard__header" style="background:<?= $ct['bg'] ?>;">
                    <span class="timeCard__header__nome" style="color:<?= $ct['text'] ?>;"><?= htmlspecialchars($time['name']) ?></span>
                </div>
                <div class="timeCard__players">
                    <?php foreach ($time['jogadores'] ?? $time['players'] ?? [] as $player): ?>
                    <div class="timeCard__player">
                        <span><?= htmlspecialchars($player['nome']) ?></span>
                        <span class="timeCard__player__nivel">Nível <?= (int)($player['nivel_jogo'] ?? $player['nivel'] ?? 0) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>

    <!-- RESULTADOS -->
    <?php
    $jogosEncerrados = array_filter($partidas, fn($p) => $p['idx_vencedor'] !== null);
    if (!empty($jogosEncerrados)):
    ?>
    <div class="tvSection tvAccordion" id="secHistorico">
        <button class="tvAccordion__head" type="button" aria-expanded="true">
            <span class="tvSection__title">&#128202; Resultados</span>
            <span class="tvAccordion__icon"></span>
        </button>
        <div class="tvAccordion__body">
        <table class="historicTable">
            <thead>
                <tr>
                    <th>Jogo</th>
                    <th>Times</th>
                    <th style="text-align:center;">Placar</th>
                    <th>Vencedor</th>
                </tr>
            </thead>
            <tbody id="historicBody">
                <?php foreach ($jogosEncerrados as $p):
                    $tc = $times[$p['idx_casa']]      ?? null;
                    $tv = $times[$p['idx_visitante']] ?? null;
                    $tw = $times[$p['idx_vencedor']]  ?? null;
                    if (!$tc || !$tv) continue;
                    $cCasa = getCor($tc['color'], $cores);
                    $cVis  = getCor($tv['color'], $cores);
                    $cWin  = $tw ? getCor($tw['color'], $cores) : getCor('cinza', $cores);
                ?>
                <tr>
                    <td class="jogo-num" data-label="Jogo">#<?= $p['numero'] ?></td>
                    <td data-label="Times">
                        <span class="timeDot" style="background:<?= $cCasa['bg'] ?>;"></span><?= htmlspecialchars($tc['name']) ?>
                        <span style="color:#d4d4d4;margin:0 6px;">×</span>
                        <span class="timeDot" style="background:<?= $cVis['bg'] ?>;"></span><?= htmlspecialchars($tv['name']) ?>
                    </td>
                    <td class="placar" data-label="Placar"><?= $p['placar_casa'] ?> × <?= $p['placar_visitante'] ?></td>
                    <td data-label="Vencedor">
                        <?php if ($tw): ?>
                        <span class="vencedor-badge" style="background:<?= $cWin['bg'] ?>;color:<?= $cWin['text'] ?>;"><?= htmlspecialchars($tw['name']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // fim temDados ?>

</div>
</div>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>

<script>
(function () {
    function isMobile() {
        return window.matchMedia('(max-width: 767px)').matches;
    }

    function ordenarSecoes() {
        var $historico = $('#secHistorico');
        var $times = $('#secTimes');
        if ($historico.length && $times.length && $historico.index() > $times.index()) {
            $historico.insertBefore($times);
        }
    }

    function configurarFilaSlider() {
        var $fila = $('#filaVivo');
        if (!$fila.length || typeof $.fn.slick !== 'function') return;

        if (isMobile()) {
            if (!$fila.hasClass('slick-initialized')) {
                $fila.slick({
                    arrows: false,
                    dots: true,
                    infinite: false,
                    slidesToShow: 1.45,
                    slidesToScroll: 1,
                    swipeToSlide: true,
                    variableWidth: false,
                    adaptiveHeight: false
                });
            }
        } else if ($fila.hasClass('slick-initialized')) {
            $fila.slick('unslick');
        }
    }
    window.treinoConfigurarFilaSlider = configurarFilaSlider;

    function configurarAccordions() {
        var $accordions = $('.tvAccordion');

        if (!isMobile()) {
            $accordions.removeClass('--open');
            $accordions.find('.tvAccordion__head').attr('aria-expanded', 'true');
            $accordions.find('.tvAccordion__body').removeAttr('style');
            return;
        }

        $accordions.each(function (index) {
            var $item = $(this);
            if (!$item.data('accordion-ready')) {
                $item.toggleClass('--open', index === 0);
                $item.find('.tvAccordion__head').attr('aria-expanded', index === 0 ? 'true' : 'false');
                $item.data('accordion-ready', true);
            }
        });
    }

    $(document).ready(function () {
        ordenarSecoes();
        configurarFilaSlider();
        configurarAccordions();

        $('.tvAccordion__head').on('click', function () {
            if (!isMobile()) return;
            var $accordion = $(this).closest('.tvAccordion');
            var aberto = $accordion.toggleClass('--open').hasClass('--open');
            $(this).attr('aria-expanded', aberto ? 'true' : 'false');
            if ($('#filaVivo').hasClass('slick-initialized')) {
                $('#filaVivo').slick('setPosition');
            }
        });

        $(window).on('resize orientationchange', function () {
            configurarFilaSlider();
            configurarAccordions();
        });
    });
})();
</script>

<?php if ($dataSelecionada): ?>
<script>
var BASE_URL         = "<?= BASE_URL ?>";
var DATA_SELECIONADA = "<?= $dataSelecionada ?>";

function hexBg(color) {
    return (color && color[0] === '#') ? color : '#6c757d';
}

function esc(str) {
    return $('<span>').text(str || '').html();
}

function atualizar() {
    $.getJSON(BASE_URL + '/services/get_treino_ativo.php?data=' + DATA_SELECIONADA, function (d) {
        if (!d.ativo) return;

        // Stats
        var encerrados = d.partidas.filter(function(p){ return p.encerrada; });
        $('#statJogos').text(d.partidas.length);
        $('#statResultados').text(encerrados.length);

        // Se encerrou durante a sessão
        if (d.encerrado) {
            $('.badge-live').hide();
            if ($('.badge-encerrado').length === 0) {
                $('.treinoVivo__header').append('<span class="badge-encerrado">Encerrado</span>');
            }
            $('#secJogoAtual, #secFila').hide();
            clearInterval(_timer);
        }

        // Jogo em andamento — atualiza times na quadra
        var eq = d.em_quadra;
        if (eq.length >= 2 && !d.encerrado) {
            $('#badgeCasa')
                .css({ background: hexBg(eq[0].color), color: '#fff' })
                .text(eq[0].name);
            $('#badgeVisitante')
                .css({ background: hexBg(eq[1].color), color: '#fff' })
                .text(eq[1].name);
            $('#jogoNum').text('Jogo #' + d.partida_atual);

            var placarC = '—', placarV = '—';
            $.each(d.partidas, function(i, p) {
                if (p.numero === d.partida_atual) {
                    if (p.placar_casa      !== null) placarC = p.placar_casa;
                    if (p.placar_visitante !== null) placarV = p.placar_visitante;
                }
            });
            $('#placarCasa').text(placarC);
            $('#placarVisitante').text(placarV);
            $('#secJogoAtual').show();
        }

        // Fila — reconstrói toda vez
        if (d.fila.length > 0 && !d.encerrado) {
            var filaHtml = '';
            $.each(d.fila, function(i, item) {
                filaHtml +=
                    '<div class="filaVivo__item">' +
                        '<span class="filaVivo__pos">' + (i + 1) + 'º</span>' +
                        '<span class="filaVivo__dot" style="background:' + hexBg(item.color) + ';"></span>' +
                        '<span class="filaVivo__nome">' + esc(item.name) + '</span>' +
                    '</div>';
            });
            if ($('#filaVivo').hasClass('slick-initialized')) {
                $('#filaVivo').slick('unslick');
            }
            $('#filaVivo').html(filaHtml);
            $('#secFila').show();
            if (typeof window.treinoConfigurarFilaSlider === 'function') {
                window.treinoConfigurarFilaSlider();
            }
        } else {
            if ($('#filaVivo').hasClass('slick-initialized')) {
                $('#filaVivo').slick('unslick');
            }
            $('#secFila').hide();
        }

        // Resultados
        if (encerrados.length > 0) {
            var html = '';
            $.each(encerrados, function(i, p) {
                if (!p.time_casa || !p.time_visitante) return;
                var cCasa = hexBg(p.time_casa.color);
                var cVis  = hexBg(p.time_visitante.color);
                var winBg = '#6c757d', winNome = '';
                if (p.vencedor_nome) {
                    winBg   = hexBg(p.vencedor_idx === p.time_casa.idx ? p.time_casa.color : p.time_visitante.color);
                    winNome = p.vencedor_nome;
                }
                html +=
                    '<tr>' +
                    '<td class="jogo-num" data-label="Jogo">#' + p.numero + '</td>' +
                    '<td data-label="Times">' +
                        '<span class="timeDot" style="background:' + cCasa + ';"></span>' + esc(p.time_casa.name) +
                        ' <span style="color:#d4d4d4;margin:0 6px;">×</span> ' +
                        '<span class="timeDot" style="background:' + cVis + ';"></span>' + esc(p.time_visitante.name) +
                    '</td>' +
                    '<td class="placar" data-label="Placar">' + p.placar_casa + ' × ' + p.placar_visitante + '</td>' +
                    '<td data-label="Vencedor">' +
                        (winNome ? '<span class="vencedor-badge" style="background:' + winBg + ';color:#fff;">' + esc(winNome) + '</span>' : '') +
                    '</td>' +
                    '</tr>';
            });
            $('#historicBody').html(html);
            $('#secHistorico').show();
        }
    });
}

// Só faz poll se o treino está ao vivo
if (<?= $isAoVivo ? 'true' : 'false' ?>) {
    var _timer = setInterval(atualizar, 3000);

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearInterval(_timer);
        } else {
            atualizar();
            _timer = setInterval(atualizar, 3000);
        }
    });
}
</script>
<?php endif; ?>

</body>
</html>
