import { isMockApiEnabled } from '@/mocks/config';
import { readDemo, demoChangedEvent } from '../mocks/storage';
import { clearPhotoBlobs, hasPhotoBlobs } from '../photos/blobs';
import { clearDemoStorage } from '../mocks/runtime';
import { getDemoNow } from '../mocks/clock';
import { defaultCatalog } from '../commerce/mocks/catalog';
import { randomKey } from '../orders/services/orders';
import { reviewFixture } from './fixture';
import { hasDemoData, reviewSettingsError, reviewTime } from './rules';
import type { ReviewSettings } from './rules';
export interface ReviewSession {
  version: 1;
  generation: string;
}
export interface ReviewStatus {
  session: ReviewSession | null;
  blocked: boolean;
  now: string;
  delay: number;
  offline: boolean;
}
const marker = 'review:session';
function requireDemo() {
  if (!isMockApiEnabled) throw new Error('Проверка доступна только на демонстрационном стенде.');
}
function session() {
  const s = readDemo<ReviewSession | null>(marker, null);
  return s?.version === 1 && s.generation ? s : null;
}
function requireSession(expected: string) {
  requireDemo();
  if (session()?.generation !== expected) throw new Error('Набор изменился. Обновите страницу проверки.');
}
export async function reviewStatus(): Promise<ReviewStatus> {
  requireDemo();
  const s = session();
  return {
    session: s,
    blocked: !s && (hasDemoData(Object.keys(localStorage)) || (await hasPhotoBlobs())),
    now: getDemoNow(),
    delay: readDemo('runtime:delay', 250),
    offline: readDemo('runtime:offline', false)
  };
}
async function locked<T>(run: () => Promise<T>): Promise<T> {
  return navigator.locks ? navigator.locks.request('morefoto:review:write', run) : run();
}
export async function prepareReview(expected?: string): Promise<void> {
  requireDemo();
  return locked(async () => {
    if (expected) requireSession(expected);
    else if (hasDemoData(Object.keys(localStorage)) || (await hasPhotoBlobs()))
      throw new Error('В браузере уже есть данные. Откройте отдельное приватное окно для проверки.');
    const data = reviewFixture(),
      next: ReviewSession = { version: 1, generation: randomKey() };
    if (expected) {
      await clearPhotoBlobs();
      requireSession(expected);
      clearDemoStorage();
    } else if (hasDemoData(Object.keys(localStorage))) throw new Error('Данные появились в другой вкладке. Подготовка отменена.');
    const values: Record<string, unknown> = {
      'organization:v1': data.organization,
      'photos:v1': data.photos,
      'catalog:v1': structuredClone(defaultCatalog),
      'orders:v1': [],
      'production:v1': { jobs: [], operations: [] },
      'clock:now': data.now,
      [marker]: next
    };
    for (const [key, value] of Object.entries(values)) localStorage.setItem('morefoto:demo:' + key, JSON.stringify(value));
    window.dispatchEvent(new Event(demoChangedEvent));
  });
}
export function configureReview(expected: string, settings: ReviewSettings): void {
  requireSession(expected);
  const error = reviewSettingsError(settings);
  if (error) throw new Error(error);
  for (const [key, value] of Object.entries({
    'clock:now': reviewTime(settings.date),
    'runtime:delay': settings.delay,
    'runtime:offline': settings.offline
  }))
    localStorage.setItem('morefoto:demo:' + key, JSON.stringify(value));
  window.dispatchEvent(new Event(demoChangedEvent));
}
export function failReviewRequest(expected: string): void {
  requireSession(expected);
  localStorage.setItem('morefoto:demo:runtime:fail-next', 'true');
}
