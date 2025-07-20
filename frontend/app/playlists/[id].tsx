import { MaterialIcons } from '@expo/vector-icons';
import { Stack, useLocalSearchParams } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Pressable,
  SafeAreaView,
  SectionList,
  StyleSheet,
  Text,
  View
} from 'react-native';
import { useAuth } from '../../context/AuthContext';
import {
  downloadTrack,
  fetchYoutubeIdForTrack,
  getMusicsByPlaylist,
  Music
} from '../../src/api/api';
import NowPlayingBanner from '../../src/components/NowPlayingBanner';

type Section = { title: string; data: Music[] };

export default function PlaylistDetailScreen() {
  const { id, name, image } = useLocalSearchParams<{
    id: string;
    name: string;
    image: string;
  }>();
  const [musics, setMusics] = useState<Music[]>([]);
  const [loading, setLoading] = useState(true);
  const [downloaded, setDownloaded] = useState<Set<number>>(new Set());
  const [loadingIds, setLoadingIds] = useState<Set<number>>(new Set());
  const { loadingAuth } = useAuth();

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    getMusicsByPlaylist(id)
      .then(setMusics)
      .catch(err => {
        console.error(err);
        Alert.alert('Erreur', err.message);
      })
      .finally(() => setLoading(false));
  }, [id]);

  if (loading || loadingAuth) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size={48} />
      </View>
    );
  }

  // Trie alphabétique et groupement par initiale
  const sections: Section[] = musics
    .slice()
    .sort((a, b) => a.titre.localeCompare(b.titre))
    .reduce<Section[]>((acc, item) => {
      const letter = item.titre[0].toUpperCase();
      let section = acc.find(s => s.title === letter);
      if (!section) {
        section = { title: letter, data: [] };
        acc.push(section);
      }
      section.data.push(item);
      return acc;
    }, []);

  const onDownloadTrackPress = async (track: Music) => {
    setLoadingIds(prev => new Set(prev).add(track.id));
    try {
      await downloadTrack(track.id);
    } catch (err: any) {
      if (err.message.includes('Aucune vidéo trouvée')) {
        try {
          await fetchYoutubeIdForTrack(track.id);
          await downloadTrack(track.id);
        } catch (ytErr: any) {
          console.error('YouTube fetch error', ytErr);
          Alert.alert('Erreur', ytErr.message);
          setLoadingIds(prev => {
            const copy = new Set(prev);
            copy.delete(track.id);
            return copy;
          });
          return;
        }
      } else {
        console.error(err);
        Alert.alert('Erreur', err.message);
        setLoadingIds(prev => {
          const copy = new Set(prev);
          copy.delete(track.id);
          return copy;
        });
        return;
      }
    }
    setLoadingIds(prev => {
      const copy = new Set(prev);
      copy.delete(track.id);
      return copy;
    });
    
  };

  return (
    <SafeAreaView style={styles.container}>
      <Stack.Screen options={{ title: name ?? 'Playlist' }} />

      {image && (
        <Image source={{ uri: image }} style={styles.playlistCover} />
      )}

      <SectionList
        style={styles.sectionList}
        sections={sections}
        keyExtractor={item => item.spotify_track_id}
        renderSectionHeader={({ section }) => (
          <Text style={styles.sectionHeader}>{section.title}</Text>
        )}
        renderItem={({ item }) => (
          <View style={styles.musicItem}>
            {item.album_image && (
              <Image
                source={{ uri: item.album_image }}
                style={styles.albumImage}
              />
            )}
            <View style={styles.info}>
              <Text style={styles.musicTitle}>{item.titre}</Text>
              <Text style={styles.musicArtist}>
                {item.artistes.join(', ')}
              </Text>
            </View>
            {!downloaded.has(item.id) && (
              <View style={styles.downloadContainer}>
                {loadingIds.has(item.id) ? (
                  <ActivityIndicator size={20} />
                ) : (
                  <Pressable
                    style={styles.downloadBtn}
                    onPress={() => onDownloadTrackPress(item)}
                  >
                    <MaterialIcons name="file-download" size={20} />
                  </Pressable>
                )}
              </View>
            )}
          </View>
        )}
        ListEmptyComponent={() => (
          <View style={styles.empty}>
            <Text>Aucune musique trouvée.</Text>
          </View>
        )}
      />

      <NowPlayingBanner
        title="Dawn"
        artist="Artist Name"
        imageUri="https://via.placeholder.com/50"
        onPlayPause={() => {}}
        onNext={() => {}}
        onPrevious={() => {}}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container:        { flex: 1, backgroundColor: '#fff' },
  center:           { flex: 1, justifyContent: 'center', alignItems: 'center' },
  playlistCover:    { width: '100%', height: 200, resizeMode: 'cover', marginBottom: 12 },
  sectionList:      { flex: 1 },
  sectionHeader:    {
    fontSize: 18,
    fontWeight: 'bold',
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: '#f0f0f0',
  },
  musicItem:        {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderColor: '#eee',
  },
  albumImage:       {
    width: 50,
    height: 50,
    borderRadius: 4,
    marginRight: 12,
    backgroundColor: '#ccc',
  },
  info:             { flex: 1, justifyContent: 'center' },
  musicTitle:       { fontSize: 16 },
  musicArtist:      { fontSize: 14, color: '#666', marginTop: 4 },
  downloadContainer:{ width: 32, alignItems: 'center' },
  downloadBtn:      { padding: 6 },
  empty:            {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
});
