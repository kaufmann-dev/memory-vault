# AGENTS.md

## Commands
- Install with `npm install`; this repo uses `package-lock.json`, not pnpm/yarn/bun.
- Dev server: `npm run dev` (`vite --host 0.0.0.0`).
- Production build: `npm run build`; production start is `npm start`, which runs `npm run db:migrate` before `node build/index.js`.
- Main verification: `npm run check` (`svelte-kit sync && svelte-check --tsconfig ./tsconfig.json`). No test, lint, or formatter scripts are currently configured.
- Database commands require `DATABASE_URL`: `npm run db:generate` creates Drizzle migrations from `src/lib/server/db/schema.ts`; `npm run db:migrate` runs `scripts/migrate.mjs`; `npm run db:migrate:kit` is the raw Drizzle Kit migrator for local debugging.

## Runtime And Data
- The only required env var shown in `.env.example` is `DATABASE_URL`.
- Drizzle config points at `src/lib/server/db/schema.ts` and writes migrations to `migrations/`; keep `migrations/meta/_journal.json` with generated SQL because `scripts/migrate.mjs` refuses to run without it.
- `src/lib/server/db/index.ts` reads `DATABASE_URL` from `$env/dynamic/private` and memoizes one postgres-js Drizzle connection.
- SvelteKit uses `@sveltejs/adapter-node`; generated production entrypoint is `build/index.js`.

## App Boundaries
- `src/hooks.server.ts` loads `locals.user` from `readSession`; `src/routes/+layout.server.ts` redirects every non-`/login` route to `/login` when unauthenticated.
- Server APIs are under `src/routes/api/`; encrypted user content is stored through `src/routes/api/records/+server.ts`.
- User-created content must stay client-encrypted. Use `src/lib/client/records.ts` plus `src/lib/crypto.ts`; do not send plaintext payloads to server routes or try to decrypt in `load`/SSR.
- The in-memory DEK lives in `src/lib/stores/cryptoKey.ts`; do not persist it to localStorage, sessionStorage, cookies, the DOM, or the database.
- Valid record types are enforced in `src/routes/api/records/+server.ts`; add new encrypted content types there and in shared types/helpers together.

## UI Notes
- Styling is Tailwind CSS 4 via `@tailwindcss/vite`, with global design tokens in `src/app.css`.
- Shared shell and small UI components live in `src/lib/components/`; feature pages live directly under `src/routes/{diary,lists,diagrams,day-counters,family,settings}`.
- `ARCHITECTURE.md` contains useful crypto intent, but some stack notes are stale compared with `package.json` (for example, no `better-auth`, `shadcn-svelte`, `mode-watcher`, or `@iconify/tailwind4` dependencies are installed). Trust executable config first.
