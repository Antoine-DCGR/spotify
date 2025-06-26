<?php
// app/Services/SpotifyService.php

class SpotifyService
{
    private $clientId;
    private $clientSecret;

    public function __construct()
    {
        $this->clientId     = getenv('CLIENT_SPOTIFY_ID');
        $this->clientSecret = getenv('CLIENT_SPOTIFY_SECRET');
        

        if (!$this->clientId || !$this->clientSecret) {
            throw new Exception("CLIENT_SPOTIFY_ID et CLIENT_SPOTIFY_SECRET doivent être définis dans config/spotify.php");
        }
    }

    /**
     * Échange un Authorization Code contre access_token + refresh_token.
     */
    public function requestAccessTokenWithCode(string $code, string $redirectUri): array
    {
        $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");
        $ch = curl_init('https://accounts.spotify.com/api/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic {$credentials}",
            "Content-Type: application/x-www-form-urlencoded",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $redirectUri,
        ]));

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erreur échange code (HTTP {$httpCode}) : {$resp}");
        }
        $data = json_decode($resp, true);
        if (!isset($data['access_token'], $data['refresh_token'], $data['expires_in'])) {
            throw new Exception("Réponse inattendue Spotify : {$resp}");
        }
        return [
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in'    => $data['expires_in'],
        ];
    }

    /**
     * Rafraîchit un access_token à partir d’un refresh_token.
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");
        $ch = curl_init('https://accounts.spotify.com/api/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic {$credentials}",
            "Content-Type: application/x-www-form-urlencoded",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]));

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Erreur rafraîchissement token (HTTP {$httpCode}) : {$resp}");
        }
        $data = json_decode($resp, true);
        if (!isset($data['access_token'], $data['expires_in'])) {
            throw new Exception("Réponse inattendue Spotify : {$resp}");
        }
        return $data;
    }

    /**
     * Récupère toutes les playlists de l’utilisateur (boucle pagination).
     */
    public function getUserPlaylists(string $accessToken): array
    {
        $limit = 50;
        $offset = 0;
        $all = [];

        do {
            $url = "https://api.spotify.com/v1/me/playlists?limit={$limit}&offset={$offset}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}"
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Erreur GET /me/playlists (HTTP {$httpCode}) : {$resp}");
            }
            $data = json_decode($resp, true);
            $all = array_merge($all, $data['items']);
            $offset += $limit;
            $total = $data['total'];
        } while ($offset < $total);

        return $all;
    }

    /**
     * Récupère toutes les pistes d’une playlist Spotify (boucle pagination).
     */
    public function getPlaylistTracks(string $accessToken, string $playlistId): array
    {
        $limit = 100;
        $offset = 0;
        $all = [];

        do {
            $url = "https://api.spotify.com/v1/playlists/{$playlistId}/tracks?limit={$limit}&offset={$offset}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}"
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Erreur GET /playlists/{$playlistId}/tracks (HTTP {$httpCode}) : {$resp}");
            }
            $data = json_decode($resp, true);
            $all = array_merge($all, $data['items']);
            $offset += $limit;
            $total = $data['total'];
        } while ($offset < $total);

        return $all;
    }
    /**
 * Récupère les genres d'un artiste Spotify.
 */
public function getArtistGenres(string $accessToken, string $artistId): array
{
    $url = "https://api.spotify.com/v1/artists/{$artistId}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}"
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Erreur GET /artists/{$artistId} (HTTP {$httpCode}) : {$resp}");
    }

    $data = json_decode($resp, true);
    return $data['genres'] ?? [];
}
  public function getArtistsInfo(string $accessToken, array $artistIds): array
    {
        $result = [];
        // Spotify limite à 50 IDs par appel
        foreach (array_chunk($artistIds, 50) as $chunk) {
            $idsParam = implode(',', $chunk);
            $url = "https://api.spotify.com/v1/artists?ids={$idsParam}";

            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer {$accessToken}\r\n"
                ]
            ]);

            $res = @file_get_contents($url, false, $ctx);
            if (! $res) {
                // Taux limité ou autre erreur : on peut lire Retry-After ou laisser l’exception
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

}
