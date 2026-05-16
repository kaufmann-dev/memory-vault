import { redirect } from '@sveltejs/kit';

const publicPaths = new Set(['/login']);

export async function load({ locals, url }) {
  if (!locals.user && !publicPaths.has(url.pathname)) {
    redirect(303, '/login');
  }

  return {
    user: locals.user
  };
}
