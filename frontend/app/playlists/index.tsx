// src/screens/playlist.tsx

import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Pressable,
  StyleSheet,
  Text,
  View
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { getPlaylist, Playlist } from '../../src/api/api';
import Header from '../../src/components/Header';
import NowPlayingBanner from '../../src/components/NowPlayingBanner';

export default function PlaylistsScreen() {
  const [playlists, setPlaylists] = useState<Playlist[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingSync, setLoadingSync] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);

  const {
    isAuthenticated,
    loadingAuth,
    errorAuth,
    userProfile,
    loadingProfile,
  } = useAuth();

  const router = useRouter();

  const loadPlaylists = async () => {
    setLoading(true);
    try {
      const pl = await getPlaylist();   // <-- API call pour les playlists
      setPlaylists(pl);
    } catch (err: any) {
      Alert.alert('Erreur', err.message || 'Impossible de charger les playlists.');
    } finally {
      setLoading(false);
      setLoadingSync(false);
    }
  };

  useEffect(() => {
    loadPlaylists();
  }, []);

  const handleSync = async () => {
    setLoadingSync(true);
    await loadPlaylists();
    Alert.alert('Succès', 'Playlists synchronisées depuis Spotify !');
  };

  if (loading || loadingAuth) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size={48} color="#333" />
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <Header
        onSync={handleSync}
        loadingSync={loadingSync}
        onAdd={() => setModalVisible(true)}
        userProfile={userProfile}
        profileLoading={loadingProfile}
      />

      <FlatList
        data={playlists}
        keyExtractor={item => item.spotify_id}
        contentContainerStyle={styles.listContent}
        renderItem={({ item }) => (
          <Pressable
            onPress={() => router.push(`/playlists/${item.spotify_id}`)} // navigation dynamique
            style={styles.playlistItem}
          >
            {item.image ? (
              <Image source={{ uri: item.image }} style={styles.playlistImage} />
            ) : (
              <View style={styles.playlistImagePlaceholder}>
                <Text style={{ color: '#888' }}>🎵</Text>
              </View>
            )}
            <View style={styles.info}>
              <Text style={styles.playlistTitle}>{item.nom}</Text>
              <Text style={styles.playlistOwner}>
                Propriétaire : {item.owner}
              </Text>
              <Text style={styles.playlistTracks}>
                {item.tracks_count} titres
              </Text>
            </View>
          </Pressable>
        )}
        ListEmptyComponent={
          <View style={styles.empty}>
            <Text>Aucune playlist synchronisée.</Text>
          </View>
        }
      />

      <NowPlayingBanner
        title="Dawn"
        artist="Artist Name"
        imageUri="https://via.placeholder.com/50"
        onPlayPause={() => console.log('Play')}
        onNext={() => console.log('Next')}
        onPrevious={() => console.log('Previous')}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#fff' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  listContent: { paddingBottom: 100, paddingTop: 16 },
  playlistItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderColor: '#eee',
    gap: 14,
  },
  playlistImage: {
    width: 56,
    height: 56,
    borderRadius: 10,
    backgroundColor: '#eee',
  },
  playlistImagePlaceholder: {
    width: 56,
    height: 56,
    borderRadius: 10,
    backgroundColor: '#eee',
    justifyContent: 'center',
    alignItems: 'center',
  },
  info: { flex: 1, justifyContent: 'center' },
  playlistTitle: { fontSize: 16, fontWeight: 'bold' },
  playlistOwner: { color: '#888', fontSize: 13 },
  playlistTracks: { color: '#666', fontSize: 12 },
  empty: { alignItems: 'center', padding: 32 },
});
