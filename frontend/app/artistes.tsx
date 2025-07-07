import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  SectionList,
  StyleSheet,
  ActivityIndicator,
  Alert
} from 'react-native';
import Header from '../src/components/Header';
import NowPlayingBanner from '../src/components/NowPlayingBanner';
import { getArtistes } from '../src/api/api';

interface Artist {
  id: number;
  nom: string;
}

interface Section {
  title: string;
  data: Artist[];
}

export default function ArtistesScreen() {
  const [sections, setSections] = useState<Section[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [syncing, setSyncing] = useState<boolean>(false);

  const loadArtists = async () => {
    try {
      const artistes = await getArtistes();
      const map: Record<string, Artist[]> = {};
      artistes.forEach(artiste => {
        const letter = artiste.nom.charAt(0).toUpperCase();
        if (!map[letter]) map[letter] = [];
        map[letter].push(artiste);
      });
      const sortedKeys = Object.keys(map).sort();
      const sectionsData: Section[] = sortedKeys.map(letter => ({
        title: letter,
        data: map[letter].sort((a, b) => a.nom.localeCompare(b.nom)),
      }));
      setSections(sectionsData);
    } catch (err) {
      console.error(err);
      Alert.alert('Erreur', "Impossible de charger les artistes.");
    } finally {
      setLoading(false);
      setSyncing(false);
    }
  };

  useEffect(() => {
    loadArtists();
  }, []);

  const handleSync = async () => {
    setSyncing(true);
    await loadArtists();
  };

  if (loading) {
    return (
      <View style={styles.loaderContainer}>
        <ActivityIndicator size={48} color="#333" />
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <Header onSync={handleSync} loadingSync={syncing} onAdd={() => {}} />

      <SectionList
        sections={sections}
        keyExtractor={item => item.id.toString()}
        renderSectionHeader={({ section }) => (
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>{section.title}</Text>
          </View>
        )}
        renderItem={({ item }) => (
          <View style={styles.item}>
            <Text style={styles.itemText}>{item.nom}</Text>
          </View>
        )}
        stickySectionHeadersEnabled
        contentContainerStyle={styles.content}
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
  screen: {
    flex: 1,
    backgroundColor: '#fff',
  },
  loaderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    paddingBottom: 100, // Laisser de l'espace pour la bannière
  },
  sectionHeader: {
    backgroundColor: '#eee',
    paddingHorizontal: 16,
    paddingVertical: 4,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
  },
  item: {
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#ddd',
  },
  itemText: {
    fontSize: 16,
  },
});
