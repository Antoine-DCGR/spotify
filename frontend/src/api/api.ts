// src/api/api.ts

import { fetchWithJwt } from '@/utils/fetchWithJwt';
import * as SecureStore from 'expo-secure-store';
import { JWT_KEY } from '../config';

// Types
export type Playlist = {
  spotify_id: string;
  nom: string;
  description: string | null;
  owner: string;
  image: string | null;
  tracks_count: number;
  is_public: boolean;
  created_at: string;
};

export type Music = {
  id: number;
  spotify_track_id: string;
  titre: string;
  artistes: string[];
  album: string;
  duree_ms: number;
  created_at: string;
  album_image: string | null;
  id_youtube?: string;
  audio_path?: string;
};

export interface Artist {
  id: number;
  spotify_artist_id: string;
  nom: string;
  genre: string | null;
  created_at: string;
}

/**
 * Récupère le profil Spotify.
 */
export async function getSpotifyProfile(): Promise<any | null> {
  const jwt = await SecureStore.getItemAsync(JWT_KEY);
  if (!jwt) return null;

  let res = await fetchWithJwt('/spotify/me');
  if (res.status === 401) {
    const refreshRes = await fetchWithJwt('/spotify/refresh', { method: 'POST' });
    if (!refreshRes.ok) throw new Error(`Refresh KO – ${await refreshRes.text()}`);
    const { token } = await refreshRes.json();
    await SecureStore.setItemAsync(JWT_KEY, token);
    res = await fetchWithJwt('/spotify/me');
  }

  const ct = res.headers.get('content-type') ?? '';
  const text = await res.text().catch(() => '');
  if (!ct.includes('application/json')) throw new Error(`Non-JSON: ${text}`);
  if (!res.ok) {
    let err = 'Erreur HTTP';
    try { err = JSON.parse(text).error || JSON.parse(text).message; } catch {}
    throw new Error(err);
  }
  return JSON.parse(text);
}

/**
 * Synchronise les playlists.
 */
export async function syncAll(): Promise<any> {
  const res = await fetchWithJwt('/sync/syncAll', { method: 'POST' });
  const text = await res.text().catch(() => '');
  if (!res.ok) {
    let err = 'Erreur sync';
    try { err = JSON.parse(text).error; } catch {}
    throw new Error(err);
  }
  return JSON.parse(text);
}

/**
 * Liste des artistes.
 */
export async function getArtistes(): Promise<Artist[]> {
  const res = await fetchWithJwt('/artiste/getArtistes');
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return (await res.json()).playlists as Artist[];
}

/**
 * Liste des playlists.
 */
export async function getPlaylist(): Promise<Playlist[]> {
  const res = await fetchWithJwt('/playlist/getPlaylists');
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return (await res.json()).playlists as Playlist[];
}

/**
 * Logout Spotify.
 */
export async function logoutSpotify(): Promise<void> {
  const jwt = await SecureStore.getItemAsync(JWT_KEY);
  if (!jwt) return;
  const res = await fetchWithJwt('/spotify/logout', { method: 'POST' });
  if (!res.ok) {
    const t = await res.text().catch(() => '');
    throw new Error(t || 'Erreur logout');
  }
  await SecureStore.deleteItemAsync(JWT_KEY);
}

/**
 * Récupère les musiques d'une playlist.
 */
export async function getMusicsByPlaylist(playlistId: string): Promise<Music[]> {
  const res = await fetchWithJwt(`/musique/getMusiqueByPlaylist/${playlistId}`);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const json = await res.json();
  return (json.musique as any[]).map(m => ({
    ...m,
    artistes:
      typeof m.artistes === 'string'
        ? m.artistes
            .split(',')
            .map((a: string) => a.trim())
            .filter((a: string) => a.length > 0)
        : []
  }));
}

/**
 * Télécharge un morceau et ne parse que le JSON final.
 */
export async function downloadTrack(id: number): Promise<{ audio_path: string; audio_url: string }> {
  const res = await fetchWithJwt(`/audio/download/music/${id}`);
  const text = await res.text().catch(() => '');
  console.log('📋 Content-Type reçu :', res.headers.get('content-type'));
  console.log('💥 BODY BRUT reçu :', text);

  if (!res.ok) {
    throw new Error(`Erreur ${res.status}: ${text}`);
  }

  // On extrait la dernière ligne JSON
  const lines = text.trim().split(/\r?\n/);
  const jsonLine = [...lines].reverse().find(l => /^\s*\{/.test(l));
  if (!jsonLine) {
    throw new Error('Réponse invalide du serveur');
  }
  return JSON.parse(jsonLine);
}

/**
 * Récupère et stocke l'ID YouTube pour un morceau.
 */
export async function fetchYoutubeIdForTrack(id: number): Promise<{ videoId: string }> {
  const res = await fetchWithJwt('/youtube/fetch', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  });
  const text = await res.text().catch(() => '');
  console.log('📋 Content-Type reçu :', res.headers.get('content-type'));
  console.log('💥 BODY BRUT reçu :', text);

  if (!res.ok) {
    throw new Error(`YouTube fetch KO – ${res.status}: ${text}`);
  }
  return JSON.parse(text);
}

/**
 * Fetch & store playlists depuis Spotify.
 */
export async function fetchPlaylists(): Promise<Playlist[]> {
  const res = await fetchWithJwt('/playlist/fetchPlaylists');
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return (await res.json()).playlists as Playlist[];
}

/**
 * Ajoute toutes les musiques de toutes les playlists.
 */
export async function fetchAndStoreMusicsForAllPlaylistsFromSpotify(): Promise<any> {
  const res = await fetchWithJwt('/musique/addMusiqueByPlaylists');
  const text = await res.text().catch(() => '');
  console.log('📋 Content-Type reçu :', res.headers.get('content-type'));
  console.log('💥 BODY BRUT reçu :', text);

  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return JSON.parse(text);
}
