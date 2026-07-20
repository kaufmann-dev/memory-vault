import { isTrustedLogoutRequest } from '$lib/auth-policy';
import { deleteSession } from '$lib/server/auth';
import { buildLogoutUrl } from '$lib/server/oidc';
import { error, redirect } from '@sveltejs/kit';

export async function POST({ cookies, request, url }) {
  if (!isTrustedLogoutRequest(request, url)) error(403, 'Invalid logout request');

  const idToken = await deleteSession(cookies);
  if (!idToken) redirect(303, '/login');

  const logoutUrl = await buildLogoutUrl(idToken);
  redirect(303, logoutUrl.href);
}
