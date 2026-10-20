import { toast } from 'svelte-sonner';

export async function copyToClipboard(value: string, label = 'Secret') {
  if (!value) return;
  try {
    await navigator.clipboard.writeText(value);
    toast.success(`${label} copied.`);
  } catch {
    toast.error(`Could not copy this ${label.toLowerCase()}.`);
  }
}
