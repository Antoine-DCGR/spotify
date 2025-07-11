import { useLocalSearchParams } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    FlatList,
    StyleSheet,
    Text,
    View,
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { getMusicsByPlaylist, Music } from '../../src/api/api'; // <-- On importe la fonction
import Header from '../../src/components/Header';
import NowPlayingBanner from '../../src/components/NowPlayingBanner';

export default function PlaylistDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [musics, setMusics] = useState<Music[]>([]);
  const [loading, setLoading] = useState(true);

  const {
    userProfile,
    loadingProfile,
    loadingAuth,
  } = useAuth();

  useEffect(() => {
    console.log('playlist id:', id)
    if (!id) return;
    setLoading(true);
    getMusicsByPlaylist(id)
      .then(setMusics)
      .catch(err => Alert.alert('Erreur', err.message))
      .finally(() => setLoading(false));
  }, [id]);

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
        onSync={() => {}}
        loadingSync={false}
        onAdd={() => {}}
        userProfile={userProfile}
        profileLoading={loadingProfile}
      />

      <Text style={styles.title}>Musiques de la playlist</Text>
      <FlatList
        data={musics}
        keyExtractor={item => item.spotify_track_id || String(item.id)}
        renderItem={({ item }) => (
          <View style={styles.musicItem}>
            <Text style={styles.musicTitle}>{item.titre}</Text>
            <Text style={styles.musicDuration}>{Math.round(item.duree_ms / 1000)} sec</Text>
          </View>
        )}
        ListEmptyComponent={
          <View style={styles.empty}>
            <Text>Aucune musique trouvée.</Text>
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
  title: { fontSize: 22, fontWeight: 'bold', margin: 18 },
  musicItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 18,
    borderBottomWidth: 1,
    borderColor: '#eee',
  },
  musicTitle: { fontSize: 16 },
  musicDuration: { color: '#888' },
  empty: { alignItems: 'center', padding: 32 },
});
