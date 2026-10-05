// Run with: npm test
/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';

import type { Coupon } from '@/api/types';

import { couponSubtitle, couponTitle, stampSlots } from './loyalty.ts';

const coupon: Coupon = {
  code: 'bunvenit',
  description: '',
  discount_type: 'percent',
  amount: '10.00',
  amount_label: '10%',
  minimum_amount: '0.00',
  first_order_only: false,
  loyalty_reward: false,
  expires_at: null,
};

test('coupon title falls back to the discount or the reward', () => {
  assert.equal(couponTitle({ ...coupon, description: ' Bun venit în aplicație ' }), 'Bun venit în aplicație');
  assert.equal(couponTitle(coupon), 'Reducere 10%');
  assert.equal(couponTitle({ ...coupon, loyalty_reward: true }), 'Recompensa cardului de fidelitate');
});

test('coupon subtitle lists the conditions and the code', () => {
  assert.equal(couponSubtitle(coupon), 'Cod BUNVENIT');
  assert.equal(
    couponSubtitle({ ...coupon, first_order_only: true, minimum_amount: '80.00', expires_at: '2026-11-12T10:00:00+02:00' }),
    'Prima comandă · de la 80 lei · până pe 12.11 · cod BUNVENIT',
  );
});

test('stamp slots are clamped to the card', () => {
  assert.deepEqual(stampSlots(3, 4), [true, true, true, false]);
  assert.deepEqual(stampSlots(9, 2), [true, true]);
  assert.deepEqual(stampSlots(-1, 2), [false, false]);
});
