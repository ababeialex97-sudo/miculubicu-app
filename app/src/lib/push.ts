import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

import { api } from '@/api/client';

const REGISTERED_TOKEN_KEY = 'mlb.pushToken';

// Status notifications are shown even while the app is open.
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});

/**
 * Registers this device for order status notifications with mlb-app-api.
 * With `ask`, the system permission prompt is shown if the customer hasn't answered it yet;
 * without it, registration only happens when permission was already granted.
 * Does nothing on web, simulators, or before the EAS project ID exists (needed by Expo Push).
 */
export async function registerForPush({ ask }: { ask: boolean }): Promise<void> {
  if (Platform.OS === 'web' || !Device.isDevice) {
    return;
  }

  const projectId: string | undefined = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
  if (!projectId) {
    return;
  }

  try {
    if (Platform.OS === 'android') {
      // Must match channelId in the plugin's push messages.
      await Notifications.setNotificationChannelAsync('comenzi', {
        name: 'Comenzi',
        importance: Notifications.AndroidImportance.HIGH,
      });
    }

    let { status } = await Notifications.getPermissionsAsync();
    if (status === 'undetermined' && ask) {
      ({ status } = await Notifications.requestPermissionsAsync());
    }
    if (status !== 'granted') {
      return;
    }

    const { data: token } = await Notifications.getExpoPushTokenAsync({ projectId });
    await api('/push-tokens', { method: 'POST', body: { token }, auth: true });
    await AsyncStorage.setItem(REGISTERED_TOKEN_KEY, token);
  } catch {
    // Notifications are a convenience; the status screen still refreshes on its own.
  }
}

/** Detaches this device from the account, so a logged-out phone gets no more notifications. */
export async function unregisterForPush(): Promise<void> {
  try {
    const token = await AsyncStorage.getItem(REGISTERED_TOKEN_KEY);
    if (token) {
      await api('/push-tokens', { method: 'DELETE', body: { token }, auth: true });
      await AsyncStorage.removeItem(REGISTERED_TOKEN_KEY);
    }
  } catch {
    // The server also drops tokens Expo reports as unregistered.
  }
}

/** Order ID carried by a status notification, if any. */
/** Loyalty notifications (a full card) open the Fidelitate screen. */
export function isLoyaltyNotification(notification: Notifications.Notification): boolean {
  return notification.request.content.data?.screen === 'fidelitate';
}

export function orderIdFrom(notification: Notifications.Notification): number | null {
  const orderId = Number(notification.request.content.data?.orderId);
  return Number.isInteger(orderId) && orderId > 0 ? orderId : null;
}
