<?php
// app/Services/YoutubeService.php


class YoutubeService
{
    private string $apiKey;
    private string $endpoint = 'https://www.googleapis.com/youtube/v3/search';

    /** Chemin vers le fichier de cache JSON */
    private string $cacheFile;
    /** Données de cache en mémoire */
    private array $cacheData = [];

    public function __construct()
    {
        $this->apiKey = getenv('YOUTUBE_API_KEY') ?: '';
        if ($this->apiKey === '') {
            throw new \RuntimeException('Variable d’environnement YOUTUBE_API_KEY manquante.');
        }

        // Initialisation du cache
        $this->cacheFile = __DIR__ . '/../../cache/youtube_ids.json';
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (file_exists($this->cacheFile)) {
            $json = file_get_contents($this->cacheFile);
            $this->cacheData = json_decode($json, true) ?: [];
        }
    }

    /**
     * Recherche la première vidéo YouTube pour une requête donnée,
     * en utilisant d'abord le cache local.
     *
     * @param string $query terme de recherche (titre + artiste)
     * @return string|null    ID de la vidéo ou null si aucune trouvée / en cas d’erreur
     */
    public function getFirstVideoId(string $query): ?string
    {
        // 1) lookup en cache
        if (isset($this->cacheData[$query])) {
            return $this->cacheData[$query];
        }

        // 2) pas en cache → appel API via cURL
        $params = http_build_query([
            'part'       => 'id',
            'q'          => $query,
            'maxResults' => 1,
            'type'       => 'video',
            'key'        => $this->apiKey,
        ]);
        $url = "{$this->endpoint}?{$params}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200) {
            return null;
        }

        $data    = json_decode($body, true);
        $videoId = $data['items'][0]['id']['videoId'] ?? null;

        // 3) si trouvé, on met à jour le cache (mémoire + fichier)
        if ($videoId) {
            $this->cacheData[$query] = $videoId;
            file_put_contents(
                $this->cacheFile,
                json_encode($this->cacheData, JSON_PRETTY_PRINT),
                LOCK_EX
            );
        }

        return $videoId;
    }
     public function searchAllVideoIds(string $query, int $perPage = 50, int $maxPages = 5): array
    {
        $endpoint     = 'https://www.googleapis.com/youtube/v3/search';
        $videoIds     = [];
        $pageToken    = null;
        $pagesFetched = 0;

        do {
            $params = [
                'part'       => 'id',
                'q'          => $query,
                'maxResults' => min(50, $perPage),
                'type'       => 'video',
                'key'        => $this->apiKey,
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $url      = $endpoint . '?' . http_build_query($params);
            $response = @file_get_contents($url);
            if ($response === false) {
                break;
            }
            $data = json_decode($response, true);
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (isset($item['id']['videoId'])) {
                        $videoIds[] = $item['id']['videoId'];
                    }
                }
            }

            $pageToken    = $data['nextPageToken'] ?? null;
            $pagesFetched++;
        } while ($pageToken && $pagesFetched < $maxPages);

        return $videoIds;
    }
}
