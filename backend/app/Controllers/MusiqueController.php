<?php
// app/Controllers/MusiqueController.php

require_once __DIR__ . '/../Models/Musique.php';
require_once __DIR__ . '/../Models/Album.php';
require_once __DIR__ . '/../Models/Artiste.php';
require_once __DIR__ . '/../Services/SpotifyService.php';


class MusiqueController
{
     private PDO $pdo;
    private SpotifyService $spotifyService;
    private Musique $musiqueModel;
    private Artiste $artisteModel;
    private Album $albumModel;

    public function __construct(PDO $pdo)
    {
       $this->pdo             = $pdo;
        $this->spotifyService  = new SpotifyService();
        $this->musiqueModel    = new Musique($pdo);
        $this->artisteModel    = new Artiste($pdo);
        $this->albumModel      = new Album($pdo);
    }

   /**
     * URL : ?action=fetchMusics&playlistId=...
     * Récupère + insère toutes les pistes d’une playlist Spotify,
     * en batchant les appels genres pour éviter les 429.
     */
    public function fetchAndStoreMusicsFromSpotify(?string $playlistSpotifyId = null): void
    {
        if (!$playlistSpotifyId) {
            http_response_code(400);
            echo json_encode(['error' => 'playlistId manquant'], JSON_UNESCAPED_UNICODE);
            return;
        }

        session_start();
        $token = $_SESSION['spotify_access_token'] ?? null;
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Token Spotify manquant'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 1) Récupération paginée des tracks
        $maxPages = 50;
$page     = 0;
$url      = "https://api.spotify.com/v1/playlists/{$playlistSpotifyId}/tracks?limit=100";
$tracks   = [];

while ($url && $page++ < $maxPages) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$token}\r\n"
        ]
    ]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) {
        break;
    }
    $json  = json_decode($res, true);
    foreach ($json['items'] ?? [] as $item) {
        if (!empty($item['track']) && is_array($item['track'])) {
            $tracks[] = $item['track'];
        }
    }
    $url = $json['next'] ?? null;
}

if ($page >= $maxPages) {
    error_log("Arrêt pagination après $maxPages pages pour playlist {$playlistSpotifyId}");
}
        

        // 2) Pré-collecte de tous les artist Spotify IDs
        $allArtistSpotifyIds = [];
        foreach ($tracks as $t) {
            if (!empty($t['artists']) && is_array($t['artists'])) {
                foreach ($t['artists'] as $art) {
                    if (!empty($art['id'])) {
                        $allArtistSpotifyIds[$art['id']] = true;
                    }
                }
            }
        }
        $allArtistSpotifyIds = array_keys($allArtistSpotifyIds);

        // 3) Batch fetch des infos artistes (dont genres)
        try {
            $artistsInfo = $this->spotifyService
                                ->getArtistsInfo($token, $allArtistSpotifyIds);
        } catch (Exception $e) {
            http_response_code(503);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 4) Traitement & insertion
        $stored = [];
        foreach ($tracks as $t) {
            // Vérifications d’usage
            if (empty($t['id']) || empty($t['name'])) {
                continue;
            }

            // 4.1) Sync ARTISTES + collecte des genres
            $artistIds   = [];
            $trackGenres = [];
            foreach ($t['artists'] as $art) {
                if (empty($art['id']) || empty($art['name'])) {
                    continue;
                }
                $aid = $this->artisteModel
                            ->insertIfNotExistsSpotify($art['id'], $art['name']);
                $artistIds[] = $aid;

                $genres = $artistsInfo[$art['id']]['genres'] ?? [];
                $trackGenres = array_merge($trackGenres, $genres);
            }
            $genreString = $trackGenres
                ? implode(',', array_unique($trackGenres))
                : null;

            // 4.2) Sync ALBUM (prend le premier artiste comme owner)
            $albumId = null;
            if (!empty($t['album']['id']) && !empty($artistIds[0])) {
                $albInfo = $t['album'];
                $albumId = $this->albumModel->insertIfNotExistsSpotify(
                    $albInfo['id'],
                    $albInfo['name']            ?? '',
                    $artistIds[0],
                    $albInfo['release_date']    ?? null,
                    $albInfo['images'][0]['url'] ?? null
                );
            }

            // 4.3) Sync MUSIQUE
            $musiqueId = $this->musiqueModel->insertIfNotExists(
                $t['id'],                   // spotify_track_id
                $t['name'],                 // titre
                $t['duration_ms']           ?? 0,
                $t['type']                  ?? 'track',
                null,                       // id_youtube initialement null
                null,                       // audio_path
                $genreString,               // genre agrégé
                $albumId                    // album_id
            );

            // 4.4) Liaison N–N musique↔artistes
            foreach ($artistIds as $aid) {
                $this->musiqueModel
                     ->insertMusicArtistRelation($musiqueId, $aid);
            }

            // 4.5) Liaison playlist↔musique
            $this->musiqueModel
                 ->insertPlaylistMusicRelation($musiqueId, $playlistSpotifyId);

            $stored[] = [
                'id'    => $musiqueId,
                'titre' => $t['name'],
                'genre' => $genreString,
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['stored' => $stored], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /?action=musiqueByPlaylist&playlistId=...
     */
    public function musiqueByPlaylist(?string $playlistSpotifyId = null): void
    {
        if (!$playlistSpotifyId) {
            http_response_code(400);
            echo json_encode(['error' => 'playlistId manquant'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $tracks = $this->musiqueModel->getByPlaylistSpotify($playlistSpotifyId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['musique' => $tracks], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /?action=allMusique
     */
    public function allMusique(): void
    {
        $all = $this->musiqueModel->getAll();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['musique' => $all], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /?action=show&id=...
     */
    public function show(int $id): void
    {
        $musique = $this->musiqueModel->findById($id);
        header('Content-Type: application/json; charset=utf-8');
        if ($musique) {
            echo json_encode(['musique' => $musique], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Musique non trouvée'], JSON_UNESCAPED_UNICODE);
        }
    }
    
}
    

