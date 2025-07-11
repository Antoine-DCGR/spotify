<?php
require_once __DIR__ . '/../Models/Playlist.php';
require_once __DIR__ . '/../Models/Musique.php';
require_once __DIR__ . '/../Models/SpotifyModel.php';
require_once __DIR__ . '/../Services/Jwt.php';

class SpotifySyncController
{
    private PDO $pdo;
    private Playlist $playlistModel;
    private Musique $musiqueModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->playlistModel = new Playlist($pdo);
        $this->musiqueModel = new Musique($pdo);
    }

    /**
     * Synchronise toutes les playlists + leurs musiques depuis Spotify
     * POST /api/syncAllFromSpotify
     */
    public function syncAll(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        // Auth via JWT (copie/colle ta logique PlaylistController)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            echo json_encode(['error' => 'Authorization manquant ou invalide']);
            return;
        }

        $jwt = trim(substr($authHeader, 7));
        try {
            $payload = Jwt::decode($jwt, getenv('JWT_SECRET'));
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'JWT invalide : ' . $e->getMessage()]);
            return;
        }

        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id manquant dans le JWT']);
            return;
        }

        // Access token
        $spotifyModel = new SpotifyModel($this->pdo);
        $tokens = $spotifyModel->getTokenByUserId((int) $userId);
        if (!$tokens || empty($tokens['access_token'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Token Spotify introuvable']);
            return;
        }
        $accessToken = $tokens['access_token'];

        // 1. Fetch & store playlists (pagine)
        $playlistIds = [];
        $url = "https://api.spotify.com/v1/me/playlists?limit=50";
        while ($url) {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'header'  => "Authorization: Bearer $accessToken\r\nContent-Type: application/json\r\n"
                ]
            ]);
            $res = @file_get_contents($url, false, $ctx);
            if (!$res) {
                http_response_code(500);
                echo json_encode(['error'=>'Erreur API Spotify']);
                return;
            }
            $data = json_decode($res, true);
            foreach ($data['items'] as $item) {
                // Insère playlist si pas existante (reprends ta logique)
                $sid   = $item['id'];
                $nom   = $item['name'];
                $descr = $item['description'] ?? null;
                $owner = $item['owner']['display_name'] ?? $item['owner']['id'];
                $img   = $item['images'][0]['url'] ?? null;
                $count = intval($item['tracks']['total']);
                $pub   = isset($item['public']) && $item['public'] ? 1 : 0;
                $this->playlistModel->insertIfNotExists(
                    $sid, $nom, $descr, $owner, $img, $count, $pub
                );
                $playlistIds[] = $sid;
            }
            $url = $data['next'] ?? null;
        }

        // 2. Pour chaque playlist, fetch + store musiques (en paginé)
        $nbMusics = 0;
        foreach ($playlistIds as $playlistId) {
            $url = "https://api.spotify.com/v1/playlists/$playlistId/tracks?limit=100";
            $page = 0;
            while ($url && $page++ < 50) {
                $ctx = stream_context_create([
                    'http' => [
                        'method'  => 'GET',
                        'header'  => "Authorization: Bearer $accessToken\r\nContent-Type: application/json\r\n"
                    ]
                ]);
                $res = @file_get_contents($url, false, $ctx);
                if (!$res) break;
                $json = json_decode($res, true);
                foreach ($json['items'] ?? [] as $item) {
                    $track = $item['track'] ?? null;
                    if (!$track || !isset($track['id'], $track['name'])) continue;
                    $this->musiqueModel->insertIfNotExists(
                        $track['id'],
                        $track['name'],
                        $track['duration_ms'] ?? 0,
                        $track['type'] ?? 'track',
                        null, // id_youtube
                        null, // audio_path
                        null, // genre
                        null  // album_id
                    );
                    // + Option : insérer la relation playlist ↔ musique ici
                    $this->musiqueModel->insertPlaylistMusicRelation(
                        $this->musiqueModel->insertIfNotExists(
                            $track['id'],
                            $track['name'],
                            $track['duration_ms'] ?? 0,
                            $track['type'] ?? 'track',
                            null, null, null, null
                        ),
                        $playlistId
                    );
                    $nbMusics++;
                }
                $url = $json['next'] ?? null;
            }
        }

        echo json_encode([
            'message' => 'Sync Spotify terminée',
            'playlists_sync' => count($playlistIds),
            'musiques_sync'  => $nbMusics,
        ]);
    }
}
