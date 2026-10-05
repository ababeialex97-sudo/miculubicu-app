import AsyncStorage from '@react-native-async-storage/async-storage';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

import type { Address, Fulfillment } from '@/api/types';
import { addLine, setLineQuantity, type CartLine } from '@/lib/cart';

type CartState = {
  lines: CartLine[];
  fulfillment: Fulfillment;
  locationId: string | null;
  address: Address;
  note: string;
  add: (line: Omit<CartLine, 'key'>) => void;
  setQuantity: (key: string, quantity: number) => void;
  setFulfillment: (fulfillment: Fulfillment) => void;
  setLocationId: (locationId: string) => void;
  setAddress: (address: Partial<Address>) => void;
  setNote: (note: string) => void;
  clear: () => void;
};

// Survives app restarts, so a half-built order is not lost.
export const useCart = create<CartState>()(
  persist(
    (set) => ({
      lines: [],
      fulfillment: 'delivery',
      locationId: null,
      address: { address_1: '', address_2: '', city: 'Vaslui' },
      note: '',
      add: (line) => set((state) => ({ lines: addLine(state.lines, line) })),
      setQuantity: (key, quantity) => set((state) => ({ lines: setLineQuantity(state.lines, key, quantity) })),
      setFulfillment: (fulfillment) => set({ fulfillment }),
      setLocationId: (locationId) => set({ locationId }),
      setAddress: (address) => set((state) => ({ address: { ...state.address, ...address } })),
      setNote: (note) => set({ note }),
      clear: () => set({ lines: [], note: '' }),
    }),
    {
      name: 'mlb.cart',
      storage: createJSONStorage(() => AsyncStorage),
    },
  ),
);
