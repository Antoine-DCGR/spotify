// app/index.tsx

import React, { useState, useEffect } from 'react';
import {
  View,
  ScrollView,
  Pressable,
  Text,
  StyleSheet,
  Modal,
  TextInput,
  ActivityIndicator,
} from 'react-native';
import { useRouter } from 'expo-router';
import Header from '../src/components/Header';
import NowPlayingBanner from '../src/components/NowPlayingBanner';
import { getStoredSpotifyToken } from '../src/auth/spotifyAuth';

export default function HomeScreen() {
  const [modalVisible, setModalVisible] = useState(false);
  const [loadingSync, setLoadingSync] = useState(false);
  const [title, setTitle] = useState('');
  const [authLoading, setAuthLoading] = useState(true);
  const router = useRouter();

  // 1️⃣ Vérification du token au montage
  useEffect(() => {
    (async () => {
      try {
        const stored = await getStoredSpotifyToken();
        if (!stored) {
          // pas de token → page de login
          router.replace('/login');
          return;
        }
      } catch (err) {
        console.error('Erreur vérif token :', err);
        router.replace('/login');
        return;
      } finally {
        setAuthLoading(false);
      }
    })();
  }, []);

  // 2️⃣ Loader pendant la vérification
  if (authLoading) {
    return (
      <View style={styles.loaderContainer}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  // 3️⃣ Fonctions sync / modal
  const handleSync = () => {
    setLoadingSync(true);
    setTimeout(() => setLoadingSync(false), 2000);
  };

  return (
    <View style={styles.screen}>
      <Header
        onSync={handleSync}
        loadingSync={loadingSync}
        onAdd={() => setModalVisible(true)}
      />

      <ScrollView contentContainerStyle={styles.scrollContent}>
        <Pressable
          style={styles.button}
          onPress={() => router.push('/playlists')}
        >
          <Text style={styles.buttonText}>Playlists</Text>
        </Pressable>

        <Pressable
          style={styles.button}
          onPress={() => router.push('/titres')}
        >
          <Text style={styles.buttonText}>Titres</Text>
        </Pressable>

        <Pressable
          style={styles.button}
          onPress={() => router.push('/albums')}
        >
          <Text style={styles.buttonText}>Albums</Text>
        </Pressable>

        <Pressable
          style={styles.button}
          onPress={() => router.push('/artistes')}
        >
          <Text style={styles.buttonText}>Artistes</Text>
        </Pressable>
      </ScrollView>

      <NowPlayingBanner
        title="Dawn"
        artist="Artist Name"
        imageUri="https://via.placeholder.com/50"
        onPlayPause={() => console.log('Play')}
        onNext={() => console.log('Next')}
        onPrevious={() => console.log('Previous')}
      />

      {/* Modal d'ajout */}
      <Modal
        visible={modalVisible}
        animationType="slide"
        transparent
        onRequestClose={() => setModalVisible(false)}
      >
        <View style={styles.modalBackground}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Ajouter une Playlist</Text>
            <TextInput
              placeholder="Nom de la playlist"
              value={title}
              onChangeText={setTitle}
              style={styles.input}
            />
            <View style={styles.modalActions}>
              <Pressable onPress={() => setModalVisible(false)}>
                <Text style={styles.modalBtn}>Annuler</Text>
              </Pressable>
              <Pressable
                onPress={() => {
                  console.log('Création :', title);
                  setModalVisible(false);
                }}
              >
                <Text style={styles.modalBtn}>Créer</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
  },
  loaderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scrollContent: {
    padding: 20,
    paddingBottom: 100,
  },
  button: {
    padding: 16,
    borderRadius: 12,
    marginVertical: 8,
    backgroundColor: '#ddd',
  },
  buttonText: {
    fontSize: 18,
    fontWeight: '600',
  },
  // Modal styles
  modalBackground: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: '#fff',
    padding: 24,
    borderRadius: 10,
    width: '80%',
    alignItems: 'stretch',
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 6,
    marginBottom: 16,
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  modalBtn: {
    fontSize: 16,
    fontWeight: 'bold',
  },
});
