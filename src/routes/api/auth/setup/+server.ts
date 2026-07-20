import { attachSessionToUser, getAdminCount } from '$lib/server/auth';
import { getDb } from '$lib/server/db';
import { users } from '$lib/server/db/schema';
import { error, json } from '@sveltejs/kit';

export async function POST({ request, locals }) {
  const authSession = locals.authSession;
  if (!authSession || locals.user || !authSession.setupEmail || !authSession.setupName) {
    error(401, 'OIDC login required');
  }

  const db = getDb();
  const adminCount = await getAdminCount();
  if (adminCount > 0) error(403, 'Admin already exists');

  const body = await request.json();
  const kekSalt = String(body.kekSalt ?? '');
  const encryptedDEK = String(body.encryptedDEK ?? '');
  const dekIV = String(body.dekIV ?? '');

  if (!kekSalt || !encryptedDEK || !dekIV) {
    error(400, 'Missing setup fields');
  }

  const [user] = await db
    .insert(users)
    .values({
      email: authSession.setupEmail,
      name: authSession.setupName,
      kekSalt,
      encryptedDek: encryptedDEK,
      dekIv: dekIV
    })
    .returning();

  await attachSessionToUser(authSession.id, user.id);

  return json({ ok: true, email: user.email });
}
