export const API_URL = "http://192.168.1.10:8000"; // Mets ton IP locale ici

export type Playlist = {
  id: number;
  nom: string;
};

export type Music = {
  id: number;
  artiste: string;
  album: string;
  titre: string;
  playlist_id: number;
};

export async function getPlaylists(): Promise<Playlist[]> {
  const res = await fetch(`${API_URL}/playlist/index`);
  return res.json();
}

export async function getMusicsByPlaylist(playlistId: number): Promise<Music[]> {
  const res = await fetch(`${API_URL}/musique/byplaylist?playlist_id=${playlistId}`);
  return res.json();
}
