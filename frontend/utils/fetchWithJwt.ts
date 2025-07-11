// src/utils/fetchWithJwt.ts

import * as SecureStore from 'expo-secure-store';
// Ici on importe depuis /src/config.ts (sans le 'src/' dans le chemin, 
// car ce fichier est déjà dans src/utils)
import { API_URL, JWT_KEY } from '../src/config';

/**
 * Appelle votre API en injectant automatiquement le JWT dans l’Authorization header.
 * @param path  Chemin relatif à API_URL, ex. '/spotify/me'
 * @param options  RequestInit additionnels
 */
export async function fetchWithJwt(
  path: string,
  options: RequestInit = {}
): Promise<Response> {
  // Récupérer le JWT
  const jwt = await SecureStore.getItemAsync(JWT_KEY);
  if (!jwt) {
    throw new Error('Non authentifié (pas de JWT trouvé)');
  }

  // Construire les headers
  const headers = {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${jwt}`,
    ...(options.headers || {}),
  };

  // Préfixer le chemin par l’URL de base
  return fetch(`${API_URL}${path}`, {
    ...options,
    headers,
  });
}
