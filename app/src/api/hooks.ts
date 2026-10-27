import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { ORDER_TOKEN_HEADER, api } from '@/api/client';
import type { CartPreview, CartPreviewRequest, Config, Customer, GuestOrder, Loyalty, Menu, NewGuestOrder, NewOrder, Order, Session } from '@/api/types';
import { guestTokenFor } from '@/lib/guest';
import { useGuestOrders } from '@/store/guest-orders';
import { useSession } from '@/store/session';

export const queryKeys = {
  menu: ['menu'] as const,
  config: ['config'] as const,
  orders: ['orders'] as const,
  order: (id: number) => ['orders', id] as const,
  guestOrders: (ids: number[]) => ['orders', 'guest', ids] as const,
  loyalty: ['loyalty'] as const,
  cartPreview: (request: CartPreviewRequest) => ['cart-preview', request] as const,
};

// Order statuses only change when staff update them, so a slow poll is enough
// until push notifications take over (step 4).
const ACTIVE_ORDER_POLL_MS = 30_000;

export function useMenu() {
  return useQuery({
    queryKey: queryKeys.menu,
    queryFn: () => api<Menu>('/menu'),
    staleTime: 5 * 60_000,
  });
}

export function useConfig() {
  return useQuery({
    queryKey: queryKeys.config,
    queryFn: () => api<Config>('/config'),
    staleTime: 5 * 60_000,
  });
}

export function useOrders() {
  const token = useSession((s) => s.token);
  return useQuery({
    queryKey: queryKeys.orders,
    queryFn: () => api<Order[]>('/orders?per_page=50', { auth: true }),
    enabled: token !== null,
  });
}

/** Orders placed on this phone without an account, while logged out. */
export function useGuestOrderList() {
  const token = useSession((s) => s.token);
  const refs = useGuestOrders((s) => s.orders);
  return useQuery({
    queryKey: queryKeys.guestOrders(refs.map((r) => r.id)),
    queryFn: () => api<Order[]>('/orders/guest/lookup', { method: 'POST', body: { orders: refs } }),
    enabled: token === null && refs.length > 0,
  });
}

/** One order: through the account, or with the token kept for an order placed without one. */
export function useOrder(id: number) {
  const token = useSession((s) => s.token);
  const guestToken = useGuestOrders((s) => guestTokenFor(s.orders, id));
  return useQuery({
    queryKey: queryKeys.order(id),
    queryFn: () =>
      guestToken
        ? api<Order>(`/orders/guest/${id}`, { headers: { [ORDER_TOKEN_HEADER]: guestToken } })
        : api<Order>(`/orders/${id}`, { auth: true }),
    enabled: (token !== null || guestToken !== null) && Number.isFinite(id),
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'completed' || status === 'cancelled' ? false : ACTIVE_ORDER_POLL_MS;
    },
  });
}

export function useLogin() {
  const signIn = useSession((s) => s.signIn);
  return useMutation({
    mutationFn: (input: { email: string; password: string }) => api<Session>('/auth/login', { method: 'POST', body: input }),
    onSuccess: signIn,
  });
}

export function useRegister() {
  const signIn = useSession((s) => s.signIn);
  return useMutation({
    mutationFn: (input: { email: string; password: string; first_name: string; last_name: string; phone: string }) =>
      api<Session>('/auth/register', { method: 'POST', body: input }),
    onSuccess: signIn,
  });
}

export type SocialLogin =
  | { provider: 'google'; id_token: string }
  | { provider: 'apple'; identity_token: string; first_name: string; last_name: string };

export function useSocialLogin() {
  const signIn = useSession((s) => s.signIn);
  return useMutation({
    mutationFn: ({ provider, ...body }: SocialLogin) => api<Session>(`/auth/${provider}`, { method: 'POST', body }),
    onSuccess: signIn,
  });
}

export function usePasswordReset() {
  return useMutation({
    mutationFn: (email: string) => api<{ message: string }>('/auth/password-reset', { method: 'POST', body: { email } }),
  });
}

export function useUpdateProfile() {
  const setCustomer = useSession((s) => s.setCustomer);
  return useMutation({
    mutationFn: (input: Partial<Pick<Customer, 'first_name' | 'last_name' | 'phone' | 'address'>>) =>
      api<Customer>('/me', { method: 'PATCH', body: input, auth: true }),
    onSuccess: setCustomer,
  });
}

export function useDeleteAccount() {
  return useMutation({
    mutationFn: (password: string) => api<{ deleted: boolean }>('/me', { method: 'DELETE', body: { password }, auth: true }),
  });
}

export function useCreateOrder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (order: NewOrder) => api<Order>('/orders', { method: 'POST', body: order, auth: true }),
    onSuccess: (order) => {
      queryClient.setQueryData(queryKeys.order(order.id), order);
      void queryClient.invalidateQueries({ queryKey: queryKeys.orders });
      // A used reward coupon disappears from the loyalty screen.
      void queryClient.invalidateQueries({ queryKey: queryKeys.loyalty });
    },
  });
}

export function useCreateGuestOrder() {
  const queryClient = useQueryClient();
  const addGuestOrder = useGuestOrders((s) => s.add);
  return useMutation({
    mutationFn: (order: NewGuestOrder) => api<GuestOrder>('/orders/guest', { method: 'POST', body: order }),
    onSuccess: ({ guest_token, ...order }) => {
      addGuestOrder({ id: order.id, token: guest_token });
      queryClient.setQueryData(queryKeys.order(order.id), order);
      void queryClient.invalidateQueries({ queryKey: queryKeys.orders });
    },
  });
}

export function useLoyalty() {
  const token = useSession((s) => s.token);
  return useQuery({
    queryKey: queryKeys.loyalty,
    queryFn: () => api<Loyalty>('/loyalty', { auth: true }),
    enabled: token !== null,
  });
}

// Works without an account too; with one, account-only codes (rewards, first order) are checked.
export function previewCart(request: CartPreviewRequest) {
  return api<CartPreview>('/cart/preview', { method: 'POST', body: request, auth: useSession.getState().token !== null });
}

/**
 * Server totals for a cart with coupon codes, recomputed when the cart changes.
 * Without codes the local totals are already exact, so nothing is fetched.
 */
export function useCartPreview(request: CartPreviewRequest) {
  const token = useSession((s) => s.token);
  return useQuery({
    // The account changes which codes apply.
    queryKey: [...queryKeys.cartPreview(request), token !== null],
    queryFn: () => previewCart(request),
    enabled: request.coupon_codes.length > 0 && request.items.length > 0,
    placeholderData: keepPreviousData,
    retry: false,
  });
}
