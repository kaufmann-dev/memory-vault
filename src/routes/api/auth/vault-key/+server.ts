import { getDb } from '$lib/server/db';
import { users } from '$lib/server/db/schema';
import { error, json } from '@sveltejs/kit';
import { eq } from 'drizzle-orm';

export async function POST({ request, locals }) {
  if (!locals.user) error(401, 'Login required');

  const body = await request.json();
  const newKekSalt = String(body.newKekSalt ?? '');
  const newEncryptedDEK = String(body.newEncryptedDEK ?? '');
  const newDekIV = String(body.newDekIV ?? '');

  if (!newKekSalt || !newEncryptedDEK || !newDekIV) {
    error(400, 'Vault key update failed');
  }

  const db = getDb();
  await db
    .update(users)
    .set({
      kekSalt: newKekSalt,
      encryptedDek: newEncryptedDEK,
      dekIv: newDekIV,
      updatedAt: new Date()
    })
    .where(eq(users.id, locals.user.id));

  return json({ ok: true });
}
