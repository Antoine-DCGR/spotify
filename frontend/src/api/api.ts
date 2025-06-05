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
  id: number;
  artiste: string;
  album: string;
  titre: string;
  playlist_id: number;
};


export async function getPlaylists(): Promise<Playlist[]> {
  const res = await fetch(`${API_URL}/index.php?page=recupPlaylists`);
  const json = await res.json();
  // Si le backend renvoie { playlists: [ ... ] }, on retourne json.playlists directement
  return json.playlists as Playlist[];
}


export async function getMusicsByPlaylist(playlistId: number): Promise<Music[]> {
  const res = await fetch(`${API_URL}/musique/byplaylist?playlist_id=${playlistId}`);
  return res.json();
}
