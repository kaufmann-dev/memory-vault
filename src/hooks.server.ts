import { isTrustedActivityRequest } from '$lib/auth-policy';
import { readSession } from '$lib/server/auth';
import type { Handle } from '@sveltejs/kit';

export const handle: Handle = async ({ event, resolve }) => {
  const session = await readSession(event.cookies, isTrustedActivityRequest(event.request, event.url));
  event.locals.authSession = session;
  event.locals.user = session?.user ?? null;
  return resolve(event);
};
