import { parseRestoreBackupRequest, type RestoreBackupRequest } from '#lib/backup.js';
import { getDb } from '#lib/server/db/index.js';
import { encryptedRecords, users } from '#lib/server/db/schema.js';
import { error } from '@sveltejs/kit';
import { eq } from 'drizzle-orm';

export async function POST({ request, locals }) {
  if (!locals.user) error(401, 'Login required');
  const userId = locals.user.id;

  let backup: RestoreBackupRequest;
  try {
    backup = parseRestoreBackupRequest(await request.json());
  } catch {
    error(400, 'Invalid backup data');
  }

  const now = new Date();
  const db = getDb();

  await db.transaction(async (tx) => {
    await tx
      .update(users)
      .set({
        kekSalt: backup.keyMaterial.kekSalt,
        encryptedDek: backup.keyMaterial.encryptedDEK,
        dekIv: backup.keyMaterial.dekIV,
        updatedAt: now
      })
      .where(eq(users.id, userId));

    await tx.delete(encryptedRecords).where(eq(encryptedRecords.userId, userId));

    if (backup.records.length > 0) {
      await tx.insert(encryptedRecords).values(
        backup.records.map((record) => ({
          id: record.id,
          userId,
          type: record.type,
          encryptedPayload: record.ciphertext,
          payloadIv: record.iv,
          createdAt: new Date(record.createdAt),
          updatedAt: new Date(record.updatedAt)
        }))
      );
    }
  });

  return Response.json({ ok: true, count: backup.records.length });
}
