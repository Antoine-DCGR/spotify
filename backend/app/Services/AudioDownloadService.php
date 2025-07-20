<?php
// app/Services/AudioDownloadService.php

require_once __DIR__ . '/../Models/Musique.php';

class AudioDownloadService
{
    private string $host     = 'youtube-mp4-mp3-downloader.p.rapidapi.com';
    private string $endpoint = 'https://youtube-mp4-mp3-downloader.p.rapidapi.com/api/v1/download';
    private string $progress = 'https://youtube-mp4-mp3-downloader.p.rapidapi.com/api/v1/progress';
    private string $key;

    public function __construct()
    {
        $this->key = getenv('RAPID_API_KEY') ?: '';
        if ($this->key === '') {
            throw new \RuntimeException('RAPIDAPI_KEY introuvable.');
        }
    }

    // Getters pour compatibilité si besoin
    public function getApiKey(): string        { return $this->key; }
    public function getHost(): string          { return $this->host; }
    public function getDownloadEndpoint(): string { return $this->endpoint; }
    public function getProgressEndpoint(): string { return $this->progress; }

    /**
     * Charge toutes les musiques sans audio en une seule fois.
     *
     * @param Musique $musiqueModel
     * @return array  [ id => ['yt'=>videoId], ... ]
     */
    public function loadAllTasks(Musique $musiqueModel): array
    {
        // Récupère tous les items sans audio en base
        $list = $musiqueModel->getWithoutAudioPaginated(0, PHP_INT_MAX);
        $tasks = [];
        foreach ($list as $item) {
            $tasks[$item['id']] = ['yt' => $item['id_youtube']];
        }
        return $tasks;
    }

    /**
     * Pipeline full-parallel pour un batch de tâches.
     * Conversion, polling puis streaming vers fichier.
     *
     * @param array   $tasks          [id => ['yt'=>videoId], ...]
     * @param Musique $musiqueModel   Modèle pour mise à jour BDD (facultatif)
     * @return array  [ id => ['status'=>'ok','audio_path'=>path] or ['status'=>'failed'] ]
     */


public function processBatch(array $tasks, Musique $musiqueModel = null): array
{
    // ✅ TRAITEMENT PAR CHUNKS pour éviter le rate limiting
    $chunks = array_chunk($tasks, 5, true); // 5 par lot
    $allResults = [];
    
    foreach ($chunks as $chunkIndex => $chunk) {
        echo "Traitement chunk " . ($chunkIndex + 1) . "/" . count($chunks) . "\n";
        $chunkResults = $this->processSingleChunk($chunk, $musiqueModel);
        $allResults = array_merge($allResults, $chunkResults);
        
        // Pause entre les chunks pour respecter le rate limiting
        if ($chunkIndex < count($chunks) - 1) {
            sleep(2);
        }
    }
    
    return $allResults;
}

private function processSingleChunk(array $tasks, Musique $musiqueModel = null): array
{
    $results = [];
    foreach ($tasks as $id => $t) {
        $results[$id] = ['status' => 'failed', 'error' => 'Non traité'];
    }
    
    $progressMap = [];
    $downloadMap = [];

    // PHASE 1 – conversion avec délais
    $mh1 = curl_multi_init();
    $map1 = [];
    
    foreach ($tasks as $id => $t) {
        $url = $this->endpoint . '?' . http_build_query([
            'id'           => $t['yt'],
            'format'       => 'mp3',
            'audioQuality' => 320,
            'addInfo'      => 'true',
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30, // ✅ Plus de temps
            CURLOPT_HTTPHEADER     => [
                "x-rapidapi-host: {$this->host}",
                "x-rapidapi-key:  {$this->key}",
            ],
        ]);
        curl_multi_add_handle($mh1, $ch);
        $map1[$id] = $ch;
        
        // ✅ Mini-pause entre chaque requête
        usleep(200000); // 0.2 secondes
    }
    
    do {
        curl_multi_exec($mh1, $running);
        curl_multi_select($mh1);
    } while ($running > 0);
    
    foreach ($map1 as $id => $ch) {
        $resp = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode === 429) {
            $results[$id] = ['status' => 'failed', 'error' => 'Rate limit - réessayer plus tard'];
            curl_multi_remove_handle($mh1, $ch);
            continue;
        }
        
        if ($httpCode !== 200) {
            $results[$id] = ['status' => 'failed', 'error' => "HTTP {$httpCode} lors de la conversion"];
            curl_multi_remove_handle($mh1, $ch);
            continue;
        }
        
        $data = json_decode($resp, true);
        if (!empty($data['progressId'])) {
            $progressMap[$id] = $data['progressId'];
            $results[$id] = ['status' => 'converting', 'progress_id' => $data['progressId']];
        } elseif (!empty($data['downloadUrl'])) {
            // ✅ Parfois c'est direct !
            $downloadMap[$id] = $data['downloadUrl'];
            $results[$id] = ['status' => 'ready_for_download'];
        } else {
            $results[$id] = ['status' => 'failed', 'error' => 'Pas de progressId reçu'];
        }
        
        curl_multi_remove_handle($mh1, $ch);
    }
    curl_multi_close($mh1);

