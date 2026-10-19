'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const { baseUrl, request } = require('./http-test-helpers.cjs');

const siteRoot = path.join(__dirname, '..');
const htmlPages = fs.readdirSync(siteRoot).filter(file => file.endsWith('.html'));

test('all site pages are served successfully with HTML content', async () => {
  assert.ok(htmlPages.length > 0, 'Expected to find HTML pages in the site root');
  for (const page of htmlPages) {
    const response = await request(page);
    assert.equal(response.status, 200, `${page} should return HTTP 200`);
    assert.match(response.headers.get('content-type') || '', /text\/html/i, `${page} should be HTML`);
  }
});

test('homepage exposes the expected site title and shared application script', async () => {
  const response = await request('index.html');
  const html = await response.text();
  assert.match(html, /<title>AweSum Brands/i);
  assert.match(html, /<script[^>]+src=["']app\.js["']/i);
});

test('local scripts, stylesheets, and images referenced by pages are available', async () => {
  const localResources = new Set();
  const origin = new URL(baseUrl).origin;
  const resourcePattern = /<(?:script|link|img)\b[^>]*?\b(?:src|href)\s*=\s*(["'])(.*?)\1/gi;

  for (const page of htmlPages) {
    const html = fs.readFileSync(path.join(siteRoot, page), 'utf8');
    for (const match of html.matchAll(resourcePattern)) {
      const value = match[2].trim();
      if (!value || value.startsWith('#') || /^(?:data:|javascript:|mailto:|tel:)/i.test(value)) continue;
      const url = new URL(value, new URL(page, baseUrl));
      if (url.origin === origin) localResources.add(url.pathname.replace(/^\/+/, ''));
    }
  }

  assert.ok(localResources.size > 0, 'Expected pages to reference local resources');
  for (const resource of localResources) {
    const response = await request(resource);
    assert.equal(response.status, 200, `${resource} should return HTTP 200`);
  }
});
