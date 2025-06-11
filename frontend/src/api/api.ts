export const API_URL = "http://192.168.1.10:8000"; // Mets ton IP locale ici

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


export async function getPlaylists(): Promise<Playlist[]> {
  const res = await fetch(`${API_URL}/index.php?page=allPlaylists`);
  const json = await res.json();
  // Si le backend renvoie { playlists: [ ... ] }, on retourne json.playlists directement
  return json.playlists as Playlist[];
}



export async function getMusicsByPlaylist(
  playlistSpotifyId: string
): Promise<Music[]> {
  // Noter qu’on passe playlistId=spotify_id (string) au backend
  const res = await fetch(
    `${API_URL}/index.php?page=musiqueByPlaylist&playlistId=${encodeURIComponent(
      playlistSpotifyId
    )}`
  );
  // Le backend renvoie { musique: [ { id, spotify_track_id, titre, … }, … ] }
  const json = await res.json();
  return json.musique as Music[];
}
