import type { ApiProblem } from './types.js';
import type { DownloadBody, FileDownload } from './files-types.js';

/** The archive of a full set is built in the background; the buyer sees progress for at most ten minutes. */
export const ARCHIVE_WAIT_MS = 10 * 60 * 1000;

export function downloadBody(photoId?: string): DownloadBody {
  return photoId === undefined ? { kind: 'zip' } : { kind: 'file', photoIds: [photoId] };
}

/** Polling starts fast for small archives and slows down for a large set. */
export function pollDelay(elapsedMs: number): number {
  return elapsedMs < 30_000 ? 2000 : 5000;
}

/** The content link is relative to the API; a separate API host is prefixed so that the browser downloads it directly. */
export function contentHref(download: FileDownload, apiBase: string | undefined): string | null {
  if (download.status !== 'ready' || !download.contentUrl) return null;
  return (apiBase ?? '').replace(/\/api\/?$/, '').replace(/\/$/, '') + download.contentUrl;
}

export function fileSize(bytes: number): string {
  if (bytes < 1024 * 1024) return Math.max(1, Math.round(bytes / 1024)) + ' КБ';
  if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1).replace('.', ',') + ' МБ';
  return (bytes / (1024 * 1024 * 1024)).toFixed(2).replace('.', ',') + ' ГБ';
}

export function filesCount(count: number): string {
  const tail = count % 100;
  const last = count % 10;
  const word = tail >= 11 && tail <= 14 ? 'файлов' : last === 1 ? 'файл' : last >= 2 && last <= 4 ? 'файла' : 'файлов';
  return count + ' ' + word;
}

const messages: Record<string, string> = {
  FILES_UNAVAILABLE: 'Файлы сейчас недоступны. Обновите страницу заказа.',
  FILES_EXPIRED: 'Срок скачивания файлов истёк.',
  ARCHIVE_TOO_LARGE: 'Архив слишком большой. Скачайте фотографии по одной.',
  ARCHIVE_IN_PROGRESS: 'Архив уже готовится. Дождитесь его и повторите.',
  COMPOSITION_CHANGED: 'Состав фотографий изменился. Подготовьте архив заново.',
  DOWNLOAD_EXPIRED: 'Ссылка на скачивание устарела. Нажмите «Скачать» ещё раз.',
  DOWNLOAD_FAILED: 'Не удалось подготовить архив. Попробуйте ещё раз — оплачивать повторно не нужно.',
  PHOTO_NOT_ENTITLED: 'Этот файл не входит в заказ.',
  ORDER_NOT_FOUND: 'Личная ссылка на заказ больше не действует.'
};

export function filesError(problem: ApiProblem): string {
  if (problem.network) return 'Нет связи с сервером. Проверьте интернет и повторите.';
  return messages[problem.code] ?? 'Не удалось подготовить скачивание. Повторите попытку.';
}

/** A request without an answer may already exist on the server: the same key repeats it instead of creating a second one. */
export function keepsRequestKey(problem: ApiProblem): boolean {
  return problem.network || problem.status === null || (problem.status >= 500 && problem.status !== 501);
}
