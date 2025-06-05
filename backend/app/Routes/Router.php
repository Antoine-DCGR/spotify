<?php
// app/Routes/Router.php

class Router
{
    public function handleRequest()
    {
        $page = $_GET['page'] ?? null;

        switch ($page) {
            case 'setup':
                require_once __DIR__ . '/../../setup.php';
                break;

            case 'allMusique':
                require_once __DIR__ . '/../Controllers/MusiqueController.php';
                require_once __DIR__ . '/../../config/database.php';
                $pdo = getDatabaseConnection();
                $musiqueCtrl = new MusiqueController($pdo);
                $musiqueCtrl->allMusique();
                break;

            case 'musiqueByPlaylist':
                require_once __DIR__ . '/../Controllers/MusiqueController.php';
                require_once __DIR__ . '/../../config/database.php';
                $pdo = getDatabaseConnection();
                $musiqueCtrl = new MusiqueController($pdo);
                $spotifyPlaylistId = $_GET['playlistId'] ?? null;
                $musiqueCtrl->musiqueByPlaylist($spotifyPlaylistId);
                break;

            case 'recupPlaylists':
            case 'allPlaylists':
                require_once __DIR__ . '/../Controllers/PlaylistController.php';
                require_once __DIR__ . '/../../config/database.php';
                $pdo = getDatabaseConnection();
                $playlistCtrl = new PlaylistController($pdo);
                $playlistCtrl->getAllSpotify();
                break;

            case 'insertPlaylist':
                require_once __DIR__ . '/../Controllers/PlaylistController.php';
                require_once __DIR__ . '/../../config/database.php';
                $pdo = getDatabaseConnection();
                $playlistCtrl = new PlaylistController($pdo);
                $sid       = $_POST['spotifyId']   ?? null;
                $nom       = $_POST['nom']         ?? null;
                $descr     = $_POST['description'] ?? null;
                $owner     = $_POST['owner']       ?? null;
                $img       = $_POST['imageURL']    ?? null;
                $count     = intval($_POST['tracksCount'] ?? 0);
                $pub       = ($_POST['isPublic'] ?? '0') === '1';
                $playlistCtrl->insertIfNotExistsSpotify(
                    $sid, $nom, $descr, $owner, $img, $count, $pub
                );
                break;
            case 'fetchPlaylists':
    require_once __DIR__ . '/../Controllers/PlaylistController.php';
    require_once __DIR__ . '/../../config/database.php';
    $pdo = getDatabaseConnection();
    $playlistCtrl = new PlaylistController($pdo);
    $playlistCtrl->fetchAndStoreFromSpotify();
    break;

            case 'connection':
                require_once __DIR__ . '/../Controllers/SpotifyController.php';
                $spotifyCtrl = new SpotifyController();
                $spotifyCtrl->connection();
                break;

            case 'spotifyCallback':
                require_once __DIR__ . '/../Controllers/SpotifyController.php';
                $spotifyCtrl = new SpotifyController();
                $spotifyCtrl->spotifyCallback();
                break;

            default:
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Route non trouvée']);
        }
    }
}
