<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/envio_helper.php';
$pdo      = getDbConnection();
$config   = getAppConfig($pdo);
$maxVagas           = (int) $config['max_vagas'];
$modoAbertura       = $config['modo_abertura_agenda'] ?? 'automatico';
$agendaLiberadaData = $config['agenda_liberada_data'] ?? '';

// Verifica se o jogador logado é favorito
$isFavoritoLogado = false;
if (!empty($_SESSION['jogador'])) {
    $stmtFav = $pdo->prepare("SELECT favorito FROM jogadores WHERE id = ? LIMIT 1");
    $stmtFav->execute([$_SESSION['jogador']['id']]);
    $favRow = $stmtFav->fetch();
    $isFavoritoLogado = $favRow && (int)$favRow['favorito'] === 1;
}

// ── PRÓXIMO TREINO ────────────────────────────────────────────
$hoje = new DateTime();
$hoje->setTime(0, 0, 0);

$proximaSexta = clone $hoje;
while ($proximaSexta->format('N') != 5) {
    $proximaSexta->modify('+1 day');
}
$proximaKey = $proximaSexta->format('Y-m-d');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM confirmacoes_treino WHERE data_treino = ?");
$stmt->execute([$proximaKey]);
$totalConfirmados = (int) $stmt->fetchColumn();

