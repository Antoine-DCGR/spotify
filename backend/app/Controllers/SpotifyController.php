<?php
// app/Controllers/SpotifyController.php

/**
 * Ce contrôleur gère l’authentification OAuth avec Spotify :
 *  - connection() : redirige l’utilisateur vers l’URL d’autorisation Spotify
 *  - callback()   : réceptionne le `code` renvoyé par Spotify, échange contre un access token
 *
 * Pour que cela fonctionne, vous devez avoir défini dans vos variables d’environnement (docker-compose.yml)
 *  SPOTIFY_CLIENT_ID, SPOTIFY_CLIENT_SECRET, SPOTIFY_REDIRECT_URI
 */

class SpotifyController
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct()
    {
        // Lecture depuis les variables d’environnement Docker
        $this->clientId     = getenv('CLIENT_SPOTIFY_ID')     ;
        $this->clientSecret = getenv('CLIENT_SPOTIFY_SECRET') ;
        $this->redirectUri  = getenv('SPOTIFY_REDIRECT_URI')  ;
    }

    /**
     * Première étape : redirige vers Spotify pour obtenir l’autorisation de l’utilisateur.
     * URL appelable : ?page=connection
     */
    public function connection()
    {
        if (!$this->clientId || !$this->redirectUri) {
            http_response_code(500);
            echo "Erreur : SPOTIFY_CLIENT_ID ou SPOTIFY_REDIRECT_URI non définis.";
            exit;
        }

        // Construire l’URL d’autorisation Spotify
        $scopes = urlencode('playlist-read-private playlist-read-collaborative');
        $state  = bin2hex(random_bytes(8));

        $authUrl = sprintf(
            'https://accounts.spotify.com/authorize?response_type=code&client_id=%s&scope=%s&redirect_uri=%s&state=%s',
            $this->clientId,
            $scopes,
            urlencode($this->redirectUri),
            $state
        );

        // Stocker le state en session pour vérifier au retour (optionnel)
        session_start();
        $_SESSION['spotify_oauth_state'] = $state;

        // Redirection
        header("Location: $authUrl");
        exit;
    }

    /**
     * Callback Spotify : Spotify renvoie le "code" en GET, on l’échange contre un access token.
     * URL appelable : ?page=spotifyCallback&code=...&state=...
     */
    public function spotifyCallback()
    {
        session_start();
        // Vérification du state
        $returnedState = $_GET['state'] ?? null;
        $expectedState = $_SESSION['spotify_oauth_state'] ?? null;
        unset($_SESSION['spotify_oauth_state']);

        if (!$returnedState || !$expectedState || $returnedState !== $expectedState) {
            http_response_code(400);
            echo "Échec CSRF / state invalide.";
            exit;
        }

        $code = $_GET['code'] ?? null;
        if (!$code) {
            http_response_code(400);
            echo "Erreur : code manquant.";
            exit;
        }

        // Préparer la requête POST pour échanger code → access_token
        $tokenUrl = 'https://accounts.spotify.com/api/token';
        $postData = http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                             "Content-Length: " . strlen($postData) . "\r\n",
                'content' => $postData,
            ],
        ];
        $context  = stream_context_create($opts);
        $response = file_get_contents($tokenUrl, false, $context);
        if ($response === false) {
            http_response_code(500);
            echo "Erreur lors de la requête de token.";
            exit;
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            http_response_code(500);
            echo "Erreur Spotify : " . htmlspecialchars($data['error_description'] ?? $data['error']);
            exit;
        }

        // On récupère les tokens et on les stocke en session (ou en BDD selon votre besoin)
        $accessToken  = $data['access_token']  ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn    = $data['expires_in']    ?? 0;

        if (!$accessToken) {
            http_response_code(500);
            echo "Impossible de récupérer l’access token.";
            exit;
        }

        // Exemple : stockage en session
        session_start();
        $_SESSION['spotify_access_token']  = $accessToken;
        $_SESSION['spotify_refresh_token'] = $refreshToken;
        $_SESSION['spotify_expires_in']    = time() + $expiresIn;

        // Rediriger vers une page interne ou renvoyer un JSON
        echo "<h1>Authentification Spotify réussie !</h1>";
        echo "<p>Access Token : " . htmlspecialchars($accessToken) . "</p>";
        // Vous pouvez rediriger vers l’accueil ou une route front-end
        // header("Location: /index.php?page=someRoute");
        exit;
    }
}
