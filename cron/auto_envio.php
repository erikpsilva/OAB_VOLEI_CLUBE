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

// Só executa na quinta-feira (N=4) após as 19h
if ($now->format('N') != 4) {
    echo 'Hoje não é quinta-feira (' . $now->format('l') . '). Nada a fazer.' . PHP_EOL;
    exit;
}

if ((int) $now->format('H') < 19) {
    echo 'Ainda não são 19h (' . $now->format('H:i') . '). Nada a fazer.' . PHP_EOL;
    exit;
}

// Calcula a sexta-feira desta semana (amanhã)
$friday    = (clone $today)->modify('+1 day');
$fridayKey = $friday->format('Y-m-d');

echo 'Verificando treino da sexta-feira: ' . $fridayKey . PHP_EOL;

$pdo = getDbConnection();

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
