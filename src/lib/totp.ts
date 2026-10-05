export const TOTP_ALGORITHMS = ['SHA1', 'SHA256', 'SHA512'] as const;
export const TOTP_DIGITS = [6, 7, 8] as const;
export const DEFAULT_TOTP_PERIOD = 30;

export type TotpAlgorithm = (typeof TOTP_ALGORITHMS)[number];

export type TotpParams = {
  secret: string;
  algorithm: TotpAlgorithm;
  digits: number;
  period: number;
};

export type TotpAccount = TotpParams & {
  issuer: string;
  account: string;
};

export type OtpQrResult = {
  accounts: TotpAccount[];
  skipped: number;
};

const base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const hashNames: Record<TotpAlgorithm, string> = {
  SHA1: 'SHA-1',
  SHA256: 'SHA-256',
  SHA512: 'SHA-512'
};

export function normalizeTotpSecret(value: string) {
  return value.toUpperCase().replace(/[\s-]/g, '').replace(/=+$/, '');
}

export function isValidTotpSecret(value: string) {
  const secret = normalizeTotpSecret(value);
  return /^[A-Z2-7]+$/.test(secret) && ![1, 3, 6].includes(secret.length % 8);
}

export function normalizeTotpAlgorithm(value: unknown): TotpAlgorithm {
  const algorithm = String(value ?? '').toUpperCase().replace(/-/g, '');
  return TOTP_ALGORITHMS.includes(algorithm as TotpAlgorithm) ? (algorithm as TotpAlgorithm) : 'SHA1';
}

export function normalizeTotpDigits(value: unknown) {
  const digits = Number(value);
  return Number.isInteger(digits) && digits >= 6 && digits <= 8 ? digits : 6;
}

export function normalizeTotpPeriod(value: unknown) {
  const period = Number(value);
  return Number.isInteger(period) && period >= 1 && period <= 300 ? period : DEFAULT_TOTP_PERIOD;
}

export function base32Decode(value: string) {
  const secret = normalizeTotpSecret(value);
  const bytes = new Uint8Array(Math.floor((secret.length * 5) / 8));
  let buffer = 0;
  let bits = 0;
  let index = 0;

  for (const char of secret) {
    const chunk = base32Alphabet.indexOf(char);
    if (chunk === -1) throw new Error('Invalid base32 secret');
    buffer = ((buffer << 5) | chunk) & 0xffff;
    bits += 5;
    if (bits >= 8) {
      bits -= 8;
      bytes[index++] = (buffer >> bits) & 0xff;
    }
  }

  return bytes;
}

export function base32Encode(bytes: Uint8Array) {
  let output = '';
  let buffer = 0;
  let bits = 0;

  for (const byte of bytes) {
    buffer = ((buffer << 8) | byte) & 0xffff;
    bits += 8;
    while (bits >= 5) {
      bits -= 5;
      output += base32Alphabet[(buffer >> bits) & 0x1f];
    }
  }

  if (bits > 0) output += base32Alphabet[(buffer << (5 - bits)) & 0x1f];
  return output;
}

export async function generateTotp(params: TotpParams, timeMs = Date.now()) {
  const counter = Math.floor(timeMs / 1000 / params.period);
  const message = new DataView(new ArrayBuffer(8));
  message.setUint32(0, Math.floor(counter / 2 ** 32));
  message.setUint32(4, counter >>> 0);

  const key = await crypto.subtle.importKey(
    'raw',
    base32Decode(params.secret),
    { name: 'HMAC', hash: hashNames[params.algorithm] },
    false,
    ['sign']
  );
  const mac = new Uint8Array(await crypto.subtle.sign('HMAC', key, message.buffer));
  const offset = mac[mac.length - 1] & 0x0f;
  const binary =
    ((mac[offset] & 0x7f) << 24) | (mac[offset + 1] << 16) | (mac[offset + 2] << 8) | mac[offset + 3];

  return String(binary % 10 ** params.digits).padStart(params.digits, '0');
}

export function totpSecondsLeft(period: number, timeMs = Date.now()) {
  return period - (Math.floor(timeMs / 1000) % period);
}

function splitLabel(label: string) {
  const separator = label.indexOf(':');
  if (separator === -1) return { issuer: '', account: label.trim() };
  return { issuer: label.slice(0, separator).trim(), account: label.slice(separator + 1).trim() };
}

