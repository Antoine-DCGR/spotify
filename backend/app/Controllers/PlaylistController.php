<?php
require_once __DIR__ . '/../Models/Playlist.php';

class PlaylistController {
    public function index() {
        global $pdo;
        $model = new Playlist($pdo);
        $data = $model->getAll();
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    
}