$stmtNomes = $pdo->prepare("
    SELECT j.nome_completo
    FROM confirmacoes_treino ct
    JOIN jogadores j ON j.id = ct.jogador_id
    WHERE ct.data_treino = ?
    ORDER BY j.nome_completo
");
$stmtNomes->execute([$proximaKey]);
$confirmadosNomes = $stmtNomes->fetchAll(PDO::FETCH_COLUMN);

$stmtEnc = $pdo->prepare("SELECT 1 FROM treinos_encerrados WHERE data_treino = ? LIMIT 1");
$stmtEnc->execute([$proximaKey]);
$isEncerrado = (bool) $stmtEnc->fetch();

$isLotado       = !$isEncerrado && $totalConfirmados >= $maxVagas;
$vagasRestantes = max(0, $maxVagas - $totalConfirmados);
$progressoPct   = min(100, round($totalConfirmados / $maxVagas * 100));

$diaDaSemana  = (int) $hoje->format('N'); // 1=seg … 7=dom
$isSexta      = ($hoje == $proximaSexta);

// Modo manual: não-favoritos só veem como aberto se a agenda foi liberada para esta sexta
$agendaBloqueadaParaEste = ($modoAbertura === 'manual' && !$isFavoritoLogado && $agendaLiberadaData !== $proximaKey);

if ($isEncerrado)                        { $statusTreino = 'encerrado';  $statusLabel = 'Encerrado'; }
elseif ($isLotado)                       { $statusTreino = 'lotado';     $statusLabel = 'Lotado';    }
elseif ($isSexta)                        { $statusTreino = 'hoje';       $statusLabel = 'Hoje!';     }
elseif ($agendaBloqueadaParaEste && $diaDaSemana <= 4) { $statusTreino = 'aguardando'; $statusLabel = 'Aguardando abertura'; }
elseif ($diaDaSemana <= 4)               { $statusTreino = 'aberto';     $statusLabel = 'Confirmações abertas'; }
else                                     { $statusTreino = 'em_breve';   $statusLabel = 'Em breve';  }

$mesesFull = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
              '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$dataFormatada = $proximaSexta->format('d') . ' de ' . $mesesFull[$proximaSexta->format('m')] . ' de ' . $proximaSexta->format('Y');

// ── Disparo configurado ────────────────────────────────────────
$_diasNomes = ['1'=>'segunda-feira','2'=>'terça-feira','3'=>'quarta-feira',
               '4'=>'quinta-feira','5'=>'sexta-feira','6'=>'sábado','7'=>'domingo'];
$_diasNomesCap = ['1'=>'Segunda-feira','2'=>'Terça-feira','3'=>'Quarta-feira',
                  '4'=>'Quinta-feira','5'=>'Sexta-feira','6'=>'Sábado','7'=>'Domingo'];
$disparoDiaNum  = $config['disparo_dia_semana'] ?? '4';
$disparoDiaNome = $_diasNomes[$disparoDiaNum]       ?? 'quinta-feira';
$disparoDiaCap  = $_diasNomesCap[$disparoDiaNum]    ?? 'Quinta-feira';
[$_h, $_m]     = explode(':', $config['disparo_hora'] ?? '19:00');
$disparoHoraFmt = ($_m === '00') ? "{$_h}h" : "{$_h}h{$_m}";

$jogadorLogado   = !empty($_SESSION['jogador']);
$jogadorConfirmou = false;
$primeiroNome    = '';

if ($jogadorLogado) {
    $primeiroNome = explode(' ', $_SESSION['jogador']['nome_completo'])[0];
    $stmtC = $pdo->prepare("SELECT 1 FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino = ? LIMIT 1");
    $stmtC->execute([$_SESSION['jogador']['id'], $proximaKey]);
    $jogadorConfirmou = (bool) $stmtC->fetch();
}

// Verifica se o treino está em curso (email enviado ou horário de disparo já passou)
$_disparoHoraRaw = $config['disparo_hora'] ?? '13:00';
$_agoraHome      = new DateTime();
$isEmCursoHome   = $isEncerrado || ($isSexta && $_agoraHome->format('H:i') >= $_disparoHoraRaw);

// Pode confirmar se logado, não confirmou, há vagas e o treino ainda está aberto
$podeConfirmarHome = $jogadorLogado && !$jogadorConfirmou && !$isEmCursoHome
                     && !$isLotado && in_array($statusTreino, ['aberto', 'hoje']);

// Pode cancelar se confirmou e o treino ainda não está em curso
$podeCancelarHome = $jogadorConfirmou && !$isEmCursoHome;
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Início</title>
<?php include ROOT . '/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/includes/header/header.php'; ?>
<?php include ROOT . '/includes/nav/nav.php'; ?>

<!-- ══ HERO ══════════════════════════════════════════════════════ -->
<section class="homeHero">
    <div class="container">
        <div class="homeHero__inner">
            <?php if ($jogadorLogado): ?>
                <span class="homeHero__tag">Bem-vindo de volta</span>
                <h1>Olá, <span><?= htmlspecialchars($primeiroNome) ?></span>!</h1>
                <p class="homeHero__sub">Pronto para mais uma sexta-feira de vôlei?</p>
                <?php if ($jogadorConfirmou): ?>
                    <div class="homeHero__confirmado">&#10003; Você já está confirmado no próximo treino</div>
                    <?php if ($podeCancelarHome): ?>
                        <button class="homeHero__cancelar" id="btnCancelarHero" data-date="<?= $proximaKey ?>">Cancelar confirmação</button>
                    <?php endif; ?>
                <?php elseif ($podeConfirmarHome): ?>
                    <button class="homeHero__cta" id="btnAbrirConfirmarHero">Confirmar minha presença &rarr;</button>
                <?php endif; ?>
            <?php else: ?>
                <span class="homeHero__tag">OAB Santana Vôlei Clube</span>
                <h1>Vôlei, saúde e <span>confraternização</span></h1>
                <p class="homeHero__sub">Um espaço para advogados e servidores da OAB Santana praticarem esporte toda sexta-feira no Clube Esperia, zona norte de São Paulo.</p>
                <a href="<?= BASE_URL ?>/login" class="homeHero__cta">Entrar na plataforma &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ══ PRÓXIMO TREINO ════════════════════════════════════════════ -->
<section class="homeTreino">
    <div class="container">
        <h2 class="homeSection__title">Próximo <span>Treino</span></h2>
        <p class="homeSection__sub">Acompanhe as vagas em tempo real e não perca o prazo de confirmação.</p>

        <div class="homeTreino__card">
            <div class="homeTreino__card__left">
                <div class="homeTreino__data">
                    <span class="homeTreino__data__dia"><?= $proximaSexta->format('d') ?></span>
                    <div class="homeTreino__data__info">
                        <span class="homeTreino__data__mes"><?= $mesesFull[$proximaSexta->format('m')] . ' ' . $proximaSexta->format('Y') ?></span>
                        <span class="homeTreino__data__semana">Sexta-feira</span>
                    </div>
                </div>
                <span class="homeTreino__status --<?= $statusTreino ?>"><?= $statusLabel ?></span>
            </div>

            <div class="homeTreino__card__right">
                <div class="homeTreino__vagas">
                    <div class="homeTreino__vagas__nums">
                        <span class="homeTreino__vagas__total"><?= $totalConfirmados ?><small>/<?= $maxVagas ?></small></span>
                        <span class="homeTreino__vagas__label">confirmados</span>
                    </div>
                    <div class="homeTreino__vagas__bar">
                        <div class="homeTreino__vagas__bar__fill --<?= $statusTreino ?>" style="width: <?= $progressoPct ?>%"></div>
                    </div>
                    <?php if (!$isEncerrado && !$isLotado): ?>
                        <p class="homeTreino__vagas__restantes"><?= $vagasRestantes ?> vaga<?= $vagasRestantes !== 1 ? 's' : '' ?> restante<?= $vagasRestantes !== 1 ? 's' : '' ?></p>
                    <?php elseif ($isLotado): ?>
                        <p class="homeTreino__vagas__restantes --esgotado">Vagas esgotadas</p>
                    <?php else: ?>
                        <p class="homeTreino__vagas__restantes --esgotado">Lista encerrada</p>
                    <?php endif; ?>
                </div>

                <?php if ($jogadorLogado && $statusTreino === 'aguardando'): ?>
                    <p class="homeTreino__vagas__restantes --esgotado">Aguardando abertura pelo admin</p>
                <?php elseif ($podeConfirmarHome): ?>
                    <button class="btn btn--primary homeTreino__btn" id="btnAbrirConfirmarHome">Confirmar presença</button>
                <?php elseif ($jogadorLogado && $jogadorConfirmou): ?>
                    <div class="homeTreino__acoes">
                        <div class="homeTreino__confirmado">&#10003; Você está confirmado!</div>
                        <?php if ($podeCancelarHome): ?>
                            <button class="homeTreino__cancelar" id="btnCancelarTreino" data-date="<?= $proximaKey ?>">Cancelar</button>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$jogadorLogado): ?>
                    <a href="<?= BASE_URL ?>/login" class="btn btn--primary homeTreino__btn">Entrar para confirmar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ══ LISTA DE CONFIRMADOS ══════════════════════════════════════ -->
