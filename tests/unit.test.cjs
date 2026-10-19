'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function createAweSum() {
  const storage = new Map();
  const localStorage = {
    getItem: key => storage.has(key) ? storage.get(key) : null,
    setItem: (key, value) => storage.set(key, String(value)),
    removeItem: key => storage.delete(key),
  };
  const document = {
    addEventListener() {},
    querySelectorAll() { return []; },
  };
  const window = { AWE_SUM_PAYMENT_GATEWAY_URL: '' };
  const source = fs.readFileSync(path.join(__dirname, '..', 'app.js'), 'utf8');

  vm.runInNewContext(source, { document, localStorage, window, fetch: async () => { throw new Error('Unexpected network request'); } });
  return window.AweSum;
}

test('storage reads return fallback for missing or malformed values', () => {
  const aweSum = createAweSum();
  assert.deepEqual(JSON.parse(JSON.stringify(aweSum.read('missing', []))), []);
  aweSum.write('broken', '{');
  assert.deepEqual(JSON.parse(JSON.stringify(aweSum.read('broken', { safe: true }))), { safe: true });
});

test('cart additions increment matching products and quote additions replace duplicates', () => {
  const aweSum = createAweSum();
  aweSum.addToCart({ id: 4, name: 'Notebook', price: 25 });
  aweSum.addToCart({ id: 4, name: 'Notebook', price: 25 });
  assert.equal(aweSum.getCart()[0].quantity, 2);

  const quote = { id: 'Q-12', category: 'Packaging', quantity: 50, quotedPrice: '120.00' };
  aweSum.addQuoteToCart(quote);
  aweSum.addQuoteToCart(quote);
  const cart = aweSum.getCart();
  assert.equal(cart.filter(item => item.quoteId === 'Q-12').length, 1);
  assert.equal(cart.find(item => item.quoteId === 'Q-12').price, 120);
});

test('HTML escaping protects text inserted into markup', () => {
  const aweSum = createAweSum();
  assert.equal(aweSum.escapeHtml(`<tag attr="&'">`), '&lt;tag attr=&quot;&amp;&#39;&quot;&gt;');
});

test('money formatting returns the site currency prefix and preserves cents', () => {
  const aweSum = createAweSum();
  assert.match(aweSum.money(1234.5), /^R /);
  assert.match(aweSum.money(1234.5), /[,.\u00a0\u202f]50$/);
});
