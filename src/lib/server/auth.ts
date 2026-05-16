import { getDb } from '$lib/server/db';
import { sessions, users } from '$lib/server/db/schema';
import type { SafeUser } from '$lib/types';
import type { Cookies } from '@sveltejs/kit';
import { eq } from 'drizzle-orm';
import { createHash, randomBytes } from 'node:crypto';

const SESSION_COOKIE = 'tsd_session';
const SESSION_DAYS = 30;

const tokenHash = (token: string) => createHash('sha256').update(token).digest('base64url');

const toSafeUser = (user: typeof users.$inferSelect): SafeUser => ({
  id: user.id,
  email: user.email,
  name: user.name,
  role: 'admin',
  kekSalt: user.kekSalt,
  encryptedDEK: user.encryptedDek,
  dekIV: user.dekIv
});

function setSessionCookie(cookies: Cookies, token: string, expiresAt: Date) {
  cookies.set(SESSION_COOKIE, token, {
    path: '/',
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    expires: expiresAt
  });
}

export async function getAdminUser() {
  const db = getDb();
  const [admin] = await db.select().from(users).limit(1);
  return admin ?? null;
}

export async function getAdminCount() {
  const db = getDb();
  const all = await db.select({ id: users.id }).from(users).limit(2);
  return all.length;
}

export async function createSession(cookies: Cookies, userId: string) {
  const db = getDb();
  const token = randomBytes(32).toString('base64url');
  const expiresAt = new Date(Date.now() + SESSION_DAYS * 24 * 60 * 60 * 1000);

  await db.insert(sessions).values({
    userId,
    tokenHash: tokenHash(token),
    expiresAt
  });

  setSessionCookie(cookies, token, expiresAt);
}

export async function readSession(cookies: Cookies) {
  const db = getDb();
  const token = cookies.get(SESSION_COOKIE);
  if (!token) return null;

  const [row] = await db
    .select({ session: sessions, user: users })
    .from(sessions)
    .innerJoin(users, eq(sessions.userId, users.id))
    .where(eq(sessions.tokenHash, tokenHash(token)))
    .limit(1);

  if (!row) return null;

  if (row.session.expiresAt.getTime() <= Date.now()) {
    await db.delete(sessions).where(eq(sessions.id, row.session.id));
    cookies.delete(SESSION_COOKIE, { path: '/' });
    return null;
  }

  return toSafeUser(row.user);
}

export async function deleteSession(cookies: Cookies) {
  const db = getDb();
  const token = cookies.get(SESSION_COOKIE);
  if (token) {
    await db.delete(sessions).where(eq(sessions.tokenHash, tokenHash(token)));
  }

  cookies.delete(SESSION_COOKIE, { path: '/' });
}
