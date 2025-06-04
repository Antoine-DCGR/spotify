import React from "react";
import { TouchableOpacity, Text, StyleSheet } from "react-native";

interface Props {
  title: string;
  onPress: () => void;
}

export default function PlaylistItem({ title, onPress }: Props) {
  return (
    <TouchableOpacity style={styles.item} onPress={onPress}>
      <Text style={styles.text}>{title}</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  item: {
    padding: 18,
    borderBottomWidth: 1,
    borderColor: "#eee",
  },
  text: {
    fontSize: 18,
  },
});
