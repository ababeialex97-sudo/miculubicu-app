import type { Fulfillment } from '@/api/types';

/** Card to the courier or at the counter (mlb-app-api's Card_Gateway). */
export const CARD_ON_DELIVERY = 'mlb_card';

/** How the customer pays, in their words: "Numerar la livrare", "Card la ridicare"… */
export function paymentLabel(method: string, fulfillment: Fulfillment | ''): string {
  const how = method === CARD_ON_DELIVERY ? 'Card' : 'Numerar';
  return `${how} ${fulfillment === 'pickup' ? 'la ridicare' : 'la livrare'}`;
}
