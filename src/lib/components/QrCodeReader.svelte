<script lang="ts">
  import { onMount, tick } from 'svelte';
  import { Camera, ImageUp, QrCode, X } from '@lucide/svelte';
  import { Button } from '#lib/components/ui/button/index.js';

  type QrDecoder = typeof import('jsqr').default;

  let { onDecode }: { onDecode: (text: string) => void } = $props();

  const scanIntervalMs = 200;

  let error = $state('');
  let busy = $state(false);
  let cameraActive = $state(false);
  let video = $state<HTMLVideoElement>();
  let fileInput: HTMLInputElement;
  let stream: MediaStream | null = null;
  let frame = 0;
  let lastScan = 0;
  let destroyed = false;
  let canvas: HTMLCanvasElement | null = null;
  let qrDecoder: QrDecoder | null = null;

  async function loadDecoder() {
    qrDecoder ??= (await import('jsqr')).default;
    return qrDecoder;
  }

  function decode(decoder: QrDecoder, source: CanvasImageSource, width: number, height: number, maxSize: number) {
    const scale = Math.min(1, maxSize / Math.max(width, height));
    const scaledWidth = Math.max(1, Math.round(width * scale));
    const scaledHeight = Math.max(1, Math.round(height * scale));
    canvas ??= document.createElement('canvas');
    canvas.width = scaledWidth;
    canvas.height = scaledHeight;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) return null;
    context.drawImage(source, 0, 0, scaledWidth, scaledHeight);
    return decoder(context.getImageData(0, 0, scaledWidth, scaledHeight).data, scaledWidth, scaledHeight)?.data || null;
  }

  async function decodeImage(file: File) {
    if (busy) return;
    stopCamera();
    error = '';
    busy = true;
    try {
      const [decoder, bitmap] = await Promise.all([loadDecoder(), createImageBitmap(file)]);
      const text = decode(decoder, bitmap, bitmap.width, bitmap.height, 2048);
      bitmap.close();
      if (text) onDecode(text);
      else error = 'No QR code found in this image.';
    } catch {
      error = 'Could not read this image.';
    } finally {
      busy = false;
    }
  }

  async function startCamera() {
    if (busy || cameraActive) return;
    error = '';
    if (!navigator.mediaDevices?.getUserMedia) {
      error = 'Camera is not available in this browser.';
      return;
    }

    busy = true;
    let media: MediaStream | null = null;
    try {
      const decoder = await loadDecoder();
      media = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
      if (destroyed) {
        media.getTracks().forEach((track) => track.stop());
        return;
      }
      stream = media;
      cameraActive = true;
      await tick();
      if (!video || stream !== media) return;
      video.srcObject = media;
      await video.play();
      if (stream === media) scanFrame(decoder);
    } catch (err) {
      if (media && stream !== media) return;
      stopCamera();
      const name = err instanceof DOMException ? err.name : '';
      error =
        name === 'NotAllowedError'
          ? 'Camera access was denied.'
          : name === 'NotFoundError'
            ? 'No camera found.'
            : 'Could not start the camera.';
    } finally {
      busy = false;
    }
  }

  function scanFrame(decoder: QrDecoder) {
    frame = requestAnimationFrame((time) => {
      if (!stream || !video) return;
      if (time - lastScan >= scanIntervalMs && video.readyState >= video.HAVE_CURRENT_DATA) {
        lastScan = time;
        const text = decode(decoder, video, video.videoWidth, video.videoHeight, 640);
        if (text) {
          stopCamera();
          onDecode(text);
          return;
        }
      }
      scanFrame(decoder);
    });
  }

  function stopCamera() {
    cancelAnimationFrame(frame);
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    cameraActive = false;
    if (video) video.srcObject = null;
  }

  function handlePaste(event: ClipboardEvent) {
    const file = Array.from(event.clipboardData?.files ?? []).find((item) => item.type.startsWith('image/'));
    if (!file) return;
    event.preventDefault();
    void decodeImage(file);
  }

  function handleFileChange(event: Event & { currentTarget: HTMLInputElement }) {
    const file = event.currentTarget.files?.[0];
    event.currentTarget.value = '';
    if (file) void decodeImage(file);
  }

  onMount(() => () => {
    destroyed = true;
    stopCamera();
  });
</script>

<svelte:window onpaste={handlePaste} />

<div class="grid gap-3 rounded-lg border border-dashed p-4">
  {#if cameraActive}
    <video bind:this={video} class="bg-muted h-64 w-full rounded-md object-cover" playsinline muted></video>
    <Button type="button" variant="outline" size="sm" onclick={stopCamera}>
      <X class="size-4" /> Stop camera
    </Button>
  {:else}
    <div class="flex items-start gap-3">
      <span class="bg-muted text-foreground grid size-9 shrink-0 place-items-center rounded-lg">
        <QrCode class="size-4" />
      </span>
      <span class="grid gap-1 text-sm">
        <span class="font-medium">Scan the QR code from the service's 2FA setup page</span>
        <span class="text-muted-foreground">Use your camera, upload an image, or paste a screenshot.</span>
      </span>
    </div>
    <div class="flex flex-wrap gap-2">
      <Button type="button" variant="outline" size="sm" onclick={startCamera} disabled={busy}>
        <Camera class="size-4" /> Use camera
      </Button>
      <Button type="button" variant="outline" size="sm" onclick={() => fileInput.click()} disabled={busy}>
        <ImageUp class="size-4" /> Upload image
      </Button>
    </div>
  {/if}
  {#if error}
    <p class="text-destructive text-sm" role="alert">{error}</p>
  {/if}
  <input bind:this={fileInput} type="file" accept="image/*" class="hidden" onchange={handleFileChange} />
</div>
