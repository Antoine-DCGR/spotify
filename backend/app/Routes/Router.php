<?php
// app/Routes/Router.php
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../Controllers/SpotifyController.php';
require_once __DIR__ . '/../Controllers/PlaylistController.php';
require_once __DIR__ . '/../Controllers/MusiqueController.php';
require_once __DIR__ . '/../Controllers/YoutubeController.php';
require_once __DIR__ . '/../Controllers/AlbumController.php';
require_once __DIR__ . '/../Controllers/ArtisteController.php';
require_once __DIR__ . '/../Controllers/AudioDownloadController.php';
require_once __DIR__ . '/../Controllers/ManualController.php';

// ... (tous les require_once comme dans ton code)

function routeMatch($pattern, $uri, &$params = []) {
    $pattern = trim($pattern, '/');
    $uri = trim($uri, '/');
    $pattern = str_replace('/', '\/', $pattern);
    $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^\/]+)', $pattern);
    if (preg_match('/^' . $pattern . '$/', $uri, $matches)) {
        foreach ($matches as $key => $value) {
            if (!is_int($key)) $params[$key] = $value;
        }
        return true;
    }
    return false;
}

class Router
{
    public function handleRequest()
    {
        $db = getDatabaseConnection();
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        error_log("DEBUG URI: $uri | METHOD: $method");

        // ==================== PLAYLISTS ====================
        // Liste toutes les playlists
        $params = [];
        if ($method === 'GET' && routeMatch('/api/playlist/getPlaylists', $uri, $params)) {
            (new PlaylistController($db))->getPlaylists();
            return;
        }

        // Détail d'une playlist
        $params = [];
        if ($method === 'GET' && routeMatch('/api/playlist/getPlaylists/{id}', $uri, $params)) {
            (new PlaylistController($db))->show($params['id']);
            return;
        }

         // Créer une playlist
        if ($method === 'GET' && routeMatch('/api/playlist/addPlaylists', $uri, $params)) {
            (new PlaylistController($db))->store();
            return;
        }
        // Liste des musiques d'une playlist
         $params = [];
        if ($method === 'GET' && routeMatch('/api/playlist/{playlistId}/getMusiques', $uri, $params)) {
            (new PlaylistController($db))->getMusics($params['playlistId']);
            return;
        }

        // Ajouter une musique à une playlist
        $params = [];
        if ($method === 'GET' && routeMatch('/api/playlists/{id}/addMusiques', $uri, $params)) {
            (new PlaylistController($db))->addMusic($params['playlistid']);
            return;
        }

        // Supprimer une musique d'une playlist
        $params = [];
        if ($method === 'DELETE' && routeMatch('/api/playlists/{playlistid}/deleteMusiques/{musicId}', $uri, $params)) {
            (new PlaylistController($db))->removeMusic($params['playlistid'], $params['musicId']);
            return;
        }

        // Récupère et stocke toutes les playlists Spotify de l'utilisateur connecté
        if ($method === 'GET' && routeMatch('/api/playlist/fetchPlaylists', $uri)) {
            (new PlaylistController($db))->fetchAndStoreFromSpotify($db);
            return;
        }

        // ==================== MUSIQUES ====================
        // Liste toutes les musiques
        $params = [];
        if ($method === 'GET' && routeMatch('/api/musique/getMusiques', $uri, $params)) {
            (new MusiqueController($db))->allMusique();
            return;
        }

        // Détail d'une musique par id
        $params = [];
        if ($method === 'GET' && routeMatch('/api/getMusiques/{id}', $uri, $params)) {
            (new MusiqueController($db))->show($params['id']);
            return;
        }

          // Récupère et stocke toutes les musiques d'une playlist Spotify donnée
        $params = [];
        if ($method === 'GET' && routeMatch('/api/musique/{playlistId}/addmusique', $uri, $params)) {
            (new MusiqueController($db))->fetchAndStoreMusicsFromSpotify($params['playlistId']);
            return;
        }

        // ==================== ALBUMS ====================
        // Liste tous les albums
        $params = [];
        if ($method === 'GET' && routeMatch('/api/getAlbums', $uri, $params)) {
            (new AlbumController($db))->index();
            return;
        }

        // Détail d'un album
        $params = [];
        if ($method === 'GET' && routeMatch('/api/getAlbums/{id}', $uri, $params)) {
            (new AlbumController($db))->show($params['id']);
            return;
        }

        // ==================== ARTISTES ====================
        // Liste tous les artistes
        $params = [];
        if ($method === 'GET' && routeMatch('/api/getArtistse', $uri, $params)) {
            (new ArtisteController($db))->index();
            return;
        }

        // Détail d'un artiste
        $params = [];
        if ($method === 'GET' && routeMatch('/api/getArtistes/{id}', $uri, $params)) {
            (new ArtisteController($db))->show($params['id']);
            return;
        }

        // ==================== SPOTIFY ====================
        // Démarre la connexion Spotify (OAuth)
        if ($method === 'GET' && routeMatch('/api/spotify/connection', $uri)) {
            (new SpotifyController($db))->connection();
            return;
        }

        // Callback OAuth Spotify
        if ($method === 'GET' && routeMatch('/api/spotify/spotifyCallback', $uri)) {
            (new SpotifyController($db))->spotifyCallback();
            return;
        }

        // ==================== AUDIODOWNLOAD ====================
        // Télécharge le MP3 pour une musique (par id)
        $params = [];
        if ($method === 'GET' && routeMatch('/api/audio/musics/{musiqueId}', $uri, $params)) {
            (new AudioDownloadController($db))->fetchByMusique($params['musiqueId']);
            return;
        }

        // Télécharge le MP3 pour une vidéo YouTube donnée
        $params = [];
        if ($method === 'GET' && routeMatch('/api/audio/youtube/{videoId}', $uri, $params)) {
            (new AudioDownloadController($db))->fetch($params['videoId']);
            return;
        }

       
       

        // Télécharge tous les MP3s (batch)
        if ($method === 'GET' && routeMatch('/api/audio/download/all', $uri)) {
            (new AudioDownloadController($db))->fetchAllDownload();
            return;
        }

        // Télécharge un seul MP3 (batch unitaire)
        if ($method === 'GET' && routeMatch('/api/audio/download/one', $uri)) {
            (new AudioDownloadController($db))->fetchSingle();
            return;
        }

        // Liste toutes les musiques sans audio téléchargé
        if ($method === 'GET' && routeMatch('/api/audio/getMissing/list', $uri)) {
            (new AudioDownloadController($db))->listMissing();
            return;
        }

        // ==================== YOUTUBE ====================
 
       
        // Liste tous les IDs Youtube trouvés
        if ($method === 'GET' && routeMatch('/api/youtube/getYoutubeIds', $uri)) {
            (new YoutubeController($db))->getYoutubeIds();
            return;
        }

        // Liste les musiques sans id Youtube associé
        if ($method === 'GET' && routeMatch('/api/youtube/getMusiqueWithoutYoutubeId', $uri)) {
            (new YoutubeController($db))->getMusiqueWithoutYoutubeId();
            return;
        }
        // Lance la récupération de tous les ids Youtube manquants
        if ($method === 'GET' && routeMatch('/api/youtube/fetchYoutubeIdAll', $uri)) {
            (new YoutubeController($db))->fetchYoutubeIdAll();
            return;
        }


    // ==================== AJOUT MANUEL ====================
        // Affiche le formulaire d'ajout manuel (si besoin en front)
        if ($method === 'GET' && routeMatch('/api/manual/addform', $uri)) {
            (new ManualController($db))->showAddForm();
            return;
        }

        // Ajoute une musique manuellement
        if ($method === 'GET' && routeMatch('/api/manual/addMusique', $uri)) {
            (new ManualController($db))->addManual();
            return;
        }

        // ==================== SETUP (BATCH DB, DEV SEULEMENT) ====================
        // Initialise la base de données (dev)
        $params = [];
        if (($method === 'GET' || $method === 'GET') && routeMatch('/api/setup', $uri, $params)) {
            require_once __DIR__ . '/../../setup.php';
            echo json_encode(['message' => 'Setup completed successfully']);
            return;
        }

        // ==================== 404 NOT FOUND (catch-all) ====================
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}