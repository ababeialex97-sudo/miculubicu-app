// Run with: npm test
/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { formatBani, formatPrice, toBani } from './money.ts';

test('toBani parses API decimal strings', () => {
  assert.equal(toBani('24.50'), 2450);
  assert.equal(toBani('0.1'), 10);
  assert.equal(toBani(19.99), 1999);
  assert.equal(toBani('abc'), 0);
});

test('formatBani uses Romanian formatting', () => {
  assert.equal(formatBani(2450), '24,50 lei');
  assert.equal(formatBani(2400), '24 lei');
  assert.equal(formatBani(123456), '1.234,56 lei');
  assert.equal(formatBani(-500), '−5 lei');
});

test('formatPrice formats API strings', () => {
  assert.equal(formatPrice('10.00'), '10 lei');
});
