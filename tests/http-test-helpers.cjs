'use strict';

const baseUrl = process.env.AWESUM_TEST_BASE_URL || 'http://127.0.0.1:8080/';

async function request(path) {
  const response = await fetch(new URL(path, baseUrl));
  return response;
}

module.exports = { baseUrl, request };
