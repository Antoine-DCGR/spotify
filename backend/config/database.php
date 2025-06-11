<?php
// config/database.php

function getDatabaseConnection(): PDO
{
    $host   = getenv('MYSQL_HOST')     ?: 'localhost';
    $dbname = getenv('MYSQL_DATABASE') ?: 'myapp';
    $user   = getenv('MYSQL_USER')     ?: 'root';
    $pass   = getenv('MYSQL_PASSWORD') ?: '';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            // obliger PDO à utiliser utf8mb4
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur DB : " . $e->getMessage());
    }
}
