export const USER_ACTIVITY_PATH = '/auth/activity';
export const USER_ACTIVITY_HEADER = 'x-memory-vault-user-activity';
export const USER_ACTIVITY_HEADER_VALUE = '1';
export const USER_ACTIVITY_THROTTLE_MS = 5 * 60 * 1000;
export const LOGOUT_PATH = '/auth/logout';

function isLocalhost(hostname: string) {
  const normalized = hostname.toLowerCase();
  return (
    normalized === 'localhost' ||
    normalized.endsWith('.localhost') ||
    normalized === '[::1]' ||
    /^127(?:\.\d{1,3}){3}$/.test(normalized)
  );
}

function parseOidcHttpUrl(value: string, label: string) {
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new Error(`${label} must be a valid absolute URL`);
  }

  if (!['http:', 'https:'].includes(url.protocol) || !/^https?:\/\//i.test(value)) {
    throw new Error(`${label} must use HTTP or HTTPS`);
  }
  const schemeSeparator = value.indexOf('://');
  const authorityAndPath = schemeSeparator === -1 ? '' : value.slice(schemeSeparator + 3);
  const authorityEnd = authorityAndPath.search(/[/?#\\]/);
  const authority =
    authorityEnd === -1 ? authorityAndPath : authorityAndPath.slice(0, authorityEnd);
  if (url.username || url.password || authority.includes('@')) {
    throw new Error(`${label} must not contain credentials`);
  }
  if (value.includes('?') || value.includes('#')) {
    throw new Error(`${label} must not contain a query or fragment`);
  }
  if (value.includes('\\')) {
    throw new Error(`${label} must not contain backslashes`);
  }
  if (url.protocol !== 'https:' && !isLocalhost(url.hostname)) {
    throw new Error(`${label} must use HTTPS outside localhost`);
  }

  return url;
}

export function parseOidcIssuerUrl(value: string) {
  return parseOidcHttpUrl(value, 'OIDC_ISSUER_URL');
}

export function parseOidcAppUrl(value: string) {
  const url = parseOidcHttpUrl(value, 'OIDC_APP_URL');
  const schemeSeparator = value.indexOf('://');
  const authorityAndPath = value.slice(schemeSeparator + 3);
  const pathStart = authorityAndPath.indexOf('/');
  const rawPath = pathStart === -1 ? '' : authorityAndPath.slice(pathStart);
  if ((rawPath !== '' && rawPath !== '/') || url.pathname !== '/') {
    throw new Error('OIDC_APP_URL must be an origin without a path');
  }
  return url;
}

export function isTrustedActivityRequest(request: Request, requestUrl: URL) {
  if (request.method.toUpperCase() !== 'POST' || requestUrl.pathname !== USER_ACTIVITY_PATH) {
    return false;
  }
  if (request.headers.get(USER_ACTIVITY_HEADER) !== USER_ACTIVITY_HEADER_VALUE) {
    return false;
  }
  if (request.headers.get('origin') !== requestUrl.origin) {
    return false;
  }

  const fetchSite = request.headers.get('sec-fetch-site');
  return fetchSite === null || fetchSite === 'same-origin';
}

export function isTrustedLogoutRequest(request: Request, requestUrl: URL) {
  if (request.method.toUpperCase() !== 'POST' || requestUrl.pathname !== LOGOUT_PATH) return false;
  if (request.headers.get('origin') !== requestUrl.origin) return false;

  const fetchSite = request.headers.get('sec-fetch-site');
  return fetchSite === null || fetchSite === 'same-origin';
}

export function shouldSendActivitySignal(isTrusted: boolean, now: number, lastSentAt: number) {
  return isTrusted && now - lastSentAt >= USER_ACTIVITY_THROTTLE_MS;
}
