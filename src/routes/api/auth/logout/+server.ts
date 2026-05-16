import { deleteSession } from '$lib/server/auth';
import { json } from '@sveltejs/kit';

export async function POST({ cookies }) {
  await deleteSession(cookies);
  return json({ ok: true });
}
