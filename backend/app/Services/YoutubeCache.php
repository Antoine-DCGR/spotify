<?php
// app/Services/YouTubeCache.php


class YouTubeCache
{
    private string $file;
    private array  $data;

    public function __construct()
    {
        // fichier JSON où seront stockées les paires [ requête => videoId ]
        $this->file = __DIR__ . '/../../cache/youtube_ids.json';

        // si dossier inexistant, on le crée
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // on charge le JSON existant, sinon tableau vide
        if (file_exists($this->file)) {
            $json = file_get_contents($this->file);
            $this->data = json_decode($json, true) ?: [];
        } else {
            $this->data = [];
        }
    }

    /**
     * Récupère un videoId en cache pour une requête donnée
     * @param string $query
     * @return string|null
     */
    public function get(string $query): ?string
    {
        return $this->data[$query] ?? null;
    }

    /**
     * Enregistre en cache un videoId pour une requête donnée
     * @param string $query
     * @param string $videoId
     * @return void
     */
    public function set(string $query, string $videoId): void
    {
        $this->data[$query] = $videoId;
        // on écrit / lock en une seule opération
        file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT));
    }
}
