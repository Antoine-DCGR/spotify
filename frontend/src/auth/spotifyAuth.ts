import { authorize, refresh, AuthorizeResult, RefreshResult } from 'react-native-app-auth';
import * as Keychain from 'react-native-keychain';
import { SPOTIFY } from '../../constants/index';

const config = {
  clientId: SPOTIFY.clientId,
  redirectUrl: SPOTIFY.redirectUrl,
  scopes: SPOTIFY.scopes,
  serviceConfiguration: {
    authorizationEndpoint: SPOTIFY.authEndpoint,
    tokenEndpoint: SPOTIFY.tokenEndpoint,
  },
};

export async function loginWithSpotify(): Promise<AuthorizeResult> {
  const result = await authorize(config);
  await Keychain.setGenericPassword(
    'spotify',
    JSON.stringify({
      accessToken: result.accessToken,
      refreshToken: result.refreshToken,
      expiresAt: result.accessTokenExpirationDate,
    })
  );
  return result;
}

export async function refreshSpotifyToken(): Promise<RefreshResult> {
  const creds = await Keychain.getGenericPassword();
  if (!creds) throw new Error('Pas de token stocké');
  const { refreshToken } = JSON.parse(creds.password) as { refreshToken: string };
  const newTokens = await refresh(config, { refreshToken });
  await Keychain.setGenericPassword(
    'spotify',
    JSON.stringify({
      accessToken: newTokens.accessToken,
      refreshToken: newTokens.refreshToken || refreshToken,
      expiresAt: newTokens.accessTokenExpirationDate,
    })
  );
  return newTokens;
}

export async function getStoredSpotifyToken(): Promise<{
  accessToken: string;
  refreshToken: string;
  expiresAt: string;
} | null> {
  const creds = await Keychain.getGenericPassword();
  return creds ? JSON.parse(creds.password) : null;
}
