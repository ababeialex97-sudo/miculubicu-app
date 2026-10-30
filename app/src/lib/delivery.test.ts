// Run with: npm test
/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';

import type { DeliveryQuote, Location } from '../api/types.ts';
import { etaLabel, formatKm, lowestBandFee, shippingBani } from './delivery.ts';

const flat: Location = {
  id: 'spital',
  name: 'La Spital',
  address: '',
  phone: '',
  delivery: true,
  pickup: true,
  delivery_fee: '10.00',
  free_delivery_threshold: '150.00',
};
const byDistance: Location = {
  ...flat,
  distance_pricing: true,
  delivery_bands: [
    { up_to_km: 2, fee: '10.00' },
    { up_to_km: 4, fee: '15.00' },
  ],
};
const quote = (fee: string | null): DeliveryQuote => ({
  mode: 'distance',
  available: true,
  fee,
  distance_km: 3.2,
  verified: true,
  formatted_address: '',
  message: '',
});

test('flat fee without distance pricing', () => {
  assert.equal(shippingBani(flat, undefined, 5000), 1000);
});

test('distance fee comes from the quote', () => {
  assert.equal(shippingBani(byDistance, quote('15.00'), 5000), 1500);
});

test('unknown until the address is measured', () => {
  assert.equal(shippingBani(byDistance, undefined, 5000), null);
  assert.equal(shippingBani(byDistance, quote(null), 5000), null);
});

test('free from the threshold whatever the distance, never when the threshold is 0', () => {
  assert.equal(shippingBani(flat, undefined, 14999), 1000);
  assert.equal(shippingBani(flat, undefined, 15000), 0);
  assert.equal(shippingBani(byDistance, undefined, 15000), 0);
  assert.equal(shippingBani({ ...flat, free_delivery_threshold: '0.00' }, undefined, 50000), 1000);
});

test('lowest band', () => {
  assert.equal(lowestBandFee(byDistance), 1000);
  assert.equal(lowestBandFee(flat), null);
});

test('km label', () => {
  assert.equal(formatKm(3.24), '3,2 km');
  assert.equal(formatKm(4), '4 km');
});

test('arrival time only while it is ahead', () => {
  const now = new Date('2026-10-30T17:30:00Z');
  assert.match(etaLabel('2026-10-30T17:42:00Z', now) ?? '', /^Ajunge în jur de \d\d:42$/);
  assert.equal(etaLabel('2026-10-30T17:20:00Z', now), null);
  assert.equal(etaLabel(null, now), null);
});
