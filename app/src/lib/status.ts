import type { Fulfillment, OrderStatus } from '@/api/types';

export type TimelineStep = { status: OrderStatus; label: string };

/** Steps shown on the status screen, in order, with the wording from the mockup. */
export function timeline(fulfillment: Fulfillment | ''): TimelineStep[] {
  const middle: TimelineStep[] = [
    { status: 'received', label: 'Primită' },
    { status: 'confirmed', label: 'Acceptată de restaurant' },
    { status: 'preparing', label: 'Pe jar' },
  ];
  if (fulfillment === 'pickup') {
    return [...middle, { status: 'ready_for_pickup', label: 'Gata de ridicare' }, { status: 'completed', label: 'Ridicată' }];
  }
  return [...middle, { status: 'on_the_way', label: 'În drum spre tine' }, { status: 'completed', label: 'Livrată' }];
}

export function headline(status: OrderStatus, fulfillment: Fulfillment | ''): string {
  switch (status) {
    case 'received':
      return 'Am primit comanda';
    case 'confirmed':
      return 'Restaurantul a acceptat comanda';
    case 'preparing':
      return 'Comanda ta e pe jar';
    case 'on_the_way':
      return 'Comanda e în drum spre tine';
    case 'ready_for_pickup':
      return 'Comanda te așteaptă';
    case 'completed':
      return fulfillment === 'pickup' ? 'Poftă bună!' : 'Comanda a fost livrată';
    case 'cancelled':
      return 'Comanda a fost anulată';
  }
}

export function isActive(status: OrderStatus): boolean {
  return status !== 'completed' && status !== 'cancelled';
}
