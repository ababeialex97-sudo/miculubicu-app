import { Figtree_400Regular, Figtree_500Medium, Figtree_600SemiBold, Figtree_700Bold } from '@expo-google-fonts/figtree';
import { ZillaSlab_700Bold } from '@expo-google-fonts/zilla-slab';
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
    ZillaSlab_700Bold,
    Figtree_400Regular,
    Figtree_500Medium,
    Figtree_600SemiBold,
    Figtree_700Bold,
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
