<?php

class Musique {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM musique");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($artiste, $album, $titre, $playlist_id) {
        $stmt = $this->pdo->prepare("INSERT INTO musique (artiste, album, titre, playlist_id) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$artiste, $album, $titre, $playlist_id]);
    }

    public function getByPlaylist($playlistId) {
        $stmt = $this->pdo->prepare("SELECT * FROM musique WHERE playlist_id = ?");
        $stmt->execute([$playlistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
