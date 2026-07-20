import { createSession, getAdminUser } from '$lib/server/auth';
import { completeAuthorization } from '$lib/server/oidc';
import { redirect } from '@sveltejs/kit';

export async function GET({ url, cookies }) {
  let destination: string;
  try {
    const identity = await completeAuthorization(url, cookies);
    const admin = await getAdminUser();

    await createSession(cookies, {
      userId: admin?.id ?? null,
      idToken: identity.idToken,
      setupEmail: admin ? undefined : identity.email,
      setupName: admin ? undefined : identity.name
    });
    destination = admin ? '/' : '/login';
  } catch {
    console.error('OIDC callback failed');
    destination = '/login?error=oidc';
  }

  redirect(303, destination);
}
