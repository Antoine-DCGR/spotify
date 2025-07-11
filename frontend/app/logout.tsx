// src/screens/LogoutScreen.tsx

import { useRouter } from 'expo-router';
import React from 'react';
import { Alert, Button, StyleSheet, Text, View } from 'react-native';
import { logoutSpotify } from '../src/api/api';

export default function LogoutScreen() {
  const router = useRouter();

  const handleLogout = async () => {
    try {
      await logoutSpotify();
      Alert.alert('Déconnexion', 'Déconnexion réussie !');
      router.replace('/'); // Redirige vers la home après logout
    } catch (e: any) {
      Alert.alert('Erreur', e.message || 'Impossible de se déconnecter');
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.text}>
        Tu es sur le point de te déconnecter de Spotify.
      </Text>
      <Button title="Se déconnecter" color="#e74c3c" onPress={handleLogout} />
      <Button title="Annuler" onPress={() => router.back()} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
  text: { fontSize: 18, marginBottom: 24 },
});
