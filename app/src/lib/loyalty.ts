// Pure helpers for the loyalty screen, kept free of React Native imports so they can be unit tested with Node.

import type { Coupon } from '@/api/types';
import { formatPrice } from './money.ts';

export function couponTitle(coupon: Coupon): string {
  if (coupon.description.trim()) {
    return coupon.description.trim();
  }
  return coupon.loyalty_reward ? 'Recompensa cardului de fidelitate' : `Reducere ${coupon.amount_label}`;
}

/** "Prima comandă · de la 80 lei · până pe 12.11 · cod BUNVENIT" */
export function couponSubtitle(coupon: Coupon): string {
  const parts: string[] = [];
  if (coupon.first_order_only) {
    parts.push('Prima comandă');
  }
  if (Number.parseFloat(coupon.minimum_amount) > 0) {
    parts.push(`de la ${formatPrice(coupon.minimum_amount)}`);
  }
  if (coupon.expires_at) {
    const date = new Date(coupon.expires_at);
    if (!Number.isNaN(date.getTime())) {
      parts.push(`până pe ${String(date.getDate()).padStart(2, '0')}.${String(date.getMonth() + 1).padStart(2, '0')}`);
    }
  }
  parts.push(`cod ${coupon.code.toUpperCase()}`);
  const text = parts.join(' · ');
  return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Filled stamps, clamped to the card size. */
export function stampSlots(stamps: number, required: number): boolean[] {
  const filled = Math.max(0, Math.min(stamps, required));
  return Array.from({ length: required }, (_, i) => i < filled);
}
