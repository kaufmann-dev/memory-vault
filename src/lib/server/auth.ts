import { getDb } from '$lib/server/db';
import { sessions, users } from '$lib/server/db/schema';
import type { SafeUser } from '$lib/types';
import type { Cookies } from '@sveltejs/kit';
import { eq } from 'drizzle-orm';
import { createHash, randomBytes } from 'node:crypto';

const SESSION_COOKIE = 'tsd_session';
const IDLE_LIFETIME_MS = 24 * 60 * 60 * 1000;
const ABSOLUTE_LIFETIME_MS = 7 * 24 * 60 * 60 * 1000;

const tokenHash = (token: string) => createHash('sha256').update(token).digest('base64url');

export type AuthSession = {
  id: string;
  user: SafeUser | null;
  setupEmail: string | null;
  setupName: string | null;
};

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

function clearSessionCookie(cookies: Cookies) {
  cookies.delete(SESSION_COOKIE, { path: '/' });
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

export async function createSession(
  cookies: Cookies,
  input: {
    userId: string | null;
    idToken: string;
    setupEmail?: string;
    setupName?: string;
  }
) {
  const db = getDb();
  const previousToken = cookies.get(SESSION_COOKIE);
  if (previousToken) {
    await db.delete(sessions).where(eq(sessions.tokenHash, tokenHash(previousToken)));
  }

  const token = randomBytes(32).toString('base64url');
  const now = Date.now();
  const idleExpiresAt = new Date(now + IDLE_LIFETIME_MS);
  const absoluteExpiresAt = new Date(now + ABSOLUTE_LIFETIME_MS);

  const [session] = await db
    .insert(sessions)
    .values({
      userId: input.userId,
      tokenHash: tokenHash(token),
      idToken: input.idToken,
      setupEmail: input.setupEmail ?? null,
      setupName: input.setupName ?? null,
      idleExpiresAt,
      absoluteExpiresAt
    })
    .returning({ id: sessions.id });

  setSessionCookie(cookies, token, absoluteExpiresAt);
  return session.id;
}

export async function readSession(cookies: Cookies, refreshIdle: boolean): Promise<AuthSession | null> {
  const db = getDb();
  const token = cookies.get(SESSION_COOKIE);
  if (!token) return null;

  const [row] = await db
    .select({ session: sessions, user: users })
    .from(sessions)
    .leftJoin(users, eq(sessions.userId, users.id))
    .where(eq(sessions.tokenHash, tokenHash(token)))
    .limit(1);

  if (!row) {
    clearSessionCookie(cookies);
    return null;
  }

  const now = Date.now();
  const expired =
    row.session.idleExpiresAt.getTime() <= now || row.session.absoluteExpiresAt.getTime() <= now;
  const missingUser = row.session.userId !== null && row.user === null;
  if (expired || missingUser) {
    await db.delete(sessions).where(eq(sessions.id, row.session.id));
    clearSessionCookie(cookies);
    return null;
  }

  if (refreshIdle) {
    const idleExpiresAt = new Date(
      Math.min(now + IDLE_LIFETIME_MS, row.session.absoluteExpiresAt.getTime())
    );
    await db.update(sessions).set({ idleExpiresAt }).where(eq(sessions.id, row.session.id));
  }

  return {
    id: row.session.id,
    user: row.user ? toSafeUser(row.user) : null,
    setupEmail: row.session.setupEmail,
    setupName: row.session.setupName
  };
}

export async function attachSessionToUser(sessionId: string, userId: string) {
  const db = getDb();
  await db
    .update(sessions)
    .set({
      userId,
      setupEmail: null,
      setupName: null
    })
    .where(eq(sessions.id, sessionId));
}

export async function deleteSession(cookies: Cookies) {
  const db = getDb();
  const token = cookies.get(SESSION_COOKIE);
  if (!token) {
    clearSessionCookie(cookies);
    return null;
  }

  const [deleted] = await db
    .delete(sessions)
    .where(eq(sessions.tokenHash, tokenHash(token)))
    .returning({ idToken: sessions.idToken });

  clearSessionCookie(cookies);
  return deleted?.idToken ?? null;
}
