import { getDb } from '$lib/server/db';
import { encryptedRecords } from '$lib/server/db/schema';
import { RECORD_TYPES, type EncryptedRecord, type RecordType } from '$lib/types';
import { error, json } from '@sveltejs/kit';
import { and, desc, eq } from 'drizzle-orm';

const recordTypes = new Set<RecordType>(RECORD_TYPES);

function assertRecordType(value: unknown): RecordType {
  const type = String(value ?? '');
  if (!recordTypes.has(type as RecordType)) error(400, 'Invalid record type');
  return type as RecordType;
}

function toRecord(row: typeof encryptedRecords.$inferSelect): EncryptedRecord {
  return {
    id: row.id,
    type: row.type as RecordType,
    ciphertext: row.encryptedPayload,
    iv: row.payloadIv,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString()
  };
}

export async function GET({ url, locals }) {
  if (!locals.user) error(401, 'Login required');

  const db = getDb();
  const type = url.searchParams.get('type');
  const rows = await db
    .select()
    .from(encryptedRecords)
    .where(
      type
        ? and(eq(encryptedRecords.userId, locals.user.id), eq(encryptedRecords.type, assertRecordType(type)))
        : eq(encryptedRecords.userId, locals.user.id)
    )
    .orderBy(desc(encryptedRecords.updatedAt));

  return json({ records: rows.map(toRecord) });
}

export async function POST({ request, locals }) {
  if (!locals.user) error(401, 'Login required');

  const db = getDb();
  const body = await request.json();
  const type = assertRecordType(body.type);
  const ciphertext = String(body.ciphertext ?? '');
  const iv = String(body.iv ?? '');
  if (!ciphertext || !iv) error(400, 'Missing encrypted payload');

  const [record] = await db
    .insert(encryptedRecords)
    .values({
      userId: locals.user.id,
      type,
      encryptedPayload: ciphertext,
      payloadIv: iv
    })
    .returning();

  return json({ record: toRecord(record) });
}

export async function PATCH({ request, locals }) {
  if (!locals.user) error(401, 'Login required');

  const db = getDb();
  const body = await request.json();
  const id = String(body.id ?? '');
  const type = assertRecordType(body.type);
  const ciphertext = String(body.ciphertext ?? '');
  const iv = String(body.iv ?? '');
  if (!id || !ciphertext || !iv) error(400, 'Missing encrypted payload');

  const [record] = await db
    .update(encryptedRecords)
    .set({
      type,
      encryptedPayload: ciphertext,
      payloadIv: iv,
      updatedAt: new Date()
    })
    .where(and(eq(encryptedRecords.id, id), eq(encryptedRecords.userId, locals.user.id)))
    .returning();

  if (!record) error(404, 'Record not found');

  return json({ record: toRecord(record) });
}

export async function DELETE({ request, locals }) {
  if (!locals.user) error(401, 'Login required');

  const db = getDb();
  const body = await request.json();
  const id = String(body.id ?? '');
  if (!id) error(400, 'Missing record id');

  await db
    .delete(encryptedRecords)
    .where(and(eq(encryptedRecords.id, id), eq(encryptedRecords.userId, locals.user.id)));

  return json({ ok: true });
}
