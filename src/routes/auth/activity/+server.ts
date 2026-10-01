import { isTrustedActivityRequest } from '#lib/auth-policy.js';
import { error } from '@sveltejs/kit';

export function POST({ locals, request, url }) {
  if (!isTrustedActivityRequest(request, url)) error(403, 'Invalid activity signal');
  if (!locals.user) error(401, 'Login required');
  return new Response(null, { status: 204 });
}
