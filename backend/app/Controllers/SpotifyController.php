<?php
// app/Controllers/SpotifyController.php

declare(strict_types=1);

require_once __DIR__ . '/../Services/SpotifyService.php';

class SpotifyController
{
    private SpotifyService $service;
    private string $redirectUri;

    public function __construct()
    {
        // Instancie le service et récupère l'URI de redirection depuis l'env
        $this->service     = new SpotifyService();
        $this->redirectUri = getenv('SPOTIFY_REDIRECT_URI') ?: '';
    }

    /**
     * Callback OAuth Spotify
     *
     * Échange le code reçu en tokens (access + refresh) et renvoie du JSON.
     * Route attendue : GET /api/spotify/callback?code=…&state=…
     */
    public function spotifyCallback(): void
    {
        // Permettre les requêtes CORS si besoin
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');

        try {
            if (empty($_GET['code'])) {
                throw new Exception('Paramètre "code" manquant');
            }

            $code = $_GET['code'];
            $tokens = $this->service->requestAccessTokenWithCode($code, $this->redirectUri);

            echo json_encode($tokens);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'error'   => true,
                'message' => $e->getMessage(),
            ]);
        }

        exit;
    }

    /**
     * Rafraîchissement de token
     *
     * Reçoit en POST { "refresh_token": "…" } et renvoie le nouveau access_token (+ éventuellement nouveau refresh_token).
     * Route attendue : POST /api/spotify/refresh
     */
    public function refresh(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');

        $body = json_decode((string) file_get_contents('php://input'), true);
        if (empty($body['refresh_token'])) {
            http_response_code(400);
            echo json_encode([
                'error'   => true,
                'message' => 'Paramètre "refresh_token" manquant',
            ]);
            exit;
        }

        try {
            $newTokens = $this->service->refreshAccessToken($body['refresh_token']);
            echo json_encode($newTokens);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'error'   => true,
                'message' => $e->getMessage(),
            ]);
        }

        exit;
    }
}
