export const SPOTIFY = {
  clientId: 'VOTRE_CLIENT_ID_SPOTIFY',
  redirectUrl: 'myapp://oauth',
  scopes: [
    'user-read-email',
    'playlist-read-private',
    'playlist-modify-private',
    
  ],
  authEndpoint: 'https://accounts.spotify.com/authorize',
  tokenEndpoint: 'https://accounts.spotify.com/api/token',
};

export const API = {
  baseUrl: 'https://api.votredomaine.com',  // ou http://10.0.2.2:8000 en dev
};
