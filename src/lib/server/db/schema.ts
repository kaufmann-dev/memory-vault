import { index, pgTable, text, timestamp, uuid } from 'drizzle-orm/pg-core';

export const users = pgTable('users', {
  id: uuid('id').primaryKey().defaultRandom(),
  email: text('email').notNull().unique(),
  name: text('name').notNull(),
  role: text('role').notNull().default('admin'),
  passwordHash: text('password_hash').notNull(),
  kekSalt: text('kek_salt').notNull(),
  encryptedDek: text('encrypted_dek').notNull(),
  dekIv: text('dek_iv').notNull(),
  createdAt: timestamp('created_at').notNull().defaultNow(),
  updatedAt: timestamp('updated_at').notNull().defaultNow()
});

export const sessions = pgTable(
  'sessions',
  {
    id: uuid('id').primaryKey().defaultRandom(),
    userId: uuid('user_id')
      .notNull()
      .references(() => users.id, { onDelete: 'cascade' }),
    tokenHash: text('token_hash').notNull().unique(),
    expiresAt: timestamp('expires_at').notNull(),
    createdAt: timestamp('created_at').notNull().defaultNow()
  },
  (table) => ({
    userIdx: index('sessions_user_idx').on(table.userId)
  })
);

export const encryptedRecords = pgTable(
  'encrypted_records',
  {
    id: uuid('id').primaryKey().defaultRandom(),
    userId: uuid('user_id')
      .notNull()
      .references(() => users.id, { onDelete: 'cascade' }),
    type: text('type').notNull(),
    encryptedPayload: text('encrypted_payload').notNull(),
    payloadIv: text('payload_iv').notNull(),
    createdAt: timestamp('created_at').notNull().defaultNow(),
    updatedAt: timestamp('updated_at').notNull().defaultNow()
  },
  (table) => ({
    userTypeIdx: index('encrypted_records_user_type_idx').on(table.userId, table.type)
  })
);
