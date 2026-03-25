<?php
/**
 * Script de envio automático de confirmações de treino.
 *
 * Deve ser executado diariamente às 19h nas quintas-feiras via agendador de tarefas.
 *
 * No Windows (Agendador de Tarefas), configure:
 *   Programa:   C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\OAB_VOLEI_CLUBE\cron\auto_envio.php
 *   Gatilho:    Semanal — toda quinta-feira às 19:00
 *
 * Em servidores Linux, adicione ao crontab (crontab -e):
 *   0 19 * * 4 /usr/bin/php /var/www/html/OAB_VOLEI_CLUBE/cron/auto_envio.php
 *   (0 19 * * 4 = às 19h toda quinta-feira)
 */

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/envio_helper.php';

$now   = new DateTime();
$today = (clone $now)->setTime(0, 0, 0);

echo '[' . $now->format('Y-m-d H:i:s') . '] Iniciando verificação de auto-envio.' . PHP_EOL;

$pdo    = getDbConnection();
$config = getAppConfig($pdo);

$disparoDia  = (int) $config['disparo_dia_semana'];           // 1=seg … 7=dom
$disparoHora = $config['disparo_hora'];                        // "HH:MM"

// Verifica se hoje é o dia configurado
if ((int) $now->format('N') !== $disparoDia) {
    echo 'Hoje não é o dia configurado para disparo (' . $now->format('l') . '). Nada a fazer.' . PHP_EOL;
    exit;
}

// Verifica se já passou do horário configurado
[$hConf, $mConf] = array_map('intval', explode(':', $disparoHora . ':00'));
$minAgora = (int) $now->format('H') * 60 + (int) $now->format('i');
$minConf  = $hConf * 60 + $mConf;

if ($minAgora < $minConf) {
    echo 'Ainda não são ' . $disparoHora . ' (' . $now->format('H:i') . '). Nada a fazer.' . PHP_EOL;
    exit;
}

// Calcula a próxima sexta-feira a partir do dia de disparo
$offsetParaSexta = (5 - $disparoDia + 7) % 7;
if ($offsetParaSexta === 0) $offsetParaSexta = 7;
$friday    = (clone $today)->modify("+{$offsetParaSexta} day");
$fridayKey = $friday->format('Y-m-d');

echo 'Verificando treino da sexta-feira: ' . $fridayKey . PHP_EOL;

// Verifica se já foi encerrado
$stmt = $pdo->prepare("SELECT 1 FROM treinos_encerrados WHERE data_treino = ? LIMIT 1");
$stmt->execute([$fridayKey]);
if ($stmt->fetch()) {
    echo 'Treino de ' . $fridayKey . ' já encerrado anteriormente. Nada a fazer.' . PHP_EOL;
    exit;
}

// Envia e encerra
echo 'Disparando envio automático para ' . $fridayKey . '...' . PHP_EOL;
$result = enviarConfirmacoes($fridayKey, $pdo, true);
echo $result['message'] . PHP_EOL;
