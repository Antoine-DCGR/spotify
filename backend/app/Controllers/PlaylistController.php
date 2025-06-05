<?php
// app/Controllers/PlaylistController.php

require_once __DIR__ . '/../Models/Playlist.php';

class PlaylistController
{
    private $playlistModel;

    /**
     * Le constructeur reçoit une instance PDO.
     */
    public function __construct(PDO $pdo)
    {
        $this->playlistModel = new Playlist($pdo);
    }

    /**
     * Récupère toutes les playlists Spotify stockées en base et renvoie en JSON.
     * URL : ?page=recupPlaylists  (ou allPlaylists)
     */
    public function getAllSpotify()
    {
        $all = $this->playlistModel->getAllSpotify();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['playlists' => $all], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Insère une playlist Spotify si elle n'existe pas.
     * URL : ?page=insertPlaylist (méthode POST)
     * POST attendu (x-www-form-urlencoded ou form-data) :
     *  - spotifyId, nom, description (optionnel), owner, imageURL (opt), tracksCount, isPublic
     */
    public function insertIfNotExistsSpotify(
        string $spotifyId = null,
        string $nom = null,
        ?string $description = null,
        string $owner = null,
        ?string $imageURL = null,
        int $tracksCount = 0,
        bool $isPublic = false
    ) {
        if (!$spotifyId || !$nom || !$owner) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Champs requis manquants']);
            return;
        }

        $success = $this->playlistModel->insertIfNotExistsSpotify(
            $spotifyId,
            $nom,
            $description,
            $owner,
            $imageURL,
            $tracksCount,
            $isPublic
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $success
                ? 'Playlist insérée ou déjà existante'
                : 'Erreur lors de l’insertion'
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Récupère toutes les playlists de l’utilisateur connecté via l’API Spotify,
     * les insère (INSERT IGNORE) en base, puis renvoie un JSON de synthèse.
     * URL : ?page=fetchPlaylists
     *
     * Pré-requis : dans SpotifyController::spotifyCallback(), l’access_token doit
     * être stocké en session sous 'spotify_access_token'.
     */
    public function fetchAndStoreFromSpotify()
    {
        // 1) Démarre la session PHP pour récupérer le token
        session_start();
        $accessToken = $_SESSION['spotify_access_token'] ?? null;

        if (!$accessToken) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Token Spotify manquant. Authentifiez-vous via OAuth.']);
            return;
        }

        // 2) Appel à l’API Spotify pour récupérer les playlists de l’utilisateur
        $url = "https://api.spotify.com/v1/me/playlists?limit=50";
        $opts = [
            'http' => [
                'method'  => 'GET',
                'header'  => "Authorization: Bearer $accessToken\r\n" .
                             "Content-Type: application/json\r\n"
            ],
        ];
        $context  = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Erreur lors de l’appel à l’API Spotify.']);
            return;
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            // Par exemple : token expiré ou autre
            http_response_code($data['error']['status'] ?? 500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $data['error']['message']]);
            return;
        }

        // 3) Pour chaque playlist, on l’insère en base via le modèle
        $inserted = [];
        foreach ($data['items'] as $item) {
            $spotifyId   = $item['id'];
            $nom         = $item['name'] ?? '';
            $description = $item['description'] ?? null;
            $owner       = $item['owner']['display_name'] ?? $item['owner']['id'];
            $imageURL    = null;
            if (!empty($item['images'][0]['url'])) {
                $imageURL = $item['images'][0]['url'];
            }
            $tracksCount = intval($item['tracks']['total']);
            $isPublic    = $item['public'] ? true : false;

            $success = $this->playlistModel->insertIfNotExistsSpotify(
                $spotifyId,
                $nom,
                $description,
                $owner,
                $imageURL,
                $tracksCount,
                $isPublic
            );

            $inserted[] = [
                'spotify_id' => $spotifyId,
                'nom'        => $nom,
                'insere'     => $success
            ];
        }

        // 4) Réponse JSON résumant l’opération
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'message'   => 'Récupération et insertion terminées',
            'playlists' => $inserted
        ], JSON_UNESCAPED_UNICODE);
    }
}
