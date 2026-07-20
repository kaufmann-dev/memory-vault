import { beginAuthorization } from '$lib/server/oidc';
import { redirect } from '@sveltejs/kit';

export async function GET({ cookies, locals }) {
  if (locals.user) redirect(303, '/');
  if (locals.authSession) redirect(303, '/login');

  const authorizationUrl = await beginAuthorization(cookies);
  redirect(303, authorizationUrl.href);
}
