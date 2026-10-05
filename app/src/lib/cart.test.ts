// Run with: npm test
/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { addLine, deliveryFeeBani, itemCount, joinPreferences, setLineQuantity, subtotalBani, type CartLine } from './cart.ts';

const mici = {
  productId: 1,
  variationId: 0,
  name: 'Mici oaie-porc',
  detail: '',
  imageUrl: null,
  unitPrice: '4.50',
  quantity: 4,
  preferences: 'Cu muștar',
};

test('same product and preferences merge into one line', () => {
  let lines: CartLine[] = [];
  lines = addLine(lines, mici);
  lines = addLine(lines, { ...mici, quantity: 2, preferences: 'cu muștar ' });
  assert.equal(lines.length, 1);
  assert.equal(lines[0].quantity, 6);
});

test('different preferences make separate lines', () => {
  const lines = addLine(addLine([], mici), { ...mici, preferences: 'Bine făcuți' });
  assert.equal(lines.length, 2);
  assert.equal(itemCount(lines), 8);
});

test('quantity is capped and zero removes the line', () => {
  let lines = addLine([], { ...mici, quantity: 80 });
  assert.equal(lines[0].quantity, 50);
  lines = setLineQuantity(lines, lines[0].key, 0);
  assert.equal(lines.length, 0);
});

test('subtotal is exact in bani', () => {
  const lines = addLine(addLine([], { ...mici, unitPrice: '0.10', quantity: 3 }), { ...mici, productId: 2, unitPrice: '0.20', quantity: 1 });
  assert.equal(subtotalBani(lines), 50);
});

test('delivery is free from the threshold, never when the threshold is 0', () => {
  assert.equal(deliveryFeeBani(9999, 1000, 10000), 1000);
  assert.equal(deliveryFeeBani(10000, 1000, 10000), 0);
  assert.equal(deliveryFeeBani(50000, 1000, 0), 1000);
});

test('preferences join chips and the note', () => {
  assert.equal(joinPreferences(['Cu muștar', 'Bine făcuți'], ' muștarul separat '), 'Cu muștar, Bine făcuți, muștarul separat');
  assert.equal(joinPreferences([], '  '), '');
});
