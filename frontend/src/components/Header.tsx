// src/components/Header.tsx

import { Ionicons } from '@expo/vector-icons';
import { useRouter, useSegments } from 'expo-router';
import React, { useEffect } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';

export interface HeaderProps {
  onSync: () => void;
  loadingSync: boolean;
  onAdd: () => void;
  userProfile: any;
  profileLoading: boolean;
  profileError?: string | null;
}

const TITLES: Record<string, string> = {
  home: 'Accueil',
  playlists: 'Playlists',
  titres: 'Titres',
  albums: 'Albums',
  artistes: 'Artistes',
  login: 'Connexion',
};

export default function Header({
  onSync,
  loadingSync,
  onAdd,
  userProfile,
  profileLoading,
  profileError,
}: HeaderProps) {
  const segments = useSegments();
  const router = useRouter();
  const current = segments[segments.length - 1] ?? '';
  const title = TITLES[current] ?? 'Page';

  const {
    authenticate,
    logout,
    isAuthenticated,
    loadingAuth,
    errorAuth,
  } = useAuth();

  // Affiche les erreurs d’authentification
  useEffect(() => {
    if (errorAuth) {
      Alert.alert('Erreur Spotify', errorAuth);
    }
  }, [errorAuth]);

  // Affiche les erreurs de profil
  useEffect(() => {
    if (profileError) {
      Alert.alert('Erreur Profil', profileError);
    }
  }, [profileError]);

  return (
    <View style={styles.header}>
      <Text style={styles.title}>{title}</Text>
      <View style={styles.actions}>
        <Pressable onPress={onSync} style={styles.iconButton}>
          {loadingSync ? (
            <ActivityIndicator size={24} color="gray" />
          ) : (
            <Ionicons name="refresh" size={24} color="#333" />
          )}
        </Pressable>
        <Pressable onPress={onAdd} style={styles.actionButton}>
          <Text style={styles.actionText}>+</Text>
        </Pressable>

        {loadingAuth || profileLoading ? (
          <ActivityIndicator size={24} style={{ marginLeft: 8 }} />
        ) : isAuthenticated && userProfile ? (
           <Pressable
 onPress={() => router.push('/logout')}
 style={[styles.actionButton, { backgroundColor: '#d1ffe0' }]} >
  <Text style={[styles.actionText, { color: '#237150' }]}>
    {userProfile.display_name || userProfile.id}
  </Text>
</Pressable>

        ) : (
          <Pressable onPress={authenticate} style={styles.actionButton}>
            <Text style={styles.actionText}>Connexion Spotify</Text>
          </Pressable>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    marginTop: 30,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 5,
  },
  title: {
    fontSize: 32,
    fontWeight: 'bold',
  },
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  iconButton: {
    marginRight: 12,
  },
  actionButton: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 8,
    backgroundColor: '#eee',
    marginLeft: 8,
  },
  actionText: {
    fontWeight: '600',
  },
});
