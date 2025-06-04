<?php
require_once 'config/database.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS playlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS musique (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artiste VARCHAR(100) NOT NULL,
    album VARCHAR(100) NOT NULL,
    titre VARCHAR(100) NOT NULL,
    playlist_id INT NOT NULL,
    FOREIGN KEY (playlist_id) REFERENCES playlist(id) ON DELETE CASCADE
)");

echo "Tables créées.";
