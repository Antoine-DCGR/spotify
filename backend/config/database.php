<?php
// config/database.php

/**
 * Renvoie une instance PDO connectée à MySQL en lecture/écriture.
 * Les paramètres de connexion sont lus depuis les variables d'environnement :
 *   - MYSQL_HOST
 *   - MYSQL_DATABASE
 *   - MYSQL_USER
 *   - MYSQL_PASSWORD
 * 
 * Si la connexion échoue, on stoppe l’exécution avec un message d’erreur (mode dev).
 */
function getDatabaseConnection(): PDO
{
    // Lecture des paramètres depuis les variables d'environnement Docker
    $host = getenv('MYSQL_HOST') ?: 'localhost';
    $dbname = getenv('MYSQL_DATABASE') ?: 'myapp';
    $user = getenv('MYSQL_USER') ?: 'root';
    $pass = getenv('MYSQL_PASSWORD') ?: '';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";

    try {
        $pdo = new PDO($dsn, $user, $pass);
        // En development, lever les exceptions PDO en cas d’erreur SQL
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // En prod, on ne renverrait pas le message brut, 
        // mais ici, pour débogage, on l'affiche :
        die("Erreur de connexion à la BDD : " . $e->getMessage());
    }
}
