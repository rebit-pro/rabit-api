import { readDemo } from './storage';
import { setDemoNow } from './clock';
import { clearPhotoBlobs } from '../photos/blobs';
import { isMockApiEnabled } from '@/mocks/config';
import { setGalleryScenario, type GalleryScenario } from '../gallery/mocks/state';

let nextFailure = false;
let delayMs = 250;

export function failNextRequest(): void {
  nextFailure = true;
}

export async function simulateRequest(): Promise<void> {
  const shouldFail = nextFailure || readDemo('runtime:fail-next', false);
  const generation = readDemo<{ generation: string } | null>('review:session', null)?.generation;
  localStorage.removeItem('morefoto:demo:runtime:fail-next');
  nextFailure = false;
  await new Promise<void>((resolve) => window.setTimeout(resolve, readDemo('runtime:delay', delayMs)));
  if (generation !== readDemo<{ generation: string } | null>('review:session', null)?.generation)
    throw new Error('Набор проверки перезапущен. Обновите страницу.');
  if (shouldFail || !navigator.onLine || readDemo('runtime:offline', false)) {
    throw new Error('Не удалось загрузить данные. Проверьте соединение и попробуйте ещё раз.');
  }
}

declare global {
  interface Window {
    __MOREFOTO_MOCKS__?: {
      setNow(value: string): void;
      failNextRequest(): void;
      setDelay(ms: number): void;
      setGalleryScenario(value: GalleryScenario): void;
      reset(): void;
    };
  }
}

export function initializeMoreFotoMocks(): void {
  if (!isMockApiEnabled) return;
  window.__MOREFOTO_MOCKS__ = {
    failNextRequest,
    setNow: setDemoNow,
    setGalleryScenario,
    setDelay(ms) {
      delayMs = Math.max(0, Math.min(ms, 10000));
    },
    reset() {
      void clearPhotoBlobs().catch(() => {});
      clearDemoStorage();
    }
  };
}

export function clearDemoStorage(): void {
  for (const key of Object.keys(sessionStorage)) {
    if (key.startsWith('morefoto:demo:uploads:')) sessionStorage.removeItem(key);
  }
  nextFailure = false;
  delayMs = 250;
  setGalleryScenario('default');
  // Reset demo data and sessions together; the next page load restores an anonymous session.
  for (const key of Object.keys(localStorage)) {
    if (key.startsWith('morefoto:demo:')) localStorage.removeItem(key);
  }
}
