import { View, Text } from 'react-native';

export default function MusicItem({ title, artist }: { title: string; artist: string }) {
  return (
    <View style={{ padding: 10, borderBottomWidth: 1 }}>
      <Text style={{ fontWeight: 'bold' }}>{title}</Text>
      <Text>{artist}</Text>
    </View>
  );
}
