import React from 'react';
import { View, Text, Pressable, StyleSheet, ActivityIndicator } from 'react-native';
import { useSegments, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

// Définition des props avec leurs types
interface HeaderProps {
  onSync: () => void;
  loadingSync: boolean;
  onAdd: () => void;
}

// Mapping des segments de route vers des titres lisibles
const TITLES: Record<string, string> = {
  '': 'Accueil',
  playlists: 'Playlists',
  titres: 'Titres',
  albums: 'Albums',
  artistes: 'Artistes',
  login: 'Connexion',
};

export default function Header({ onSync, loadingSync, onAdd }: HeaderProps) {
  const segments = useSegments();
  const router = useRouter();
  const current = segments[segments.length - 1] ?? '';

  // Utilisation sécurisée du mapping, fallback sur "Page" si non trouvé
  const title = TITLES[current] ?? 'Page';

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

        <Pressable onPress={() => router.push('/login')} style={styles.actionButton}>
          <Text style={styles.actionText}>Connexion</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
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
