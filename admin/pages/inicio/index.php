<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/envio_helper.php';
$pdo = getDbConnection();

// ── Config ────────────────────────────────────────────────────
$config   = getAppConfig($pdo);
$maxVagas = (int) $config['max_vagas'];

// ── Próximo treino (próxima sexta-feira) ──────────────────────
$hoje = new DateTime();
$hoje->setTime(0, 0, 0);
$proximaSexta = clone $hoje;
while ($proximaSexta->format('N') != 5) {
    $proximaSexta->modify('+1 day');
}
$proximaKey = $proximaSexta->format('Y-m-d');
$mesesFull  = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
               '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$dataFormatada = $proximaSexta->format('d') . ' de ' . $mesesFull[$proximaSexta->format('m')] . ' de ' . $proximaSexta->format('Y');

// ── Contagem confirmados no próximo treino ─────────────────────
$stmtConf = $pdo->prepare("SELECT COUNT(*) FROM confirmacoes_treino WHERE data_treino = ?");
$stmtConf->execute([$proximaKey]);
$totalConfirmados = (int) $stmtConf->fetchColumn();

// ── Treino encerrado? ─────────────────────────────────────────
$stmtEnc = $pdo->prepare("SELECT 1 FROM treinos_encerrados WHERE data_treino = ? LIMIT 1");
$stmtEnc->execute([$proximaKey]);
$isEncerrado    = (bool) $stmtEnc->fetch();
$vagasRestantes = max(0, $maxVagas - $totalConfirmados);
$progressoPct   = $maxVagas > 0 ? min(100, round($totalConfirmados / $maxVagas * 100)) : 0;
$isLotado       = !$isEncerrado && $totalConfirmados >= $maxVagas;

if      ($isEncerrado) { $statusTreino = 'encerrado'; $statusLabel = 'Encerrado'; }
elseif  ($isLotado)    { $statusTreino = 'lotado';    $statusLabel = 'Lotado';    }
elseif  ($hoje == $proximaSexta) { $statusTreino = 'hoje'; $statusLabel = 'Hoje!'; }
elseif  ((int)$hoje->format('N') <= 4) { $statusTreino = 'aberto'; $statusLabel = 'Aberto'; }
else                   { $statusTreino = 'em_breve';  $statusLabel = 'Em breve';  }

