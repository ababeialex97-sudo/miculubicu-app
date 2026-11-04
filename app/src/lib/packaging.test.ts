// Run with: npm test
/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';

import type { PackagingConfig, PackagingRules } from '../api/types.ts';
import { freebiesLabel, packagingLabel, packagingSummary } from './packaging.ts';

const config: PackagingConfig = { capacity: 10, pack_price: '3.50', platter_price: '10.00', at_least_one: true, pickup: true, mustard_ml: 20 };
const mic: PackagingRules = { units: 1, box: 'mici', platter: false, bread: 1, mustard: 1 };
const ceafa: PackagingRules = { units: 1, box: 'ceafa', platter: false, bread: 2, mustard: 2 };
const cartofi: PackagingRules = { units: 0, box: '-', platter: false, bread: 0, mustard: 0 };
const platou: PackagingRules = { units: 0, box: '-', platter: true, bread: 0, mustard: 0 };

test('8 mici and 4 cefe need 2 packs', () => {
  const summary = packagingSummary(config, [{ quantity: 8, rules: mic }, { quantity: 4, rules: ceafa }], 'delivery');
  assert.deepEqual(summary, { packs: 2, platters: 0, totalBani: 700, bread: 16, mustardMl: 320 });
});

test('mici and cefe never share a box', () => {
  assert.equal(packagingSummary(config, [{ quantity: 3, rules: mic }, { quantity: 2, rules: ceafa }], 'delivery')?.packs, 2);
  assert.equal(packagingSummary(config, [{ quantity: 15, rules: mic }, { quantity: 12, rules: ceafa }], 'delivery')?.packs, 4);
});

test('pack edges', () => {
  assert.equal(packagingSummary(config, [{ quantity: 10, rules: mic }], 'delivery')?.packs, 1);
  assert.equal(packagingSummary(config, [{ quantity: 11, rules: mic }], 'delivery')?.packs, 2);
});

test('platter box on its own, pack added for mici next to it', () => {
  assert.deepEqual(packagingSummary(config, [{ quantity: 1, rules: platou }], 'delivery'), { packs: 0, platters: 1, totalBani: 1000, bread: 0, mustardMl: 0 });
  assert.equal(packagingSummary(config, [{ quantity: 1, rules: platou }, { quantity: 2, rules: mic }], 'delivery')?.totalBani, 1350);
});

test('at least one pack, even for fries alone or products without rules', () => {
  assert.equal(packagingSummary(config, [{ quantity: 1, rules: cartofi }], 'delivery')?.packs, 1);
  assert.equal(packagingSummary(config, [{ quantity: 1, rules: undefined }], 'delivery')?.packs, 1);
  assert.equal(packagingSummary({ ...config, at_least_one: false }, [{ quantity: 1, rules: cartofi }], 'delivery')?.packs, 0);
});

test('off without config, or for pickup when pickup is free', () => {
  assert.equal(packagingSummary(null, [{ quantity: 1, rules: mic }], 'delivery'), null);
  assert.equal(packagingSummary({ ...config, pickup: false }, [{ quantity: 1, rules: mic }], 'pickup'), null);
});

test('labels', () => {
  assert.equal(freebiesLabel(16, 320), '16 felii de pâine și 320 ml de muștar');
  assert.equal(freebiesLabel(1, 0), '1 felie de pâine');
  assert.equal(freebiesLabel(0, 0), null);
  assert.equal(packagingLabel(2, 1), '2 pachete, 1 platou');
});
