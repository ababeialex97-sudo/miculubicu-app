import { Barlow_400Regular, Barlow_500Medium, Barlow_600SemiBold, Barlow_700Bold } from '@expo-google-fonts/barlow';
import { BarlowCondensed_800ExtraBold } from '@expo-google-fonts/barlow-condensed';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useFonts } from 'expo-font';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';

import { colors } from '@/constants/theme';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import { useSession } from '@/store/session';

void SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: { queries: { retry: 1 } },
      }),
  );
  const [fontsLoaded, fontError] = useFonts({
    BarlowCondensed_800ExtraBold,
    Barlow_400Regular,
    Barlow_500Medium,
    Barlow_600SemiBold,
    Barlow_700Bold,
  });
  const sessionHydrated = useSession((s) => s.hydrated);

  useEffect(() => {
    void useSession.getState().hydrate();
  }, []);

  const ready = (fontsLoaded || fontError !== null) && sessionHydrated;

  useEffect(() => {
    if (ready) {
      void SplashScreen.hideAsync();
    }
  }, [ready]);

  if (!ready) {
    return null;
  }

  return (
    <QueryClientProvider client={queryClient}>
      <StatusBar style="light" />
      <AppStack />
    </QueryClientProvider>
  );
}

function AppStack() {
  usePushNotifications();

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.background },
      }}>
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="produs/[id]" />
      <Stack.Screen name="cos" />
      <Stack.Screen name="comanda/[id]" />
      <Stack.Screen name="autentificare" options={{ presentation: 'modal' }} />
      <Stack.Screen name="locatie" options={{ presentation: 'modal' }} />
    </Stack>
  );
}
