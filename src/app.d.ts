import type { SafeUser } from '$lib/types';
import type { AuthSession } from '$lib/server/auth';

declare global {
  namespace App {
    interface Locals {
      user: SafeUser | null;
      authSession: AuthSession | null;
    }
  }
}

export {};
