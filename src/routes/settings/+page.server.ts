export async function load({ locals }) {
  return {
    keyMaterial: locals.user
      ? {
          kekSalt: locals.user.kekSalt,
          encryptedDEK: locals.user.encryptedDEK,
          dekIV: locals.user.dekIV
        }
      : null
  };
}
