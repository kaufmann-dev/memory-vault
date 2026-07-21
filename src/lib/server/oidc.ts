import { env } from '$env/dynamic/private';
import { parseOidcAppUrl, parseOidcIssuerUrl } from '$lib/auth-policy';
import { getDb } from '$lib/server/db';
import { oidcLoginStates } from '$lib/server/db/schema';
import type { Cookies } from '@sveltejs/kit';
import { and, eq, gt, lt } from 'drizzle-orm';
import { createHash, timingSafeEqual } from 'node:crypto';
import * as oidc from 'openid-client';

const FLOW_COOKIE = 'mv_oidc_flow';
const FLOW_LIFETIME_MS = 10 * 60 * 1000;
const OIDC_SCOPE = 'openid profile email';

type OidcSettings = {
  issuerUrl: URL;
  clientId: string;
  clientSecret: string;
  callbackUrl: string;
  postLogoutUrl: string;
};

let configurationPromise: Promise<oidc.Configuration> | null = null;

function requireEnv(name: 'OIDC_ISSUER_URL' | 'OIDC_CLIENT_ID' | 'OIDC_CLIENT_SECRET' | 'OIDC_APP_URL') {
  const value = env[name]?.trim();
  if (!value) throw new Error(`${name} is required`);
  return value;
}

function getSettings(): OidcSettings {
  const issuerUrl = parseOidcIssuerUrl(requireEnv('OIDC_ISSUER_URL'));
  const appUrl = parseOidcAppUrl(requireEnv('OIDC_APP_URL'));

  return {
    issuerUrl,
    clientId: requireEnv('OIDC_CLIENT_ID'),
    clientSecret: requireEnv('OIDC_CLIENT_SECRET'),
    callbackUrl: new URL('/auth/callback', appUrl).href,
    postLogoutUrl: new URL('/login', appUrl).href
  };
}

function hashState(state: string) {
  return createHash('sha256').update(state).digest('base64url');
}

function matches(left: string, right: string) {
  const leftBuffer = Buffer.from(left);
  const rightBuffer = Buffer.from(right);
  return leftBuffer.length === rightBuffer.length && timingSafeEqual(leftBuffer, rightBuffer);
}

function setFlowCookie(cookies: Cookies, state: string, expiresAt: Date) {
  cookies.set(FLOW_COOKIE, state, {
    path: '/auth/callback',
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    expires: expiresAt
  });
}

function clearFlowCookie(cookies: Cookies) {
  cookies.delete(FLOW_COOKIE, { path: '/auth/callback' });
}

async function getConfiguration() {
  if (!configurationPromise) {
    const settings = getSettings();
    configurationPromise = oidc
      .discovery(
        settings.issuerUrl,
        settings.clientId,
        { client_secret: settings.clientSecret },
        oidc.ClientSecretPost(settings.clientSecret),
        settings.issuerUrl.protocol === 'http:'
          ? { execute: [oidc.allowInsecureRequests] }
          : undefined
      )
      .catch((cause) => {
        configurationPromise = null;
        throw cause;
      });
  }
  return configurationPromise;
}

export async function beginAuthorization(cookies: Cookies) {
  const db = getDb();
  const configuration = await getConfiguration();
  const settings = getSettings();
  const state = oidc.randomState();
  const nonce = oidc.randomNonce();
  const codeVerifier = oidc.randomPKCECodeVerifier();
  const codeChallenge = await oidc.calculatePKCECodeChallenge(codeVerifier);
  const expiresAt = new Date(Date.now() + FLOW_LIFETIME_MS);

  await db.delete(oidcLoginStates).where(lt(oidcLoginStates.expiresAt, new Date()));
  await db.insert(oidcLoginStates).values({
    stateHash: hashState(state),
    codeVerifier,
    nonce,
    expiresAt
  });
  setFlowCookie(cookies, state, expiresAt);

  return oidc.buildAuthorizationUrl(configuration, {
    redirect_uri: settings.callbackUrl,
    response_type: 'code',
    scope: OIDC_SCOPE,
    state,
    nonce,
    code_challenge: codeChallenge,
    code_challenge_method: 'S256'
  });
}

export async function completeAuthorization(currentUrl: URL, cookies: Cookies) {
  const state = currentUrl.searchParams.get('state') ?? '';
  const cookieState = cookies.get(FLOW_COOKIE) ?? '';
  clearFlowCookie(cookies);

  if (!state || !cookieState || !matches(state, cookieState)) {
    throw new Error('Invalid OIDC state');
  }

  const db = getDb();
  const now = new Date();
  const [flow] = await db
    .delete(oidcLoginStates)
    .where(and(eq(oidcLoginStates.stateHash, hashState(state)), gt(oidcLoginStates.expiresAt, now)))
    .returning();
  if (!flow) throw new Error('Expired or unknown OIDC state');

  const configuration = await getConfiguration();
  const callbackUrl = new URL(getSettings().callbackUrl);
  callbackUrl.search = currentUrl.search;
  const tokens = await oidc.authorizationCodeGrant(configuration, callbackUrl, {
    pkceCodeVerifier: flow.codeVerifier,
    expectedState: state,
    expectedNonce: flow.nonce,
    idTokenExpected: true
  });

  const claims = tokens.claims();
  if (!tokens.id_token || !claims) throw new Error('OIDC provider did not return an ID token');

  const email = typeof claims.email === 'string' && claims.email.trim() ? claims.email.trim().toLowerCase() : null;
  const preferredUsername =
    typeof claims.preferred_username === 'string' && claims.preferred_username.trim()
      ? claims.preferred_username.trim()
      : null;
  const name = typeof claims.name === 'string' && claims.name.trim() ? claims.name.trim() : null;

  return {
    idToken: tokens.id_token,
    email: email ?? preferredUsername ?? `oidc:${claims.sub}`,
    name: name ?? preferredUsername ?? email ?? claims.sub
  };
}

export async function buildLogoutUrl(idToken: string) {
  const configuration = await getConfiguration();
  if (!configuration.serverMetadata().end_session_endpoint) {
    throw new Error('OIDC provider does not advertise an end_session_endpoint');
  }

  return oidc.buildEndSessionUrl(configuration, {
    id_token_hint: idToken,
    post_logout_redirect_uri: getSettings().postLogoutUrl
  });
}