<?php if (!empty($confirmadosNomes) && ($config['exibir_lista_home'] ?? '1') === '1'): ?>
<section class="homeConfirmados">
    <div class="container">
        <h2 class="homeSection__title">Confirmados para o <span>Próximo Treino</span></h2>
        <p class="homeSection__sub"><?= count($confirmadosNomes) ?> jogador<?= count($confirmadosNomes) !== 1 ? 'es confirmados' : ' confirmado' ?> para sexta-feira, <?= $dataFormatada ?>.</p>

        <ol class="homeConfirmados__lista">
            <?php foreach ($confirmadosNomes as $nome): ?>
                <li class="homeConfirmados__item"><?= htmlspecialchars($nome) ?></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<!-- ══ COMO FUNCIONA ═════════════════════════════════════════════ -->
<section class="homeGuia">
    <div class="container">
        <h2 class="homeSection__title">Como <span>Funciona</span></h2>
        <p class="homeSection__sub">Siga os passos abaixo para garantir sua vaga no treino semanal.</p>

        <div class="homeGuia__steps">
            <div class="homeGuia__step">
                <div class="homeGuia__step__num">1</div>
                <div class="homeGuia__step__icon">&#128197;</div>
                <h3>Segunda-feira</h3>
                <p>O calendário de treinos abre para confirmações de presença no início de cada semana.</p>
            </div>
            <div class="homeGuia__step">
                <div class="homeGuia__step__num">2</div>
                <div class="homeGuia__step__icon">&#9989;</div>
                <h3>Confirme até <?= $disparoDiaCap ?> às <?= $disparoHoraFmt ?></h3>
                <p>Acesse o calendário e confirme sua presença antes do prazo. Máximo de <?= $maxVagas ?> vagas por treino.</p>
            </div>
            <div class="homeGuia__step">
                <div class="homeGuia__step__num">3</div>
                <div class="homeGuia__step__icon">&#128274;</div>
                <h3>Encerramento <?= $disparoDiaCap ?> às <?= $disparoHoraFmt ?></h3>
                <p>Às <?= $disparoHoraFmt ?> de <?= $disparoDiaNome ?> a lista é encerrada automaticamente e enviada para a coordenação do clube.</p>
            </div>
            <div class="homeGuia__step">
                <div class="homeGuia__step__num">4</div>
                <div class="homeGuia__step__icon">&#127944;</div>
                <h3>Treino na sexta-feira</h3>
                <p>Apareça no Clube Esperia, zona norte de SP. O treino acontece das <strong>21h às 22h30</strong> — horário fixo toda sexta-feira.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══ REGRAS RÁPIDAS ════════════════════════════════════════════ -->
