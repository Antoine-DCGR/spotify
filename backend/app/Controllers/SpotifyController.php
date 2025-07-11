<?php
// app/Controllers/SpotifyController.php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/spotify.php';
require_once __DIR__ . '/../Services/SpotifyService.php';
require_once __DIR__ . '/../Services/HttpClient.php';
require_once __DIR__ . '/../Services/Jwt.php';

use Firebase\JWT\ExpiredException;

class SpotifyController
{
    private PDO $db;
    private SpotifyService $service;
    private array $cfg;
    private string $jwtSecret;

    public function __construct()
    {
        // On démarre la session pour PKCE verifier
        session_start();

        $this->db        = getDatabaseConnection();
        $this->cfg       = require __DIR__ . '/../../config/spotify.php';
        $this->service   = new SpotifyService($this->db);
        $this->jwtSecret = getenv('JWT_SECRET') ?: '';
    }

    /**
     * 1) Redirige vers Spotify pour l'OAuth PKCE
     * GET /spotify/redirect
     */
    public function redirectToProvider(): void
    {
        $verifier  = bin2hex(random_bytes(64));
        $challenge = rtrim(
            strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'),
            '='
        );
        $_SESSION['pkce_verifier'] = $verifier;

        $params = http_build_query([
            'client_id'             => $this->cfg['client_id'],
            'response_type'         => 'code',
            'redirect_uri'          => $this->cfg['redirect_uri'],
            'code_challenge_method' => 'S256',
            'code_challenge'        => $challenge,
            'scope'                 => $this->cfg['scopes'],
            'state'                 => bin2hex(random_bytes(16)),
        ]);

        header('Location: ' . $this->cfg['authorize_url'] . '?' . $params);
        exit;
    }

    /**
     * 2) Callback PKCE → échange code/verifier contre tokens + JWT
     * POST /spotify/spotifyCallback
     */
    public function spotifyCallback(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $code         = $input['code']          ?? null;
        $codeVerifier = $input['code_verifier'] ?? null;

        if (!$code || !$codeVerifier) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing code or code_verifier']);
            exit;
        }

        try {
            $tokenResponse = HttpClient::post($this->cfg['token_url'], [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->cfg['redirect_uri'],
                'client_id'     => $this->cfg['client_id'],
                'client_secret' => $this->cfg['client_secret'],
                'code_verifier' => $codeVerifier,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error'    => 'Spotify token exchange failed',
                'response' => $e->getMessage()
            ]);
            exit;
        }

        if (!isset($tokenResponse['access_token'])) {
            http_response_code(500);
            echo json_encode([
                'error'    => 'Spotify token exchange failed',
                'response' => $tokenResponse
            ]);
            exit;
        }

        $accessToken  = $tokenResponse['access_token'];
        $refreshToken = $tokenResponse['refresh_token'] ?? '';
        $expiresIn    = (int) ($tokenResponse['expires_in'] ?? 0);

        try {
            $userInfo = $this->service->getUserProfile($accessToken);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get user info', 'details' => $e->getMessage()]);
            exit;
        }

