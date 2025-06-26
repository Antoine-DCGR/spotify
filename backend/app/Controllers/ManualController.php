<?php
// app/Controllers/ManualTrackController.php
require_once __DIR__ . '/../Models/Musique.php';
require_once __DIR__ . '/../Models/Album.php';
require_once __DIR__ . '/../Models/Artiste.php';
require_once __DIR__ . '/../Models/Playlist.php';
require_once __DIR__ . '/../Services/AudioDownloadService.php';
require_once __DIR__ . '/../Services/YoutubeService.php';


class ManualController
{
    private PDO $pdo;
    private Musique $musiqueModel;
    private Artiste $artisteModel;
    private Album $albumModel;
    private Playlist $playlistModel;
    private AudioDownloadService $audioSvc;

    public function __construct(PDO $pdo)
    {
        $this->pdo           = $pdo;
        $this->musiqueModel  = new Musique($pdo);
        $this->artisteModel  = new Artiste($pdo);
        $this->albumModel    = new Album($pdo);
        $this->playlistModel = new Playlist($pdo);
        $this->audioSvc      = new AudioDownloadService($pdo);
    }

    /**
     * GET /?action=showAddForm
     */
    public function showAddForm(): void
    {
        $playlists = $this->playlistModel->getAllSpotify();
        include __DIR__ . '/../views/add_manual_track.php';
    }

    /**
     * POST /?action=addManual
     */
    public function addManual(array $post): void
    {
        // 1) Validation
        if (empty($post['titre']) || empty($post['artistes']) || empty($post['playlists'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Titre, artiste(s) et playlist(s) requis'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 2) Rechercher videoId sur YouTube
        $yt       = new YouTubeService();
        $query    = $post['titre'] . ' ' . implode(' ', $post['artistes']);
        $videoId  = $yt->searchVideoId($query);
        if (!$videoId) {
            http_response_code(500);
            echo json_encode(['error' => 'Vidéo YouTube introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 3) Créer/récupérer l'album "Single {titre}"
        $albumId = $this->createAlbum($post['titre'], $post['artistes']);

        // 4) Insérer la musique sans audio_path
        $trackId = $this->musiqueModel->insertIfNotExists(
            null,
            trim($post['titre']),
            0,
            'track',
            $videoId,
            null,
            null,
            $albumId
        );

        // 5) Télécharger et sauvegarder le MP3 sous musique_{id}.mp3
        $links    = $this->audioSvc->fetchDownloadLinks($videoId);
        if (empty($links['audio'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Conversion audio impossible'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $data     = @file_get_contents($links['audio']);
        $filename = "musique_{$trackId}.mp3";
        $relPath  = $this->audioSvc->downloadAndSaveFromString($data, $filename);
        if (!$relPath) {
            http_response_code(500);
            echo json_encode(['error' => 'Enregistrement fichier impossible'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 6) Mettre à jour audio_path en base
        $upd = $this->pdo->prepare("
            UPDATE musiques
               SET audio_path = :p
             WHERE id = :id
        ");
        $upd->execute([
            ':p'  => $relPath,
            ':id' => $trackId,
        ]);

        // 7) Lier les artistes
        foreach ($post['artistes'] as $name) {
            $aid = $this->artisteModel->insertIfNotExistsSpotify(null, trim($name));
            $this->musiqueModel->insertMusicArtistRelation($trackId, $aid);
        }

        // 8) Lier les playlists
        foreach ($post['playlists'] as $spid) {
            $this->musiqueModel->insertPlaylistMusicRelation($trackId, $spid);
        }

        // 9) Réponse JSON
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'track_id' => $trackId], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Crée ou récupère un album “Single {titre}”
     */
    private function createAlbum(string $titre, array $artistes): int
    {
        $firstAid = null;
        if (!empty($artistes[0])) {
            $firstAid = $this->artisteModel->insertIfNotExistsSpotify(null, trim($artistes[0]));
        }
        return $this->albumModel->insertIfNotExistsSpotify(
            null,
            'Single ' . $titre,
            $firstAid ?? 0,
            date('Y-m-d'),
            null
        );
    }
}