// ── Últimas confirmações do próximo treino ────────────────────
// Tenta ordenar por created_at; se a coluna não existir, ordena por id
try {
    $stmtUltConf = $pdo->prepare("
        SELECT j.nome_completo, ct.created_at
        FROM confirmacoes_treino ct
        JOIN jogadores j ON j.id = ct.jogador_id
        WHERE ct.data_treino = ?
        ORDER BY ct.created_at DESC
        LIMIT 8
    ");
    $stmtUltConf->execute([$proximaKey]);
    $ultimasConfirmacoes = $stmtUltConf->fetchAll();
    $temHorario = true;
} catch (Exception $e) {
    $stmtUltConf = $pdo->prepare("
        SELECT j.nome_completo, NULL as created_at
        FROM confirmacoes_treino ct
        JOIN jogadores j ON j.id = ct.jogador_id
        WHERE ct.data_treino = ?
        ORDER BY ct.id DESC
        LIMIT 8
    ");
    $stmtUltConf->execute([$proximaKey]);
    $ultimasConfirmacoes = $stmtUltConf->fetchAll();
    $temHorario = false;
}

// ── Total jogadores ────────────────────────────────────────────
$totalJogadores = (int) $pdo->query("SELECT COUNT(*) FROM jogadores")->fetchColumn();

// ── Total treinos encerrados ───────────────────────────────────
$totalEncerrados = (int) $pdo->query("SELECT COUNT(*) FROM treinos_encerrados")->fetchColumn();

// ── Últimos jogadores cadastrados ──────────────────────────────
$ultimosJogadores = $pdo->query("SELECT nome_completo, email, created_at FROM jogadores ORDER BY created_at DESC LIMIT 6")->fetchAll();

// ── Usuários admin (totais por nível) ─────────────────────────
$niveis = ['admin' => 0, 'editor' => 0, 'leitor' => 0];
$totalUsuarios = 0;
foreach ($pdo->query("SELECT nivel_acesso, COUNT(*) as total FROM admin_usuarios GROUP BY nivel_acesso")->fetchAll() as $row) {
    $niveis[$row['nivel_acesso']] = $row['total'];
    $totalUsuarios += $row['total'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin - Início</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="adminInicio">

            <!-- Boas-vindas -->
            <div class="row adminInicio__header">
                <div class="col-md-12">
                    <h2>Olá, <span><?= htmlspecialchars(explode(' ', $_SESSION['usuario']['nome_completo'])[0]) ?></span>!</h2>
                    <p>Aqui está o resumo da plataforma.</p>
                </div>
            </div>

            <!-- Cards de estatísticas -->
            <div class="row adminInicio__stats">
                <div class="col-md-3 col-6">
                    <div class="dashCard dashCard--jogadores">
                        <span class="dashCard__icon">&#127944;</span>
                        <span class="dashCard__number"><?= $totalJogadores ?></span>
                        <span class="dashCard__label">Jogadores cadastrados</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="dashCard dashCard--confirmados">
                        <span class="dashCard__icon">&#9989;</span>
                        <span class="dashCard__number"><?= $totalConfirmados ?><small>/<?= $maxVagas ?></small></span>
                        <span class="dashCard__label">Confirmados no próximo treino</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="dashCard dashCard--vagas">
                        <span class="dashCard__icon">&#128337;</span>
                        <span class="dashCard__number"><?= $isEncerrado ? '—' : $vagasRestantes ?></span>
                        <span class="dashCard__label">Vagas restantes</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="dashCard dashCard--encerrados">
                        <span class="dashCard__icon">&#128197;</span>
                        <span class="dashCard__number"><?= $totalEncerrados ?></span>
                        <span class="dashCard__label">Treinos encerrados</span>
                    </div>
                </div>
            </div>

            <!-- Atalhos rápidos -->
            <div class="row adminInicio__atalhos">
                <div class="col-md-3 col-6">
                    <a href="<?= BASE_URL ?>/admin/confirmacoes" class="dashAtalho dashAtalho--confirmacoes">
                        <span class="dashAtalho__icon">&#9989;</span>
                        <span class="dashAtalho__title">Confirmações</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= BASE_URL ?>/admin/usuarios" class="dashAtalho dashAtalho--usuarios">
                        <span class="dashAtalho__icon">&#128100;</span>
                        <span class="dashAtalho__title">Usuários</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= BASE_URL ?>/admin/configuracoes" class="dashAtalho dashAtalho--config">
                        <span class="dashAtalho__icon">&#9881;</span>
                        <span class="dashAtalho__title">Configurações</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="<?= BASE_URL ?>/admin/cadastrarusuario" class="dashAtalho dashAtalho--novo">
                        <span class="dashAtalho__icon">&#43;</span>
                        <span class="dashAtalho__title">Novo Usuário</span>
                    </a>
                </div>
            </div>

            <!-- Próximo treino + confirmados -->
            <div class="row">

                <!-- Próximo treino -->
                <div class="col-md-4">
                    <div class="dashTreino">
                        <h4 class="dashTreino__title">Próximo Treino</h4>
                        <div class="dashTreino__data"><?= $dataFormatada ?></div>
                        <span class="dashTreino__status --<?= $statusTreino ?>"><?= $statusLabel ?></span>

                        <div class="dashTreino__progress">
                            <div class="dashTreino__progress__bar">
                                <div class="dashTreino__progress__fill --<?= $statusTreino ?>" style="width:<?= $progressoPct ?>%"></div>
                            </div>
                            <span class="dashTreino__progress__label">
                                <?= $totalConfirmados ?> de <?= $maxVagas ?> vagas
                                <?php if ($isEncerrado): ?>
                                    &mdash; encerrado
                                <?php elseif ($isLotado): ?>
                                    &mdash; lotado
                                <?php else: ?>
                                    preenchidas
                                <?php endif; ?>
                            </span>
                        </div>

                        <a href="<?= BASE_URL ?>/admin/confirmacoes" class="btn btn--primary dashTreino__btn">
                            Ver confirmações completas
                        </a>
                    </div>
                </div>

                <!-- Últimas confirmações -->
                <div class="col-md-8">
                    <div class="dashRecentes">
                        <h4 class="dashRecentes__title">
                            Últimas confirmações
                            <span class="dashRecentes__sub">próximo treino</span>
                        </h4>
                        <?php if (empty($ultimasConfirmacoes)): ?>
                            <p class="dashRecentes__vazio">Nenhuma confirmação registrada ainda.</p>
                        <?php else: ?>
                        <table class="dashTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Jogador</th>
                                    <?php if ($temHorario): ?><th>Confirmado em</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $posicao = $totalConfirmados;
                                foreach ($ultimasConfirmacoes as $c):
                                ?>
                                <tr>
                                    <td class="dashTable__num"><?= $posicao-- ?></td>
                                    <td><?= htmlspecialchars($c['nome_completo']) ?></td>
                                    <?php if ($temHorario): ?>
                                    <td><?= date('d/m H:i', strtotime($c['created_at'])) ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($totalConfirmados > 8): ?>
                            <p class="dashRecentes__mais">
                                + <?= $totalConfirmados - 8 ?> outros &mdash;
                                <a href="<?= BASE_URL ?>/admin/confirmacoes">ver todos</a>
                            </p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Últimos jogadores + perfil -->
            <div class="row">

                <!-- Últimos jogadores -->
                <div class="col-md-8">
                    <div class="dashRecentes">
                        <h4 class="dashRecentes__title">
                            Últimos jogadores cadastrados
                            <span class="dashRecentes__sub"><a href="<?= BASE_URL ?>/admin/usuarios">ver todos</a></span>
                        </h4>
                        <?php if (empty($ultimosJogadores)): ?>
                            <p class="dashRecentes__vazio">Nenhum jogador cadastrado ainda.</p>
                        <?php else: ?>
                        <table class="dashTable">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Cadastrado em</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimosJogadores as $j): ?>
                                <tr>
                                    <td><?= htmlspecialchars($j['nome_completo']) ?></td>
                                    <td><?= htmlspecialchars($j['email']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($j['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Perfil do usuário logado -->
                <div class="col-md-4">
                    <div class="dashPerfil">
                        <h4 class="dashPerfil__title">Meu Perfil</h4>
                        <ul class="dashPerfil__list">
                            <li class="dashPerfil__item">
                                <span class="dashPerfil__key">Nome</span>
                                <span class="dashPerfil__val"><?= htmlspecialchars($_SESSION['usuario']['nome_completo']) ?></span>
                            </li>
                            <li class="dashPerfil__item">
                                <span class="dashPerfil__key">E-mail</span>
                                <span class="dashPerfil__val"><?= htmlspecialchars($_SESSION['usuario']['email']) ?></span>
                            </li>
                            <li class="dashPerfil__item">
                                <span class="dashPerfil__key">Nível</span>
                                <span class="dashPerfil__val">
                                    <span class="nivelBadge nivelBadge--<?= $_SESSION['usuario']['nivel_acesso'] ?>">
                                        <?= strtoupper($_SESSION['usuario']['nivel_acesso']) ?>
                                    </span>
                                </span>
                            </li>
                            <li class="dashPerfil__item">
                                <span class="dashPerfil__key">Usuários admin</span>
                                <span class="dashPerfil__val"><?= $totalUsuarios ?> (<?= $niveis['admin'] ?> admin · <?= $niveis['editor'] ?> editor · <?= $niveis['leitor'] ?> leitor)</span>
                            </li>
                        </ul>
                        <a href="<?= BASE_URL ?>/admin/meusdados" class="btn btn--primary dashPerfil__btn">Editar perfil</a>
                    </div>
                </div>

            </div>

        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

</body>
</html>
