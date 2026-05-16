import { createSession, getAdminUser } from '$lib/server/auth';
import { verifyPassword } from '$lib/server/password';
import { error, json } from '@sveltejs/kit';

export async function POST({ request, cookies }) {
  const admin = await getAdminUser();
  if (!admin) error(404, 'Admin has not been created');

  const body = await request.json();
  const email = String(body.email ?? '').trim().toLowerCase();
  const password = String(body.password ?? '');

  if (email !== admin.email || !(await verifyPassword(password, admin.passwordHash))) {
    error(401, 'Invalid credentials');
  }

  await createSession(cookies, admin.id);

  return json({ ok: true });
}
