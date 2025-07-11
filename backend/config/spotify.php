<?php
// app/Config/spotify.php

return [
    // Identifiants OAuth
    'client_id'       => getenv('CLIENT_SPOTIFY_ID'),
    'client_secret'   => getenv('CLIENT_SPOTIFY_SECRET'),

    // Flux PKCE
    'redirect_uri'    => getenv('SPOTIFY_REDIRECT_URI'),
    'authorize_url'   => 'https://accounts.spotify.com/authorize',
    'token_url'       => 'https://accounts.spotify.com/api/token',
    'scopes'          => 'user-read-private user-read-email',

    // Durées
    'jwt_ttl'         => 600,    // en secondes (10 min)
    'token_cache_ttl' => 300,    // en secondes (5 min avant de refresh)
];
