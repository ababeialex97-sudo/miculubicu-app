import Constants, { ExecutionEnvironment } from 'expo-constants';
import type * as AppleAuthenticationModule from 'expo-apple-authentication';
import { Platform } from 'react-native';

import type { SocialLogin } from '@/api/hooks';

type GoogleSigninModule = typeof import('@react-native-google-signin/google-signin');

// Google's OAuth client of type "Web application": its ID is the audience of the ID token
// the server checks. The iOS client ID is only needed on iOS (see app.config.js).
const GOOGLE_WEB_CLIENT_ID = process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID ?? '';
const GOOGLE_IOS_CLIENT_ID = process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID ?? '';

// Both need native code that Expo Go doesn't have (and Expo Go's own bundle ID would not match).
const isExpoGo = Constants.executionEnvironment === ExecutionEnvironment.StoreClient;

/** Thrown when the customer closes the Google or Apple sheet: nothing to report. */
export class SignInCancelled extends Error {}

let google: GoogleSigninModule | null | undefined;

function loadGoogle(): GoogleSigninModule | null {
  if (google !== undefined) {
    return google;
  }
  google = null;
  if (isExpoGo || Platform.OS === 'web' || !GOOGLE_WEB_CLIENT_ID || (Platform.OS === 'ios' && !GOOGLE_IOS_CLIENT_ID)) {
    return null;
  }
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const module = require('@react-native-google-signin/google-signin') as GoogleSigninModule;
    module.GoogleSignin.configure({ webClientId: GOOGLE_WEB_CLIENT_ID, iosClientId: GOOGLE_IOS_CLIENT_ID || undefined });
    google = module;
  } catch {
    // A build without the native module: the button stays hidden.
  }
  return google;
}

export function googleAvailable(): boolean {
  return loadGoogle() !== null;
}

export async function signInWithGoogle(): Promise<SocialLogin> {
  const module = loadGoogle();
  if (!module) {
    throw new Error('Conectarea cu Google nu este disponibilă.');
  }
  const { GoogleSignin, isErrorWithCode, statusCodes } = module;
  try {
    await GoogleSignin.hasPlayServices({ showPlayServicesUpdateDialog: true });
    // Always show the account picker, so a shared phone can switch accounts.
    await GoogleSignin.signOut().catch(() => null);
    const response = await GoogleSignin.signIn();
    if (response.type !== 'success') {
      throw new SignInCancelled();
    }
    if (!response.data.idToken) {
      throw new Error('Google nu a trimis datele contului. Încearcă din nou.');
    }
    return { provider: 'google', id_token: response.data.idToken };
  } catch (error) {
    if (isErrorWithCode(error) && error.code === statusCodes.SIGN_IN_CANCELLED) {
      throw new SignInCancelled();
    }
    if (isErrorWithCode(error) && error.code === statusCodes.PLAY_SERVICES_NOT_AVAILABLE) {
      throw new Error('Pe acest telefon lipsesc serviciile Google Play.');
    }
    throw error;
  }
}

export function loadApple(): typeof AppleAuthenticationModule | null {
  if (isExpoGo || Platform.OS !== 'ios') {
    return null;
  }
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    return require('expo-apple-authentication') as typeof AppleAuthenticationModule;
  } catch {
    return null;
  }
}

export async function appleAvailable(): Promise<boolean> {
  const Apple = loadApple();
  return Apple ? Apple.isAvailableAsync().catch(() => false) : false;
}

export async function signInWithApple(): Promise<SocialLogin> {
  const Apple = loadApple();
  if (!Apple) {
    throw new Error('Conectarea cu Apple nu este disponibilă.');
  }
  try {
    const credential = await Apple.signInAsync({
      requestedScopes: [Apple.AppleAuthenticationScope.FULL_NAME, Apple.AppleAuthenticationScope.EMAIL],
    });
    if (!credential.identityToken) {
      throw new Error('Apple nu a trimis datele contului. Încearcă din nou.');
    }
    // Apple shares the name only the first time; the server keeps it from then on.
    return {
      provider: 'apple',
      identity_token: credential.identityToken,
      first_name: credential.fullName?.givenName ?? '',
      last_name: credential.fullName?.familyName ?? '',
    };
  } catch (error) {
    if (error instanceof Error && 'code' in error && error.code === 'ERR_REQUEST_CANCELED') {
      throw new SignInCancelled();
    }
    throw error;
  }
}
