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

export const MAX_COUPONS = 2;

/** Coupon codes as WooCommerce stores them: trimmed and lower-case. */
export function normalizeCouponCode(code: string): string {
  return code.trim().toLowerCase();
}

/** Adds a code once; with two codes already applied the newest replaces the last one. */
export function addCouponCode(codes: string[], code: string): string[] {
  const normalized = normalizeCouponCode(code);
  if (!normalized || codes.includes(normalized)) {
    return codes;
  }
  return [...codes.slice(0, MAX_COUPONS - 1), normalized];
}

export function removeCouponCode(codes: string[], code: string): string[] {
  return codes.filter((c) => c !== normalizeCouponCode(code));
}

/** The items part of an order or a cart preview. */
export function orderItems(lines: CartLine[]) {
  return lines.map((l) => ({
    product_id: l.productId,
    variation_id: l.variationId || undefined,
    quantity: l.quantity,
    preferences: l.preferences || undefined,
  }));
}

/** Stamps left until the loyalty reward, for the menu banner. */
export function stampsLeftLabel(stamps: number, required: number): string {
  const left = Math.max(1, required - stamps);
  return left === 1 ? 'Încă o comandă până la reducere' : `Încă ${left} comenzi până la reducere`;
}
