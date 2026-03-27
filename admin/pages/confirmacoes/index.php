<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$today = new DateTime();
$today->setTime(0, 0, 0);
$year  = (int) $today->format('Y');

// ── TODAS AS SEXTAS DO ANO ─────────────────────────────────────
$fridays = [];
$date = new DateTime("$year-01-01");
while ($date->format('N') != 5) {
    $date->modify('+1 day');
}
while ((int) $date->format('Y') === $year) {
    $fridays[] = clone $date;
    $date->modify('+7 days');
}
// ordem: mais antigo no topo, mais futuro no fim

// ── SEXTA DA SEMANA ATUAL ──────────────────────────────────────
$proximaSexta = clone $today;
while ($proximaSexta->format('N') != 5) {
    $proximaSexta->modify('+1 day');
}
$proximaSexta->setTime(0, 0, 0);
$currentFridayKey = $proximaSexta->format('Y-m-d');

// ── CONTAGEM DE CONFIRMAÇÕES POR DATA ─────────────────────────
$stmt = $pdo->query("SELECT data_treino, COUNT(*) as total FROM confirmacoes_treino GROUP BY data_treino");
$counts = [];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['data_treino']] = (int) $row['total'];
}

// ── TREINOS ENCERRADOS ─────────────────────────────────────────
$stmtEnc  = $pdo->query("SELECT data_treino FROM treinos_encerrados");
$encerrados = array_flip($stmtEnc->fetchAll(PDO::FETCH_COLUMN));

$mesesAbrev = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
               '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
$mesesFull  = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
               '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];

$nivel    = $_SESSION['usuario']['nivel_acesso'];
$canSend  = ($nivel === 'admin' || $nivel === 'editor');
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin - Confirmações</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="confLayout">

            <div class="confHeader">
                <h2>Confirmações de <span>Presença</span></h2>
                <p class="confHeader__sub">Sextas-feiras de <?= $year ?> — clique num treino para ver quem confirmou.</p>
            </div>

            <div class="confFiltro">
                <label class="confFiltro__toggle">
                    <input type="checkbox" id="filtroPassados" />
                    <span class="confFiltro__slider"></span>
                </label>
                <span class="confFiltro__label">Exibir treinos já realizados</span>
            </div>

            <div class="confGrid" id="confGrid">
                <?php foreach ($fridays as $friday):
                    $key        = $friday->format('Y-m-d');
                    $count      = $counts[$key] ?? 0;
                    $isDestaque = ($key === $currentFridayKey);
                    $isPast     = !$isDestaque && ($friday < $proximaSexta);
                    $dia        = $friday->format('d');
                    $mes        = $mesesAbrev[$friday->format('m')];
                    $label      = $dia . ' de ' . $mesesFull[$friday->format('m')] . ' de ' . $friday->format('Y');
                ?>
                <?php $isEncerrado = isset($encerrados[$key]); ?>
                <div class="confCard <?= $isDestaque ? '--destaque' : '' ?> <?= $isPast ? '--passado' : '' ?> <?= $isEncerrado ? '--encerrado' : '' ?>"
                     data-date="<?= $key ?>"
                     data-label="<?= htmlspecialchars($label) ?>"
                     data-encerrado="<?= $isEncerrado ? '1' : '0' ?>">
                    <div class="confCard__date">
                        <span class="confCard__date__dia"><?= $dia ?></span>
                        <span class="confCard__date__mes"><?= $mes . ' ' . $friday->format('Y') ?></span>
                    </div>
                    <div class="confCard__weekday">Sexta-feira</div>
                    <div class="confCard__count">
                        <span class="confCard__count__num"><?= $count ?></span>
                        <span class="confCard__count__label">confirmação<?= $count !== 1 ? 'ões' : '' ?></span>
                    </div>
                    <?php if ($isDestaque): ?>
                        <span class="confCard__badge">Semana atual</span>
                    <?php endif; ?>
                    <?php if ($isEncerrado): ?>
                        <span class="confCard__badge --encerrado">Encerrado</span>
                    <?php elseif ($count >= 30): ?>
                        <span class="confCard__badge --lotado">Lotado</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

        </section>

    </main>
</div>

<!-- MODAL CONFIRMAÇÕES -->
<div class="confModal" id="confModal">
    <div class="confModal__box">
        <div class="confModal__header">
            <h3 class="confModal__title">Confirmações — <span id="confModalLabel"></span></h3>
            <button class="confModal__close" id="confModalClose">&times;</button>
        </div>
        <div class="confModal__body" id="confModalBody">
            <p class="confModal__vazio">Carregando...</p>
        </div>
        <div class="confModal__footer">
            <button class="btn btn--gray" id="btnImprimir" title="Abrir lista para impressão">
                Imprimir
            </button>
            <button class="btn btn--primary"
                    id="btnEnviar"
                    <?= !$canSend ? 'disabled' : '' ?>
                    style="<?= !$canSend ? 'cursor: not-allowed; opacity: 0.5;' : '' ?>"
                    title="<?= !$canSend ? 'Apenas admin e editor podem enviar' : '' ?>">
                Enviar
            </button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var canSend = <?= $canSend ? 'true' : 'false' ?>;
