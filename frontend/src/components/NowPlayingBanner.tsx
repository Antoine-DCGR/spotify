import React from 'react';
import { View, Text, Image, Pressable, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

interface NowPlayingBannerProps {
  title: string;
  artist: string;
  imageUri: string;
  onPlayPause: () => void;
  onNext: () => void;
  onPrevious: () => void;
}

const NowPlayingBanner: React.FC<NowPlayingBannerProps> = ({
  title,
  artist,
  imageUri,
  onPlayPause,
  onNext,
  onPrevious,
}) => {
  return (
    <View style={styles.container}>
      <Image source={{ uri: imageUri }} style={styles.cover} />

      <View style={styles.info}>
        <Text style={styles.title} numberOfLines={1}>{title}</Text>
        <Text style={styles.artist} numberOfLines={1}>{artist}</Text>
      </View>

      <View style={styles.controls}>
        <Pressable onPress={onPrevious}>
          <Ionicons name="play-back" size={24} />
        </Pressable>
        <Pressable onPress={onPlayPause}>
          <Ionicons name="play" size={24} />
        </Pressable>
        <Pressable onPress={onNext}>
          <Ionicons name="play-forward" size={24} />
        </Pressable>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    width: '100%',
    backgroundColor: '#eee',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderTopWidth: 1,
    borderColor: '#ccc',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  cover: {
    width: 50,
    height: 50,
    borderRadius: 6,
  },
  info: {
    flex: 1,
    justifyContent: 'center',
  },
  title: {
    fontWeight: '600',
    fontSize: 16,
  },
  artist: {
    color: '#666',
    fontSize: 14,
  },
  controls: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'center',
  },
});

export default NowPlayingBanner;
