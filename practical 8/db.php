<?php

declare(strict_types=1);

$dbHost = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('STUDENTHUB_DB_PORT') ?: '3306';
$dbName = getenv('STUDENTHUB_DB_NAME') ?: 'studenthub';
$dbUser = getenv('STUDENTHUB_DB_USER') ?: 'root';
$dbPassword = getenv('STUDENTHUB_DB_PASSWORD') ?: '';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $dbHost,
    $dbPort,
    $dbName
);

$pdo = new PDO($dsn, $dbUser, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);
