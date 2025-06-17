<?php
// app/Controllers/YoutubeController.php

require_once __DIR__ . '/../Models/Musique.php';
require_once __DIR__ . '/../Services/YoutubeService.php';

class YoutubeController
{
    /** @var PDO */
    private $db;

    /** @var Musique */
    private $musiqueModel;

    /** @var YoutubeService */
    private $ytService;

    public function __construct(PDO $db)
    {
        $this->db            = $db;
        $this->musiqueModel  = new Musique($db);
        $this->ytService     = new YoutubeService();
    }

    /**
     * Recherche et stocke l'ID YouTube pour une musique donnée (POST JSON { "id": … })
     */
    public function fetchAndStore(): void
    {
        $raw   = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
        $id    = $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID musique manquant']);
            return;
        }

        $music = $this->musiqueModel->findById((int)$id);
        if (!$music) {
            http_response_code(404);
            echo json_encode(['error' => 'Musique non trouvée']);
            return;
        }

        $query   = $music['titre'] . ' ' . $music['artiste'];
        $videoId = $this->ytService->getFirstVideoId($query);

        if ($videoId) {
            $this->musiqueModel->updateYoutubeVideoId((int)$id, $videoId);
            echo json_encode(['videoId' => $videoId]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Aucune vidéo trouvée']);
        }
    }

    /**
     * Recherche et stocke l'ID YouTube pour toutes les musiques sans vidéo (batch)
     */
    public function fetchAndStoreAll(): void
{
    // 1) Lecture des paramètres de pagination
    $page    = isset($_GET['page'])     ? max(1, (int)$_GET['page'])         : 1;
    $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page'])     : 10;
    $offset  = ($page - 1) * $perPage;

    // 2) Nombre total de musiques sans YouTube
    $total      = $this->musiqueModel->countWithoutYoutube();
    // 3) Récupère juste la page courante
    $list       = $this->musiqueModel->getWithoutYoutubePaginated($offset, $perPage);

    $results    = [];
    foreach ($list as $item) {
        $query   = $item['titre'] . ' ' . $item['artiste'];
        $videoId = $this->ytService->getFirstVideoId($query);

        if ($videoId) {
            $this->musiqueModel->updateYoutubeVideoId((int)$item['id'], $videoId);
            $results[$item['id']] = $videoId;
        } else {
            $results[$item['id']] = null;
        }
    }

    // 4) Calcul du nombre de pages
    $totalPages = (int)ceil($total / $perPage);

    // 5) Réponse JSON paginée
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => $totalPages,
        'data'        => $results,
    ], JSON_UNESCAPED_UNICODE);
}

    /**
     * Retourne une liste paginée des musiques sans ID YouTube
     * GET params : page (défaut=1), per_page (défaut=10)
     */
    public function getMusiqueWithoutYoutube(): void
    {
        $page    = isset($_GET['page'])     ? max(1, (int)$_GET['page'])     : 1;
        $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
        $offset  = ($page - 1) * $perPage;

        $total      = $this->musiqueModel->countWithoutYoutube();
        $items      = $this->musiqueModel->getWithoutYoutubePaginated($offset, $perPage);
        $totalPages = (int)ceil($total / $perPage);

        header('Content-Type: application/json');
        echo json_encode([
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
            'data'        => $items,
        ]);
    }

    /**
     * Retourne tous les ID internes avec leur videoId YouTube existant
     */
    public function getYoutubeIds(): void
    {
        $stmt  = $this->db->query(
            "SELECT id, id_youtube 
               FROM musiques 
              WHERE id_youtube IS NOT NULL"
        );
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($items);
    }
     public function fetchYoutubeAllPaginated(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $perPage = isset($_GET['per_page'])
            ? min(100, max(1, (int)$_GET['per_page']))
            : 50;
        $page   = 1;
        $offset = 0;
        $allResults = [];

        // boucle jusqu'à ce qu'on récupère 0 lignes
        do {
            $offset      = ($page - 1) * $perPage;
            $pageItems   = $this->musiqueModel
                                ->getWithoutYoutubePaginated($offset, $perPage);
            $count       = count($pageItems);

            foreach ($pageItems as $item) {
                $id    = (int)$item['id'];
                $query = "{$item['titre']} {$item['artiste']}";
                $videoId = $this->ytService->getFirstVideoId($query);

                if ($videoId) {
                    $this->musiqueModel->updateYoutubeVideoId($id, $videoId);
                }
                $allResults[$id] = $videoId;
            }

            $page++;
        } while ($count === $perPage);

        echo json_encode($allResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