    // PHASE 2 – polling plus patient (15 secondes au lieu de 5)
    $remaining = $progressMap;
    for ($i = 0; $i < 15 && $remaining; $i++) {
        sleep(1);
        echo "Polling iteration " . ($i + 1) . "/15, remaining: " . count($remaining) . "\n";
        
        $mh2 = curl_multi_init();
        $map2 = [];
        
        foreach ($remaining as $id => $pid) {
            $ch2 = curl_init("{$this->progress}?id=" . urlencode($pid));
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    "x-rapidapi-host: {$this->host}",
                    "x-rapidapi-key:  {$this->key}",
                ],
            ]);
            curl_multi_add_handle($mh2, $ch2);
            $map2[$id] = $ch2;
        }
        
        do {
            curl_multi_exec($mh2, $r2);
            curl_multi_select($mh2);
        } while ($r2 > 0);
        
        foreach ($map2 as $id => $ch2) {
            $resp2 = curl_multi_getcontent($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            
            curl_multi_remove_handle($mh2, $ch2);
            
            if ($httpCode2 === 429) {
                $results[$id] = ['status' => 'failed', 'error' => 'Rate limit lors du polling'];
                unset($remaining[$id]);
                continue;
            }
            
            if ($httpCode2 !== 200) {
                continue; // On garde dans remaining pour réessayer
            }
            
            $data2 = json_decode($resp2, true);
            if (!empty($data2['downloadUrl'])) {
                $downloadMap[$id] = $data2['downloadUrl'];
                $results[$id] = ['status' => 'ready_for_download'];
                unset($remaining[$id]);
            }
        }
        curl_multi_close($mh2);
        
        // ✅ Si plus rien à poller, on sort
        if (empty($remaining)) break;
    }

    // Marquer les vrais timeouts
    foreach ($remaining as $id => $pid) {
        $results[$id] = ['status' => 'failed', 'error' => 'Conversion trop lente (>15s)'];
    }

    // PHASE 3 – téléchargement (inchangé)
    if (!empty($downloadMap)) {
        $results = $this->downloadFiles($downloadMap, $results, $musiqueModel);
    }

    return $results;
}

private function downloadFiles(array $downloadMap, array $results, Musique $musiqueModel = null): array
{
    $mh3 = curl_multi_init();
    $fps = []; $chs = [];
    
    foreach ($downloadMap as $id => $url) {
        $filename = "musique_{$id}.mp3";
        $dir = __DIR__ . '/../../public/fichier_mp3';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        
        $fp = fopen("$dir/$filename", 'wb');
        if (!$fp) {
            $results[$id] = ['status' => 'failed', 'error' => 'Impossible de créer le fichier'];
            continue;
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120, // ✅ 2 minutes pour les gros fichiers
        ]);
        curl_multi_add_handle($mh3, $ch);
        $fps[$id] = $fp;
        $chs[$id] = $ch;
    }
    
    do {
        curl_multi_exec($mh3, $r3);
        curl_multi_select($mh3);
    } while ($r3 > 0);
    
    foreach ($chs as $id => $ch) {
        curl_multi_remove_handle($mh3, $ch);
        fclose($fps[$id]);
        
        $info = curl_getinfo($ch);
        if ($info['http_code'] === 200) {
            $rel = "fichier_mp3/musique_{$id}.mp3";
            if ($musiqueModel) {
                $musiqueModel->updateAudioPath($id, $rel);
            }
            $results[$id] = ['status' => 'ok', 'audio_path' => $rel];
        } else {
            $results[$id] = ['status' => 'failed', 'error' => "HTTP {$info['http_code']} lors du téléchargement"];
        }
    }
    curl_multi_close($mh3);
    
    return $results;
}}