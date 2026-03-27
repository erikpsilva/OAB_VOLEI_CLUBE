<?php

$isProducao = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false
           && strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false;

if ($isProducao) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'oabvoleiclube_oab_db');
    define('DB_USER', 'oabvoleiclube');
    define('DB_PASS', 'Theking!@389518');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'oab_bd');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

function getDbConnection() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']);
        exit;
    }
}
