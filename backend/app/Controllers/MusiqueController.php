<?php
require_once __DIR__ . '/../Models/Musique.php';

class MusiqueController {
    public function index() {
        global $pdo;
        $model = new Musique($pdo);
        $data = $model->getAll();
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function byplaylist() {
    global $pdo;
    $model = new Musique($pdo);

    // /musique/byplaylist/1 → playlist id = 1
    $playlistId = $_GET['playlist_id'] ?? null;
    if (!$playlistId) {
        http_response_code(400);
        echo json_encode(["error" => "playlist_id manquant"]);
        return;
    }
    $data = $model->getByPlaylist($playlistId);
    header('Content-Type: application/json');
    echo json_encode($data);
    }


}
