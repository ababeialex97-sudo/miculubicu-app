// Prices arrive from the API as decimal strings ("24.50"). Math is done in bani (cents)
// to avoid float rounding; the server stays the source of truth for order totals.

export function toBani(amount: string | number): number {
  const value = typeof amount === 'number' ? amount : Number.parseFloat(amount);
  return Number.isFinite(value) ? Math.round(value * 100) : 0;
}

export function formatBani(bani: number): string {
  const sign = bani < 0 ? '−' : '';
  const abs = Math.abs(bani);
  const lei = Math.floor(abs / 100);
  const rest = abs % 100;
  const whole = String(lei).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return rest === 0 ? `${sign}${whole} lei` : `${sign}${whole},${String(rest).padStart(2, '0')} lei`;
}

export function formatPrice(amount: string | number): string {
  return formatBani(toBani(amount));
}
