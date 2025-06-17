<?php
// setup.php

require_once __DIR__ . '/config/database.php';
$pdo = getDatabaseConnection();

// 1) Supprimer
$pdo->exec("DROP TABLE IF EXISTS `playlist_musique`");
$pdo->exec("DROP TABLE IF EXISTS `musiques`");
$pdo->exec("DROP TABLE IF EXISTS `spotify_playlists`");

// 2) Recréation en UTF8MB4
$pdo->exec("
  CREATE TABLE `spotify_playlists` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spotify_id VARCHAR(50) NOT NULL,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    owner VARCHAR(200) NOT NULL,
    image VARCHAR(500),
    tracks_count INT UNSIGNED NOT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_spotify_id (spotify_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
  CREATE TABLE `musiques` (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    spotify_track_id VARCHAR(100) NULL DEFAULT NULL,
    titre VARCHAR(255) NOT NULL,
    artiste VARCHAR(255) NOT NULL,
    album VARCHAR(255) NULL DEFAULT NULL,
    genre VARCHAR(255) NULL DEFAULT NULL,
    duree_ms INT NOT NULL,
    type ENUM('track','episode') NOT NULL DEFAULT 'track',
    id_youtube VARCHAR(20)   DEFAULT NULL,
    audio_path varchar(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_spotify_track_id (spotify_track_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
  CREATE TABLE `playlist_musique` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    playlist_spotify_id VARCHAR(50) NOT NULL,
    musique_id INT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (playlist_spotify_id)
      REFERENCES spotify_playlists(spotify_id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (musique_id)
      REFERENCES musiques(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_playlist_musique (playlist_spotify_id, musique_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

echo "Tables créées en utf8mb4.";
