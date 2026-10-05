// Pure cart helpers, kept free of React Native imports so they can be unit tested with Node.

export type CartLine = {
  key: string;
  productId: number;
  variationId: number;
  name: string;
  detail: string;
  imageUrl: string | null;
  unitPrice: string;
  quantity: number;
  preferences: string;
};

export const MAX_QUANTITY = 50;

/** Lines with the same product, variation and preferences are merged. */
export function lineKey(productId: number, variationId: number, preferences: string): string {
  return `${productId}:${variationId}:${preferences.trim().toLowerCase()}`;
}

export function addLine(lines: CartLine[], line: Omit<CartLine, 'key'>): CartLine[] {
  const key = lineKey(line.productId, line.variationId, line.preferences);
  const existing = lines.find((l) => l.key === key);
  if (existing) {
    return lines.map((l) => (l.key === key ? { ...l, quantity: Math.min(MAX_QUANTITY, l.quantity + line.quantity) } : l));
  }
  return [...lines, { ...line, key, quantity: Math.min(MAX_QUANTITY, line.quantity) }];
}

export function setLineQuantity(lines: CartLine[], key: string, quantity: number): CartLine[] {
  if (quantity <= 0) {
    return lines.filter((l) => l.key !== key);
  }
  return lines.map((l) => (l.key === key ? { ...l, quantity: Math.min(MAX_QUANTITY, quantity) } : l));
}

export function subtotalBani(lines: CartLine[]): number {
  return lines.reduce((sum, l) => sum + Math.round(Number.parseFloat(l.unitPrice) * 100) * l.quantity, 0);
}

export function itemCount(lines: CartLine[]): number {
  return lines.reduce((sum, l) => sum + l.quantity, 0);
}

/** Delivery fee shown before ordering; the server recomputes it when the order is placed. */
export function deliveryFeeBani(subtotal: number, feeBani: number, freeThresholdBani: number): number {
  return freeThresholdBani > 0 && subtotal >= freeThresholdBani ? 0 : feeBani;
}

/** Joins the chosen preference chips and the free-text kitchen note into one line for the order. */
export function joinPreferences(chips: string[], note: string): string {
  return [...chips, note.trim()].filter(Boolean).join(', ');
}
