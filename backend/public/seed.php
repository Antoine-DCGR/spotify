<?php
require_once 'config/database.php';

// Création d'une playlist
$pdo->exec("INSERT INTO playlist (nom) VALUES ('Playlist Chill')");
$playlistId = $pdo->lastInsertId();

// Ajout de 2 musiques à la playlist
$pdo->exec("INSERT INTO musique (artiste, album, titre, playlist_id) VALUES
    ('Orelsan', 'Civilisation', 'La Quête', $playlistId),
    ('Nekfeu', 'Les étoiles vagabondes', 'Sous les nuages', $playlistId)
");

echo "Playlist et musiques ajoutées.";
