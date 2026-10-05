import { useQueryClient } from '@tanstack/react-query';
import * as Notifications from 'expo-notifications';
import { router } from 'expo-router';
import { useEffect } from 'react';
import { Platform } from 'react-native';

import { queryKeys } from '@/api/hooks';
import { orderIdFrom, registerForPush } from '@/lib/push';
import { useSession } from '@/store/session';

/**
 * Keeps the device registered while logged in, refreshes order data when a status
 * notification arrives, and opens the order when the customer taps one.
 */
export function usePushNotifications() {
  const queryClient = useQueryClient();
  const token = useSession((s) => s.token);

  useEffect(() => {
    if (token) {
      void registerForPush({ ask: false });
    }
  }, [token]);

  useEffect(() => {
    if (Platform.OS === 'web') {
      return;
    }

    const openOrder = (response: Notifications.NotificationResponse) => {
      const orderId = orderIdFrom(response.notification);
      if (orderId) {
        router.push(`/comanda/${orderId}`);
      }
    };

    // A tap that launched the app from a closed state.
    const initial = Notifications.getLastNotificationResponse();
    if (initial) {
      openOrder(initial);
    }

    const received = Notifications.addNotificationReceivedListener((notification) => {
      const orderId = orderIdFrom(notification);
      void queryClient.invalidateQueries({ queryKey: orderId ? queryKeys.order(orderId) : queryKeys.orders });
      void queryClient.invalidateQueries({ queryKey: queryKeys.orders, exact: true });
    });
    const tapped = Notifications.addNotificationResponseReceivedListener(openOrder);

    return () => {
      received.remove();
      tapped.remove();
    };
  }, [queryClient]);
}
