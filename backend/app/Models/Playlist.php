<?php
// app/Models/Playlist.php

class Playlist
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insère une playlist Spotify en base si elle n'existe pas déjà.
     * Retourne true si l’insertion a eu lieu ou a été ignorée (duplicate), false sinon.
     */
    public function insertIfNotExistsSpotify(
        string $spotifyId,
        string $nom,
        ?string $description,
        string $owner,
        ?string $imageURL,
        int $tracksCount,
        bool $isPublic
    ): bool {
        $sql = "
            INSERT IGNORE INTO spotify_playlists
              (spotify_id, nom, description, owner, image, tracks_count, is_public, created_at)
            VALUES
              (:sid, :nom, :descr, :owner, :img, :count, :pub, NOW())
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':sid'   => $spotifyId,
            ':nom'   => $nom,
            ':descr' => $description,
            ':owner' => $owner,
            ':img'   => $imageURL,
            ':count' => $tracksCount,
            ':pub'   => $isPublic ? 1 : 0,
        ]);
    }

    /**
     * Récupère toutes les playlists Spotify stockées en base, triées par date de création.
     */
    public function getAllSpotify(): array
    {
        $stmt = $this->pdo->query("
            SELECT spotify_id, nom, description, owner, image, tracks_count, is_public, created_at
            FROM spotify_playlists
            ORDER BY created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les données d'une playlist Spotify par son ID Spotify.
     */
    public function findBySpotifyId(string $spotifyId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT spotify_id, nom, description, owner, image, tracks_count, is_public, created_at
            FROM spotify_playlists
            WHERE spotify_id = :sid
            LIMIT 1
        ");
        $stmt->execute([':sid' => $spotifyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
