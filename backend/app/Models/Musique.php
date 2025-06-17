<?php
// app/Models/Musique.php

class Musique
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insère une piste ou un épisode si inexistant.
     * @return int ID interne (nouvel ou existant)
     */
    public function insertIfNotExists(
        string $spotifyTrackId,
        string $titre,
        string $artiste,
        string $album,
        int $dureeMs,
        string $type = 'track'
    ): int {
        $stmt = $this->pdo->prepare("
            SELECT id
              FROM musiques
             WHERE spotify_track_id = :track_id
             LIMIT 1
        ");
        $stmt->execute([':track_id' => $spotifyTrackId]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return (int)$row['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO musiques
              (spotify_track_id, titre, artiste, album, duree_ms, type, created_at)
            VALUES
              (:track_id, :titre, :artiste, :album, :duree_ms, :type, NOW())
        ");
        $stmt->execute([
            ':track_id' => $spotifyTrackId,
            ':titre'    => $titre,
            ':artiste'  => $artiste,
            ':album'    => $album,
            ':duree_ms' => $dureeMs,
            ':type'     => $type,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Insère la relation playlist⇆musique (INSERT IGNORE).
     * @return bool
     */
    public function insertPlaylistMusicRelation(
        string $playlistSpotifyId,
        int $musiqueId
    ): bool {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO playlist_musique
              (playlist_spotify_id, musique_id, added_at)
            VALUES
              (:playlist_id, :musique_id, NOW())
        ");
        return $stmt->execute([
            ':playlist_id' => $playlistSpotifyId,
            ':musique_id'  => $musiqueId,
        ]);
    }

    /**
     * Récupère toutes les musiques/épisodes d’une playlist (pivot join).
     * @return array
     */
    public function getByPlaylistSpotify(string $playlistSpotifyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*
              FROM musiques m
              JOIN playlist_musique pm
                ON m.id = pm.musique_id
             WHERE pm.playlist_spotify_id = :playlist_id
        ");
        $stmt->execute([':playlist_id' => $playlistSpotifyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les musiques/épisodes stockés (global).
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM musiques");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour l'ID YouTube associé à une musique
     * @return bool Succès de la mise à jour
     */
    public function updateYoutubeVideoId(int $id, string $videoId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE musiques
               SET id_youtube = :videoId
             WHERE id = :id
        ");
        return $stmt->execute([
            ':videoId' => $videoId,
            ':id'      => $id,
        ]);
    }

    /**
     * Récupère une musique par son ID interne
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM musiques WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Récupère toutes les musiques sans ID YouTube
     * @return array[] ['id','titre','artiste']
     */
    public function getAllWithoutYoutube(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, titre, artiste
              FROM musiques
             WHERE id_youtube IS NULL
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compte le nombre de musiques sans ID YouTube
     * @return int
     */
    public function countWithoutYoutube(): int
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM musiques WHERE id_youtube IS NULL
        ");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Récupère un lot paginé de musiques sans ID YouTube
     * @param int $offset
     * @param int $limit
     * @return array[] ['id','titre','artiste']
     */
    public function getWithoutYoutubePaginated(int $offset, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, titre, artiste
              FROM musiques
             WHERE id_youtube IS NULL
             LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Met à jour le chemin du fichier audio pour une musique.
     */
    public function updateAudioPath(int $id, string $path): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE musiques
            SET audio_path = :path
            WHERE id = :id
        ");
        return $stmt->execute([
            ':path' => $path,
            ':id'   => $id,
        ]);
    }
    public function findByYoutubeId(string $yt): ?array
{
    $stmt = $this->pdo->prepare("
        SELECT * 
        FROM musiques 
        WHERE id_youtube = :yt
        LIMIT 1
    ");
    $stmt->execute([':yt' => $yt]);
    $row = $stmt->fetch();
    return $row ?: null;
}
/**
 * Récupère toutes les musiques ayant un id_youtube renseigné
 * et un audio_path à NULL.
 *
 * @return array<int,array{id:int,id_youtube:string}>
 */
 public function getWithoutAudioPaginated(int $offset, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, id_youtube
               FROM musiques
              WHERE id_youtube IS NOT NULL
                AND audio_path IS NULL
              ORDER BY id
              LIMIT :off, :lim"
        );
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
public function countWithoutAudio(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) 
               FROM musiques 
              WHERE id_youtube IS NOT NULL 
                AND audio_path IS NULL"
        );
        return (int)$stmt->fetchColumn();
    }
     public function updateYoutubeVideoId(int $id, string $videoId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE musiques
               SET id_youtube = :vid
             WHERE id = :id
        ");
        return $stmt->execute([
            ':vid' => $videoId,
            ':id'  => $id,
        ]);
    }

    /**
     * Récupère toutes les musiques avec un YouTube ID
     * et sans audio_path (à télécharger).
     *
     * @return array<int,array{id:int,id_youtube:string}>
     */
    public function getAllWithoutAudio(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, id_youtube
              FROM musiques
             WHERE id_youtube IS NOT NULL
               AND audio_path IS NULL
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

}
