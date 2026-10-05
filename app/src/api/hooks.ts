import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/api/client';
import type { CartPreview, CartPreviewRequest, Config, Customer, Loyalty, Menu, NewOrder, Order, Session } from '@/api/types';
import { useSession } from '@/store/session';

export const queryKeys = {
  menu: ['menu'] as const,
  config: ['config'] as const,
  orders: ['orders'] as const,
  order: (id: number) => ['orders', id] as const,
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

export function useOrder(id: number) {
  const token = useSession((s) => s.token);
  return useQuery({
    queryKey: queryKeys.order(id),
    queryFn: () => api<Order>(`/orders/${id}`, { auth: true }),
    enabled: token !== null && Number.isFinite(id),
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

export function useLoyalty() {
  const token = useSession((s) => s.token);
  return useQuery({
    queryKey: queryKeys.loyalty,
    queryFn: () => api<Loyalty>('/loyalty', { auth: true }),
    enabled: token !== null,
  });
}

export function previewCart(request: CartPreviewRequest) {
  return api<CartPreview>('/cart/preview', { method: 'POST', body: request, auth: true });
}

/**
 * Server totals for a cart with coupon codes, recomputed when the cart changes.
 * Without codes the local totals are already exact, so nothing is fetched.
 */
export function useCartPreview(request: CartPreviewRequest | null) {
  const token = useSession((s) => s.token);
  return useQuery({
    queryKey: queryKeys.cartPreview(request ?? { items: [], coupon_codes: [], fulfillment: 'delivery', location_id: '' }),
    queryFn: () => previewCart(request!),
    enabled: token !== null && request !== null && request.coupon_codes.length > 0 && request.items.length > 0,
    placeholderData: keepPreviousData,
    retry: false,
  });
}
