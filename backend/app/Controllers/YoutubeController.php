<?php
// app/Controllers/YoutubeController.php

require_once __DIR__ . '/../Models/Musique.php';
require_once __DIR__ . '/../Services/YoutubeService.php';

class YoutubeController
{
    /** @var Musique */
    private $musiqueModel;

    /** @var YoutubeService */
    private $ytService;

    public function __construct(PDO $db)
    {
        $this->musiqueModel = new Musique($db);
        $this->ytService    = new YoutubeService();
    }

    /**
     * POST /youtube/fetch
     * Body JSON: { "id": … }
     * → Recherche l’ID YouTube pour la musique,
     *    le stocke en BDD puis renvoie { videoId }.
     */
       public function fetchAndStore(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id    = isset($input['id']) ? (int)$input['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error'=>'ID musique manquant'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Récupère le titre + artistes (implémenter findWithArtists si besoin)
        $m = $this->musiqueModel->findWithArtists($id);
        if (!$m) {
            http_response_code(404);
            echo json_encode(['error'=>'Musique introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $query   = trim($m['titre'] . ' ' . $m['artistes']);
        $videoId = $this->ytService->getFirstVideoId($query);
        if (!$videoId) {
            http_response_code(404);
            echo json_encode(['error'=>'Aucune vidéo trouvée'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->musiqueModel->updateYoutubeVideoId($id, $videoId);
        echo json_encode(['videoId'=>$videoId], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /youtube/getMusiqueWithoutYoutubeId
     * Liste paginée des musiques sans id_youtube.
     */
    public function getMusiqueWithoutYoutubeId(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $page    = isset($_GET['page'])     ? max(1, (int)$_GET['page'])     : 1;
        $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
        $offset  = ($page - 1) * $perPage;

        $total      = $this->musiqueModel->countWithoutYoutube();
        $items      = $this->musiqueModel->getWithoutYoutubePaginated($offset, $perPage);
        $totalPages = (int)ceil($total / $perPage);

        echo json_encode([
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
            'data'        => $items,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /youtube/getYoutubeIds
     * Renvoie tous les id internes + id_youtube non null.
     */
    public function getYoutubeIds(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $stmt  = $this->musiqueModel->pdo->query(
            "SELECT id, id_youtube FROM musiques WHERE id_youtube IS NOT NULL"
        );
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /youtube/fetchYoutubeIdAll
     * Batch : recherche et stocke tous les ids YouTube manquants.
     */
    public function fetchYoutubeAllPaginated(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $perPage = isset($_GET['per_page'])
            ? min(100, max(1, (int)$_GET['per_page']))
            : 50;
        $page       = 1;
        $offset     = 0;
        $allResults = [];

        do {
            $offset    = ($page - 1) * $perPage;
            $batch     = $this->musiqueModel->getWithoutYoutubePaginated($offset, $perPage);
            $count     = count($batch);

            foreach ($batch as $item) {
                $id    = (int)$item['id'];
                $query = trim($item['titre'] . ' ' . $item['artiste']);
                $vid   = $this->ytService->getFirstVideoId($query);

                if ($vid) {
                    $this->musiqueModel->updateYoutubeVideoId($id, $vid);
                }
                $allResults[$id] = $vid;
            }

            $page++;
        } while ($count === $perPage);

        echo json_encode($allResults, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    }
}
