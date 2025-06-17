<?php
// app/Controllers/AudioDownloadController.php

require_once __DIR__ . '/../Services/AudioDownloadService.php';
require_once __DIR__ . '/../Models/Musique.php';

class AudioDownloadController
{
    private PDO $db;
    private AudioDownloadService $audioService;
    private Musique $musiqueModel;

    public function __construct(PDO $db)
    {
        $this->db           = $db;
        $this->audioService = new AudioDownloadService();
        $this->musiqueModel = new Musique($db);
    }

    /**
     * GET ?page=audio.fetchParallel&page=1&per_page=5
     * Traite **une** page de téléchargements (<= per_page).
     */
    public function fetchParallel(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        extract($this->preparePagination(5));  // $page, $perPage, $total, $totalPages

        // Lecture de la page
        $tasks   = $this->loadTasksPage($page, $perPage);
        $results = $this->initResults($tasks);

        // Lancement du pipeline full-parallel sur cette page seulement
        $batchResults = $this->processBatch($tasks);
        foreach ($batchResults as $id => $res) {
            $results[$id] = $res;
        }

        echo json_encode([
            'page'         => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'total_pages'  => $totalPages,
            'data'         => $results,
        ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    /**
     * GET ?page=audio.fetchAllParallel&per_page=5
     * Parcourt TOUTES les pages de téléchargements,
     * accumule les résultats et renvoie un résumé complet.
     */
    public function fetchAllParallel(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        // On fixe per_page (max 5 pour petit ordi)
        $perPage = isset($_GET['per_page'])
            ? max(1, min((int)$_GET['per_page'], 5))
            : 5;

        // Initialisation pagination
        $total      = $this->musiqueModel->countWithoutAudio();
        $totalPages = (int)ceil($total / $perPage);

        $allResults = [];
        // Boucle sur les pages
        for ($page = 1; $page <= $totalPages; $page++) {
            $tasks        = $this->loadTasksPage($page, $perPage);
            $resultsPage  = $this->initResults($tasks);
            $batchResults = $this->processBatch($tasks);
            foreach ($batchResults as $id => $res) {
                $resultsPage[$id] = $res;
            }
            // Fusionne dans l’ensemble
            $allResults = array_merge($allResults, $resultsPage);
        }

        // Sépare les failed pour relance facile
        $failed = array_filter($allResults, fn($r) => $r['status'] !== 'ok');

        echo json_encode([
            'total'       => $total,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'results'     => $allResults,
            'failed'      => $failed,
        ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }


    //
    // ——— MÉTHODES PRIVÉES D’AIDE —————————————————————————————————————
    //

    /** Prépare page/per_page/total/totalPages depuis $_GET */
    private function preparePagination(int $defaultPerPage): array
    {
        $page    = isset($_GET['page'])     ? max(1, (int)$_GET['page'])                : 1;
        $perPage = isset($_GET['per_page'])
            ? max(1, min((int)$_GET['per_page'], $defaultPerPage))
            : $defaultPerPage;
        $total      = $this->musiqueModel->countWithoutAudio();
        $totalPages = (int)ceil($total / $perPage);
        return compact('page','perPage','total','totalPages');
    }

    /** Charge le slice des tâches (id + id_youtube) pour une page donnée */
    private function loadTasksPage(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $list   = $this->musiqueModel->getWithoutAudioPaginated($offset, $perPage);
        $tasks  = [];
        foreach ($list as $item) {
            $tasks[$item['id']] = [
                'id' => (int)$item['id'],
                'yt' => $item['id_youtube'],
            ];
        }
        return $tasks;
    }

    /** Préremplit un tableau id=>['status'=>'failed'] pour chaque tâche */
    private function initResults(array $tasks): array
    {
        return array_fill_keys(array_keys($tasks), ['status'=>'failed']);
    }

    /**
     * Pipeline full-parallel pour un **batch** de tâches (<= per_page).
     * Idem code précédent : conversion, polling, streaming.
     */
    private function processBatch(array $tasks): array
    {
        $results     = [];
        $progressMap = [];
        $downloadMap = [];

        // PHASE 1 – conversion
        $mh1 = curl_multi_init();
        $map1 = [];
        foreach ($tasks as $id => $t) {
            $url = $this->audioService->getDownloadEndpoint()
                 . '?' . http_build_query([
                     'id'           => $t['yt'],
                     'format'       => 'mp3',
                     'audioQuality' => 320,
                     'addInfo'      => 'true',
                 ]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    "x-rapidapi-host: {$this->audioService->getHost()}",
                    "x-rapidapi-key:  {$this->audioService->getApiKey()}",
                ],
            ]);
            curl_multi_add_handle($mh1, $ch);
            $map1[$id] = $ch;
        }
        do {
            curl_multi_exec($mh1, $r);
            curl_multi_select($mh1);
        } while ($r > 0);
        foreach ($map1 as $id => $ch) {
            $d = json_decode(curl_multi_getcontent($ch), true);
            if (!empty($d['progressId'])) {
                $progressMap[$id] = $d['progressId'];
            }
            curl_multi_remove_handle($mh1, $ch);
        }
        curl_multi_close($mh1);

        // PHASE 2 – polling
        $remaining = $progressMap;
        for ($i=0; $i<10 && $remaining; $i++) {
            sleep(2);
            $mh2 = curl_multi_init();
            $map2 = [];
            foreach ($remaining as $id => $pid) {
                $url2 = $this->audioService->getProgressEndpoint()
                      . '?id=' . urlencode($pid);
                $ch2 = curl_init($url2);
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        "x-rapidapi-host: {$this->audioService->getHost()}",
                        "x-rapidapi-key:  {$this->audioService->getApiKey()}",
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
                $d2 = json_decode(curl_multi_getcontent($ch2), true);
                curl_multi_remove_handle($mh2, $ch2);
                if (!empty($d2['downloadUrl'])) {
                    $downloadMap[$id] = $d2['downloadUrl'];
                    unset($remaining[$id]);
                }
            }
            curl_multi_close($mh2);
        }

        // PHASE 3 – streaming vers fichier
        $mh3 = curl_multi_init();
        $fps = []; // file pointers
        $chs = []; // handles
        foreach ($downloadMap as $id => $url) {
            $fn  = "musique_{$id}.mp3";
            $fd  = __DIR__ . '/../../public/fichier_mp3';
            if (!is_dir($fd)) mkdir($fd, 0775, true);
            $fp = fopen("$fd/$fn", 'wb');
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 60,
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
                $this->musiqueModel->updateAudioPath($id, $rel);
                $results[$id] = ['status'=>'ok','audio_path'=>$rel];
            }
        }
        curl_multi_close($mh3);

        return $results;
    }
    public function fetchSingle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = isset($_GET['musiqueId']) ? (int)$_GET['musiqueId'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'musiqueId manquant']);
            return;
        }

