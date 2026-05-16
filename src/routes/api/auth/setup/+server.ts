import { createSession, getAdminCount } from '$lib/server/auth';
import { getDb } from '$lib/server/db';
import { users } from '$lib/server/db/schema';
import { hashPassword } from '$lib/server/password';
import { error, json } from '@sveltejs/kit';

export async function POST({ request, cookies }) {
  const db = getDb();
  const adminCount = await getAdminCount();
  if (adminCount > 0) error(403, 'Admin already exists');

  const body = await request.json();
  const email = String(body.email ?? '').trim().toLowerCase();
  const name = String(body.name ?? '').trim();
  const password = String(body.password ?? '');
  const kekSalt = String(body.kekSalt ?? '');
  const encryptedDEK = String(body.encryptedDEK ?? '');
  const dekIV = String(body.dekIV ?? '');

  if (!email || !name || password.length < 12 || !kekSalt || !encryptedDEK || !dekIV) {
    error(400, 'Missing setup fields');
  }

  const [user] = await db
    .insert(users)
    .values({
      email,
      name,
      passwordHash: await hashPassword(password),
      kekSalt,
      encryptedDek: encryptedDEK,
      dekIv: dekIV
    })
    .returning();

  await createSession(cookies, user.id);

  return json({ ok: true });
}
