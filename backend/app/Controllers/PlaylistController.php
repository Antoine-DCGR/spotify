<?php
// app/Controllers/PlaylistController.php

require_once __DIR__ . '/../Models/Playlist.php';

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
     * Récupère et insère toutes les playlists Spotify en base,
     * en paginant et en minimisant les requêtes SQL.
     * URL : ?page=fetchPlaylists
     */
    public function fetchAndStoreFromSpotify()
    {
        session_start();
        $accessToken = $_SESSION['spotify_access_token'] ?? null;
        if (!$accessToken) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Token Spotify manquant']);
            return;
        }

        // 1) Précharger en mémoire tous les spotify_id existants
        $existing = [];
        $stmt = $this->pdo->query("SELECT spotify_id FROM spotify_playlists");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[$row['spotify_id']] = true;
        }

        // 2) Préparer le INSERT IGNORE
        $insertStmt = $this->pdo->prepare("
            INSERT IGNORE INTO spotify_playlists
              (spotify_id, nom, description, owner, image, tracks_count, is_public, created_at)
            VALUES
              (:spotify_id, :nom, :description, :owner, :image, :tracks_count, :is_public, NOW())
        ");

        // 3) Paginer Spotify /v1/me/playlists
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

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'message'         => 'Playlists synchronisées',
            'total'           => $total,
            'inserted_new'    => $inserted
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Renvoie en JSON toutes les playlists stockées.
     * URL : ?page=allPlaylists
     */
    public function getPlaylists()
    {
        $all = $this->playlistModel->getPlaylists();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['playlists' => $all], JSON_UNESCAPED_UNICODE);
    }

    // insertIfNotExistsSpotify() reste inchangé pour usage ponctuel si besoin
}
