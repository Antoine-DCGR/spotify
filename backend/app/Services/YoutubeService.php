<?php


class YoutubeService
{
    private string $apiKey;

    public function __construct()
    {
      
        $this->apiKey = getenv('YOUTUBE_API_KEY');
    }

    /**
     * Recherche la première vidéo YouTube pour une requête donnée
     *
     * @param string $query terme de recherche (titre + artiste)
     * @return string|null    ID de la vidéo ou null si aucune trouvée
     */
    public function getFirstVideoId(string $query): ?string
    {
        $endpoint = 'https://www.googleapis.com/youtube/v3/search';
        $params = http_build_query([
            'part'       => 'id',
            'q'          => $query,
            'maxResults' => 1,
            'type'       => 'video',
            'key'        => $this->apiKey,
        ]);

        $response = file_get_contents("{$endpoint}?{$params}");
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['items'][0]['id']['videoId'] ?? null;
    }
}