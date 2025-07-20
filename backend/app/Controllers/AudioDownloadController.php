<?php
// app/Controllers/AudioDownloadController.php

require_once __DIR__ . '/../Services/AudioDownloadService.php';
require_once __DIR__ . '/../Services/YoutubeService.php';
require_once __DIR__ . '/../Models/Musique.php';

class AudioDownloadController
{
    private PDO $db;
    private AudioDownloadService $audioService;
    private Musique $musiqueModel;
    private YoutubeService $ytService;

    public function __construct(PDO $db)
    {
        $this->db           = $db;
        $this->audioService = new AudioDownloadService();
        $this->musiqueModel = new Musique($db);
        $this->ytService    = new YoutubeService();
    }

    /**
     * GET /audio/download/music/{musiqueId}
     */
    public function downloadMusic($musiqueId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)$musiqueId;
        // 1) Récupère la musique
        $m = $this->musiqueModel->findById($id);
        if (!$m) {
            http_response_code(404);
            echo json_encode(['error' => 'Musique non trouvée'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 2) Si pas d'ID YouTube → récupération + MAJ
        if (empty($m['id_youtube'])) {
            $query   = trim($m['titre'] . ' ' . ($m['artistes'] ?? ''));
            $videoId = $this->ytService->getFirstVideoId($query);
            if (!$videoId) {
                http_response_code(404);
                echo json_encode(['error' => 'Aucune vidéo trouvée'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->musiqueModel->updateYoutubeVideoId($id, $videoId);
            $m['id_youtube'] = $videoId;
        }

        // 3) Lancement du téléchargement — on tamponne les logs
        ob_start();
        try {
            $results = $this->audioService->processBatch(
                [ $id => ['yt' => $m['id_youtube']] ],
                $this->musiqueModel
            );
        } catch (\Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode([
                'error'   => 'processBatch error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        ob_end_clean();

        // 4) Comme processBatch renvoie un tableau indexé (0,1,2…), on récupère le premier élément
        if (!is_array($results) || count($results) === 0) {
            http_response_code(500);
            echo json_encode(['error' => 'Résultat vide'], JSON_UNESCAPED_UNICODE);
            return;
        }
        // si l’API renvoie un tableau associatif, on prend directement
        if (isset($results[$id]) && is_array($results[$id])) {
            $res = $results[$id];
        } else {
            // sinon on récupère le premier
            $res = reset($results);
        }

        // 5) Vérification du statut
        if (($res['status'] ?? null) !== 'ok') {
            http_response_code(500);
            echo json_encode([
                'error' => $res['error'] ?? 'Téléchargement échoué',
                'raw'   => $res
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 6) Construction de l’URL publique
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
        $host   = $_SERVER['HTTP_HOST'];
        $url    = "{$scheme}://{$host}/{$res['audio_path']}";

        // 7) Réponse finale (HTTP 200)
        echo json_encode([
            'musiqueId'  => $id,
            'audio_path' => $res['audio_path'],
            'audio_url'  => $url
        ], JSON_UNESCAPED_UNICODE);
    }
}
