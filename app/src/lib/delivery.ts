import type { DeliveryQuote, Location } from '@/api/types';

import { toBani } from './money.ts';
import { formatTime } from './status.ts';

/** "3,2 km" */
export function formatKm(km: number): string {
  return `${String(Math.round(km * 10) / 10).replace('.', ',')} km`;
}

/**
 * Delivery fee in bani before ordering, or null while it depends on an address not measured yet.
 * Above the free delivery threshold it is zero whatever the distance.
 */
export function shippingBani(location: Location | undefined, quote: DeliveryQuote | undefined, subtotal: number): number | null {
  const threshold = toBani(location?.free_delivery_threshold ?? 0);
  if (threshold > 0 && subtotal >= threshold) {
    return 0;
  }
  if (location?.distance_pricing) {
    return quote?.fee != null ? toBani(quote.fee) : null;
  }
  return toBani(location?.delivery_fee ?? 0);
}

/** Lowest price band, for "from X lei" before the address is known. */
export function lowestBandFee(location: Location | undefined): number | null {
  const fees = (location?.delivery_bands ?? []).map((b) => toBani(b.fee));
  return fees.length > 0 ? Math.min(...fees) : null;
}

/** "Ajunge în jur de 19:42", or null when there is no arrival time or it has passed. */
export function etaLabel(etaAt: string | null | undefined, now: Date = new Date()): string | null {
  if (!etaAt) {
    return null;
  }
  const eta = new Date(etaAt);
  if (Number.isNaN(eta.getTime()) || eta.getTime() <= now.getTime()) {
    return null;
  }
  return `Ajunge în jur de ${formatTime(etaAt)}`;
}
