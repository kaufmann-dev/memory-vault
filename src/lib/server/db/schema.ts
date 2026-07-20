import { index, pgTable, text, timestamp, uniqueIndex, uuid } from 'drizzle-orm/pg-core';

export const users = pgTable('users', {
  id: uuid('id').primaryKey().defaultRandom(),
  email: text('email').notNull().unique(),
  name: text('name').notNull(),
  role: text('role').notNull().default('admin'),
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
      .references(() => users.id, { onDelete: 'cascade' }),
    tokenHash: text('token_hash').notNull().unique(),
    idToken: text('id_token').notNull(),
    setupEmail: text('setup_email'),
    setupName: text('setup_name'),
    idleExpiresAt: timestamp('idle_expires_at').notNull(),
    absoluteExpiresAt: timestamp('absolute_expires_at').notNull(),
    createdAt: timestamp('created_at').notNull().defaultNow()
  },
  (table) => ({
    userIdx: index('sessions_user_idx').on(table.userId)
  })
);

export const oidcLoginStates = pgTable(
  'oidc_login_states',
  {
    id: uuid('id').primaryKey().defaultRandom(),
    stateHash: text('state_hash').notNull(),
    codeVerifier: text('code_verifier').notNull(),
    nonce: text('nonce').notNull(),
    expiresAt: timestamp('expires_at').notNull(),
    createdAt: timestamp('created_at').notNull().defaultNow()
  },
  (table) => ({
    stateHashIdx: uniqueIndex('oidc_login_states_state_hash_idx').on(table.stateHash),
    expiresAtIdx: index('oidc_login_states_expires_at_idx').on(table.expiresAt)
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
