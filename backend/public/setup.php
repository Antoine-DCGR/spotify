<?php
// setup.php

require_once __DIR__ . '/config/database.php';

// Récupérer la connexion PDO
$pdo = getDatabaseConnection();

// Supprimer les tables existantes (dans l'ordre inverse des dépendances)
$pdo->exec("DROP TABLE IF EXISTS `playlist_musique`");
$pdo->exec("DROP TABLE IF EXISTS `spotify_playlists`");
$pdo->exec("DROP TABLE IF EXISTS `musique`");

// 1) Table pour stocker les playlists Spotify
$pdo->exec("
    CREATE TABLE `spotify_playlists` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        spotify_id VARCHAR(50) NOT NULL,
        nom VARCHAR(200) NOT NULL,
        description TEXT DEFAULT NULL,
        owner VARCHAR(200) NOT NULL,
        image VARCHAR(500) DEFAULT NULL,
        tracks_count INT UNSIGNED NOT NULL,
        is_public TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_spotify_id (spotify_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2) Table pour stocker les musiques locales
$pdo->exec("
    CREATE TABLE `musique` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        artiste VARCHAR(100) NOT NULL,
        album VARCHAR(100) NOT NULL,
        titre VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 3) Table pivot pour lier chaque musique à plusieurs playlists Spotify
$pdo->exec("
    CREATE TABLE `playlist_musique` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        playlist_spotify_id VARCHAR(50) NOT NULL,
        musique_id INT UNSIGNED NOT NULL,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (playlist_spotify_id)
            REFERENCES `spotify_playlists` (spotify_id)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
        FOREIGN KEY (musique_id)
            REFERENCES `musique` (id)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
        UNIQUE KEY uq_playlist_musique (playlist_spotify_id, musique_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "Tables supprimées si existantes, puis créées avec succès.";
