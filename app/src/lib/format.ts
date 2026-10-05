import type { Product } from '@/api/types';

/** "85 g" rather than "0.085 kg" for portions under a kilogram. */
export function formatWeight(weight: Product['weight']): string | null {
  if (!weight) {
    return null;
  }
  const value = Number.parseFloat(weight.value);
  if (!Number.isFinite(value) || value <= 0) {
    return null;
  }
  if (weight.unit === 'kg' && value < 1) {
    return `${Math.round(value * 1000)} g`;
  }
  return `${String(value).replace('.', ',')} ${weight.unit}`;
}

export function productSubtitle(product: Product): string {
  return [product.short_description, formatWeight(product.weight)].filter(Boolean).join(' · ');
}
