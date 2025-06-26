<?php
// app/Controllers/AlbumController.php

class AlbumController
{
    private PDO $pdo;
    private Album $albumModel;
    private SpotifyService $spotifyService;

    public function __construct(PDO $pdo)
    {
        $this->pdo           = $pdo;
        $this->albumModel    = new Album($pdo);
        $this->spotifyService = new SpotifyService();
    }

    public function fetchAndStoreFromSpotify(string $playlistId = null): void
    {
        // récupérer les albums des pistes d’une playlist,
        // puis $this->albumModel->insertIfNotExistsSpotify()
    }

    public function getAllAlbums(): void
    {
        $all = $this->albumModel->getAll();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['albums' => $all], JSON_UNESCAPED_UNICODE);
    }

    public function show(int $id): void
    {
        $alb = $this->albumModel->findById($id);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['album' => $alb], JSON_UNESCAPED_UNICODE);
    }

    public function getByArtist(int $artistId): void
    {
        $list = $this->albumModel->getByArtistId($artistId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['albums' => $list], JSON_UNESCAPED_UNICODE);
    }
}
