import { DATABASE_URL } from '$app/env/private';
import { drizzle } from 'drizzle-orm/postgres-js';
import postgres from 'postgres';
import * as schema from './schema';

type Database = ReturnType<typeof drizzle<typeof schema>>;

let database: Database | null = null;

export function getDb() {
  if (database) return database;

  const databaseUrl = DATABASE_URL;
  if (!databaseUrl) {
    throw new Error('DATABASE_URL is required');
  }

  const client = postgres(databaseUrl, { max: 10 });
  database = drizzle(client, { schema });
  return database;
}
