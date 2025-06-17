<?php
// app/Routes/Router.php

// Gestion de la connexion à la BDD
require_once __DIR__ . '/../../config/database.php';

// Chargement des contrôleurs
require_once __DIR__ . '/../Controllers/SpotifyController.php';
require_once __DIR__ . '/../Controllers/PlaylistController.php';
require_once __DIR__ . '/../Controllers/MusiqueController.php';
require_once __DIR__ . '/../Controllers/YoutubeController.php';
require_once __DIR__ . '/../Controllers/AudioDownloadController.php';

class Router
{
    public function handleRequest()
    {
        // Instanciation unique de la connexion PDO
        $db   = getDatabaseConnection();
        $page = $_GET['page'] ?? null;

        switch ($page) {

            // OAuth Spotify :
            // Démarre la connexion OAuth vers Spotify
            case 'connection':
                (new SpotifyController())->connection();
                break;

            // Callback OAuth Spotify :
            // Point d’entrée que Spotify appelle après authentification
            case 'spotifyCallback':
                (new SpotifyController())->spotifyCallback();
                break;

            // Récupération des playlists depuis Spotify :
            // Fetch et stockage des playlists de l’utilisateur connecté
            case 'fetchPlaylists':
                (new PlaylistController($db))->fetchAndStoreFromSpotify();
                break;

            // Liste de toutes les playlists Spotify stockées en BDD
            case 'allPlaylists':
                (new PlaylistController($db))->getAllSpotify();
                break;

            // Récupération des musiques d’une playlist :
            // Fetch et stockage des morceaux d’une playlist Spotify (param playlistId)
            case 'fetchMusics':
                (new MusiqueController($db))
                    ->fetchAndStoreMusicsFromSpotify($_GET['playlistId'] ?? null);
                break;

            // Liste des morceaux d’une playlist donnée (param playlistId)
            case 'musiqueByPlaylist':
                (new MusiqueController($db))
                    ->musiqueByPlaylist($_GET['playlistId'] ?? null);
                break;

            // Liste de toutes les musiques stockées
            case 'allMusique':
                (new MusiqueController($db))->allMusique();
                break;
           


            // Setup initial :
            // Création de la base et de la table via setup.php
            case 'setup':
                require_once __DIR__ . '/../../setup.php';
                break;

            // YouTube integration (single) :
            // Recherche et sauvegarde de l’ID YouTube pour une musique (POST JSON { "id": … })
            case 'fetchYoutube':
                (new YoutubeController($db))->fetchAndStore();
                break;

            // YouTube integration (batch) :
            // Recherche et sauvegarde de l’ID YouTube pour toutes les musiques sans vidéo
            case 'fetchYoutubeAll':
                (new YoutubeController($db))->fetchAndStoreAll();
                break;

            // Liste paginée des musiques sans YouTube ID (params page, per_page)
            case 'getMusiqueNoYoutube':
                (new YoutubeController($db))->getMusiqueWithoutYoutube();
                break;

            // Liste de tous les YouTube IDs stockés en BDD
            case 'getYoutubeIds':
                (new YoutubeController($db))->getYoutubeIds();
                break;

            // Téléchargement audio par musique BDD :
            // Télécharge le MP3 pour une musique existante (param musiqueId) et renvoie l’URL
            case 'audio.fetchByMusique':
                (new AudioDownloadController($db))
                    ->fetchByMusique((int)($_GET['musiqueId'] ));
                break;

            // Téléchargement audio générique par videoId YouTube :
            // Télécharge le MP3 directement depuis l’ID YouTube et renvoie l’URL
            case 'audio.fetch':
                (new AudioDownloadController($db))
                    ->fetchByVideo((string)($_GET['videoId'] ?? ''));
                break;

            case 'audio.fetchMissing':
    (new AudioDownloadController($db))->fetchMissing();
    break;
case 'audio.fetchAllParallel':
    (new AudioDownloadController($db))->fetchAllParallel();
    break;
    case 'audio.fetchSingle':
    (new AudioDownloadController($db))->fetchSingle();
    break;
    case 'fetchYoutubeAllPaginated':
    (new YoutubeController($db))->fetchYoutubeAllPaginated();
    break;

case 'audio.listMissing':
    (new AudioDownloadController($db))->listMissingAudio();
    break;
            // Route inconnue
            default:
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Route non trouvée']);
                break;
        }
    }
}