        $m = $this->musiqueModel->findById($id);
        if (!$m) {
            http_response_code(404);
            echo json_encode(['error' => 'Musique non trouvée']);
            return;
        }

        // 1) Recherche du premier YouTube ID
        $query    = $m['titre'] . ' ' . $m['artiste'];
        $videoId  = $this->ytService->getFirstVideoId($query);
        if (!$videoId) {
            http_response_code(404);
            echo json_encode(['error' => 'Aucune vidéo trouvée']);
            return;
        }

        // 2) Mise à jour du champ id_youtube
        $this->musiqueModel->updateYoutubeVideoId($id, $videoId);

        // 3) Téléchargement de l’audio (stream to file)
        $links = $this->audioService->fetchDownloadLinks($videoId);
        if (!$links || empty($links['audio'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de récupérer le lien audio']);
            return;
        }

        // 4) Enregistrement sur disque
        $filename  = "musique_{$id}.mp3";
        $dir       = __DIR__ . '/../../public/fichier_mp3';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fullPath  = "{$dir}/{$filename}";
        $fp        = fopen($fullPath, 'wb');
        $ch        = curl_init($links['audio']);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($err) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur CURL : ' . $err]);
            return;
        }

        // 5) Mise à jour de audio_path en BDD
        $relativePath = "fichier_mp3/{$filename}";
        $this->musiqueModel->updateAudioPath($id, $relativePath);

        // 6) Réponse
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
        $host   = $_SERVER['HTTP_HOST'];
        $url    = "{$scheme}://{$host}/{$relativePath}";

        echo json_encode([
            'musiqueId'  => $id,
            'videoId'    => $videoId,
            'audio_path' => $relativePath,
            'audio_url'  => $url,
        ]);
    }

    /**
     * Renvoie toutes les musiques avec id_youtube non null
     * et sans audio_path.
     * URL : GET ?page=audio.listMissing
     */
    public function listMissingAudio(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $list = $this->musiqueModel->getAllWithoutAudio();
        echo json_encode($list);
    }

    // … vos autres méthodes existantes (fetchParallel, processBatch, etc.) …

}
