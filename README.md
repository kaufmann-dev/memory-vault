# The Second Directory

The Second Directory is a private, admin-only personal archive built with SvelteKit. It stores diary entries, lists, day counters, diagrams, and family-tree data in PostgreSQL while encrypting user-created content in the browser before it reaches the server.

## Navigation

- [Features](#features)
- [Stack](#stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Development Commands](#development-commands)
- [Project Structure](#project-structure)
- [Database And Encryption](#database-and-encryption)
- [Usage](#usage)

## Features

- Admin-only access with first-run account setup from `/login`
- Client-side encryption using the browser Web Crypto API
- PostgreSQL persistence through Drizzle ORM
- Encrypted diary, lists, diagrams, day counters, and family-tree records
- Password rotation by re-encrypting only the data encryption key

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
- A `DATABASE_URL` connection string

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

3. Set `DATABASE_URL` in `.env`:

   ```env
   DATABASE_URL=postgres://user:password@localhost:5432/theseconddirectory
   ```

4. Run database migrations:

   ```bash
   npm run db:migrate
   ```

5. Start the development server:

   ```bash
   npm run dev
   ```

6. Open the Vite URL shown in the terminal and create the first admin account at `/login`.

### Deploy To Coolify

1. Create a PostgreSQL service in the same Coolify project.

2. Create a new application for this repository and configure it as a Node application.

3. Add this environment variable to the application:

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
    |   |-- server/          # Auth, password, and database modules
    |   |-- stores/          # In-memory crypto key store
    |   |-- crypto.ts        # Web Crypto helpers
    |   `-- types.ts         # Shared application types
    `-- routes/
        |-- api/             # Auth and encrypted record endpoints
        |-- day-counters/    # Day counter UI
        |-- diagrams/        # Diagram and measurement UI
        |-- diary/           # Unified diary UI
        |-- family/          # Family tree UI
        |-- lists/           # Lists UI
        |-- login/           # Setup and login UI
        `-- settings/        # Password settings
```

## Database And Encryption

The database stores users, sessions, and typed encrypted records. User-created content is encrypted in the browser with a data encryption key before it is sent to `/api/records`; the server stores ciphertext and IV values only.

At account setup, the browser generates a random AES-256-GCM data encryption key. The user's password derives a key encryption key with PBKDF2 and SHA-256, and that key encrypts the data encryption key for storage. During login, the decrypted data encryption key is kept only in a memory-backed Svelte store and is cleared when the tab session ends.

Password changes re-encrypt the data encryption key with the new password-derived key. Existing encrypted records do not need to be rewritten.

See `ARCHITECTURE.md` for the detailed encryption model.

## Usage

- Visit `/login` on a fresh database to create the first admin account.
- Use `/diary`, `/lists`, `/diagrams`, `/day-counters`, and `/family` to manage encrypted records.
- Use `/settings` to change the admin password.