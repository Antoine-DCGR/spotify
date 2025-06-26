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
     * Insère une playlist Spotify si elle n'existe pas déjà.
     * Utilisé ponctuellement (POST) ; en mode fetch, on utilise INSERT IGNORE préparé dans le contrôleur.
     *
     * @return bool
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
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO spotify_playlists
              (spotify_id, nom, description, owner, image, tracks_count, is_public, created_at)
            VALUES
              (:spotify_id, :nom, :description, :owner, :image, :tracks_count, :is_public, NOW())
        ");
        return $stmt->execute([
            ':spotify_id'   => $spotifyId,
            ':nom'          => $nom,
            ':description'  => $description,
            ':owner'        => $owner,
            ':image'        => $imageURL,
            ':tracks_count' => $tracksCount,
            ':is_public'    => $isPublic ? 1 : 0,
        ]);
    }

    /**
     * Récupère toutes les playlists stockées en base.
     *
     * @return array
     */
    public function getPlaylists(): array
    {
        $stmt = $this->pdo->query("
            SELECT spotify_id, nom, description, owner, image, tracks_count, is_public, created_at
            FROM spotify_playlists
            ORDER BY created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
