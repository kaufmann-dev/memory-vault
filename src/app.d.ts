import type { SafeUser } from '#lib/types.js';
import type { AuthSession } from '#lib/server/auth.js';

declare global {
  namespace App {
    interface Locals {
      user: SafeUser | null;
      authSession: AuthSession | null;
    }
  }
}

export {};
