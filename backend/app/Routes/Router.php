<?php
// app/Routes/Router.php

// Gestion de la connexion à la BDD
require_once __DIR__ . '/../../config/database.php';

// Chargement des contrôleurs
require_once __DIR__ . '/../Controllers/SpotifyController.php';
require_once __DIR__ . '/../Controllers/PlaylistController.php';
require_once __DIR__ . '/../Controllers/MusiqueController.php';
require_once __DIR__ . '/../Controllers/YoutubeController.php';

class Router
{
    public function handleRequest()
    {
        // Instanciation unique de la connexion PDO
        $db   = getDatabaseConnection();   
        $page = $_GET['page'] ?? null;

        switch ($page) {
            case 'connection':
                (new SpotifyController())->connection();
                break;

            case 'spotifyCallback':
                (new SpotifyController())->spotifyCallback();
                break;

            case 'fetchPlaylists':
                (new PlaylistController($db))->fetchAndStoreFromSpotify();
                break;

            case 'allPlaylists':
                (new PlaylistController($db))->getAllSpotify();
                break;

            case 'fetchMusics':
                (new MusiqueController($db))
                    ->fetchAndStoreMusicsFromSpotify($_GET['playlistId'] ?? null);
                break;

            case 'musiqueByPlaylist':
                (new MusiqueController($db))
                    ->musiqueByPlaylist($_GET['playlistId'] ?? null);
                break;

            case 'allMusique':
                (new MusiqueController($db))->allMusique();
                break;

            case 'setup':
                require_once __DIR__ . '/../../setup.php';
                break;

            case 'fetchYoutube':
                (new YoutubeController($db))->fetchAndStore();
                break;

            case 'fetchYoutubeAll':
                (new YoutubeController($db))->fetchAndStoreAll();
                break;

            case 'getMusiqueNoYoutube':
                (new YoutubeController($db))->getMusiqueWithoutYoutube();
                break;

            case 'getYoutubeIds':
                (new YoutubeController($db))->getYoutubeIds();
                break;

            default:
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Route non trouvée']);
                break;
        }
    }
}
