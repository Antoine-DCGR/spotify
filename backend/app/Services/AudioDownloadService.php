<?php
// app/Services/AudioDownloadService.php


class AudioDownloadService
{
    private string $host     = 'youtube-mp4-mp3-downloader.p.rapidapi.com';
    private string $endpoint = 'https://youtube-mp4-mp3-downloader.p.rapidapi.com/api/v1/download';
    private string $progress  = 'https://youtube-mp4-mp3-downloader.p.rapidapi.com/api/v1/progress';
    private string $key;

    public function __construct()
    {
        $this->key = getenv('RAPID_API_KEY') ?: '';
        if ($this->key === '') {
            // Lance bien l'exception globale, sans use en tête
            throw new \RuntimeException('RAPIDAPI_KEY introuvable.');
        }
    }


 


    /**
     * Lance la conversion et attend le résultat final.
     *
     * @param string $videoId
     * @param int    $quality   kbps
     * @param string $format    'mp3' ou 'mp4'
     * @return array|null       ['audio'=>URL,…] ou null
     */
   public function fetchDownloadLinks(string $videoId, int $quality = 320, string $format = 'mp3'): ?array
    {
        // 1) Appel initial pour démarrer la conversion et obtenir progressId
        $query = http_build_query([
            'id'           => $videoId,
            'format'       => $format,
            'audioQuality' => $quality,
            'addInfo'      => 'true',
        ]);
        $ch = curl_init("{$this->endpoint}?{$query}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "x-rapidapi-host: {$this->host}",
                "x-rapidapi-key:  {$this->key}",
            ],
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $code !== 200 || !$resp) {
            return null;
        }

        $data = json_decode($resp, true);
        // Si l’API renvoie directement downloadUrl
        if (!empty($data['downloadUrl'])) {
            return ['audio' => $data['downloadUrl']];
        }
        if (empty($data['progressId'])) {
            return null;
        }
        $progressId = $data['progressId'];

        // 2) Polling sur /progress pour récupérer downloadUrl
        for ($i = 0; $i < 10; $i++) {
            sleep(2);
            $ch2 = curl_init("{$this->progress}?id=" . urlencode($progressId));
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    "x-rapidapi-host: {$this->host}",
                    "x-rapidapi-key:  {$this->key}",
                ],
            ]);
            $resp2 = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if ($resp2 && $code2 === 200) {
                $d2 = json_decode($resp2, true);
                if (!empty($d2['downloadUrl'])) {
                    return ['audio' => $d2['downloadUrl']];
                }
            }
        }

        return null;
    }

    /**
     * Télécharge l’URL fournie et l’enregistre dans public/audio/.
     */
   public function downloadAndSaveFromString(string $data, string $filename): ?string
    {
        $relativeDir = 'fichier_mp3';
        $publicDir   = __DIR__ . '/../../public/' . $relativeDir;

        if (!is_dir($publicDir) && !mkdir($publicDir, 0775, true)) {
            return null;
        }

        $fullPath = "{$publicDir}/{$filename}";
        if (file_put_contents($fullPath, $data) === false) {
            return null;
        }

        return "{$relativeDir}/{$filename}";
    }

 public function getHost(): string
    {
        return $this->host;
    }

    public function getDownloadEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getProgressEndpoint(): string
    {
        return $this->progress;
    }

    public function getApiKey(): string
    {
        return $this->key;
    }
}
