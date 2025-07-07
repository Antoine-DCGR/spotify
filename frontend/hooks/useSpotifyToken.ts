import { useState, useEffect } from 'react';
import { getStoredSpotifyToken, refreshSpotifyToken } from '../src/auth/spotifyAuth'; // ajustez le chemin si besoin

export function useSpotifyToken() {
  const [token, setToken] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      const stored = await getStoredSpotifyToken();
      if (!stored) return;
      let access = stored.accessToken;
      if (new Date(stored.expiresAt).getTime() < Date.now()) {
        const refreshed = await refreshSpotifyToken();
        access = refreshed.accessToken;
      }
      setToken(access);
    })();
  }, []);

  return token;
}
