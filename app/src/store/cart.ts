import AsyncStorage from '@react-native-async-storage/async-storage';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

import type { Address, Fulfillment } from '@/api/types';
import { addCouponCode, addLine, removeCouponCode, setLineQuantity, type CartLine } from '@/lib/cart';

type CartState = {
  lines: CartLine[];
  fulfillment: Fulfillment;
  locationId: string | null;
  address: Address;
  note: string;
  couponCodes: string[];
  add: (line: Omit<CartLine, 'key'>) => void;
  setQuantity: (key: string, quantity: number) => void;
  setFulfillment: (fulfillment: Fulfillment) => void;
  setLocationId: (locationId: string) => void;
  setAddress: (address: Partial<Address>) => void;
  setNote: (note: string) => void;
  addCoupon: (code: string) => void;
  removeCoupon: (code: string) => void;
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
      couponCodes: [],
      add: (line) => set((state) => ({ lines: addLine(state.lines, line) })),
      setQuantity: (key, quantity) => set((state) => ({ lines: setLineQuantity(state.lines, key, quantity) })),
      setFulfillment: (fulfillment) => set({ fulfillment }),
      setLocationId: (locationId) => set({ locationId }),
      setAddress: (address) => set((state) => ({ address: { ...state.address, ...address } })),
      setNote: (note) => set({ note }),
      addCoupon: (code) => set((state) => ({ couponCodes: addCouponCode(state.couponCodes, code) })),
      removeCoupon: (code) => set((state) => ({ couponCodes: removeCouponCode(state.couponCodes, code) })),
      clear: () => set({ lines: [], note: '', couponCodes: [] }),
    }),
    {
      name: 'mlb.cart',
      storage: createJSONStorage(() => AsyncStorage),
    },
  ),
);