        if (empty($userInfo['id'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get Spotify user ID']);
            exit;
        }

        // ⚠️ Idéalement, ici tu dois utiliser un vrai user_id en base !
        // Le mieux : vérifier si tu as déjà cet utilisateur (via $userInfo['id']),
        // sinon l'insérer, et utiliser sa clé primaire comme userId.
        // Pour la démo, on continue avec crc32 :
        $userId = intval(crc32($userInfo['id']));

        $this->service->getTokenModel()->upsertToken(
            $userId,
            $accessToken,
            $refreshToken,
            date('Y-m-d H:i:s', time() + $expiresIn)
        );

        $jwt = Jwt::encode([
            'sub' => $userId,
            'exp' => time() + $expiresIn,
        ], $this->jwtSecret);

        echo json_encode(['token' => $jwt]);
        exit;
    }

    /**
     * 3) Rafraîchit les tokens et renvoie un nouveau JWT
     * POST /spotify/refresh
     */
    public function refreshAccessToken(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            echo json_encode(['error' => 'Authorization header manquant ou invalide']);
            exit;
        }
        $jwt = substr($authHeader, 7);

        try {
            $payload = Jwt::decode($jwt, $this->jwtSecret);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'expir') !== false) {
                [, $bodyBase64] = explode('.', $jwt);
                $payload = json_decode(base64_decode($bodyBase64), true);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'JWT invalide : ' . $msg]);
                exit;
            }
        }

        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id manquant dans le JWT']);
            exit;
        }

        // Vérifie la présence d'un refresh_token pour ce userId
        $tokenRow = $this->service->getTokenModel()->getTokenByUserId((int)$userId);
        if (!$tokenRow || empty($tokenRow['refresh_token'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Session expirée, veuillez vous reconnecter']);
            exit;
        }

        try {
            $newTokens = $this->service->refreshTokenForUser((int)$userId);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur refresh Spotify : ' . $e->getMessage()]);
            exit;
        }

        $newJwt = Jwt::encode([
            'sub' => $userId,
            'exp' => time() + ($newTokens['expires_in'] ?? 0),
        ], $this->jwtSecret);

        echo json_encode(['token' => $newJwt]);
        exit;
    }

    /**
     * 4) Récupère les données /me à partir du JWT
     * GET /spotify/me
     * Avec logs détaillés
     */
    public function getSpotifyMe(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');

        error_log('→ [SpotifyController/getSpotifyMe] Called');
        error_log('→ HTTP_AUTHORIZATION = ' . ($_SERVER['HTTP_AUTHORIZATION'] ?? '<none>'));

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            error_log('→ [SpotifyController/getSpotifyMe] Missing Authorization header');
            http_response_code(401);
            echo json_encode(['error' => 'Authorization header manquant ou invalide']);
            exit;
        }
        $jwt = substr($authHeader, 7);

        try {
            $payload = Jwt::decode($jwt, $this->jwtSecret);
        } catch (\Exception $e) {
            error_log('→ [SpotifyController/getSpotifyMe] JWT invalide: ' . $e->getMessage());
            http_response_code(401);
            echo json_encode(['error' => 'JWT invalide : ' . $e->getMessage()]);
            exit;
        }

        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            error_log('→ [SpotifyController/getSpotifyMe] user_id manquant dans le JWT');
            http_response_code(400);
            echo json_encode(['error' => 'user_id manquant dans le JWT']);
            exit;
        }

        $row = $this->service->getTokenModel()->getTokenByUserId((int)$userId);
        if (!$row || empty($row['access_token'])) {
            error_log('→ [SpotifyController/getSpotifyMe] access_token introuvable en BDD');
            http_response_code(404);
            echo json_encode(['error' => 'access_token introuvable']);
            exit;
        }

        // PATCH: Essaye d'abord, puis refresh si token expiré, avec logs
        try {
            error_log('→ [SpotifyController/getSpotifyMe] Tentative appel getUserProfile avec access_token');
            $profile = $this->service->getUserProfile($row['access_token']);
            error_log('→ [SpotifyController/getSpotifyMe] Succès getUserProfile');
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            error_log('→ [SpotifyController/getSpotifyMe] Erreur 1ère tentative: ' . $msg);
            if (stripos($msg, 'access token expired') !== false || stripos($msg, '401') !== false) {
                error_log('→ [SpotifyController/getSpotifyMe] Token expiré, tentative refresh…');
                // Token expiré, on tente un refresh
                try {
                    $newTokens = $this->service->refreshTokenForUser((int)$userId);
                    error_log('→ [SpotifyController/getSpotifyMe] Refresh OK. Nouveau access_token: ' . substr($newTokens['access_token'] ?? 'N/A', 0, 20));
                    // Recharge access_token après refresh
                    $row = $this->service->getTokenModel()->getTokenByUserId((int)$userId);
                    $profile = $this->service->getUserProfile($row['access_token']);
                    error_log('→ [SpotifyController/getSpotifyMe] Succès getUserProfile après refresh');
                } catch (\Exception $refreshEx) {
                    error_log('→ [SpotifyController/getSpotifyMe] Echec refresh: ' . $refreshEx->getMessage());
                    http_response_code(500);
                    echo json_encode(['error' => 'Impossible de rafraîchir le token : ' . $refreshEx->getMessage()]);
                    exit;
                }
            } else {
                error_log('→ [SpotifyController/getSpotifyMe] Autre erreur: ' . $msg);
                http_response_code(500);
                echo json_encode(['error' => 'Erreur API Spotify /me : ' . $msg]);
                exit;
            }
        }

        http_response_code(200);
        echo json_encode($profile);
        error_log('→ [SpotifyController/getSpotifyMe] Fini, profil envoyé');
        exit;
    }
}
