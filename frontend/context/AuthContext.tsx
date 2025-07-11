// src/context/AuthContext.tsx

import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { useSpotifyAuth } from '../hooks/useSpotifyAuth';
import { fetchPlaylists, getSpotifyProfile } from '../src/api/api'; // adjust path if needed

interface AuthContextValue {
  isAuthenticated: boolean;
  loadingAuth: boolean;
  errorAuth: string | null;
  userProfile: any | null;
  loadingProfile: boolean;
  authenticate: () => Promise<void>;
  logout: () => Promise<void>;
  syncPlaylists: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  // 1) Core auth state from hook
  const {
    authenticate,
    logout,
    isAuthenticated,
    loadingAuth,
    errorAuth,
    refreshKey,
    triggerRefresh,
  } = useSpotifyAuth();

  // 2) Load profile whenever auth or refreshKey changes
  const [userProfile, setUserProfile] = useState<any | null>(null);
  const [loadingProfile, setLoadingProfile] = useState(false);

  useEffect(() => {
    if (!isAuthenticated) {
      setUserProfile(null);
      setLoadingProfile(false);
      return;
    }
    let active = true;
    setLoadingProfile(true);
    getSpotifyProfile()
      .then(p => { if (active) setUserProfile(p) })
      .catch(() => { if (active) setUserProfile(null) })
      .finally(() => { if (active) setLoadingProfile(false) });
    return () => { active = false };
  }, [isAuthenticated, refreshKey]);

  // 3) Expose a `syncPlaylists` method too
  const syncPlaylists = async () => {
    await fetchPlaylists();
    triggerRefresh();
  };

  // 4) Memoize so children don’t re-render unnecessarily
  const value = useMemo<AuthContextValue>(() => ({
    isAuthenticated,
    loadingAuth,
    errorAuth,
    userProfile,
    loadingProfile,
    authenticate,
    logout,
    syncPlaylists,
  }), [
    isAuthenticated,
    loadingAuth,
    errorAuth,
    userProfile,
    loadingProfile,
    authenticate,
    logout,
    syncPlaylists,
  ]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = (): AuthContextValue => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be inside AuthProvider');
  return ctx;
};
