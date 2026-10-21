import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';

import { api } from '@/api/client';
import { queryKeys } from '@/api/hooks';
import { useGuestOrders } from '@/store/guest-orders';
import { useSession } from '@/store/session';

/**
 * After logging in, orders placed on this phone without an account move to the account:
 * they show up in its history and completed ones add their loyalty stamps.
 */
export function useClaimGuestOrders() {
  const queryClient = useQueryClient();
  const token = useSession((s) => s.token);
  const refs = useGuestOrders((s) => s.orders);

  useEffect(() => {
    if (!token || refs.length === 0) {
      return;
    }
    let cancelled = false;
    void (async () => {
      try {
        await api<{ claimed: number[] }>('/orders/claim', { method: 'POST', body: { orders: refs }, auth: true });
      } catch {
        // Tried again on the next launch.
        return;
      }
      if (cancelled) {
        return;
      }
      // Orders the server didn't move (already in an account) have nothing more to show here either.
      useGuestOrders.getState().clearOrders();
      void queryClient.invalidateQueries({ queryKey: queryKeys.orders });
      void queryClient.invalidateQueries({ queryKey: queryKeys.loyalty });
    })();
    return () => {
      cancelled = true;
    };
  }, [token, refs, queryClient]);
}
