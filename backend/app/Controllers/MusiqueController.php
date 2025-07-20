<?php
// app/Controllers/MusiqueController.php

require_once __DIR__ . '/../Models/Musique.php';
require_once __DIR__ . '/../Models/Album.php';
require_once __DIR__ . '/../Models/Artiste.php';
require_once __DIR__ . '/../Models/SpotifyModel.php';
require_once __DIR__ . '/../Services/SpotifyService.php';
require_once __DIR__ . '/../Services/Jwt.php';

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
        $this->spotifyService  = new SpotifyService($pdo);
        $this->musiqueModel    = new Musique($pdo);
        $this->artisteModel    = new Artiste($pdo);
        $this->albumModel      = new Album($pdo);
    }

    /**
     * POST /api/musique/fetchAndStoreMusicsFromSpotify
     */
    public function fetchAndStoreMusicsFromSpotify(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

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

        $input = json_decode(file_get_contents('php://input'), true);
        $playlistSpotifyId = $input['playlistId'] ?? null;
        if (!$playlistSpotifyId) {
            http_response_code(400);
            echo json_encode(['error' => 'playlistId manquant']);
            return;
        }

        $spotifyModel = new SpotifyModel($this->pdo);
        $tokens = $spotifyModel->getTokenByUserId((int)$userId);
        if (!$tokens || empty($tokens['access_token'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Token Spotify introuvable']);
            return;
        }
        $token = $tokens['access_token'];

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
            if (!$res) break;

            $json = json_decode($res, true);
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

        $allArtistSpotifyIds = [];
        foreach ($tracks as $t) {
            foreach ($t['artists'] ?? [] as $art) {
                if (!empty($art['id'])) {
                    $allArtistSpotifyIds[$art['id']] = true;
                }
            }
        }
        $allArtistSpotifyIds = array_keys($allArtistSpotifyIds);

        try {
            $artistsInfo = $this->spotifyService->getArtistsInfo($token, $allArtistSpotifyIds);
        } catch (Exception $e) {
            http_response_code(503);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stored = [];
        foreach ($tracks as $t) {
            if (empty($t['id']) || empty($t['name'])) continue;

            $artistIds = [];
            $trackGenres = [];
            foreach ($t['artists'] as $art) {
                if (empty($art['id']) || empty($art['name'])) continue;
                $aid = $this->artisteModel->insertIfNotExistsSpotify($art['id'], $art['name']);
                $artistIds[] = $aid;
                $trackGenres = array_merge($trackGenres, $artistsInfo[$art['id']]['genres'] ?? []);
            }
            $genreString = $trackGenres ? implode(',', array_unique($trackGenres)) : null;

            $albumId = null;
            if (!empty($t['album']['id']) && !empty($artistIds[0])) {
                $albInfo = $t['album'];
                $albumId = $this->albumModel->insertIfNotExistsSpotify(
                    $albInfo['id'],
                    $albInfo['name'] ?? '',
                    $artistIds[0],
                    $albInfo['release_date'] ?? null,
                    $albInfo['images'][0]['url'] ?? null
                );
            }

            $musiqueId = $this->musiqueModel->insertIfNotExists(
                $t['id'],
                $t['name'],
                $t['duration_ms'] ?? 0,
                $t['type'] ?? 'track',
                null,
                null,
                $genreString,
                $albumId
            );

            foreach ($artistIds as $aid) {
                $this->musiqueModel->insertMusicArtistRelation($musiqueId, $aid);
            }

            $this->musiqueModel->insertPlaylistMusicRelation($musiqueId, $playlistSpotifyId);

            $stored[] = [
                'id'    => $musiqueId,
                'titre' => $t['name'],
                'genre' => $genreString,
            ];
        }

        echo json_encode(['stored' => $stored], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/musique/musiqueByPlaylist?playlistId=...
     */
    public function musiqueByPlaylist($spotify_id): void
    {
        if (!$spotify_id) {
            http_response_code(400);
            echo json_encode(['error' => 'spotify_id manquant'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $tracks = $this->musiqueModel->getByPlaylistSpotify($spotify_id);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['musique' => $tracks], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/musique/allMusique
     */
    public function allMusique(): void
    {
        $all = $this->musiqueModel->getAll();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['musique' => $all], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/musique/show?id=...
     */
    public function show(): void
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id manquant'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $musique = $this->musiqueModel->findById((int)$id);
        header('Content-Type: application/json; charset=utf-8');
        if ($musique) {
            echo json_encode(['musique' => $musique], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Musique non trouvée'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST /api/musique/fetchAndStoreMusicsAllPlaylistsFromSpotify
     */
    public function fetchAndStoreMusicsAllPlaylistsFromSpotify(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');

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

    $spotifyModel = new SpotifyModel($this->pdo);
    $tokens = $spotifyModel->getTokenByUserId((int)$userId);
    if (!$tokens || empty($tokens['access_token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Token Spotify introuvable']);
        return;
    }
    $token = $tokens['access_token'];

    $playlists = $this->pdo->query("SELECT spotify_id, nom FROM spotify_playlists")->fetchAll(PDO::FETCH_ASSOC);
    $results = [];

    foreach ($playlists as $playlist) {
        $playlistSpotifyId = $playlist['spotify_id'];
        $playlistName = $playlist['nom'];
        $url = "https://api.spotify.com/v1/playlists/{$playlistSpotifyId}/tracks?limit=100";
        $page = 0;
        $maxPages = 50;
        $tracks = [];

        while ($url && $page++ < $maxPages) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer {$token}\r\n"
                ]
            ]);
            $res = @file_get_contents($url, false, $ctx);
            if (!$res) break;

            $json = json_decode($res, true);
            foreach ($json['items'] ?? [] as $item) {
                if (!empty($item['track']) && is_array($item['track'])) {
                    $tracks[] = $item['track'];
                }
            }
            $url = $json['next'] ?? null;
        }

        // Collecte de tous les artist IDs pour récupération groupée des genres
        $allArtistSpotifyIds = [];
        foreach ($tracks as $t) {
            foreach ($t['artists'] ?? [] as $art) {
                if (!empty($art['id'])) {
                    $allArtistSpotifyIds[$art['id']] = true;
                }
            }
        }
        $allArtistSpotifyIds = array_keys($allArtistSpotifyIds);

        try {
            $artistsInfo = $this->spotifyService->getArtistsInfo($token, $allArtistSpotifyIds);
        } catch (Exception $e) {
            error_log("Erreur Spotify Artists : " . $e->getMessage());
            continue;
        }

        $stored = [];

        foreach ($tracks as $t) {
            if (empty($t['id']) || empty($t['name'])) continue;

            // ARTISTES
            $artistIds = [];
            $trackGenres = [];
            foreach ($t['artists'] ?? [] as $art) {
                if (empty($art['id']) || empty($art['name'])) continue;
                $aid = $this->artisteModel->insertIfNotExistsSpotify($art['id'], $art['name']);
                $artistIds[] = $aid;
                $trackGenres = array_merge($trackGenres, $artistsInfo[$art['id']]['genres'] ?? []);
            }
            $genreString = $trackGenres ? implode(',', array_unique($trackGenres)) : null;

            // ALBUM
            $albumId = null;
            if (!empty($t['album']['id']) && !empty($artistIds[0])) {
                $albInfo = $t['album'];
                $albumId = $this->albumModel->insertIfNotExistsSpotify(
                    $albInfo['id'],
                    $albInfo['name'] ?? '',
                    $artistIds[0],
                    $albInfo['release_date'] ?? null,
                    $albInfo['images'][0]['url'] ?? null
                );
            }

            // MUSIQUE
            $musiqueId = $this->musiqueModel->insertIfNotExists(
                $t['id'],
                $t['name'],
                $t['duration_ms'] ?? 0,
                $t['type'] ?? 'track',
                null,
                null,
                $genreString,
                $albumId
            );

            foreach ($artistIds as $aid) {
                $this->musiqueModel->insertMusicArtistRelation($musiqueId, $aid);
            }

            // RELATION PLAYLIST-MUSIQUE
            $this->musiqueModel->insertPlaylistMusicRelation($musiqueId, $playlistSpotifyId);

            $stored[] = [
                'id'    => $musiqueId,
                'titre' => $t['name'],
                'genre' => $genreString,
            ];
        }

        $results[] = [
            'playlist'     => $playlistName,
            'playlist_id'  => $playlistSpotifyId,
            'nb_musiques'  => count($stored),
        ];
    }

    echo json_encode([
        'message' => 'Musiques synchronisées avec artistes, albums et genres',
        'resultats' => $results
    ], JSON_UNESCAPED_UNICODE);
}}