// src/screens/LogoutScreen.tsx

import { useRouter } from 'expo-router';
import React from 'react';
import { Button, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '../context/AuthContext';

export default function LogoutScreen() {
  const router = useRouter();
  const { logout } = useAuth();

  const handleLogout = async () => {
    await logout();
    router.replace('/'); // redirige vers la home
  };

  return (
    <View style={styles.container}>
      <Text style={styles.text}>Tu es sur le point de te déconnecter de Spotify.</Text>
      <Button title="Se déconnecter" color="#e74c3c" onPress={handleLogout} />
      <Button title="Annuler" onPress={() => router.back()} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
  text: { fontSize: 18, marginBottom: 24 },
});
