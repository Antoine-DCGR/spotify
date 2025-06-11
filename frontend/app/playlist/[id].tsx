import React, { useEffect, useState } from "react";
import { useLocalSearchParams } from "expo-router";
import { View, Text, FlatList, ActivityIndicator } from "react-native";
import { getMusicsByPlaylist, Music } from "../../src/api/api";

export default function PlaylistScreen() {
  // Note : id est une string correspondant au spotify_id de la playlist
  const { id } = useLocalSearchParams<{ id: string }>();
  const [musics, setMusics] = useState<Music[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (id) {
      // Passez id directement, sans le convertir en nombre
      getMusicsByPlaylist(id).then((data) => {
        setMusics(data);
        setLoading(false);
      });
    }
  }, [id]);

  if (loading)
    return (
      <View style={{ flex: 1, justifyContent: "center", alignItems: "center" }}>
        <ActivityIndicator size="large" />
      </View>
    );

  return (
    <View style={{ flex: 1, padding: 16 }}>
      <Text style={{ fontSize: 24, marginBottom: 16 }}>
        Musiques de la playlist
      </Text>
      <FlatList
        data={musics}
        // item.id ici correspond à l’ID numérique de la musique (clé primaire de la table `musiques`)
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <View style={{ padding: 16, borderBottomWidth: 1, borderColor: "#eee" }}>
            <Text style={{ fontSize: 16 }}>{item.titre}</Text>
            <Text style={{ color: "#888" }}>
              {item.artiste} – {item.album}
            </Text>
          </View>
        )}
      />
    </View>
  );
}
