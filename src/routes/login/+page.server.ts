import { getAdminUser } from '$lib/server/auth';
import { redirect } from '@sveltejs/kit';

export async function load({ locals }) {
  if (locals.user) {
    redirect(303, '/');
  }

  const admin = await getAdminUser();

  return {
    hasAdmin: Boolean(admin)
  };
}
