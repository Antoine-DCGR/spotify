<?php
// app/Services/SpotifyService.php

require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/../Models/SpotifyModel.php';

class SpotifyService
{
    private $clientId;
    private $clientSecret;
    private $model;
    private $tokenUrl;

    public function __construct(\PDO $db)
    {
        $this->clientId     = getenv('CLIENT_SPOTIFY_ID');
        $this->clientSecret = getenv('CLIENT_SPOTIFY_SECRET');
        if (!$this->clientId || !$this->clientSecret) {
            throw new Exception("CLIENT_SPOTIFY_ID et CLIENT_SPOTIFY_SECRET doivent être définis");
        }

        $this->model    = new SpotifyModel($db);
        // On récupère l'URL du endpoint token depuis la config
        $cfg            = require __DIR__ . '/../../config/spotify.php';
        $this->tokenUrl = $cfg['token_url'];
    }

    /**
     * Échange le code PKCE + verifier contre tokens et stocke en base.
     *
     * @param string $code
     * @param string $redirectUri
     * @param string $verifier
     * @param int    $userId
     * @return array ['access_token','refresh_token','expires_in']
     */
    public function exchangeCodeAndStore(string $code, string $redirectUri, string $verifier, int $userId): array
    {
        // 1) échange code + verifier contre tokens
        $tokens = $this->requestAccessTokenWithCode($code, $redirectUri, $verifier);

        // 2) calcul de l'expiration
        $expiresAt = (new DateTime())
            ->add(new DateInterval('PT' . $tokens['expires_in'] . 'S'))
            ->format('Y-m-d H:i:s');

        // 3) stockage en base via le model
        $this->model->upsertToken(
            $userId,
            $tokens['access_token'],
            $tokens['refresh_token'],
            $expiresAt
        );

        return $tokens;
    }

    /**
     * Rafraîchit le token Spotify si besoin et met à jour la base.
     *
     * @param int $userId
     * @return array ['access_token','refresh_token','expires_in']
     */
    public function refreshTokenForUser(int $userId): array
    {
        $row = $this->model->getTokenByUserId($userId);
        if (!$row) {
            throw new Exception("Utilisateur introuvable");
        }

        // Si expiré
        if (new DateTime($row['expires_at']) < new DateTime()) {
            $new = $this->refreshAccessToken($row['refresh_token']);
            $newExp = (new DateTime())
                ->add(new DateInterval('PT' . $new['expires_in'] . 'S'))
                ->format('Y-m-d H:i:s');

            $this->model->upsertToken(
                $userId,
                $new['access_token'],
                $new['refresh_token'] ?? $row['refresh_token'],
                $newExp
            );

            return $new;
        }

        // Sinon on retourne l’actuel
        return [
            'access_token'  => $row['access_token'],
            'refresh_token' => $row['refresh_token'],
            'expires_in'    => (new DateTime($row['expires_at']))->getTimestamp() - time(),
        ];
    }

    /**
     * Envoie la requête d'échange de code PKCE contre tokens.
     */
    public function requestAccessTokenWithCode(string $code, string $redirectUri, string $verifier): array
    {
        $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");
        return HttpClient::post($this->tokenUrl, [
            'grant_type'     => 'authorization_code',
            'code'           => $code,
            'redirect_uri'   => $redirectUri,
            'code_verifier'  => $verifier,
            'client_id'      => $this->clientId,
            'client_secret'  => $this->clientSecret,
        ]);
    }

    /**
     * Envoie la requête de refresh_token à Spotify.
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");
        return HttpClient::post($this->tokenUrl, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);
    }

    /**
     * Récupère toutes les playlists de l’utilisateur (avec pagination).
     */
    public function getUserPlaylists(string $accessToken): array
    {
        $limit  = 50;
        $offset = 0;
        $all    = [];

        do {
            $url = "https://api.spotify.com/v1/me/playlists?limit={$limit}&offset={$offset}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}"
            ]);
            $resp     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Erreur GET /me/playlists (HTTP {$httpCode}) : {$resp}");
            }

            $data = json_decode($resp, true);
            $all  = array_merge($all, $data['items']);
            $offset += $limit;
            $total  = $data['total'];
        } while ($offset < $total);

        return $all;
    }

    /**
     * Récupère les pistes d’une playlist (avec pagination).
     */
    public function getPlaylistTracks(string $accessToken, string $playlistId): array
    {
        $limit  = 100;
        $offset = 0;
        $all    = [];

        do {
            $url = "https://api.spotify.com/v1/playlists/{$playlistId}/tracks?limit={$limit}&offset={$offset}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}"
            ]);
            $resp     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Erreur GET /playlists/{$playlistId}/tracks (HTTP {$httpCode}) : {$resp}");
            }

            $data = json_decode($resp, true);
            $all  = array_merge($all, $data['items']);
            $offset += $limit;
            $total  = $data['total'];
        } while ($offset < $total);

        return $all;
    }

    /**
     * Récupère les genres d'un artiste.
     */
    public function getArtistGenres(string $accessToken, string $artistId): array
    {
        $url = "https://api.spotify.com/v1/artists/{$artistId}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}"
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erreur GET /artists/{$artistId} (HTTP {$httpCode}) : {$resp}");
        }

        $data = json_decode($resp, true);
        return $data['genres'] ?? [];
    }

    /**
     * Récupère des infos sur plusieurs artistes (batch).
     */
    public function getArtistsInfo(string $accessToken, array $artistIds): array
    {
        $result = [];
        foreach (array_chunk($artistIds, 50) as $chunk) {
            $idsParam = implode(',', $chunk);
            $url      = "https://api.spotify.com/v1/artists?ids={$idsParam}";

            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer {$accessToken}\r\n"
                ]
            ]);
            $res = @file_get_contents($url, false, $ctx);
            if (! $res) {
                $info = $http_response_header[0] ?? '';
                if (preg_match('/429/', $info)) {
                    throw new Exception("Rate limit exceeded on batch artist fetch");
                }
                throw new Exception("Erreur GET {$url} : {$info}");
            }

            $json = json_decode($res, true);
            foreach ($json['artists'] as $artist) {
                $result[$artist['id']] = $artist;
            }
        }

        return $result;
    }
    public function getUserProfile(string $accessToken): array
{
    $url = "https://api.spotify.com/v1/me";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}"
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Erreur GET /me (HTTP {$httpCode}) : {$resp}");
    }

    return json_decode($resp, true);
}
public function getTokenModel(): SpotifyModel
{
    return $this->model;
}

}
