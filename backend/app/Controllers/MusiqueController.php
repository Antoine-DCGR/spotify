<?php
// app/Controllers/MusiqueController.php

require_once __DIR__ . '/../Models/Musique.php';

class MusiqueController
{
    private $pdo;
    private $musiqueModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->musiqueModel = new Musique($pdo);
    }

    /**
     * Récupère + insère toutes les pistes (musiques ET épisodes) d’une playlist Spotify,
     * en gérant la pagination, la précharge des IDs et les requêtes préparées.
     * URL : ?page=fetchMusics&playlistId=...
     */
    public function fetchAndStoreMusicsFromSpotify(string $playlistSpotifyId = null)
    {
        if (!$playlistSpotifyId) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'playlistId manquant']);
            return;
        }
        session_start();
        $token = $_SESSION['spotify_access_token'] ?? null;
        if (!$token) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Token Spotify manquant, connectez-vous en OAuth.']);
            return;
        }

        // 1) Précharger tous les spotify_track_id déjà en base
        $existing = [];
        $stmt = $this->pdo->query("SELECT spotify_track_id, id FROM musiques");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[$row['spotify_track_id']] = (int)$row['id'];
        }

        // 2) Préparer les requêtes INSERT
        $insertTrack = $this->pdo->prepare("
            INSERT INTO musiques
              (spotify_track_id, titre, artiste, album, duree_ms, type, created_at)
            VALUES
              (:id, :titre, :artiste, :album, :duree, :type, NOW())
        ");
        $insertPivot = $this->pdo->prepare("
            INSERT IGNORE INTO playlist_musique
              (playlist_spotify_id, musique_id, added_at)
            VALUES
              (:playlist, :music, NOW())
        ");

        // 3) Pagination Spotify
        $total = $newTracks = $newRels = 0;
        $url = "https://api.spotify.com/v1/playlists/"
             . urlencode($playlistSpotifyId)
             . "/tracks?limit=100";

        while ($url) {
            $opts = ['http' => [
                'method'  => 'GET',
                'header'  => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n"
            ]];
            $res = @file_get_contents($url, false, stream_context_create($opts));
            if ($res === false) {
                http_response_code(500);
                echo json_encode(['error' => 'Erreur API Spotify']);
                return;
            }
            $data = json_decode($res, true);
            if (isset($data['error'])) {
                http_response_code($data['error']['status'] ?? 500);
                echo json_encode(['error' => $data['error']['message']]);
                return;
            }

            foreach ($data['items'] as $item) {
                if (empty($item['track']['id']) || empty($item['track']['type'])) {
                    continue;
                }
                $t       = $item['track'];
                $sid     = $t['id'];
                $type    = $t['type'];
                $titre   = $t['name'] ?? '';
                // --- Gestion multiple d’artistes pour les tracks ---
                if ($type === 'track') {
                    // Récupère tous les noms d'artistes et les joint par ", "
                    $names = array_map(fn($a) => $a['name'], $t['artists']);
                    $artiste = implode(', ', $names);
                    $album   = $t['album']['name'] ?? '';
                    $dur     = intval($t['duration_ms'] ?? 0);
                } else {
                    // Épisode de podcast
                    $artiste = $t['show']['publisher'] 
                               ?? ($t['show']['name'] ?? '');
                    $album   = $t['show']['name'] ?? '';
                    $dur     = intval($t['duration_ms'] ?? 0);
                }

                $total++;
                // INSERT piste si nouvelle
                if (isset($existing[$sid])) {
                    $mid = $existing[$sid];
                } else {
                    $insertTrack->execute([
                        ':id'      => $sid,
                        ':titre'   => $titre,
                        ':artiste' => $artiste,
                        ':album'   => $album,
                        ':duree'   => $dur,
                        ':type'    => $type
                    ]);
                    $mid = (int)$this->pdo->lastInsertId();
                    $existing[$sid] = $mid;
                    $newTracks++;
                }
                // INSERT pivot playlist⇆musique
                $insertPivot->execute([
                    ':playlist' => $playlistSpotifyId,
                    ':music'    => $mid
                ]);
                $newRels++;
            }
            // page suivante
            $url = $data['next'] ?? null;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'message'       => 'Synchronisation terminée',
            'playlist'      => $playlistSpotifyId,
            'total_items'   => $total,
            'new_tracks'    => $newTracks,
            'new_relations' => $newRels
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Renvoie en JSON toutes les musiques/épisodes d’une playlist.
     * URL : ?page=musiqueByPlaylist&playlistId=...
     */
    public function musiqueByPlaylist(string $playlistSpotifyId = null)
    {
        if (!$playlistSpotifyId) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'playlistId manquant']);
            return;
        }
        $tracks = $this->musiqueModel->getByPlaylistSpotify($playlistSpotifyId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['musique' => $tracks], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Récupère toutes les musiques/épisodes stockés (global).
     * URL : ?page=allMusique
     */
    public function allMusique()
    {
        $all = $this->musiqueModel->getAll();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['musique' => $all], JSON_UNESCAPED_UNICODE);
    }
    public function show(int $id): void
{
    header('Content-Type: application/json; charset=utf-8');

    $all = $this->musiqueModel->findById($id);
    echo json_encode(['musique' => $all], JSON_UNESCAPED_UNICODE);

    
}
    
}
