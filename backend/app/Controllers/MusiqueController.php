<?php
// app/Controllers/MusiqueController.php

require_once __DIR__ . '/../Models/Musique.php';

class MusiqueController
{
    private $musiqueModel;

    /**
     * Le constructeur reçoit une instance PDO.
     */
    public function __construct(PDO $pdo)
    {
        $this->musiqueModel = new Musique($pdo);
    }

    /**
     * Récupère toutes les musiques locales en base et renvoie en JSON.
     * URL : ?page=allMusique
     */
    public function allMusique()
    {
        $all = $this->musiqueModel->getAll();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['musique' => $all], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Récupère les musiques d'une playlist Spotify par son ID Spotify.
     * URL : ?page=musiqueByPlaylist&playlistId={spotifyPlaylistId}
     */
    public function musiqueByPlaylist(string $playlistSpotifyId = null)
    {
        if (!$playlistSpotifyId) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'playlistId manquant']);
            return;
        }

        $tracks = $this->musiqueModel->getByPlaylistSpotify($playlistSpotifyId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['musique' => $tracks], JSON_UNESCAPED_UNICODE);
    }
}
