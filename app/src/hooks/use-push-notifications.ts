import { useQueryClient } from '@tanstack/react-query';
import type * as NotificationsModule from 'expo-notifications';
import { router } from 'expo-router';
import { useEffect } from 'react';

import { queryKeys } from '@/api/hooks';
import { getNotifications, isLoyaltyNotification, orderIdFrom, registerForPush } from '@/lib/push';
import { useSession } from '@/store/session';

/**
 * Keeps the device registered while logged in, refreshes order or loyalty data when a
 * notification arrives, and opens the order (or the loyalty card) when the customer taps one.
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
    const Notifications = getNotifications();
    if (!Notifications) {
      return;
    }

    const openOrder = (response: NotificationsModule.NotificationResponse) => {
      if (isLoyaltyNotification(response.notification)) {
        router.push('/fidelitate');
        return;
      }
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
      if (isLoyaltyNotification(notification)) {
        void queryClient.invalidateQueries({ queryKey: queryKeys.loyalty });
        return;
      }
      const orderId = orderIdFrom(notification);
      void queryClient.invalidateQueries({ queryKey: orderId ? queryKeys.order(orderId) : queryKeys.orders });
      void queryClient.invalidateQueries({ queryKey: queryKeys.orders, exact: true });
      // A completed order adds a stamp.
      void queryClient.invalidateQueries({ queryKey: queryKeys.loyalty });
    });
    const tapped = Notifications.addNotificationResponseReceivedListener(openOrder);

    return () => {
      received.remove();
      tapped.remove();
    };
  }, [queryClient]);
}
