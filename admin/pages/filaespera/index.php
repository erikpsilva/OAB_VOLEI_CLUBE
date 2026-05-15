<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$today = new DateTime();
$today->setTime(0, 0, 0);
$year  = (int) $today->format('Y');

// Todas as sextas do ano
$fridays = [];
$date = new DateTime("$year-01-01");
while ($date->format('N') != 5) {
    $date->modify('+1 day');
}
while ((int) $date->format('Y') === $year) {
    $fridays[] = clone $date;
    $date->modify('+7 days');
}

// Sexta da semana atual
$proximaSexta = clone $today;
while ($proximaSexta->format('N') != 5) {
    $proximaSexta->modify('+1 day');
}
$proximaSexta->setTime(0, 0, 0);
$currentFridayKey = $proximaSexta->format('Y-m-d');

// Contagem de fila por data
$stmt = $pdo->query("SELECT data_treino, COUNT(*) as total FROM fila_espera GROUP BY data_treino");
$filaCount = [];
foreach ($stmt->fetchAll() as $row) {
    $filaCount[$row['data_treino']] = (int) $row['total'];
}

$mesesAbrev = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
               '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
$mesesFull  = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
               '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin - Fila de Espera</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<style>
.filaLayout { padding: 0; }
.filaHeader { margin-bottom: 28px; }
.filaHeader h2 { font-size: 22px; font-weight: 700; color: #0b3c75; margin-bottom: 6px; }
.filaHeader h2 span { color: #6f42c1; }
.filaHeader__sub { font-size: 13px; color: #7d7d7d; }
.filaFiltro { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.filaFiltro__toggle { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; }
.filaFiltro__toggle input { opacity: 0; width: 0; height: 0; }
.filaFiltro__toggle input:checked + .filaFiltro__slider { background-color: #0b3c75; }
.filaFiltro__toggle input:checked + .filaFiltro__slider::before { transform: translateX(20px); }
.filaFiltro__slider { position: absolute; inset: 0; background-color: #d4d4d4; border-radius: 24px; transition: .3s; }
.filaFiltro__slider::before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background-color: #fff; border-radius: 50%; transition: .3s; }
.filaFiltro__label { font-size: 13px; font-weight: 600; color: #7d7d7d; cursor: pointer; }
.filaGrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
.filaCard { background: #fff; border-radius: 10px; padding: 18px 14px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,.07); border-top: 4px solid #6f42c1; cursor: pointer; transition: .2s transform; }
.filaCard:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(111,66,193,.2); }
.filaCard.--passado { display: none; }
.filaGrid.--mostrar-passados .filaCard.--passado { display: block; }
.filaCard.--destaque { border-top-color: #ffc300; box-shadow: 0 4px 16px rgba(255,195,0,.25); }
.filaCard.--vazia { opacity: .5; cursor: default; }
.filaCard.--vazia:hover { transform: none; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
.filaCard__date { display: flex; align-items: baseline; justify-content: center; gap: 4px; margin-bottom: 6px; }
.filaCard__date__dia { font-size: 32px; font-weight: 700; color: #0b3c75; line-height: 1; }
.filaCard__date__mes { font-size: 13px; font-weight: 600; color: #7d7d7d; text-transform: uppercase; }
.filaCard__weekday { font-size: 11px; color: #7d7d7d; margin-bottom: 10px; }
.filaCard__count { display: flex; flex-direction: column; align-items: center; }
.filaCard__count__num { font-size: 28px; font-weight: 700; color: #6f42c1; line-height: 1; }
.filaCard__count__label { font-size: 11px; color: #7d7d7d; margin-top: 2px; }
.filaCard__badge { display: inline-block; margin-top: 8px; font-size: 10px; font-weight: 700; padding: 2px 10px; border-radius: 20px; background: #ffc300; color: #0b3c75; }

/* Modal */
.filaModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 100; align-items: center; justify-content: center; }
.filaModal.--open { display: flex; }
.filaModal__box { background: #fff; border-radius: 10px; padding: 0; width: 100%; max-width: 600px; box-shadow: 0 8px 32px rgba(0,0,0,.18); max-height: 90vh; display: flex; flex-direction: column; }
.filaModal__header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #e0e0e0; flex-shrink: 0; }
.filaModal__title { font-size: 16px; font-weight: 700; color: #0b3c75; margin: 0; }
.filaModal__close { background: none; border: none; font-size: 22px; cursor: pointer; color: #7d7d7d; line-height: 1; }
.filaModal__body { padding: 20px 24px; overflow-y: auto; flex: 1; }
.filaModal__vazio { font-size: 13px; color: #7d7d7d; text-align: center; padding: 24px 0; }
.filaTable { width: 100%; border-collapse: collapse; font-size: 13px; }
.filaTable th { background: #f4f6f9; color: #555; font-weight: 600; padding: 10px 12px; text-align: left; border-bottom: 2px solid #e0e0e0; }
.filaTable td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; color: #555; }
.filaTable tbody tr:hover { background: #fafafa; }
.filaTable .posicao { font-weight: 700; color: #6f42c1; font-size: 14px; }
.filaTable .inscrito_em { font-size: 11px; color: #7d7d7d; }
</style>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="filaLayout">

            <div class="filaHeader">
                <h2>Fila de <span>Espera</span></h2>
                <p class="filaHeader__sub">Jogadores aguardando vaga por ordem de chegada — clique num treino para ver a fila.</p>
            </div>

            <div class="filaFiltro">
                <label class="filaFiltro__toggle">
                    <input type="checkbox" id="filtroPassados" />
                    <span class="filaFiltro__slider"></span>
                </label>
                <span class="filaFiltro__label">Exibir treinos já realizados</span>
            </div>

            <div class="filaGrid" id="filaGrid">
                <?php foreach ($fridays as $friday):
                    $key        = $friday->format('Y-m-d');
                    $count      = $filaCount[$key] ?? 0;
                    $isDestaque = ($key === $currentFridayKey);
                    $isPast     = !$isDestaque && ($friday < $proximaSexta);
                    $dia        = $friday->format('d');
                    $mes        = $mesesAbrev[$friday->format('m')];
                    $label      = $dia . ' de ' . $mesesFull[$friday->format('m')] . ' de ' . $friday->format('Y');
                ?>
                <div class="filaCard <?= $isDestaque ? '--destaque' : '' ?> <?= $isPast ? '--passado' : '' ?> <?= $count === 0 ? '--vazia' : '' ?>"
                     data-date="<?= $key ?>"
                     data-label="<?= htmlspecialchars($label) ?>">
                    <div class="filaCard__date">
                        <span class="filaCard__date__dia"><?= $dia ?></span>
                        <span class="filaCard__date__mes"><?= $mes ?></span>
                    </div>
                    <div class="filaCard__weekday">Sexta-feira</div>
                    <div class="filaCard__count">
                        <span class="filaCard__count__num"><?= $count ?></span>
                        <span class="filaCard__count__label">na fila</span>
                    </div>
                    <?php if ($isDestaque): ?>
                        <span class="filaCard__badge">Semana atual</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

        </section>

    </main>
</div>

<!-- MODAL FILA -->
<div class="filaModal" id="filaModal">
    <div class="filaModal__box">
        <div class="filaModal__header">
            <h3 class="filaModal__title">Fila de Espera — <span id="filaModalLabel"></span></h3>
            <button class="filaModal__close" id="filaModalClose">&times;</button>
        </div>
        <div class="filaModal__body" id="filaModalBody">
            <p class="filaModal__vazio">Carregando...</p>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
</script>

<script>
$(document).ready(function () {

    $('#filtroPassados').on('change', function () {
        if ($(this).is(':checked')) {
            $('#filaGrid').addClass('--mostrar-passados');
        } else {
            $('#filaGrid').removeClass('--mostrar-passados');
        }
    });

    $(document).on('click', '.filaCard:not(.--vazia)', function () {
        var date  = $(this).data('date');
        var label = $(this).data('label');

        $('#filaModalLabel').text(label);
        $('#filaModalBody').html('<p class="filaModal__vazio">Carregando...</p>');
        $('#filaModal').addClass('--open');

        $.getJSON(ADMIN_BASE_URL + '/services/get_fila_espera.php?data=' + date, function (data) {
            if (data.error) {
                $('#filaModalBody').html('<p class="filaModal__vazio">Erro ao carregar dados.</p>');
                return;
            }
            if (data.length === 0) {
                $('#filaModalBody').html('<p class="filaModal__vazio">Nenhum jogador na fila para este treino.</p>');
                return;
            }

            var html = '<table class="filaTable"><thead><tr>' +
                       '<th>#</th><th>Nome</th><th>CPF</th><th>Telefone</th><th>Inscrito em</th>' +
                       '</tr></thead><tbody>';

            $.each(data, function (i, row) {
                var dt = new Date(row.inscrito_em);
                var dtFmt = dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
                html += '<tr>' +
                        '<td class="posicao">' + row.posicao + '</td>' +
                        '<td>' + $('<div>').text(row.nome_completo).html() + '</td>' +
                        '<td>' + $('<div>').text(row.cpf_masked).html() + '</td>' +
                        '<td>' + $('<div>').text(row.telefone || '—').html() + '</td>' +
                        '<td class="inscrito_em">' + dtFmt + '</td>' +
                        '</tr>';
            });

            html += '</tbody></table>';
            $('#filaModalBody').html(html);
        }).fail(function () {
            $('#filaModalBody').html('<p class="filaModal__vazio">Erro ao carregar dados.</p>');
        });
    });

    $('#filaModalClose').on('click', function () {
        $('#filaModal').removeClass('--open');
    });

    $('#filaModal').on('click', function (e) {
        if ($(e.target).is('#filaModal')) {
            $('#filaModal').removeClass('--open');
        }
    });

});
</script>

</body>
</html>
