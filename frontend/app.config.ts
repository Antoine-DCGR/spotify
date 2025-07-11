import { ConfigContext, ExpoConfig } from '@expo/config';
import 'dotenv/config';

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'frontend',
  slug: 'frontend',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './assets/images/icon.png',
  scheme: 'monapp',
  userInterfaceStyle: 'automatic',
  newArchEnabled: false,
  ios: {
    supportsTablet: true,
  },
  android: {
    adaptiveIcon: {
      foregroundImage: './assets/images/adaptive-icon.png',
      backgroundColor: '#ffffff',
    },
    edgeToEdgeEnabled: true,
    package: 'com.anonymous.frontend',
  },
  web: {
    bundler: 'metro',
    output: 'static',
    favicon: './assets/images/favicon.png',
  },
  plugins: [
    'expo-router',
    [
      'expo-splash-screen',
      {
        image: './assets/images/splash-icon.png',
        imageWidth: 200,
        resizeMode: 'contain',
        backgroundColor: '#ffffff',
      },
    ],
    'expo-web-browser',
    'expo-secure-store',
  ],
  experiments: {
    typedRoutes: true,
  },
  extra: {
    CLIENT_SPOTIFY_ID: process.env.CLIENT_SPOTIFY_ID,
  },
});