<section class="homeRegras">
    <div class="container">
        <h2 class="homeSection__title">Regras <span>Rápidas</span></h2>
        <p class="homeSection__sub">Respeite as regras para garantir a participação de todos.</p>

        <div class="homeRegras__grid">
            <div class="homeRegras__item">
                <span class="homeRegras__item__icon">&#9201;</span>
                <h4>Prazo de confirmação</h4>
                <p>Confirme sua presença até <strong><?= $disparoDiaNome ?> às <?= $disparoHoraFmt ?></strong>. Sem confirmação, sem garantia de vaga.</p>
            </div>
            <div class="homeRegras__item">
                <span class="homeRegras__item__icon">&#128337;</span>
                <h4>Pontualidade</h4>
                <p>Chegue com pelo menos <strong>10 minutos de antecedência</strong> para o aquecimento e organização.</p>
            </div>
            <div class="homeRegras__item">
                <span class="homeRegras__item__icon">&#128249;</span>
                <h4>Traje esportivo</h4>
                <p>Use <strong>calçado esportivo adequado</strong> para quadra. Sapatos sociais não são permitidos.</p>
            </div>
            <div class="homeRegras__item">
                <span class="homeRegras__item__icon">&#129309;</span>
                <h4>Respeito</h4>
                <p>Respeite os colegas, a coordenação e as dependências do <strong>Clube Esperia</strong>.</p>
            </div>
            <div class="homeRegras__item">
                <span class="homeRegras__item__icon">&#128683;</span>
                <h4>Ausências</h4>
                <p>Faltas frequentes sem aviso prévio podem afetar sua <strong>participação nas próximas semanas</strong>.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══ SOBRE O CLUBE ═════════════════════════════════════════════ -->
<section class="homeSobre">
    <div class="container">
        <div class="homeSobre__inner">
            <div class="homeSobre__text">
                <h2>Sobre o <span>OAB Santana Vôlei Clube</span></h2>
                <p>O OAB Santana Vôlei Clube reúne advogados, estagiários e servidores da OAB Santana para praticar vôlei toda sexta-feira em um ambiente descontraído e saudável.</p>
                <p>Os treinos acontecem nas quadras do <strong>Clube Esperia</strong>, localizado na zona norte de São Paulo, reunindo profissionais do direito que acreditam que esporte e saúde fazem parte de uma carreira equilibrada.</p>
            </div>
            <div class="homeSobre__destaques">
                <div class="homeSobre__destaque">
                    <span class="homeSobre__destaque__num"><?= $maxVagas ?></span>
                    <span class="homeSobre__destaque__label">Vagas por treino</span>
                </div>
                <div class="homeSobre__destaque">
                    <span class="homeSobre__destaque__num">1x</span>
                    <span class="homeSobre__destaque__label">Por semana</span>
                </div>
                <div class="homeSobre__destaque">
                    <span class="homeSobre__destaque__num">6ª</span>
                    <span class="homeSobre__destaque__label">Toda sexta-feira</span>
                </div>
                <div class="homeSobre__destaque">
                    <span class="homeSobre__destaque__num">21h</span>
                    <span class="homeSobre__destaque__label">às 22h30 — horário fixo</span>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>

