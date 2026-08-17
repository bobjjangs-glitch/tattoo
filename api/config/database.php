<?php
function getDbConnection(): PDO {
    $host = 'localhost';
    $dbname = 'bobjjangs1231';
    $user = 'bobjjangs1231';
    $pass = 'ssy201029@@';

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
