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

$jogadorLogado   = !empty($_SESSION['jogador']);
$jogadorConfirmou = false;
$primeiroNome    = '';

if ($jogadorLogado) {
    $primeiroNome = explode(' ', $_SESSION['jogador']['nome_completo'])[0];
    $stmtC = $pdo->prepare("SELECT 1 FROM confirmacoes_treino WHERE jogador_id = ? AND data_treino = ? LIMIT 1");
    $stmtC->execute([$_SESSION['jogador']['id'], $proximaKey]);
    $jogadorConfirmou = (bool) $stmtC->fetch();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Vôlei Clube - Início</title>
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
                <?php elseif ($statusTreino === 'aberto'): ?>
                    <a href="<?= BASE_URL ?>/calendario" class="homeHero__cta">Confirmar minha presença &rarr;</a>
                <?php endif; ?>
            <?php else: ?>
                <span class="homeHero__tag">OAB Vôlei Clube</span>
                <h1>Vôlei, saúde e <span>confraternização</span></h1>
                <p class="homeHero__sub">Um espaço para advogados e servidores da OAB/SP praticarem esporte toda sexta-feira no Clube Esperia, zona norte de São Paulo.</p>
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
                <?php elseif ($jogadorLogado && $statusTreino === 'aberto' && !$jogadorConfirmou): ?>
                    <a href="<?= BASE_URL ?>/calendario" class="btn btn--primary homeTreino__btn">Confirmar presença</a>
                <?php elseif ($jogadorLogado && $jogadorConfirmou): ?>
                    <div class="homeTreino__confirmado">&#10003; Você está confirmado!</div>
                <?php elseif (!$jogadorLogado): ?>
                    <a href="<?= BASE_URL ?>/login" class="btn btn--primary homeTreino__btn">Entrar para confirmar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

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
                <h3>Confirme até quinta às 18h</h3>
                <p>Acesse o calendário e confirme sua presença antes do prazo. Máximo de 30 vagas por treino.</p>
            </div>
            <div class="homeGuia__step">
                <div class="homeGuia__step__num">3</div>
                <div class="homeGuia__step__icon">&#128274;</div>
                <h3>Encerramento sexta às 19h</h3>
                <p>Às 19h da sexta-feira a lista é encerrada automaticamente e enviada para a coordenação do clube.</p>
            </div>
            <div class="homeGuia__step">
                <div class="homeGuia__step__num">4</div>
                <div class="homeGuia__step__icon">&#127944;</div>
                <h3>Treino na sexta-feira</h3>
                <p>Apareça no Clube Esperia, zona norte de SP, e aproveite mais um treino com os colegas da OAB!</p>
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
                <p>Confirme sua presença até <strong>quinta-feira às 18h</strong>. Sem confirmação, sem garantia de vaga.</p>
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
                <h2>Sobre o <span>OAB Vôlei Clube</span></h2>
                <p>O OAB Vôlei Clube reúne advogados, estagiários e servidores da OAB/SP para praticar vôlei toda sexta-feira em um ambiente descontraído e saudável.</p>
                <p>Os treinos acontecem nas quadras do <strong>Clube Esperia</strong>, localizado na zona norte de São Paulo, reunindo profissionais do direito que acreditam que esporte e saúde fazem parte de uma carreira equilibrada.</p>
            </div>
            <div class="homeSobre__destaques">
                <div class="homeSobre__destaque">
                    <span class="homeSobre__destaque__num">30</span>
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
            </div>
        </div>
    </div>
</section>

<?php include ROOT . '/includes/footer/footer.php'; ?>
<?php include ROOT . '/includes/scripts.php'; ?>

</body>
</html>
