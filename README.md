# Memory Vault

Memory Vault is a private, admin-only personal archive built with SvelteKit. It stores encrypted personal records in PostgreSQL while encrypting user-created content in the browser before it reaches the server.

## Navigation

- [Features](#features)
- [Stack](#stack)
- [Prerequisites](#prerequisites)
- [Authentication Setup](#authentication-setup)
- [Installation](#installation)
- [Development Commands](#development-commands)
- [Project Structure](#project-structure)
- [Database And Encryption](#database-and-encryption)
- [Usage](#usage)

## Features

- Admin-only access through an OpenID Connect provider access policy
- Separate provider authentication and client-only vault passphrase
- Client-side encryption using the browser Web Crypto API
- Encrypted diary entries, notes, lists, diagrams, milestones, and secrets
- Vault passphrase rotation by re-encrypting only the data encryption key
- Encrypted `.mvault` exports and destructive restores from Settings
- PostgreSQL persistence through Drizzle ORM

## Stack

- SvelteKit with `@sveltejs/adapter-node`
- TypeScript
- Tailwind CSS 4
- PostgreSQL
- Drizzle ORM and Drizzle Kit
- npm

## Prerequisites

- Node.js compatible with the installed SvelteKit/Vite toolchain
- npm
- PostgreSQL
- An OpenID Connect provider that supports Authorization Code flow, PKCE, and RP-Initiated Logout
- A `DATABASE_URL` connection string

## Authentication Setup

Memory Vault uses OIDC Authorization Code + PKCE and maps admitted users to a single vault owner, then stores an HttpOnly server session; user content remains client-encrypted and never leaves the browser in plaintext.

- Public Client: Off
- Callback URL: `/auth/callback`
- Logout Callback URL: `/login`
- Authentication environment variables:
  - `OIDC_ISSUER_URL` (required) — OIDC issuer URL for discovery.
  - `OIDC_CLIENT_ID` (required) — Confidential client ID.
  - `OIDC_CLIENT_SECRET` (required) — Confidential client secret.
  - `OIDC_APP_URL` (required) — Public app origin (no path, query, or fragment).

## Installation

Choose one path: local development or deployment to Coolify.

### Local Development

1. Install dependencies:

   ```bash
   npm install
   ```

2. Create an environment file:

   ```bash
   cp .env.example .env
   ```

3. Set `DATABASE_URL` and all OIDC variables listed in [Authentication Setup](#authentication-setup) in `.env`:

   ```env
   DATABASE_URL=postgres://user:password@localhost:5432/memory_vault
   ```

4. Run database migrations:

   ```bash
   npm run db:migrate
   ```

5. Start the development server:

   ```bash
   npm run dev
   ```

6. Open `/login`, authenticate through the OIDC provider, and create the client-only vault passphrase if the database is new.

### Deploy To Coolify

1. Create a PostgreSQL service in the same Coolify project.

2. Create a new application for this repository and configure it as a Node application.

3. Add `DATABASE_URL` and every required OIDC variable from [Authentication Setup](#authentication-setup) to the application. Store `OIDC_CLIENT_SECRET` as a secret:

   ```env
   DATABASE_URL=<internal PostgreSQL connection URL from the Coolify database service>
   ```

4. Use these application commands:

   ```text
   Install Command: npm install
   Build Command: npm run build
   Start Command: npm start
   ```

5. Deploy the application.

`npm start` runs pending migrations first and then starts the generated Node server:

```bash
npm run db:migrate && node build/index.js
```

Use the internal PostgreSQL URL from Coolify, not a public database URL, when the database service is in the same project.

## Development Commands

```bash
npm run dev          # Start Vite on 0.0.0.0
npm run build        # Build the SvelteKit app
npm run preview      # Preview the production build
npm run check        # Run svelte-kit sync and svelte-check
npm test             # Run focused authentication policy tests
npm run db:generate  # Generate Drizzle migrations
npm run db:migrate   # Run migrations with scripts/migrate.mjs
```

`npm run db:migrate:kit` is also available for local Drizzle Kit migration debugging.

## Project Structure

```text
.
|-- ARCHITECTURE.md          # Architecture and encryption notes
|-- DESIGN.md                # Visual design direction
|-- drizzle.config.ts        # Drizzle migration configuration
|-- migrations/              # SQL migrations and Drizzle journal
|-- scripts/migrate.mjs      # Production-safe migration runner
`-- src/
    |-- hooks.server.ts      # Session loading for protected routes
    |-- lib/
    |   |-- client/          # Browser-side encrypted record helpers
    |   |-- components/      # Shared Svelte components
    |   |-- server/          # OIDC, local session, and database modules
    |   |-- stores/          # In-memory client stores (crypto key, decrypted caches)
    |   |-- crypto.ts        # Web Crypto helpers
    |   `-- types.ts         # Shared application types
    `-- routes/
        |-- api/             # Auth and encrypted record endpoints
        |-- auth/            # OIDC login, callback, activity, and logout endpoints
        |-- day-counters/    # Milestone UI
        |-- diagrams/        # Diagram and measurement UI
        |-- diary/           # Diary UI
        |-- lists/           # Lists UI
        |-- login/           # OIDC entry and first vault setup UI
        |-- notes/           # Quick notes and encrypted note groups
        |-- secrets/         # Passwords, keys, accounts, connections, and more
        `-- settings/        # Account and vault settings
```

## Database And Encryption

The database stores the singleton vault owner, local OIDC-backed sessions, and typed encrypted records. User-created content is encrypted in the browser with a data encryption key before it is sent to `/api/records`; the server stores ciphertext and IV values only.

At first setup, after OIDC authentication, the browser generates a random AES-256-GCM data encryption key. The vault passphrase is never sent to the server; it derives a key encryption key with PBKDF2 and SHA-256, and that key encrypts the data encryption key for storage. Existing key metadata and ciphertext are unchanged by the authentication migration.

After OIDC login, Memory Vault creates an HttpOnly local session with a 24-hour sliding idle timeout and a seven-day absolute lifetime. Idle time is refreshed only by throttled same-origin signals from trusted pointer, keyboard, or click activity; navigation preloads and background requests do not extend it. The OIDC ID token stays server-side only for RP-Initiated Logout; access tokens and refresh tokens are not used as application sessions.

When the in-memory data encryption key is missing, unlocking happens in the browser with the vault passphrase. The decrypted data encryption key is kept only in a memory-backed Svelte store and is cleared when the tab session ends. Vault passphrase changes re-encrypt only the data encryption key, so existing encrypted records do not need to be rewritten.

Settings can export one `.mvault` backup file. The backup payload is encrypted in the browser with the active vault key and includes the encrypted records plus the encrypted data-key metadata required for disaster recovery. Import decrypts and validates the file in the browser first, then replaces the current server entries with the encrypted backup records in one transaction. The server never receives plaintext vault content or the raw data encryption key.

See `ARCHITECTURE.md` for the detailed encryption model.

## Usage

- Visit `/login` and sign in through the configured OIDC provider. On a fresh database, create the separate vault passphrase after OIDC login.
- On later visits, sign in through OIDC and unlock the vault with the existing vault passphrase.
- Use `/diary`, `/notes`, `/lists`, `/diagrams`, `/day-counters`, and `/secrets` to manage encrypted records.
- Use `/settings` to change the vault passphrase, remember the current device, or export/import encrypted backups.
