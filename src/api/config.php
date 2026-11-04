<?php
date_default_timezone_set('America/Sao_Paulo');

function env(string $k, $d = null) {
    $v = getenv($k);
    return $v !== false ? $v : $d;
}

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', env('DB_HOST', 'db'), env('DB_NAME', 'gerenciador'));
        $pdo = new PDO($dsn, env('DB_USER', 'app'), env('DB_PASS', 'app123'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
