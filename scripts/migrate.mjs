import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { drizzle } from 'drizzle-orm/postgres-js';
import { migrate } from 'drizzle-orm/postgres-js/migrator';
import postgres from 'postgres';

const rootDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const migrationsFolder = resolve(rootDir, 'migrations');
const databaseUrl = process.env.DATABASE_URL;

if (!databaseUrl) {
  console.error('DATABASE_URL is required to run database migrations.');
  process.exit(1);
}

if (!existsSync(resolve(migrationsFolder, 'meta', '_journal.json'))) {
  console.error(`Missing Drizzle migration journal at ${resolve(migrationsFolder, 'meta', '_journal.json')}.`);
  process.exit(1);
}

const client = postgres(databaseUrl, { max: 1 });
const db = drizzle(client);

try {
  console.log(`Applying database migrations from ${migrationsFolder}...`);
  await migrate(db, { migrationsFolder });
  console.log('Database migrations applied successfully.');
} catch (error) {
  console.error('Database migration failed.');
  console.error(error instanceof Error && error.stack ? error.stack : error);
  process.exitCode = 1;
} finally {
  await client.end();
}
