// Run with: npm test
/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { MAX_GUEST_ORDERS, addGuestOrder, guestContactErrors, guestTokenFor } from './guest.ts';

test('newest guest order comes first and replaces an older entry for the same order', () => {
  let refs = addGuestOrder([], { id: 1, token: 'a' });
  refs = addGuestOrder(refs, { id: 2, token: 'b' });
  refs = addGuestOrder(refs, { id: 1, token: 'c' });
  assert.deepEqual(refs, [
    { id: 1, token: 'c' },
    { id: 2, token: 'b' },
  ]);
  assert.equal(guestTokenFor(refs, 1), 'c');
  assert.equal(guestTokenFor(refs, 3), null);
});

test('only the most recent guest orders are kept', () => {
  let refs: { id: number; token: string }[] = [];
  for (let id = 1; id <= MAX_GUEST_ORDERS + 5; id++) {
    refs = addGuestOrder(refs, { id, token: String(id) });
  }
  assert.equal(refs.length, MAX_GUEST_ORDERS);
  assert.equal(refs[0].id, MAX_GUEST_ORDERS + 5);
});

test('guest contact needs a first name; email is optional but must look right', () => {
  assert.deepEqual(guestContactErrors({ first_name: 'Ion', last_name: '', email: '', phone: '' }), {});
  assert.ok(guestContactErrors({ first_name: ' ', last_name: '', email: '', phone: '' }).first_name);
  assert.ok(guestContactErrors({ first_name: 'Ion', last_name: '', email: 'ion@', phone: '' }).email);
  assert.deepEqual(guestContactErrors({ first_name: 'Ion', last_name: '', email: 'ion@exemplu.ro', phone: '' }), {});
});
