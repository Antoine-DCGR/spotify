// src/screens/HomeScreen.tsx

import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
  Alert,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import { fetchPlaylists } from '../src/api/api'; // on garde fetchPlaylists ici
import Header from '../src/components/Header';
import NowPlayingBanner from '../src/components/NowPlayingBanner';

export default function HomeScreen() {
  const router = useRouter();

  const {
    isAuthenticated,
    loadingAuth,
    errorAuth,
    userProfile,
    loadingProfile,
    authenticate,
    logout,
  } = useAuth();

  // === AJOUT DEBUG AUTH STATUS ===
  useEffect(() => {
    if (loadingAuth) {
      console.log('⏳ Authentification en cours...');
    } else if (isAuthenticated) {
      console.log('✅ Utilisateur CONNECTÉ');
      console.log('Profil :', userProfile);
    } else {
      console.log('❌ Utilisateur NON connecté');
    }
  }, [loadingAuth, isAuthenticated, userProfile]);
  // === FIN AJOUT ===

  // **LOCAL** pour la sync
  const [loadingSync, setLoadingSync] = useState(false);

  // Modal d'ajout
  const [modalVisible, setModalVisible] = useState(false);
  const [title, setTitle] = useState('');

  // ------------ Sync Playlists ------------
  const handleSync = async () => {
    if (!isAuthenticated) {
      Alert.alert('Erreur', "Veuillez vous connecter à Spotify d'abord.");
      return;
    }
    setLoadingSync(true);
    try {
      // on utilise fetchPlaylists directement
      await fetchPlaylists();
      Alert.alert('Succès', 'Playlists synchronisées depuis Spotify !');
    } catch (e: any) {
      Alert.alert('Erreur synchronisation', e.message);
    } finally {
      setLoadingSync(false);
    }
  };

  // ------------ Rendu selon l’état d’auth ------------
  if (loadingAuth) {
    return (
      <View style={styles.center}>
        <Text>Chargement de l’authentification…</Text>
      </View>
    );
  }

 

  // ------------ UI principale ------------
  return (
    <View style={styles.screen}>
      <Header
        onSync={handleSync}
        loadingSync={loadingSync}
        onAdd={() => setModalVisible(true)}
        userProfile={userProfile}
        profileLoading={loadingProfile}
        
      />

      <ScrollView contentContainerStyle={styles.scrollContent}>
        <Pressable style={styles.button} onPress={() => router.push('/playlists')}>
          <Text style={styles.buttonText}>Playlists</Text>
        </Pressable>
        <Pressable style={styles.button} onPress={() => router.push('/titres')}>
          <Text style={styles.buttonText}>Titres</Text>
        </Pressable>
        <Pressable style={styles.button} onPress={() => router.push('/albums')}>
          <Text style={styles.buttonText}>Albums</Text>
        </Pressable>
        <Pressable style={styles.button} onPress={() => router.push('/artistes')}>
          <Text style={styles.buttonText}>Artistes</Text>
        </Pressable>
        <Pressable style={styles.button} onPress={() => router.push('/logout')}>
          <Text style={styles.buttonText}>logout</Text>
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
  screen: { flex: 1 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  button: {
    marginTop: 16,
    padding: 12,
    backgroundColor: '#1DB954',
    borderRadius: 25,
  },
  buttonText: { color: '#fff', fontWeight: 'bold' },
  error: { marginTop: 8, color: 'red' },
  scrollContent: { padding: 20, paddingBottom: 100 },
  navButton: {
    padding: 16,
    borderRadius: 12,
    marginVertical: 8,
    backgroundColor: '#ddd',
  },
  navText: { fontSize: 18, fontWeight: '600' },
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
  modalTitle: { fontSize: 18, fontWeight: 'bold', marginBottom: 12 },
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
  modalBtn: { fontSize: 16, fontWeight: 'bold' },
});
