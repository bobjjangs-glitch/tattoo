<?php
require_once __DIR__ . '/../../includes/env.php';

function getDbConnection(): PDO
{
    $host   = env('DB_HOST');
    $dbname = env('DB_NAME');
    $user   = env('DB_USER');
    $pass   = env('DB_PASS');

    if ($host === null || $dbname === null || $user === null || $pass === null) {
        throw new RuntimeException('DB 접속 정보가 .env에 설정되지 않았습니다. .env 파일을 확인하세요.');
    }

    return new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}
