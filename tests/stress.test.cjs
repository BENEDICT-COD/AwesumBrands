'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');
const { request } = require('./http-test-helpers.cjs');

test('site pages remain available during a burst of concurrent requests', async () => {
  const requests = Array.from({ length: 80 }, (_, index) => {
    const page = index % 2 === 0 ? 'index.html' : 'marketplace.html';
    return request(page).then(async response => {
      assert.equal(response.status, 200, `${page} should return HTTP 200 under load`);
      const body = await response.text();
      assert.ok(body.length > 0, `${page} should return a non-empty response under load`);
    });
  });
  let timeoutId;
  const deadline = new Promise((_, reject) => {
    timeoutId = setTimeout(() => reject(new Error('Stress test exceeded its 20 second limit')), 20000);
  });
  await Promise.race([Promise.all(requests), deadline]).finally(() => clearTimeout(timeoutId));
});
