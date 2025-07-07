import { Stack } from 'expo-router';

export default function RootLayout() {
  return (
    <Stack>
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen name="playlists" options={{ headerShown: false }} />
      <Stack.Screen name="titres" options={{ headerShown: false }} />
      <Stack.Screen name="albums" options={{ headerShown: false }} />
      <Stack.Screen name="artistes" options={{ headerShown: false }} />
      <Stack.Screen name="login" options={{ headerShown: false }} />
    </Stack>
  );
}