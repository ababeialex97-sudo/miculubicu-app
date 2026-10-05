import { create } from 'zustand';

import type { Customer, Session } from '@/api/types';
import { secureStorage } from '@/lib/secure-storage';

const STORAGE_KEY = 'mlb.session';

type SessionState = {
  token: string | null;
  customer: Customer | null;
  hydrated: boolean;
  hydrate: () => Promise<void>;
  signIn: (session: Session) => void;
  setCustomer: (customer: Customer) => void;
  signOut: () => void;
};

// The token lives in the device keychain/keystore, never in plain storage.
export const useSession = create<SessionState>((set, get) => ({
  token: null,
  customer: null,
  hydrated: false,

  hydrate: async () => {
    try {
      const raw = await secureStorage.get(STORAGE_KEY);
      if (raw) {
        const session = JSON.parse(raw) as Session;
        set({ token: session.token, customer: session.customer });
      }
    } catch {
      // A corrupt entry just means the customer logs in again.
    } finally {
      set({ hydrated: true });
    }
  },

  signIn: (session) => {
    set({ token: session.token, customer: session.customer });
    void secureStorage.set(STORAGE_KEY, JSON.stringify(session));
  },

  setCustomer: (customer) => {
    const { token } = get();
    set({ customer });
    if (token) {
      void secureStorage.set(STORAGE_KEY, JSON.stringify({ token, customer }));
    }
  },

  signOut: () => {
    set({ token: null, customer: null });
    void secureStorage.remove(STORAGE_KEY);
  },
}));