function parseOtpauthUri(url: URL): OtpQrResult {
  const kind = url.hostname.toLowerCase();
  if (kind === 'hotp') return { accounts: [], skipped: 1 };
  if (kind !== 'totp') throw new Error('Unsupported OTP type');

  const label = splitLabel(decodeURIComponent(url.pathname.slice(1)));
  const secret = url.searchParams.get('secret') ?? '';
  if (!isValidTotpSecret(secret)) throw new Error('Invalid TOTP secret');

  return {
    accounts: [
      {
        issuer: url.searchParams.get('issuer')?.trim() || label.issuer,
        account: label.account,
        secret: normalizeTotpSecret(secret),
        algorithm: normalizeTotpAlgorithm(url.searchParams.get('algorithm')),
        digits: normalizeTotpDigits(url.searchParams.get('digits') ?? 6),
        period: normalizeTotpPeriod(url.searchParams.get('period') ?? DEFAULT_TOTP_PERIOD)
      }
    ],
    skipped: 0
  };
}

type ProtobufField = { field: number; value: number | Uint8Array };

function readProtobuf(bytes: Uint8Array) {
  const fields: ProtobufField[] = [];
  let position = 0;

  const readVarint = () => {
    let result = 0;
    let multiplier = 1;
    while (true) {
      if (position >= bytes.length) throw new Error('Truncated protobuf');
      const byte = bytes[position++];
      result += (byte & 0x7f) * multiplier;
      if ((byte & 0x80) === 0) return result;
      multiplier *= 128;
    }
  };

  const skip = (length: number) => {
    if (position + length > bytes.length) throw new Error('Truncated protobuf');
    position += length;
  };

  while (position < bytes.length) {
    const tag = readVarint();
    const field = Math.floor(tag / 8);
    const wireType = tag % 8;

    if (wireType === 0) {
      fields.push({ field, value: readVarint() });
    } else if (wireType === 2) {
      const length = readVarint();
      const start = position;
      skip(length);
      fields.push({ field, value: bytes.subarray(start, position) });
    } else if (wireType === 1) {
      skip(8);
    } else if (wireType === 5) {
      skip(4);
    } else {
      throw new Error('Unsupported protobuf wire type');
    }
  }

  return fields;
}

const migrationAlgorithms: Record<number, TotpAlgorithm> = { 0: 'SHA1', 1: 'SHA1', 2: 'SHA256', 3: 'SHA512' };
const migrationDigits: Record<number, number> = { 0: 6, 1: 6, 2: 8 };
const migrationHotpType = 1;

function parseMigrationUri(url: URL): OtpQrResult {
  const data = url.search.match(/[?&]data=([^&]*)/)?.[1];
  if (!data) throw new Error('Missing migration data');

  const base64 = decodeURIComponent(data).replace(/-/g, '+').replace(/_/g, '/');
  const bytes = Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
  const decoder = new TextDecoder();
  const result: OtpQrResult = { accounts: [], skipped: 0 };

  for (const entry of readProtobuf(bytes)) {
    if (entry.field !== 1 || typeof entry.value === 'number') continue;

    let secret: Uint8Array = new Uint8Array();
    let name = '';
    let issuer = '';
    let algorithm = 0;
    let digits = 0;
    let type = 0;

    for (const { field, value } of readProtobuf(entry.value)) {
      if (field === 1 && typeof value !== 'number') secret = value;
      else if (field === 2 && typeof value !== 'number') name = decoder.decode(value);
      else if (field === 3 && typeof value !== 'number') issuer = decoder.decode(value).trim();
      else if (field === 4 && typeof value === 'number') algorithm = value;
      else if (field === 5 && typeof value === 'number') digits = value;
      else if (field === 6 && typeof value === 'number') type = value;
    }

    if (type === migrationHotpType || !(algorithm in migrationAlgorithms) || secret.length === 0) {
      result.skipped++;
      continue;
    }

    let account = name.trim();
    if (issuer && account.startsWith(`${issuer}:`)) {
      account = account.slice(issuer.length + 1).trim();
    } else if (!issuer) {
      ({ issuer, account } = splitLabel(account));
    }

    result.accounts.push({
      issuer,
      account,
      secret: base32Encode(secret),
      algorithm: migrationAlgorithms[algorithm],
      digits: migrationDigits[digits] ?? 6,
      period: DEFAULT_TOTP_PERIOD
    });
  }

  return result;
}

export function parseOtpQr(text: string): OtpQrResult {
  const value = text.trim();
  const scheme = value.slice(0, value.indexOf(':') + 1).toLowerCase();
  if (scheme === 'otpauth:') return parseOtpauthUri(new URL(value));
  if (scheme === 'otpauth-migration:') return parseMigrationUri(new URL(value));
  throw new Error('Not a 2FA QR code');
}
