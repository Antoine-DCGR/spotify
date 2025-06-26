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
        ?string $spotifyTrackId,
        string  $titre,
        int     $dureeMs,
        string  $type,
        ?string $idYoutube,
        ?string $audioPath,
        ?string $genre,
        ?int    $albumId
    ): int {
        // 1) Si on a un Spotify ID, on tente la recherche dessus
        if ($spotifyTrackId !== null) {
            $stmt = $this->pdo->prepare("
                SELECT id 
                  FROM musiques 
                 WHERE spotify_track_id = :s
                 LIMIT 1
            ");
            $stmt->execute([':s' => $spotifyTrackId]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return (int)$row['id'];
            }
        }

        // 2) Fallback : recherche par titre + id_youtube (pour manuel)
        if ($idYoutube !== null) {
            $stmt2 = $this->pdo->prepare("
                SELECT id
                  FROM musiques
                 WHERE titre = :t
                   AND id_youtube = :y
                 LIMIT 1
            ");
            $stmt2->execute([
                ':t' => $titre,
                ':y' => $idYoutube,
            ]);
            if ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                return (int)$row2['id'];
            }
        }

        // 3) Aucun trouvée → insert
        $ins = $this->pdo->prepare("
            INSERT INTO musiques 
              (spotify_track_id, titre, duree_ms, type, id_youtube, audio_path, genre, album_id)
            VALUES 
              (:s, :t, :d, :ty, :y, :a, :g, :al)
        ");
        $ins->execute([
            ':s'  => $spotifyTrackId,
            ':t'  => $titre,
            ':d'  => $dureeMs,
            ':ty' => $type,
            ':y'  => $idYoutube,
            ':a'  => $audioPath,
            ':g'  => $genre,
            ':al' => $albumId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
    public function insertMusicArtistRelation(int $musiqueId, int $artisteId, string $role = 'principal'): void
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO musique_artiste
                (musique_id, artiste_id, role)
            VALUES
                (:m, :a, :r)
        ");
        $stmt->execute([
            ':m' => $musiqueId,
            ':a' => $artisteId,
            ':r' => $role,
        ]);
    }

    /**
     * Crée la relation entre playlist et musique.
     */
    public function insertPlaylistMusicRelation(int $musiqueId, string $playlistSpotifyId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO playlist_musique
                (playlist_spotify_id, musique_id)
            VALUES
                (:p, :m)
        ");
        $stmt->execute([
            ':p' => $playlistSpotifyId,
            ':m' => $musiqueId,
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
    $sql = "
        SELECT
            m.id,
            m.titre,
            ANY_VALUE(a.nom)    AS artiste,
            ANY_VALUE(al.titre) AS album,
            m.duree_ms,
            m.type,
            m.genre
          FROM musiques m
     LEFT JOIN musique_artiste ma ON ma.musique_id = m.id
     LEFT JOIN artistes      a  ON a.id          = ma.artiste_id
     LEFT JOIN albums        al ON al.id         = m.album_id
         WHERE m.id_youtube IS NULL
      GROUP BY m.id
      ORDER BY m.id ASC
      LIMIT :offset, :limit
    ";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
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
              LIMIT :lim OFFSET :off"
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
    public function getAllWithoutAudio(): int
    {
    $stmt = $this->pdo->prepare(
        "SELECT COUNT(*) as total 
         FROM musiques 
         WHERE id_youtube IS NOT NULL 
         AND audio_path IS NULL"
    );
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($result['total']) ? (int)$result['total'] : 0;
}

    public function getPaginatedTasks(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(""
            SELECT * FROM musiques
            WHERE audio_path IS NULL
            AND youtube_id IS NOT NULL
            LIMIT :limit OFFSET :offset
        "");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

}
