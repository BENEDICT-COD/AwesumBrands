'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');
const { request } = require('./http-test-helpers.cjs');

test('homepage and application script meet the response-time budget', async () => {
  const paths = ['index.html', 'app.js'];
  const durations = [];

  for (let iteration = 0; iteration < 25; iteration++) {
    for (const path of paths) {
      const start = performance.now();
      const response = await request(path);
      await response.arrayBuffer();
      durations.push(performance.now() - start);
      assert.equal(response.status, 200, `${path} should return HTTP 200`);
    }
  }

  durations.sort((left, right) => left - right);
  const p95 = durations[Math.ceil(durations.length * 0.95) - 1];
  assert.ok(p95 < 2000, `95th percentile response time was ${p95.toFixed(1)}ms (limit: 2000ms)`);
});
