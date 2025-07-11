// app/_layout.tsx

import { Buffer } from 'buffer';
import { Stack } from 'expo-router';
import React from 'react';
import { AuthProvider } from '../context/AuthContext'; // Assurez-vous que AuthProvider est utilisé dans votre app
global.Buffer = Buffer;



export default function RootLayout() {
  return (
    <AuthProvider>
      <Stack>
        <Stack.Screen name="home" options={{ headerShown: false }} />
        <Stack.Screen name="playlists" options={{ headerShown: false }} />
        <Stack.Screen name="titres" options={{ headerShown: false }} />
        <Stack.Screen name="albums" options={{ headerShown: false }} />
        <Stack.Screen name="artistes" options={{ headerShown: false }} />
        <Stack.Screen name="logout" options={{ headerShown: false }} />
      </Stack>
    </AuthProvider>
  );
}
