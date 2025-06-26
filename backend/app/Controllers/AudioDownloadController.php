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

        // Délégation au service pour le traitement
        $results = $this->audioService->processPage($page, $perPage, $this->musiqueModel);

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
    public function fetchAllDownload(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage  = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 3;
        $offset   = ($page - 1) * $perPage;

        $musiques = $this->musiqueModel->getPaginatedTasks($perPage, $offset);
        if (empty($musiques)) {
            echo json_encode([
                'message' => 'Aucune musique à traiter',
                'page'    => $page,
                'results' => []
            ]);
            return;
        }

        $tasks = [];
        foreach ($musiques as $m) {
            $tasks[$m['id']] = ['yt' => $m['youtube_id']];
        }

        $results = $this->audioService->processBatch($tasks, $this->musiqueModel);
        $failed  = array_filter($results, fn($r) => $r['status'] !== 'ok');

        echo json_encode([
            'page'    => $page,
            'count'   => count($results),
            'results' => $results,
            'failed'  => $failed,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    

    /**
     * Télécharge une seule musique par son ID
     */
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

        try {
            // Délégation au service pour le téléchargement unique
            $result = $this->audioService->downloadSingle($id, $m, $this->musiqueModel);
            
            // Construction de l'URL complète
            $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
            $host   = $_SERVER['HTTP_HOST'];
            $url    = "{$scheme}://{$host}/{$result['audio_path']}";

            echo json_encode([
                'musiqueId'  => $id,
                'videoId'    => $result['videoId'],
                'audio_path' => $result['audio_path'],
                'audio_url'  => $url,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
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

    //
    // ——— MÉTHODES PRIVÉES D'AIDE —————————————————————————————————————
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
}