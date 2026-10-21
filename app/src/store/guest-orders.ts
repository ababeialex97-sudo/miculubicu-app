import { create } from 'zustand';

import { addGuestOrder, type GuestContact, type GuestOrderRef } from '@/lib/guest';
import { secureStorage } from '@/lib/secure-storage';

const STORAGE_KEY = 'mlb.guestOrders';

type GuestOrdersState = {
  orders: GuestOrderRef[];
  // Remembered so the next order without an account is quicker.
  contact: GuestContact;
  hydrated: boolean;
  hydrate: () => Promise<void>;
  add: (ref: GuestOrderRef) => void;
  setContact: (contact: GuestContact) => void;
  clearOrders: () => void;
};

type Stored = Pick<GuestOrdersState, 'orders' | 'contact'>;

const EMPTY_CONTACT: GuestContact = { first_name: '', last_name: '', email: '', phone: '' };

// Order tokens give access to the orders, so they live in the keychain/keystore like the session.
export const useGuestOrders = create<GuestOrdersState>((set, get) => {
  const persist = () => {
    const { orders, contact } = get();
    void secureStorage.set(STORAGE_KEY, JSON.stringify({ orders, contact } satisfies Stored));
  };

  return {
    orders: [],
    contact: EMPTY_CONTACT,
    hydrated: false,

    hydrate: async () => {
      try {
        const raw = await secureStorage.get(STORAGE_KEY);
        if (raw) {
          const stored = JSON.parse(raw) as Partial<Stored>;
          set({ orders: stored.orders ?? [], contact: { ...EMPTY_CONTACT, ...stored.contact } });
        }
      } catch {
        // Losing the list only hides old guest orders on this phone; the restaurant still has them.
      } finally {
        set({ hydrated: true });
      }
    },

    add: (ref) => {
      set((state) => ({ orders: addGuestOrder(state.orders, ref) }));
      persist();
    },

    setContact: (contact) => {
      set({ contact });
      persist();
    },

    clearOrders: () => {
      set({ orders: [] });
      persist();
    },
  };
});
