// frontend/src/api.ts

export const API_URL = "http://192.168.1.168:8000"; // Mets ton IP locale ici

export type Playlist = {
  spotify_id: string;   // ID Spotify (unique)
  nom: string;
  description: string | null;
  owner: string;
  image: string | null;
  tracks_count: number;
  is_public: boolean;
  created_at: string;   // ou Date si vous faites un parsing manuel
};

export type Music = {
  id: number;            // clé primaire interne (AUTO_INCREMENT)
  spotify_track_id: string;
  titre: string;
  artiste: string;
  album: string;
  duree_ms: number;
  created_at: string;    // ou Date selon votre parsing
};

export interface Artist {
  id: number;                  // identifiant interne
  spotify_artist_id: string;   // ID Spotify
  nom: string;                 // Nom de l'artiste
  genre: string | null;        // Genre musical (optionnel)
  created_at: string;          // Date de création en base
}

/**
 * Récupère la liste des artistes formatée par l'API
 * L'API renvoie { artists: Artist[] }
 */
export async function getArtistes(): Promise<Artist[]> {
  const res = await fetch(`${API_URL}/artiste/getArtistes`);
  if (!res.ok) {
    throw new Error(`Erreur getArtistes: ${res.statusText}`);
  }
  const json = await res.json();
  return json.artists as Artist[];
}

/**
 * Récupère la liste des playlists formatée par l'API
 * L'API renvoie { playlists: Playlist[] }
 */
export async function getPlaylists(): Promise<Playlist[]> {
  const res = await fetch(`${API_URL}/playlist/getPlaylists`);
  if (!res.ok) {
    throw new Error(`Erreur getPlaylists: ${res.statusText}`);
  }
  const json = await res.json();
  return json.playlists as Playlist[];
}

/**
 * Envoie le refresh token à votre backend pour obtenir un nouveau access_token
 * Backend attend POST /api/spotify/refresh { refresh_token: string }
 */
export async function postRefreshToken(refreshToken: string): Promise<{
  access_token: string;
  refresh_token?: string;
  expires_in: number;
}> {
  const res = await fetch(`${API_URL}/api/spotify/refresh`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ refresh_token: refreshToken }),
  });
  if (!res.ok) {
    throw new Error(`Erreur postRefreshToken: ${res.statusText}`);
  }
  return res.json();
}

/**
 * Récupère vos playlists directement depuis l'API Spotify
 */
export async function fetchSpotifyPlaylists(accessToken: string): Promise<{
  items: any[]; // vous pouvez typer plus précisément selon la réponse Spotify
}> {
  const res = await fetch("https://api.spotify.com/v1/me/playlists", {
    headers: { Authorization: `Bearer ${accessToken}` },
  });
  if (!res.ok) {
    throw new Error(`Erreur fetchSpotifyPlaylists: ${res.statusText}`);
  }
  return res.json();
}
