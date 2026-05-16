import type { SafeUser } from '$lib/types';

declare global {
  namespace App {
    interface Locals {
      user: SafeUser | null;
    }
  }
}

export {};
