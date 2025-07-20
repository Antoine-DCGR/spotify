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


function routeMatch($pattern, $uri, &$params = []) {
    $pattern = trim($pattern, '/');
    $uri     = trim($uri, '/');
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
        $db     = getDatabaseConnection();
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // ——— PLAYLISTS —————————————————————————
        $params = [];
        if ($method==='GET' && routeMatch('/playlist/getPlaylists',$uri,$params)) {
            (new PlaylistController($db))->getPlaylists(); return;
        }
        if ($method==='GET' && routeMatch('/playlist/getPlaylists/{id}',$uri,$params)) {
            (new PlaylistController($db))->show($params['id']); return;
        }
        if ($method==='GET' && routeMatch('/playlist/addPlaylists',$uri)) {
            (new PlaylistController($db))->store(); return;
        }
        if ($method==='GET' && routeMatch('/playlist/{playlistId}/getMusiques',$uri,$params)) {
            (new PlaylistController($db))->getMusics($params['playlistId']); return;
        }
        if ($method==='GET' && routeMatch('/playlists/{id}/addMusiques',$uri,$params)) {
            (new PlaylistController($db))->addMusic($params['id']); return;
        }
        if ($method==='DELETE' && routeMatch('/playlists/{playlistid}/deleteMusiques/{musicId}',$uri,$params)) {
            (new PlaylistController($db))->removeMusic($params['playlistid'],$params['musicId']); return;
        }
        if ($method==='GET' && routeMatch('/playlist/fetchPlaylists',$uri)) {
            (new PlaylistController($db))->fetchAndStoreFromSpotify($db); return;
        }

     

        // ——— MUSIQUES ——————————————————————
        if ($method==='GET' && routeMatch('/musique/getMusiques',$uri,$params)) {
            (new MusiqueController($db))->allMusique(); return;
        }
        if ($method==='GET' && routeMatch('/musique/getMusiqueByPlaylist/{spotify_id}',$uri,$params)) {
            (new MusiqueController($db))->musiqueByPlaylist($params['spotify_id']); return;
        }
        if ($method==='GET' && routeMatch('/musique/getMusiques/{id}',$uri,$params)) {
            (new MusiqueController($db))->show($params['id']); return;
        }
        if ($method==='GET' && routeMatch('/musique/addMusiqueByPlaylists',$uri)) {
            (new MusiqueController($db))->fetchAndStoreMusicsAllPlaylistsFromSpotify(); return;
        }

        // ——— ALBUMS —————————————————————————
        if ($method==='GET' && routeMatch('/album/getAlbums',$uri,$params)) {
            (new AlbumController($db))->index(); return;
        }
        if ($method==='GET' && routeMatch('/album/getAlbums/{id}',$uri,$params)) {
            (new AlbumController($db))->show($params['id']); return;
        }

        // ——— ARTISTES ——————————————————————
        if ($method==='GET' && routeMatch('/artiste/getArtistes',$uri,$params)) {
            (new ArtisteController($db))->getAllArtists(); return;
        }
        if ($method==='GET' && routeMatch('/artiste/getArtistes/{id}',$uri,$params)) {
            (new ArtisteController($db))->show($params['id']); return;
        }

        // ——— SPOTIFY ——————————————————————
        if ($method==='GET' && routeMatch('/spotify/redirect',$uri,$params)) {
            (new SpotifyController())->redirectToProvider(); return;
        }
        if ($method==='POST'&& routeMatch('/spotify/spotifyCallback',$uri,$params)) {
            (new SpotifyController())->spotifyCallback(); return;
        }
        if ($method==='POST'&& routeMatch('/spotify/refresh',$uri,$params)) {
            (new SpotifyController())->refreshAccessToken(); return;
        }
        if ($method==='GET' && routeMatch('/spotify/me',$uri)) {
            (new SpotifyController())->getSpotifyMe(); return;
        }
        if ($method==='POST'&& routeMatch('/spotify/logout',$uri)) {
            (new SpotifyController())->logout(); return;
        }

        // ——— AUDIODOWNLOAD ——————————————————
        // Télécharger un seul titre (avec Youtube-ID auto-fetch)
        if ($method==='GET' && routeMatch('/audio/download/music/{musiqueId}',$uri,$params)) {
            (new AudioDownloadController($db))->downloadMusic($params['musiqueId']); return;
        }
        // Batch / pagination
        if ($method==='GET' && routeMatch('/audio/download/all',$uri)) {
            (new AudioDownloadController($db))->fetchAllDownload(); return;
        }
        if ($method==='GET' && routeMatch('/audio/download/one',$uri)) {
            (new AudioDownloadController($db))->fetchSingle(); return;  // ou alias
        }
        if ($method==='GET' && routeMatch('/audio/getMissing/list',$uri)) {
            (new AudioDownloadController($db))->listMissingAudio(); return;
        }

        // ——— YOUTUBE ——————————————————————
        if ($method==='POST'&& routeMatch('/youtube/fetch',$uri)) {
            (new YoutubeController($db))->fetchAndStore(); return;
        }
        if ($method==='GET' && routeMatch('/youtube/getYoutubeIds',$uri)) {
            (new YoutubeController($db))->getYoutubeIds(); return;
        }
        if ($method==='GET' && routeMatch('/youtube/getMusiqueWithoutYoutubeId',$uri)) {
            (new YoutubeController($db))->getMusiqueWithoutYoutubeId(); return;
        }
        if ($method==='GET' && routeMatch('/youtube/fetchYoutubeIdAll',$uri)) {
            (new YoutubeController($db))->fetchYoutubeAllPaginated(); return;
        }

        // ——— MANUEL ——————————————————————
        if ($method==='GET' && routeMatch('/manual/addform',$uri)) {
            (new ManualController($db))->showAddForm(); return;
        }
        if ($method==='GET' && routeMatch('/manual/addMusique',$uri)) {
            (new ManualController($db))->addManual(); return;
        }

        // ——— SETUP (DEV) ————————————————
        if (($method==='GET') && routeMatch('/setup',$uri,$params)) {
            require_once __DIR__ . '/../../setup.php';
            echo json_encode(['message' => 'Setup completed successfully']); return;
        }

        // 404 catch-all
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}