</script>

<script>
$(document).ready(function () {

    var currentDate      = null;
    var currentEncerrado = false;

    // ── Filtro de treinos passados ────────────────────────────
    $('#filtroPassados').on('change', function () {
        if ($(this).is(':checked')) {
            $('#confGrid').addClass('--mostrar-passados');
        } else {
            $('#confGrid').removeClass('--mostrar-passados');
        }
    });

    // ── Abrir modal ao clicar no card ─────────────────────────
    $(document).on('click', '.confCard', function () {
        currentDate      = $(this).data('date');
        currentEncerrado = $(this).data('encerrado') == 1;
        var label        = $(this).data('label');

        $('#confModalLabel').text(label);
        $('#confModalBody').html('<p class="confModal__vazio">Carregando...</p>');
        $('#confModal').addClass('--open');

        // Atualiza estado do botão Enviar
        var btn = $('#btnEnviar');
        if (!canSend) {
            btn.prop('disabled', true).css({cursor: 'not-allowed', opacity: '0.5'})
               .attr('title', 'Apenas admin e editor podem enviar');
        } else if (currentEncerrado) {
            btn.prop('disabled', true).css({cursor: 'not-allowed', opacity: '0.5'})
               .attr('title', 'Este treino já foi encerrado e os e-mails foram enviados');
        } else {
            btn.prop('disabled', false).css({cursor: 'pointer', opacity: '1'})
               .attr('title', '');
        }

        $.getJSON(ADMIN_BASE_URL + '/services/get_confirmacoes.php?data=' + currentDate, function (data) {
            if (data.error) {
                $('#confModalBody').html('<p class="confModal__vazio">Erro ao carregar dados.</p>');
                return;
            }
            if (data.length === 0) {
                $('#confModalBody').html('<p class="confModal__vazio">Nenhuma confirmação para este treino.</p>');
                return;
            }

            var html = '<table class="confTable"><thead><tr>' +
                       '<th>#</th><th>Nome Completo</th><th>CPF</th><th>Telefone</th>' +
                       '</tr></thead><tbody>';

            $.each(data, function (i, row) {
                html += '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + $('<div>').text(row.nome_completo).html() + '</td>' +
                        '<td>' + $('<div>').text(row.cpf_masked).html() + '</td>' +
                        '<td>' + $('<div>').text(row.telefone || '—').html() + '</td>' +
                        '</tr>';
            });

            html += '</tbody></table>';
            $('#confModalBody').html(html);
        }).fail(function () {
            $('#confModalBody').html('<p class="confModal__vazio">Erro ao carregar dados.</p>');
        });
    });

    // ── Botão Imprimir ────────────────────────────────────────
    $('#btnImprimir').on('click', function () {
        if (!currentDate) return;
        window.open(ADMIN_BASE_URL + '/imprimir?data=' + currentDate, '_blank');
    });

    // ── Botão Enviar ──────────────────────────────────────────
    $('#btnEnviar').on('click', function () {
        if (!canSend || currentEncerrado || !currentDate) return;

        var confirmou = confirm(
            'Atenção!\n\nAo enviar a lista, o treino será ENCERRADO e ninguém mais poderá se inscrever.\n\nDeseja realmente enviar e encerrar as confirmações?'
        );
        if (!confirmou) return;

        var btn = $(this);
        btn.prop('disabled', true).text('Enviando...');

        $.post(
            ADMIN_BASE_URL + '/services/enviar_confirmacoes.php',
            { data_treino: currentDate },
            function (res) {
                if (res.success) {
                    alert('✔ ' + res.message);
                    closeModal();
                    location.reload();
                } else {
                    alert('⚠ ' + res.message);
                    btn.prop('disabled', false).text('Enviar');
                }
            },
            'json'
        ).fail(function () {
            alert('Erro de comunicação com o servidor.');
            btn.prop('disabled', false).text('Enviar');
        });
    });

    // ── Fechar modal ──────────────────────────────────────────
    $('#confModalClose').on('click', closeModal);
    $('#confModal').on('click', function (e) {
        if ($(e.target).is('#confModal')) closeModal();
    });

    function closeModal() {
        $('#confModal').removeClass('--open');
        currentDate = null;
    }

});
</script>

</body>
</html>
