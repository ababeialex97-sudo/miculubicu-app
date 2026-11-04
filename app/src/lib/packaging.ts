import type { Fulfillment, PackagingConfig, PackagingRules } from '@/api/types';

import { toBani } from './money.ts';

export type PackagingSummary = {
  packs: number;
  platters: number;
  totalBani: number;
  bread: number;
  mustardMl: number;
};

/**
 * Packs, platter boxes and free extras for the cart, the same way mlb-app-api counts them
 * (Packaging_Rules): a pack holds `capacity` pieces across products, platters bring their
 * own box, and bread and mustard come per piece. Null when packaging isn't charged.
 */
export function packagingSummary(
  config: PackagingConfig | null | undefined,
  lines: { quantity: number; rules: PackagingRules | undefined }[],
  fulfillment: Fulfillment,
): PackagingSummary | null {
  if (!config || (fulfillment === 'pickup' && !config.pickup)) {
    return null;
  }

  let units = 0;
  let platters = 0;
  let bread = 0;
  let mustard = 0;
  for (const { quantity, rules } of lines) {
    units += (rules?.units ?? 0) * quantity;
    platters += rules?.platter ? quantity : 0;
    bread += (rules?.bread ?? 0) * quantity;
    mustard += (rules?.mustard ?? 0) * quantity;
  }
  if (config.platter_price === null) {
    platters = 0;
  }

  let packs = Math.ceil(units / Math.max(1, config.capacity));
  // Fries or sauces alone still leave in a bag; a platter has its own box.
  if (packs === 0 && platters === 0 && config.at_least_one && lines.length > 0) {
    packs = 1;
  }

  return {
    packs,
    platters,
    totalBani: packs * toBani(config.pack_price) + platters * toBani(config.platter_price ?? 0),
    bread,
    mustardMl: mustard * config.mustard_ml,
  };
}

/** "16 felii de pâine și 320 ml de muștar", or null when there is nothing free. */
export function freebiesLabel(bread: number, mustardMl: number): string | null {
  const parts = [bread > 0 ? `${bread} ${bread === 1 ? 'felie' : bread < 20 ? 'felii' : 'de felii'} de pâine` : '', mustardMl > 0 ? `${mustardMl} ml de muștar` : ''].filter(Boolean);
  return parts.length > 0 ? parts.join(' și ') : null;
}

/** "2 pachete", "1 pachet, 1 platou" */
export function packagingLabel(packs: number, platters: number): string {
  return [packs > 0 ? `${packs} ${packs === 1 ? 'pachet' : 'pachete'}` : '', platters > 0 ? `${platters} ${platters === 1 ? 'platou' : 'platouri'}` : '']
    .filter(Boolean)
    .join(', ');
}
