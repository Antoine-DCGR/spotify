<?php
// app/Models/Artiste.php

class Artiste
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insère un artiste Spotify s’il n’existe pas, et renvoie son ID interne.
     */
 
    public function insertIfNotExistsSpotify(?string $spotifyArtistId, string $nom): int
    {
        if ($spotifyArtistId) {
            // 1) Recherche par spotify_artist_id
            $stmt = $this->pdo->prepare("
                SELECT id
                  FROM artistes
                 WHERE spotify_artist_id = :s
                 LIMIT 1
            ");
            $stmt->execute([':s' => $spotifyArtistId]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return (int)$row['id'];
            }
        }

        // 2) Si pas trouvé (ou pas d'ID), recherche par nom
        $stmt2 = $this->pdo->prepare("
            SELECT id
              FROM artistes
             WHERE nom = :n
             LIMIT 1
        ");
        $stmt2->execute([':n' => $nom]);
        if ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            return (int)$row2['id'];
        }

        // 3) Insert
        $ins = $this->pdo->prepare("
            INSERT INTO artistes
                (spotify_artist_id, nom)
            VALUES
                (:s, :n)
        ");
        $ins->execute([
            ':s' => $spotifyArtistId,
            ':n' => $nom,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getAll(): array
    {
        return $this->pdo
            ->query("SELECT * FROM artistes ORDER BY nom")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM artistes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
