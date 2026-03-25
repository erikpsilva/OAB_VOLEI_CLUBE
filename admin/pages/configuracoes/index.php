<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if ($_SESSION['usuario']['nivel_acesso'] !== 'admin') {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$defaults = [
    'email_admin'        => '',
    'email_esperia'      => '',
    'disparo_dia_semana' => '4',
    'disparo_hora'       => '19:00',
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
?>
<!DOCTYPE html>
<html>
<head>
<title>OAB Vôlei Clube - Admin - Configurações</title>
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

            <?php if (!empty($dbError)): ?>
            <div class="configPage__alert --error">
                A tabela <code>app_configuracoes</code> ainda não foi criada no banco de dados. Execute o SQL indicado na documentação e recarregue a página.
            </div>
            <?php else: ?>

            <div class="formGroup">
                <div class="row">
                    <div class="col-md-12 formGroup__divisor">
                        <h3>E-mails de <span>destino</span></h3>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>E-mail da Coordenação <small>(recebe CPF mascarado)</small></label>
                            <input class="input" type="email" id="emailAdmin"
                                   placeholder="email@exemplo.com.br"
                                   value="<?= htmlspecialchars($defaults['email_admin']) ?>" />
                            <span class="errorText">Digite um e-mail válido</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="formGroup__item">
                            <label>E-mail do Clube Esperia <small>(recebe CPF completo)</small></label>
                            <input class="input" type="email" id="emailEsperia"
                                   placeholder="email@exemplo.com.br"
                                   value="<?= htmlspecialchars($defaults['email_esperia']) ?>" />
                            <span class="errorText">Digite um e-mail válido</span>
                        </div>
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
