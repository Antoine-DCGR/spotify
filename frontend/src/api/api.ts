// src/api.ts

import { fetchWithJwt } from '@/utils/fetchWithJwt';
import * as SecureStore from 'expo-secure-store';
import { JWT_KEY } from '../config'; // ← chemin correct

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
  artiste: string;
  album: string;
  duree_ms: number;
  created_at: string;
};

export interface Artist {
  id: number;
  spotify_artist_id: string;
  nom: string;
  genre: string | null;
  created_at: string;
}

/**
 * Récupère le profil Spotify depuis ton backend.
 * - Si pas de JWT stocké, renvoie `null`.
 * - Si 401, tente un refresh puis réessaie.
 * - Vérifie le Content-Type avant JSON.parse.
 */
export async function getSpotifyProfile(): Promise<any | null> {
  console.log('🔍 getSpotifyProfile démarré');

  // 1) Récupère le JWT
  const jwt = await SecureStore.getItemAsync(JWT_KEY);
  console.log('🔑 JWT extrait :', jwt);
  if (!jwt) {
    console.error('🚫 Pas de JWT, on stoppe getSpotifyProfile');
    return null;
  }

  try {
    // 2) Premier appel
    console.log('🚀 Appel GET /spotify/me');
    let res = await fetchWithJwt('/spotify/me');
    console.log('📥 Statut réponse initiale :', res.status);

    // 3) Si JWT expiré : on tente le refresh
    if (res.status === 401) {
      console.warn('⚠️ JWT expiré, tentative de refresh');
      const refreshRes = await fetchWithJwt('/spotify/refresh', { method: 'POST' });
      console.log('📥 Statut /spotify/refresh :', refreshRes.status);
      const refreshText = await refreshRes.text().catch(() => '');
      console.log('💬 Body /spotify/refresh :', refreshText);

      if (!refreshRes.ok) {
        throw new Error(`Refresh JWT KO – ${refreshText}`);
      }
      const { token: newToken } = JSON.parse(refreshText);
      console.log('🔄 Nouveau token reçu :', newToken);
      await SecureStore.setItemAsync(JWT_KEY, newToken);

      // on réessaie avec le nouveau token
      res = await fetchWithJwt('/spotify/me');
      console.log('📥 Statut réponse après refresh :', res.status);
    }

    // 4) Lecture du body
    const ct = res.headers.get('content-type') || '';
    const text = await res.text().catch(() => '');
    console.log('📋 Content-Type reçu :', ct);
    console.log('💥 BODY BRUT reçu :', text);

    // 5) Vérification JSON
    if (!ct.includes('application/json')) {
      throw new Error(`Réponse inattendue (pas JSON) : ${text}`);
    }

    // 6) Erreur HTTP
    if (!res.ok) {
      let errMsg = 'Erreur HTTP';
      try {
        const err = JSON.parse(text);
        errMsg = err.error || err.message || errMsg;
      } catch {}
      throw new Error(errMsg);
    }

    // 7) Tout va bien
    const data = JSON.parse(text);
    console.log('✅ getSpotifyProfile réussi :', data);
    return data;

  } catch (err) {
    const msg = (err as Error).message;
    console.error('❌ Erreur dans getSpotifyProfile :', msg);

    // Si le back a renvoyé “Utilisateur introuvable”, on efface la session et on revient à null
    if (msg.includes('Utilisateur introuvable')) {
      console.warn('🔒 Session morte détectée, suppression du JWT');
      await SecureStore.deleteItemAsync(JWT_KEY);
      return null;
    }

    throw err;
  }
}

/**
 * Synchronise les playlists depuis ton backend.
 */
export async function fetchPlaylists(): Promise<Playlist[]> {
  const res = await fetchWithJwt('/playlist/fetchPlaylists');
  if (!res.ok) {
    throw new Error(`HTTP ${res.status} - ${res.statusText}`);
  }
  const json = await res.json();
  return json.playlists as Playlist[];
}

export async function getArtistes(): Promise<Artist[]> {
  const res = await fetchWithJwt('/artiste/getArtistes');
  if (!res.ok) {
    throw new Error(`HTTP ${res.status} - ${res.statusText}`);
  }
  const json = await res.json();
  return json.playlists as Artist[];
}


export async function getPlaylist(): Promise<Playlist[]> {
  const res = await fetchWithJwt('/playlist/getPlaylists');
  if (!res.ok) {
    throw new Error(`HTTP ${res.status} - ${res.statusText}`);
  }
  const json = await res.json();
  return json.playlists as Playlist[];
}