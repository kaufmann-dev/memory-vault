# The Second Directory

The Second Directory is a private, admin-only personal archive built with SvelteKit. It stores diary entries, lists, day counters, diagrams, and family-tree data in PostgreSQL while encrypting user-created content in the browser before it reaches the server.

The current application is a complete rewrite of the previous PHP/JSON project. Legacy source files and assets are kept under `legacy/` for reference.

## Table of Contents

- [Key Features](#key-features)
- [Technology Stack](#technology-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Usage Examples](#usage-examples)
- [Project Structure](#project-structure)
- [Database and Encryption](#database-and-encryption)
- [Deployment Notes](#deployment-notes)
- [License](#license)

## Key Features

- **Admin-only access**: all application pages are protected behind login.
- **First-run setup**: the first admin account is created from the login screen when no user exists.
- **Client-side encryption**: user-created content is encrypted with the browser Web Crypto API before it is sent to the server.
- **PostgreSQL persistence**: encrypted records, users, and sessions are stored in PostgreSQL through Drizzle ORM.
- **Unified diary**: diary entries replace the previous separate text, collection, PDF, and reflection areas.
- **Encrypted lists**: create checklist-style or plain lists with encrypted tasks and descriptions.
- **Encrypted diagrams**: store and visualize weight, blood-pressure, and hormone measurements.
- **Standalone day counters**: track dates and optional target durations without the old tools section.
- **Rewritten family tree**: manage encrypted family-tree people, notes, relationships, and descendants.
- **Password rotation**: changing the admin password re-encrypts the data encryption key without rewriting every record.

## Technology Stack

- **Framework**: SvelteKit
- **Runtime adapter**: `@sveltejs/adapter-node`
- **Language**: TypeScript
- **Styling**: Tailwind CSS 4 with project-level CSS tokens
- **Icons**: `@lucide/svelte`
- **Database**: PostgreSQL
- **ORM and migrations**: Drizzle ORM and Drizzle Kit
- **Encryption**: Browser-native Web Crypto API using PBKDF2 and AES-GCM
- **Package manager**: npm

## Prerequisites

- Node.js compatible with the installed SvelteKit/Vite toolchain
- npm
- PostgreSQL database
- A `DATABASE_URL` connection string for the application database

For Coolify deployments, use the internal PostgreSQL URL from the database service in the same project.

## Installation

1. Install dependencies:

   ```bash
   npm install
   ```

2. Create an environment file:

   ```bash
   cp .env.example .env
   ```

3. Set `DATABASE_URL` in `.env`:

   ```bash
   DATABASE_URL=postgres://user:password@postgres:5432/theseconddirectory
   ```

4. Run database migrations:

   ```bash
   npm run db:migrate
   ```

5. Start the development server:

   ```bash
   npm run dev
   ```

6. Open the local server URL shown by Vite and create the first admin account.

## Usage Examples

### Create the First Admin Account

On a fresh database, visit `/login`. The page shows setup mode and asks for the first admin name, email, and password.

During setup, the browser generates the encryption keys, encrypts the data encryption key with the password-derived key, and sends only encrypted key material to the server.

### Sign In

Visit `/login`, enter the admin email and password, and unlock the encrypted vault. The decrypted content key is kept only in browser memory for the current tab session.

### Add Encrypted Content

Use the app navigation to create content in:

- `/diary` for long-form writing and ramblings
- `/lists` for checklist or plain list records
- `/diagrams` for measurement records and charts
- `/day-counters` for date counters
- `/family` for family-tree records

All saved content in these sections is encrypted before being posted to `/api/records`.

### Change the Password

Open `/settings` while logged in. Password changes re-encrypt the data encryption key with the new password; existing encrypted records do not need to be rewritten.

### Build for Production

```bash
npm run build
npm start
```

## Project Structure

```text
.
├── ARCHITECTURE.md          # Architecture and encryption notes
├── DESIGN.md                # Visual design direction
├── drizzle.config.ts        # Drizzle migration configuration
├── migrations/              # SQL migrations
├── src/
│   ├── hooks.server.ts      # Session loading for protected routes
│   ├── lib/
│   │   ├── client/          # Browser-side encrypted record helpers
│   │   ├── components/      # Shared Svelte components
│   │   ├── server/          # Auth, password, and database modules
│   │   ├── stores/          # In-memory crypto key store
│   │   ├── crypto.ts        # Web Crypto helpers
│   │   └── types.ts         # Shared application types
│   └── routes/
│       ├── api/             # Auth and encrypted record endpoints
│       ├── day-counters/    # Day counter UI
│       ├── diagrams/        # Diagram and measurement UI
│       ├── diary/           # Unified diary UI
│       ├── family/          # Family tree UI
│       ├── lists/           # Lists UI
│       ├── login/           # Setup and login UI
│       └── settings/        # Password settings
└── legacy/                  # Previous project files kept for reference
```

## Database and Encryption

The database stores three primary record groups:

- `users`: admin profile, password hash, key salt, encrypted data encryption key, and key IV
- `sessions`: hashed login tokens and expiry timestamps
- `encrypted_records`: typed encrypted payloads for diary, lists, diagrams, day counters, and family tree data

Encryption follows the architecture in `ARCHITECTURE.md`:

- A password-derived key encryption key is created with PBKDF2 and SHA-256.
- A random AES-256-GCM data encryption key is generated during account setup.
- User-created content is encrypted with the data encryption key in the browser.
- The server stores ciphertext and IV values only.
- The decrypted data encryption key is held in a memory-only Svelte store and is cleared when the tab session ends.

## Deployment Notes

The app uses `@sveltejs/adapter-node`, so the production entrypoint is the generated Node server in `build/`.

For Coolify:

- Configure the app as a Node service.
- Add a PostgreSQL service in the same Coolify project.
- Set `DATABASE_URL` to the internal PostgreSQL connection URL.
- Run `npm run db:migrate` manually when needed, or let `npm start` run pending migrations before the server starts.
- Use `npm run build` as the build command.
- Use `npm start` as the start command.

`npm run db:migrate` uses the production-safe Node migrator in `scripts/migrate.mjs`. The raw Drizzle Kit CLI remains available as `npm run db:migrate:kit` for local debugging.

## License

No license file or package license is currently declared in this repository. Until a license is added, the project should be treated as private and unlicensed.
