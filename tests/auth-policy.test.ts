import assert from 'node:assert/strict';
import test from 'node:test';
import {
  USER_ACTIVITY_HEADER,
  USER_ACTIVITY_HEADER_VALUE,
  USER_ACTIVITY_THROTTLE_MS,
  isTrustedLogoutRequest,
  isTrustedActivityRequest,
  parseOidcAppUrl,
  parseOidcIssuerUrl,
  shouldSendActivitySignal
} from '../src/lib/auth-policy.ts';

function activityRequest(
  url = 'https://vault.example.com/auth/activity',
  headers: Record<string, string> = {}
) {
  return new Request(url, {
    method: 'POST',
    headers: {
      origin: 'https://vault.example.com',
      [USER_ACTIVITY_HEADER]: USER_ACTIVITY_HEADER_VALUE,
      ...headers
    }
  });
}

test('accepts only an explicit same-origin activity signal', () => {
  const request = activityRequest();
  assert.equal(isTrustedActivityRequest(request, new URL(request.url)), true);

  const sameOriginMetadata = activityRequest(undefined, { 'sec-fetch-site': 'same-origin' });
  assert.equal(isTrustedActivityRequest(sameOriginMetadata, new URL(sameOriginMetadata.url)), true);
});

test('rejects passive traffic, arbitrary mutations, and forged activity signals', () => {
  const missingHeader = new Request('https://vault.example.com/auth/activity', {
    method: 'POST',
    headers: { origin: 'https://vault.example.com' }
  });
  assert.equal(isTrustedActivityRequest(missingHeader, new URL(missingHeader.url)), false);

  const crossOrigin = activityRequest(undefined, { origin: 'https://attacker.example' });
  assert.equal(isTrustedActivityRequest(crossOrigin, new URL(crossOrigin.url)), false);

  const crossSite = activityRequest(undefined, { 'sec-fetch-site': 'cross-site' });
  assert.equal(isTrustedActivityRequest(crossSite, new URL(crossSite.url)), false);

  const mutation = activityRequest('https://vault.example.com/api/records');
  assert.equal(isTrustedActivityRequest(mutation, new URL(mutation.url)), false);

  const navigation = new Request('https://vault.example.com/diary', {
    headers: { accept: 'text/html' }
  });
  assert.equal(isTrustedActivityRequest(navigation, new URL(navigation.url)), false);
});

test('throttles trusted browser activity signals', () => {
  const now = 1_000_000;
  assert.equal(shouldSendActivitySignal(false, now, 0), false);
  assert.equal(shouldSendActivitySignal(true, now, now - USER_ACTIVITY_THROTTLE_MS + 1), false);
  assert.equal(shouldSendActivitySignal(true, now, now - USER_ACTIVITY_THROTTLE_MS), true);
});

test('accepts only a same-origin logout POST', () => {
  const url = new URL('https://vault.example.com/auth/logout');
  assert.equal(
    isTrustedLogoutRequest(
      new Request(url, {
        method: 'POST',
        headers: { origin: url.origin, 'sec-fetch-site': 'same-origin' }
      }),
      url
    ),
    true
  );
  assert.equal(
    isTrustedLogoutRequest(
      new Request(url, { method: 'POST', headers: { origin: 'https://other.example.com' } }),
      url
    ),
    false
  );
  assert.equal(
    isTrustedLogoutRequest(new Request(url, { headers: { origin: url.origin } }), url),
    false
  );
});

test('accepts HTTPS OIDC URLs and loopback HTTP URLs', () => {
  assert.equal(
    parseOidcIssuerUrl('https://id.example.com/application/o/memory-vault/').href,
    'https://id.example.com/application/o/memory-vault/'
  );
  assert.equal(
    parseOidcIssuerUrl('http://localhost:9000/realms/dev').href,
    'http://localhost:9000/realms/dev'
  );
  assert.equal(parseOidcAppUrl('https://vault.example.com').origin, 'https://vault.example.com');
  assert.equal(parseOidcAppUrl('http://127.0.0.1:5173').origin, 'http://127.0.0.1:5173');
  assert.equal(parseOidcAppUrl('http://[::1]:5173').origin, 'http://[::1]:5173');
});

test('rejects insecure, non-HTTP, credentialed, or decorated OIDC URLs', () => {
  for (const value of [
    'http://id.example.com/realms/prod',
    'ftp://id.example.com',
    'https:id.example.com',
    'https://@id.example.com',
    'https://user:secret@id.example.com',
    'https://id.example.com/realms/prod?tenant=one',
    'https://id.example.com/realms/prod#metadata'
  ]) {
    assert.throws(() => parseOidcIssuerUrl(value));
  }

  for (const value of [
    'http://vault.example.com',
    'ftp://vault.example.com',
    'https://@vault.example.com',
    'https://user:secret@vault.example.com',
    'https://vault.example.com/app',
    'https://vault.example.com/.',
    'https://vault.example.com?',
    'https://vault.example.com#'
  ]) {
    assert.throws(() => parseOidcAppUrl(value));
  }
});
