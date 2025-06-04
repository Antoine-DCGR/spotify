<?php

class Playlist {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM playlist");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($nom) {
        $stmt = $this->pdo->prepare("INSERT INTO playlist (nom) VALUES (?)");
        return $stmt->execute([$nom]);
    }
}
