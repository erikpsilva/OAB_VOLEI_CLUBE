<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if ($_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$manutencaoAtiva = file_exists(ROOT . '/config/maintenance.flag');

$defaults = [
    'emails_admin'         => '[]',
    'emails_esperia'       => '[]',
    'disparo_dia_semana'   => '4',
    'disparo_hora'         => '19:00',
    'max_vagas'            => '30',
    'modo_abertura_agenda' => 'automatico',
    'agenda_liberada_data' => '',
];

try {
    $stmt = $pdo->query("SELECT chave, valor FROM app_configuracoes");
    foreach ($stmt->fetchAll() as $row) {
        $defaults[$row['chave']] = $row['valor'];
    }
} catch (Exception $e) {
    $dbError = true;
}

$diasSemana = [
    '1' => 'Segunda-feira',
    '2' => 'Terça-feira',
    '3' => 'Quarta-feira',
    '4' => 'Quinta-feira',
    '5' => 'Sexta-feira',
    '6' => 'Sábado',
    '7' => 'Domingo',
];

// Próxima sexta para exibir no botão de liberar agenda
$_hoje = new DateTime(); $_hoje->setTime(0,0,0);
$_proxSexta = clone $_hoje;
while ($_proxSexta->format('N') != 5) $_proxSexta->modify('+1 day');
$_meses = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
           '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$proxSextaKey  = $_proxSexta->format('Y-m-d');
$proxSextaFmt  = $_proxSexta->format('d') . ' de ' . $_meses[$_proxSexta->format('m')];
$agendaJaLiberada = ($defaults['agenda_liberada_data'] === $proxSextaKey);

$emailsAdmin    = json_decode($defaults['emails_admin'],   true) ?: [];
$emailsEsperia  = json_decode($defaults['emails_esperia'], true) ?: [];
$emailRemetente = $defaults['email_remetente']  ?? '';
$mensagemEmail  = $defaults['mensagem_email']   ?? '';
$smtpAtivo      = ($defaults['smtp_ativo']      ?? '0') === '1';
$smtpHost       = $defaults['smtp_host']        ?? '';
$smtpPorta      = $defaults['smtp_porta']       ?? '587';
$smtpUsuario    = $defaults['smtp_usuario']     ?? '';
$smtpSenhaSalva = !empty($defaults['smtp_senha']);
$smtpEncryption = $defaults['smtp_encryption']  ?? 'tls';
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Santana Vôlei Clube - Admin - Configurações</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="configPage">
            <div class="row">
                <div class="col-md-12">
                    <h2>Configurações do <span>Sistema</span></h2>
                    <p class="configPage__sub">Gerencie os e-mails de destino e o horário de disparo automático da lista de confirmações.</p>
                </div>
            </div>

            <!-- ── Modo de manutenção ──────────────────────────────── -->
            <div class="formGroup configManutencao <?= $manutencaoAtiva ? '--ativa' : '' ?>">
                <div class="row">
                    <div class="col-md-8">
                        <h3 class="configManutencao__title">Modo de <span>Manutenção</span></h3>
                        <p class="configManutencao__desc">
                            Quando ativo, o site público exibe uma página de manutenção e impede qualquer acesso.
                            O painel admin continua funcionando normalmente.
                        </p>
                        <div id="manutencaoStatus" class="configManutencao__status <?= $manutencaoAtiva ? '--ativa' : '--inativa' ?>">
                            <?= $manutencaoAtiva ? '&#9888; Site em manutenção — visitantes não conseguem acessar' : '&#10003; Site funcionando normalmente' ?>
                        </div>
                    </div>
                    <div class="col-md-4 configManutencao__actions">
                        <?php if ($manutencaoAtiva): ?>
                            <button class="btn btn--success" id="btnDesativarManutencao">Desativar manutenção</button>
                        <?php else: ?>
                            <button class="btn btn--danger" id="btnAtivarManutencao">Ativar manutenção</button>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-12">
                        <div id="manutencaoFeedback" class="configPage__feedback" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($dbError)): ?>
            <div class="configPage__alert --error">
                A tabela <code>app_configuracoes</code> ainda não foi criada no banco de dados. Execute o SQL indicado na documentação e recarregue a página.
            </div>
            <?php else: ?>

            <!-- ── SMTP ──────────────────────────────────────────────── -->
            <div class="formGroup configSmtp">
                <div class="row">
                    <div class="col-md-12 formGroup__divisor">
                        <h3>Servidor de <span>E-mail (SMTP)</span></h3>
                    </div>

                    <div class="col-md-12">
                        <div class="configSmtp__toggle">
                            <label class="configSmtp__toggleLabel">
                                <input type="checkbox" id="smtpAtivo" <?= $smtpAtivo ? 'checked' : '' ?>>
                                <span class="configSmtp__toggleSwitch"></span>
                                Usar SMTP personalizado
                            </label>
                            <span class="configSmtp__toggleStatus <?= $smtpAtivo ? '--ativo' : '--inativo' ?>">
                                <?= $smtpAtivo ? 'Ativo — usando SMTP configurado abaixo' : 'Inativo — usando mail() nativo do servidor' ?>
                            </span>
                        </div>
                    </div>

                    <div class="configSmtp__campos" style="<?= !$smtpAtivo ? 'display:none;' : '' ?>">
                    <div class="col-md-12">
                        <div class="configPage__infoBox" style="margin-top:12px;">
                            Para Gmail: host <code>smtp.gmail.com</code>, porta <code>587</code>, criptografia <code>TLS</code>,
                            usuário seu e-mail e senha um <strong>App Password</strong> gerado na sua conta Google.
                            <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Gerar App Password &rarr;</a>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="formGroup__item">
                            <label>Servidor SMTP (host)</label>
                            <input class="input" type="text" id="smtpHost"
                                   placeholder="smtp.gmail.com"
                                   value="<?= htmlspecialchars($smtpHost) ?>" />
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="formGroup__item">
                            <label>Porta</label>
                            <input class="input" type="number" id="smtpPorta"
                                   placeholder="587"
                                   value="<?= htmlspecialchars($smtpPorta) ?>" />
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="formGroup__item">
                            <label>Criptografia</label>
                            <select class="input" id="smtpEncryption">
                                <option value="tls"  <?= $smtpEncryption === 'tls'  ? 'selected' : '' ?>>TLS (porta 587)</option>
                                <option value="ssl"  <?= $smtpEncryption === 'ssl'  ? 'selected' : '' ?>>SSL (porta 465)</option>
                                <option value="none" <?= $smtpEncryption === 'none' ? 'selected' : '' ?>>Nenhuma (porta 25)</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Usuário SMTP</label>
                            <input class="input" type="email" id="smtpUsuario"
                                   placeholder="seu@gmail.com"
                                   value="<?= htmlspecialchars($smtpUsuario) ?>" />
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>Senha SMTP <?php if ($smtpSenhaSalva): ?><small>(senha salva — deixe em branco para manter)</small><?php endif; ?></label>
                            <input class="input configSmtp__senha" type="password" id="smtpSenha"
                                   placeholder="<?= $smtpSenhaSalva ? '••••••••••••' : 'App Password do Gmail' ?>"
                                   autocomplete="new-password" />
                        </div>
                    </div>
                    </div><!-- /.configSmtp__campos -->
                </div>
            </div>

            <div class="formGroup">
                <div class="row">
                    <div class="col-md-12 formGroup__divisor">
                        <h3>E-mails de <span>destino</span></h3>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>E-mails da Coordenação <small>(recebem CPF mascarado — até 5)</small></label>
                            <div class="emailList" id="emailsAdmin">
                                <?php foreach ($emailsAdmin as $em): ?>
                                <div class="emailList__item">
                                    <input class="input emailList__input" type="email"
                                           placeholder="email@exemplo.com.br"
                                           value="<?= htmlspecialchars($em) ?>" />
                                    <button type="button" class="emailList__remove" title="Remover">&times;</button>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($emailsAdmin)): ?>
                                <div class="emailList__item">
                                    <input class="input emailList__input" type="email"
                                           placeholder="email@exemplo.com.br" value="" />
                                    <button type="button" class="emailList__remove" title="Remover">&times;</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="emailList__add"
                                    data-target="emailsAdmin" data-max="5">+ Adicionar e-mail</button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>E-mails do Clube Esperia <small>(recebem CPF completo — até 5)</small></label>
                            <div class="emailList" id="emailsEsperia">
                                <?php foreach ($emailsEsperia as $em): ?>
                                <div class="emailList__item">
                                    <input class="input emailList__input" type="email"
                                           placeholder="email@exemplo.com.br"
                                           value="<?= htmlspecialchars($em) ?>" />
                                    <button type="button" class="emailList__remove" title="Remover">&times;</button>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($emailsEsperia)): ?>
                                <div class="emailList__item">
                                    <input class="input emailList__input" type="email"
                                           placeholder="email@exemplo.com.br" value="" />
                                    <button type="button" class="emailList__remove" title="Remover">&times;</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="emailList__add"
                                    data-target="emailsEsperia" data-max="5">+ Adicionar e-mail</button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>E-mail do remetente <small>(aparece como "De:" no email enviado)</small></label>
                            <input class="input" type="email" id="emailRemetente"
                                   placeholder="noreply@oabvoleiclube.com.br"
                                   value="<?= htmlspecialchars($emailRemetente) ?>" />
                            <span class="errorText">Digite um e-mail válido</span>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="formGroup__item">
                            <label>Mensagem do e-mail <small>(exibida antes da lista de confirmados — opcional)</small></label>
                            <textarea class="input configPage__textarea" id="mensagemEmail"
                                      placeholder="Ex: Lista de presença vôlei OAB SANTANA, do dia {{data}} horário a partir das 21h00!"
                                      rows="4"><?= htmlspecialchars($mensagemEmail) ?></textarea>
                            <div class="configPage__tagHint">
                                <strong>Tag disponível:</strong>
                                <code class="configPage__tag" title="Clique para inserir" data-tag="{{data}}">{{data}}</code>
                                — substituída pela data do treino (ex: <em>27 de Março de 2026</em>)
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 configPage__testeEnvio">
                        <button class="btn btn--outline" id="btnTestarEnvio" type="button">
                            &#9993; Testar envio (somente Coordenação)
                        </button>
                        <div id="testarEnvioFeedback" class="configPage__feedback" style="display:none;"></div>
                    </div>

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Disparo <span>automático</span></h3>
                    </div>

                    <div class="col-md-12">
                        <div class="configPage__disparoAtual">
                            <span class="configPage__disparoAtual__label">Configuração ativa:</span>
                            <strong><?= $diasSemana[$defaults['disparo_dia_semana']] ?> às <?= htmlspecialchars($defaults['disparo_hora']) ?>h</strong>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="configPage__infoBox">
                            <strong>Como funciona:</strong> no dia e horário configurados abaixo, o script agendado envia
                            automaticamente a lista de confirmações para os dois e-mails acima e encerra as inscrições
                            para o treino da próxima sexta-feira. O agendador de tarefas (Windows) ou cron (Linux)
                            deve ser configurado para executar <code>cron/auto_envio.php</code> nesse mesmo dia e hora.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Dia da semana do disparo</label>
                            <select class="input" id="disparoDia">
                                <?php foreach ($diasSemana as $val => $label): ?>
                                <option value="<?= $val ?>" <?= (int)$defaults['disparo_dia_semana'] === (int)$val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Horário do disparo</label>
                            <input class="input" type="time" id="disparoHora"
                                   value="<?= htmlspecialchars($defaults['disparo_hora']) ?>" />
                            <span class="errorText">Horário inválido</span>
                        </div>
                    </div>

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Regras do <span>Treino</span></h3>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Máximo de vagas por treino</label>
                            <input class="input" type="number" id="maxVagas" min="1" max="200"
                                   value="<?= (int) $defaults['max_vagas'] ?>" />
                            <span class="errorText">Informe um número válido (mínimo 1)</span>
                        </div>
                    </div>

                    <div class="col-md-12 formGroup__divisor">
                        <h3>Abertura da <span>Agenda</span></h3>
                    </div>

                    <div class="col-md-12">
                        <div class="configPage__infoBox">
                            <strong>Automático:</strong> confirmações abrem toda segunda-feira para todos.
                            <strong>Manual:</strong> o admin libera quando quiser. Jogadores <em>favoritos</em> têm acesso automático de segunda a sexta independente do modo.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="formGroup__item">
                            <label>Modo de abertura</label>
                            <select class="input" id="modoAbertura">
                                <option value="automatico" <?= $defaults['modo_abertura_agenda'] === 'automatico' ? 'selected' : '' ?>>Automático (toda segunda-feira)</option>
                                <option value="manual"     <?= $defaults['modo_abertura_agenda'] === 'manual'     ? 'selected' : '' ?>>Manual (admin libera)</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-8 configAgendaManual" style="<?= $defaults['modo_abertura_agenda'] !== 'manual' ? 'display:none;' : '' ?>">
                        <div class="configAgendaManual__inner">
                            <div id="agendaStatus" class="configAgendaManual__status <?= $agendaJaLiberada ? '--liberada' : '--bloqueada' ?>">
                                <?php if ($agendaJaLiberada): ?>
                                    &#10003; Agenda liberada para <?= $proxSextaFmt ?>
                                <?php else: ?>
                                    &#9888; Agenda não liberada para <?= $proxSextaFmt ?>
                                <?php endif; ?>
                            </div>
                            <button class="btn <?= $agendaJaLiberada ? 'btn--gray' : 'btn--primary' ?>" id="btnLiberarAgenda"
                                    data-sexta="<?= $proxSextaKey ?>" data-fmt="<?= $proxSextaFmt ?>"
                                    <?= $agendaJaLiberada ? 'disabled' : '' ?>>
                                <?= $agendaJaLiberada ? 'Já liberada' : 'Liberar para ' . $proxSextaFmt ?>
                            </button>
                        </div>
                        <div id="agendaFeedback" class="configPage__feedback" style="display:none; margin-top:10px;"></div>
                    </div>

                    <div class="col-md-12">
                        <div id="configFeedback" class="configPage__feedback" style="display:none;"></div>
                    </div>

                    <div class="col-md-12">
                        <button class="btn btn--primary" id="btnSalvarConfig">Salvar configurações</button>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/configuracoes/configuracoes.js?v' . $version . '"></script>';
?>

</body>
</html>
