import React, { useEffect, useState } from "react";
import { View, Text, FlatList, ActivityIndicator } from "react-native";
import { router } from "expo-router";
import { getPlaylists, Playlist } from "../src/api/api";
import PlaylistItem from "../src/components/PlaylistItems";

export default function HomeScreen() {
  const [playlists, setPlaylists] = useState<Playlist[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getPlaylists().then((data) => {
      setPlaylists(data);
      setLoading(false);
    });
  }, []);

  const navigateToPlaylist = (id: number | string) => {
    router.push({
      pathname: "/playlist/[id]"as any,
      params: { id: String(id) }
    });
  };

  if (loading)
    return (
      <View style={{ flex: 1, justifyContent: "center", alignItems: "center" }}>
        <ActivityIndicator size="large" />
      </View>
    );

  return (
    <View style={{ flex: 1, padding: 16 }}>
      <Text style={{ fontSize: 24, marginBottom: 16 }}>Playlists</Text>
      <FlatList
        data={playlists}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <PlaylistItem 
            title={item.nom} 
            onPress={() => navigateToPlaylist(item.id)} 
          />
        )}
      />
    </View>
  );
}