<?php
// app/Controllers/ArtisteController.php

require_once __DIR__ . '/../Models/Artiste.php';
require_once __DIR__ . '/../Services/SpotifyService.php';

class ArtisteController
{
    private PDO $pdo;
    private Artiste $artisteModel;
    private SpotifyService $spotifyService;

    public function __construct(PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->artisteModel   = new Artiste($pdo);
        $this->spotifyService = new SpotifyService($pdo);
    }

    public function fetchAndStoreFromSpotify(string $playlistId = null): void
    {
        // récupérer les artistes des pistes d’une playlist puis $this->artisteModel->insertIfNotExistsSpotify()
    }

    public function getAllArtists(): void
    {
        $all = $this->artisteModel->getAll();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['artists' => $all], JSON_UNESCAPED_UNICODE);
    }

    public function show(int $id): void
    {
        $art = $this->artisteModel->findById($id);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['artist' => $art], JSON_UNESCAPED_UNICODE);
    }
}
