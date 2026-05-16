import { getDb } from '$lib/server/db';
import { users } from '$lib/server/db/schema';
import { hashPassword, verifyPassword } from '$lib/server/password';
import { error, json } from '@sveltejs/kit';
import { eq } from 'drizzle-orm';

export async function POST({ request, locals }) {
  if (!locals.user) error(401, 'Login required');

  const db = getDb();
  const [user] = await db.select().from(users).where(eq(users.id, locals.user.id)).limit(1);
  if (!user) error(401, 'Login required');

  const body = await request.json();
  const currentPassword = String(body.currentPassword ?? '');
  const newPassword = String(body.newPassword ?? '');

  if (!(await verifyPassword(currentPassword, user.passwordHash)) || newPassword.length < 12) {
    error(400, 'Account password change failed');
  }

  await db
    .update(users)
    .set({
      passwordHash: await hashPassword(newPassword),
      updatedAt: new Date()
    })
    .where(eq(users.id, user.id));

  return json({ ok: true });
}
