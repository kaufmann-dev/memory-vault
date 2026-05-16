import { getAdminUser } from '$lib/server/auth';

export async function load() {
  const admin = await getAdminUser();

  return {
    hasAdmin: Boolean(admin),
    adminEmail: admin?.email ?? '',
    kekSalt: admin?.kekSalt ?? '',
    encryptedDEK: admin?.encryptedDek ?? '',
    dekIV: admin?.dekIv ?? ''
  };
}