<?php if ($podeConfirmarHome): ?>
<!-- MODAL CONFIRMAR PRESENÇA (home) -->
<div class="confirmModal" id="homeConfirmModal">
    <div class="confirmModal__box">
        <h3 class="confirmModal__title">Confirmar presença</h3>
        <p class="confirmModal__text">Você confirma que irá comparecer ao treino do dia <strong><?= $dataFormatada ?></strong>?</p>
        <label class="confirmModal__check">
            <input type="checkbox" id="homeConfirmCheck" />
            Eu confirmo minha presença
        </label>
        <div class="confirmModal__actions">
            <button class="confirmModal__btn --cancelar" id="btnFecharConfirmarHome">Cancelar</button>
            <button class="confirmModal__btn --enviar" id="btnEnviarConfirmarHome">Confirmar</button>
        </div>
    </div>
</div>
<script>
(function () {
    var BASE_URL = "<?= BASE_URL ?>";
    var dataKey  = "<?= $proximaKey ?>";

    function abrirModal() {
        $('#homeConfirmCheck').prop('checked', false);
        $('#homeConfirmModal').addClass('--open');
    }

    $(document).ready(function () {
        $('#btnAbrirConfirmarHome, #btnAbrirConfirmarHero').on('click', abrirModal);

        $('#btnFecharConfirmarHome').on('click', function () {
            $('#homeConfirmModal').removeClass('--open');
        });

        $('#homeConfirmModal').on('click', function (e) {
            if ($(e.target).is('#homeConfirmModal')) {
                $('#homeConfirmModal').removeClass('--open');
            }
        });

        $('#btnEnviarConfirmarHome').on('click', function () {
            if (!$('#homeConfirmCheck').is(':checked')) {
                alert('Marque o checkbox para confirmar sua presença.');
                return;
            }
            $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');
            $.post(BASE_URL + '/services/confirmar_presenca.php', { data_treino: dataKey }, function (res) {
                $('.overlayForm').remove();
                $('#homeConfirmModal').removeClass('--open');
                alert(res.message);
                if (res.success) location.reload();
            }, 'json').fail(function (xhr) {
                $('.overlayForm').remove();
                alert(xhr.responseJSON?.message || 'Erro ao confirmar presença.');
            });
        });
    });
})();
</script>
<?php endif; ?>

<?php if ($podeCancelarHome): ?>
<script>
(function () {
    var BASE_URL = "<?= BASE_URL ?>";
    var dataKey  = "<?= $proximaKey ?>";

    function cancelarConfirmacao() {
        if (!confirm('Tem certeza que deseja cancelar sua confirmação?\n\nSua vaga será liberada para outros participantes.')) return;
        $('body').append('<div class="overlay overlayForm"><div class="loader"></div></div>');
        $.post(BASE_URL + '/services/cancelar_presenca.php', { data_treino: dataKey }, function (res) {
            $('.overlayForm').remove();
            alert(res.message);
            if (res.success) location.reload();
        }, 'json').fail(function (xhr) {
            $('.overlayForm').remove();
            alert(xhr.responseJSON?.message || 'Erro ao cancelar confirmação.');
        });
    }

    $(document).ready(function () {
        $('#btnCancelarHero, #btnCancelarTreino').on('click', cancelarConfirmacao);
    });
})();
</script>
<?php endif; ?>

</body>
</html>
