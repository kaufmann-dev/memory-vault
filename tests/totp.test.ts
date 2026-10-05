import assert from 'node:assert/strict';
import test from 'node:test';
import {
  base32Decode,
  base32Encode,
  generateTotp,
  isValidTotpSecret,
  normalizeTotpSecret,
  parseOtpQr,
  totpSecondsLeft,
  type TotpAlgorithm
} from '../src/lib/totp.ts';

const ascii = (value: string) => new TextEncoder().encode(value);
const seed = (length: number) => base32Encode(ascii('1234567890'.repeat(7).slice(0, length)));

test('matches the RFC 6238 test vectors', async () => {
  const vectors: Array<[number, TotpAlgorithm, number, string]> = [
    [59, 'SHA1', 20, '94287082'],
    [59, 'SHA256', 32, '46119246'],
    [59, 'SHA512', 64, '90693936'],
    [1111111109, 'SHA1', 20, '07081804'],
    [1111111109, 'SHA256', 32, '68084774'],
    [1111111109, 'SHA512', 64, '25091201'],
    [2000000000, 'SHA1', 20, '69279037'],
    [2000000000, 'SHA256', 32, '90698825'],
    [2000000000, 'SHA512', 64, '38618901']
  ];

  for (const [seconds, algorithm, length, expected] of vectors) {
    const code = await generateTotp({ secret: seed(length), algorithm, digits: 8, period: 30 }, seconds * 1000);
    assert.equal(code, expected, `${algorithm} at ${seconds}`);
  }
});

test('counts down to the next time step', () => {
  assert.equal(totpSecondsLeft(30, 59_000), 1);
  assert.equal(totpSecondsLeft(30, 60_000), 30);
});

test('round-trips and normalizes base32 secrets', () => {
  const bytes = ascii('Hello!Þ­¾ï');
  assert.deepEqual(base32Decode(base32Encode(bytes)), bytes);
  assert.equal(normalizeTotpSecret('jbsw y3dp-ehpk 3pxp=='), 'JBSWY3DPEHPK3PXP');
  assert.equal(isValidTotpSecret('jbsw y3dp-ehpk 3pxp'), true);
  assert.equal(isValidTotpSecret('JBSWY3D1'), false);
  assert.equal(isValidTotpSecret('ABC'), false);
  assert.equal(isValidTotpSecret(''), false);
});

test('parses otpauth TOTP URIs', () => {
  assert.deepEqual(
    parseOtpQr(
      'otpauth://totp/ACME%20Co:alice@example.com?secret=jbswy3dpehpk3pxp&issuer=ACME%20Co&digits=8&period=60&algorithm=SHA256'
    ),
    {
      accounts: [
        {
          issuer: 'ACME Co',
          account: 'alice@example.com',
          secret: 'JBSWY3DPEHPK3PXP',
          algorithm: 'SHA256',
          digits: 8,
          period: 60
        }
      ],
      skipped: 0
    }
  );

  assert.deepEqual(parseOtpQr('  OTPAUTH://totp/Example:bob?secret=JBSWY3DPEHPK3PXP  ').accounts[0], {
    issuer: 'Example',
    account: 'bob',
    secret: 'JBSWY3DPEHPK3PXP',
    algorithm: 'SHA1',
    digits: 6,
    period: 30
  });
});

test('skips HOTP and rejects invalid OTP QR codes', () => {
  assert.deepEqual(parseOtpQr('otpauth://hotp/Example:bob?secret=JBSWY3DPEHPK3PXP&counter=1'), {
    accounts: [],
    skipped: 1
  });
  assert.throws(() => parseOtpQr('otpauth://totp/Example:bob?secret=not-base32!'));
  assert.throws(() => parseOtpQr('otpauth://totp/Example:bob'));
  assert.throws(() => parseOtpQr('https://example.com'));
});

function varint(value: number) {
  const bytes: number[] = [];
  while (value > 127) {
    bytes.push((value & 0x7f) | 0x80);
    value >>>= 7;
  }
  bytes.push(value);
  return bytes;
}

function bytesField(field: number, value: Uint8Array | number[]) {
  return [...varint(field * 8 + 2), ...varint(value.length), ...value];
}

function varintField(field: number, value: number) {
  return [...varint(field * 8), ...varint(value)];
}

function otpParameters(options: {
  secret: Uint8Array;
  name: string;
  issuer?: string;
  algorithm: number;
  digits: number;
  type: number;
}) {
  return [
    ...bytesField(1, options.secret),
    ...bytesField(2, ascii(options.name)),
    ...(options.issuer ? bytesField(3, ascii(options.issuer)) : []),
    ...varintField(4, options.algorithm),
    ...varintField(5, options.digits),
    ...varintField(6, options.type)
  ];
}

test('parses Google Authenticator migration QR codes', () => {
  const firstSecret = Uint8Array.from([0x00, 0x01, 0xfb, 0xef, 0xbe, 0x02, 0x03, 0x04, 0x05, 0x06]);
  const secondSecret = ascii('12345678901234567890');
  const payload = Uint8Array.from([
    ...bytesField(1, otpParameters({ secret: firstSecret, name: 'alice@example.com', issuer: 'ACME', algorithm: 1, digits: 1, type: 2 })),
    ...bytesField(1, otpParameters({ secret: secondSecret, name: 'Example:bob', algorithm: 2, digits: 2, type: 2 })),
    ...bytesField(1, otpParameters({ secret: secondSecret, name: 'Counter', algorithm: 1, digits: 1, type: 1 })),
    ...varintField(2, 1),
    ...varintField(3, 1)
  ]);
  const data = Buffer.from(payload).toString('base64');
  assert.ok(data.includes('+'), 'fixture should exercise "+" in the data parameter');

  const result = parseOtpQr(`otpauth-migration://offline?data=${encodeURIComponent(data)}`);

  assert.equal(result.skipped, 1);
  assert.deepEqual(result.accounts, [
    {
      issuer: 'ACME',
      account: 'alice@example.com',
      secret: base32Encode(firstSecret),
      algorithm: 'SHA1',
      digits: 6,
      period: 30
    },
    {
      issuer: 'Example',
      account: 'bob',
      secret: base32Encode(secondSecret),
      algorithm: 'SHA256',
      digits: 8,
      period: 30
    }
  ]);
});
