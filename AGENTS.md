# AGENTS.md

## Commands
- Install with `npm install`; this repo uses `package-lock.json`, not pnpm/yarn/bun.
- Dev server: `npm run dev` (`vite --host 0.0.0.0`).
- Production build: `npm run build`; production start is `npm start`, which runs `npm run db:migrate` before `node build/index.js`.
- Main verification: `npm run check` (`svelte-kit sync && svelte-check --tsconfig ./tsconfig.json`). No test, lint, or formatter scripts are currently configured.
- Database commands require `DATABASE_URL`: `npm run db:generate` creates Drizzle migrations from `src/lib/server/db/schema.ts`; `npm run db:migrate` runs `scripts/migrate.mjs`; `npm run db:migrate:kit` is the raw Drizzle Kit migrator for local debugging.
- Always generate and run migrations through Drizzle Kit. Keep schema files and migrations aligned with `drizzle.config.ts`.
- Do not run the application or test it locally. The app needs a database; the user will run it and give feedback if something is wrong.

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

## Svelte 5 Patterns
Always treat this as a Svelte 5 project. Use runes, snippets, and fine-grained reactivity.

### Core State and Props
- Use `$props()` for component inputs. Always treat props as changeable.
- Use `$state` only for values that must update the template, a derived value, or an effect.
- Use `$state.raw` for large objects, API responses, or arrays that are reassigned entirely without deep mutation tracking.
- Use `$derived` for computed state, including values derived from props.
- Use `$derived.by` only when computation requires a multi-line function.

### Reactivity and Effects ($effect)
Never use `$effect` unless absolutely necessary (e.g., syncing with external non-Svelte libraries). Using `$effect` is almost always a sign the code should be refactored.

- For computed state, use `$derived` instead of `$effect`.
- To react to state changes, use event handlers (`onclick`, `onValueChange`, etc.) instead of `$effect`.
- For type conversion with `bind:`, use getter/setter bindings instead of `$effect`.

If an effect genuinely must write state, track the external source and keep reads of the written target out of the dependency set using `untrack`:

```svelte
import { untrack } from "svelte";

let source = $state(0);
let target = $state(0);

$effect(() => {
  const next = source + 1;
  untrack(() => { target = next; });
});
```

Always write effects as browser-only by nature. For global event listeners, use `<svelte:window>` or `<svelte:document>` rather than `onMount` or an effect.

### Component Architecture & Styling
- Use snippets and `{@render ...}` for reusable markup and component children.
- Use `<DynamicComponent>` for dynamic component rendering.
- Use `import Self from "./ThisComponent.svelte"` and `<Self>` for recursive rendering.
- Use `clsx`-style arrays and objects in `class` attributes for conditional classes.
- Use `onclick={...}` and other `on...` attributes for event listeners.
- Use keyed `{#each}` blocks with stable object identifiers. Never use the index as a key. Avoid destructuring if mutating the item.
- Use CSS custom properties for parent-to-child styling boundaries.

### Legacy Features to Avoid
- `export let`, `$$props`, `$$restProps`
- `$: ` reactive blocks or assignments
- `<slot>`, `$$slots`, `<svelte:fragment>`
- `<svelte:component>` and `<svelte:self>`
- `use:action`
- `class:` directives
- `on:` event attributes (e.g., `on:click`)

## UI and Styling
- Styling is Tailwind CSS 4 via `@tailwindcss/vite`, with global design tokens in `src/app.css`.
- Shared shell and small UI components live in `src/lib/components/`; feature pages live directly under `src/routes/{diary,lists,diagrams,day-counters,family,settings}`.
- All shadcn-svelte components are pre-installed in `src/lib/components/ui/`. **Never run the CLI to install components, and never delete unused shadcn components during codebase cleanup.**
  - Never import components from a package named `shadcn-svelte`.
  - Import them only from local `$lib/components/ui/...` paths.
  - Use local component source as the final source of truth; customize with Tailwind classes using `class` and the local `cn()` helper.
- **Icons**: Always use `@lucide/svelte` (e.g., `<Search class="size-4" />`).
- **Theming**: Use `setMode("light" | "dark" | "system")` or `toggleMode` from `mode-watcher` for controls. Add `ModeWatcher` once in the root layout:
  ```svelte
  <script lang="ts">
    import { ModeWatcher } from "mode-watcher";
    let { children } = $props();
  </script>
  <ModeWatcher />
  {@render children()}
  ```
- `ARCHITECTURE.md` contains the crypto design and intended full-stack architecture. Trust it for encryption intent and key management; `package.json` is authoritative for currently installed dependencies.
