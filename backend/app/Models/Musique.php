<?php
// app/Models/Musique.php

class Musique
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère toutes les musiques locales stockées en base.
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM musiques");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les musiques correspondant à l'ID Spotify d'une playlist.
     * Ajustez la requête selon votre schéma de tables.
     */
    public function getByPlaylistSpotify(string $playlistSpotifyId): array
    {
        // Exemple : la table 'musiques' contient un champ 'playlist_spotify_id'
        $stmt = $this->pdo->prepare("
            SELECT * 
            FROM musiques 
            WHERE playlist_spotify_id = ?
        ");
        $stmt->execute([$playlistSpotifyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

        /*
        // Si vous utilisez une table pivot 'playlist_musique', décommentez et adaptez :
        $stmt = $this->pdo->prepare("
            SELECT m.* 
            FROM musiques m
            JOIN playlist_musique pm 
              ON m.id = pm.musique_id
            WHERE pm.playlist_spotify_id = ?
        ");
        $stmt->execute([$playlistSpotifyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        */
    }
}
