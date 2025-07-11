<?php
// app/Controllers/PlaylistController.php

require_once __DIR__ . '/../Models/Playlist.php';
require_once __DIR__ . '/../Models/SpotifyModel.php';
require_once __DIR__ . '/../Services/Jwt.php';

class PlaylistController
{
    private $pdo;
    private $playlistModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->playlistModel = new Playlist($pdo);
    }

    /**
     * Récupère et insère toutes les playlists Spotify en base (auth via JWT).
     * URL : POST /api/playlists/fetchAndStoreFromSpotify
     */
    public function fetchAndStoreFromSpotify(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        // 1) Vérifie JWT
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

        // 2) Récupère access_token depuis la BDD
        $spotifyModel = new SpotifyModel($this->pdo);
        $tokens = $spotifyModel->getTokenByUserId((int) $userId);
        if (!$tokens || empty($tokens['access_token'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Token Spotify introuvable']);
            return;
        }

        $this->fetchAndStoreFromSpotifyWithToken($tokens['access_token']);
    }

    /**
     * Renvoie en JSON toutes les playlists stockées.
     * URL : GET ?page=allPlaylists
     */
    public function getPlaylists()
    {
        $all = $this->playlistModel->getPlaylists();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['playlists' => $all], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ancienne logique inchangée, extraite dans une méthode privée.
     */
    private function fetchAndStoreFromSpotifyWithToken(string $accessToken): void
    {
        $existing = [];
        $stmt = $this->pdo->query("SELECT spotify_id FROM spotify_playlists");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[$row['spotify_id']] = true;
        }

        $insertStmt = $this->pdo->prepare("
            INSERT IGNORE INTO spotify_playlists
              (spotify_id, nom, description, owner, image, tracks_count, is_public, created_at)
            VALUES
              (:spotify_id, :nom, :description, :owner, :image, :tracks_count, :is_public, NOW())
        ");

        $total = 0;
        $inserted = 0;
        $url = "https://api.spotify.com/v1/me/playlists?limit=50";

        while ($url) {
            $opts = [
                'http' => [
                    'method'  => 'GET',
                    'header'  => "Authorization: Bearer $accessToken\r\n"
                               . "Content-Type: application/json\r\n"
                ],
            ];
            $ctx = stream_context_create($opts);
            $res = @file_get_contents($url, false, $ctx);
            if ($res === false) {
                http_response_code(500);
                echo json_encode(['error'=>'Erreur API Spotify']);
                return;
            }

            $data = json_decode($res, true);
            if (isset($data['error'])) {
                http_response_code($data['error']['status'] ?? 500);
                echo json_encode(['error'=>$data['error']['message']]);
                return;
            }

            foreach ($data['items'] as $item) {
                if (!isset($item['id'], $item['name'], $item['owner']['display_name'], $item['tracks']['total'])) {
                    continue;
                }
                $total++;
                $sid   = $item['id'];
                if (isset($existing[$sid])) {
                    continue;
                }
                $nom   = $item['name'];
                $descr = $item['description'] ?? null;
                $owner = $item['owner']['display_name'] ?? $item['owner']['id'];
                $img   = $item['images'][0]['url'] ?? null;
                $count = intval($item['tracks']['total']);
                $pub   = isset($item['public']) && $item['public'] ? 1 : 0;

                $insertStmt->execute([
                    ':spotify_id'   => $sid,
                    ':nom'          => $nom,
                    ':description'  => $descr,
                    ':owner'        => $owner,
                    ':image'        => $img,
                    ':tracks_count' => $count,
                    ':is_public'    => $pub
                ]);
                if ($insertStmt->rowCount() > 0) {
                    $existing[$sid] = true;
                    $inserted++;
                }
            }

            $url = $data['next'] ?? null;
        }

        echo json_encode([
            'message'         => 'Playlists synchronisées',
            'total'           => $total,
            'inserted_new'    => $inserted
        ], JSON_UNESCAPED_UNICODE);
    }
}
