import { deleteSession, getAdminUser } from '$lib/server/auth';
import { redirect } from '@sveltejs/kit';

export async function load({ cookies, locals, url }) {
  if (locals.user) {
    redirect(303, '/');
  }

  const admin = await getAdminUser();
  if (admin && locals.authSession) {
    await deleteSession(cookies);
    redirect(303, '/login');
  }

  return {
    needsVaultSetup: !admin && Boolean(locals.authSession?.setupEmail && locals.authSession?.setupName),
    setupEmail: !admin ? (locals.authSession?.setupEmail ?? null) : null,
    oidcError: url.searchParams.has('error')
  };
}
