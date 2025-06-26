<?php
// app/Models/Album.php

class Album
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insère un album Spotify s’il n’existe pas, et renvoie son ID interne.
     */
   public function insertIfNotExistsSpotify(
        ?string $spotifyAlbumId,
        string  $titre,
        int     $artisteId,
        ?string $releaseDate = null,
        ?string $image       = null
    ): int {
        // 1) Normalisation de la date (comme avant)
        if ($releaseDate) {
            if (preg_match('/^\d{4}$/', $releaseDate)) {
                $releaseDate .= '-01-01';
            } elseif (preg_match('/^\d{4}-\d{2}$/', $releaseDate)) {
                $releaseDate .= '-01';
            }
        }

        // 2) Recherche par spotify_album_id si fourni
        if ($spotifyAlbumId) {
            $stmt = $this->pdo->prepare("
                SELECT id
                  FROM albums
                 WHERE spotify_album_id = :s
                 LIMIT 1
            ");
            $stmt->execute([':s' => $spotifyAlbumId]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return (int)$row['id'];
            }
        }

        // 3) Fallback par titre + artiste_id
        $stmt2 = $this->pdo->prepare("
            SELECT id
              FROM albums
             WHERE titre = :t
               AND artiste_id = :a
             LIMIT 1
        ");
        $stmt2->execute([
            ':t' => $titre,
            ':a' => $artisteId,
        ]);
        if ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            return (int)$row2['id'];
        }

        // 4) Insert
        $ins = $this->pdo->prepare("
            INSERT INTO albums
                (spotify_album_id, titre, artiste_id, release_date, image)
            VALUES
                (:s, :t, :a, :r, :i)
        ");
        $ins->execute([
            ':s' => $spotifyAlbumId,
            ':t' => $titre,
            ':a' => $artisteId,
            ':r' => $releaseDate,
            ':i' => $image,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
    public function getAll(): array
    {
        return $this->pdo
            ->query("SELECT * FROM albums ORDER BY release_date DESC, titre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM albums WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
