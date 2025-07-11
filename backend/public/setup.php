<?php
// setup.php

require_once __DIR__ . '/config/database.php';
$pdo = getDatabaseConnection();

// 1) Table `spotify_playlists`
$pdo->exec("
  CREATE TABLE IF NOT EXISTS `spotify_playlists` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `spotify_id` VARCHAR(50) NOT NULL,
    `nom` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `owner` VARCHAR(200) NOT NULL,
    `image` VARCHAR(500),
    `tracks_count` INT UNSIGNED NOT NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_spotify_id` (`spotify_id`)
  ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;
");

// 2) Table `artistes`
$pdo->exec("
  CREATE TABLE IF NOT EXISTS `artistes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `spotify_artist_id` VARCHAR(100) DEFAULT NULL,
    `nom` VARCHAR(255) DEFAULT NULL,
    `genre` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_artistes_spotify` (`spotify_artist_id`),
    UNIQUE KEY `uq_artistes_nom` (`nom`)
  ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;
");

// 3) Table `albums`
$pdo->exec("
  CREATE TABLE IF NOT EXISTS `albums` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `spotify_album_id` VARCHAR(100) DEFAULT NULL,
    `titre` VARCHAR(255) NOT NULL,
    `artiste_id` INT UNSIGNED NOT NULL,
    `release_date` DATE DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_albums_spotify` (`spotify_album_id`),
    UNIQUE KEY `uq_albums_titre_artiste` (`titre`, `artiste_id`),
    CONSTRAINT `fk_albums_artiste`
      FOREIGN KEY (`artiste_id`)
      REFERENCES `artistes` (`id`)
      ON UPDATE CASCADE
      ON DELETE CASCADE
  ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;
");

// 4) Table `musiques` (sans artiste_id direct)
$pdo->exec("
  CREATE TABLE IF NOT EXISTS `musiques` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `spotify_track_id` VARCHAR(100) DEFAULT NULL,
    `titre` VARCHAR(255) NOT NULL,
    `duree_ms` INT NOT NULL,
    `type` ENUM('track','episode') NOT NULL DEFAULT 'track',
    `id_youtube` VARCHAR(255) DEFAULT NULL,
    `audio_path` VARCHAR(255) DEFAULT NULL,
    `album_id` INT UNSIGNED DEFAULT NULL,
    `genre` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_spotify_track_id` (`spotify_track_id`),
    CONSTRAINT `fk_musiques_albums`
      FOREIGN KEY (`album_id`)
      REFERENCES `albums` (`id`)
      ON UPDATE CASCADE
      ON DELETE SET NULL
  ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;
");

// 5) Table de jonction N↔N `musique_artiste`
$pdo->exec("
  CREATE TABLE IF NOT EXISTS `musique_artiste` (
    `musique_id` INT UNSIGNED NOT NULL,
    `artiste_id` INT UNSIGNED NOT NULL,
    `role` VARCHAR(100) DEFAULT NULL,  -- ex. 'principal', 'featuring'
    `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`musique_id`, `artiste_id`),
    CONSTRAINT `fk_musique_artiste_musique`
      FOREIGN KEY (`musique_id`)
      REFERENCES `musiques` (`id`)
      ON UPDATE CASCADE
      ON DELETE CASCADE,
    CONSTRAINT `fk_musique_artiste_artiste`
      FOREIGN KEY (`artiste_id`)
      REFERENCES `artistes` (`id`)
      ON UPDATE CASCADE
      ON DELETE CASCADE
  ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;
");

// 6) Table `playlist_musique`
$pdo->exec("
  CREATE TABLE IF NOT EXISTS `playlist_musique` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `playlist_spotify_id` VARCHAR(50) NOT NULL,
    `musique_id` INT UNSIGNED NOT NULL,
    `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_playlist_musique` (`playlist_spotify_id`, `musique_id`),
    CONSTRAINT `fk_playlist_musique_playlist`
      FOREIGN KEY (`playlist_spotify_id`)
      REFERENCES `spotify_playlists` (`spotify_id`)
      ON UPDATE CASCADE
      ON DELETE CASCADE,
    CONSTRAINT `fk_playlist_musique_musique`
      FOREIGN KEY (`musique_id`)
      REFERENCES `musiques` (`id`)
      ON UPDATE CASCADE
      ON DELETE CASCADE
  ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS `oauth_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` varchar(100) NOT NULL,
    `access_token` TEXT NOT NULL,
    `refresh_token` TEXT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    INDEX (`expires_at`)
  ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci;
");

echo "Base prête : tables `spotify_playlists`, `artistes`, `albums`, `musiques`, `musique_artiste` et `playlist_musique` créées en utf8mb4.\n";
