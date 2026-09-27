import { isAxiosError } from 'axios';
import api from '@/api/http';
import { childTransferErrorText } from './rules';
import type { PhotoDeletionAttempt } from './deletion';

export type ServerPhotoStatus = 'processing' | 'ready' | 'failed' | 'duplicate';
export interface ServerPhotoAssignment {
  childId: string;
  childCode: string;
  sequence: number;
  code: string;
}
export interface ServerPhoto {
  id: string;
  status: ServerPhotoStatus;
  shootId: string;
  groupId: string;
  originalGroupId: string;
  childCode: string | null;
  code: string | null;
  sequence: number | null;
  assignments: ServerPhotoAssignment[];
  filename: string;
  bytes: number;
  width: number;
  height: number;
  fingerprint: string;
  revision: number;
  thumbSrc: string | null;
  previewSrc: string | null;
  error: string | null;
  existingPhotoId: string | null;
}
export interface ServerMediaGroup {
  id: number;
  publicId: string;
  name: string;
  kind: string;
}
/** Ready frames of the requested group, whatever page or filter is shown. */
export interface ServerPhotoGroupSummary {
  photos: number;
  unassigned: number;
  children: string[];
}
/** Processing split of the selected group without the page filters (U5). */
export interface ServerPhotoStats {
  byStatus: {
    processing: number;
    ready: number;
    failed: number;
    duplicate: number;
  };
  unassigned: number;
}
export interface ServerPhotoPage {
  items: ServerPhoto[];
  groups: ServerMediaGroup[];
  covers: Record<string, string>;
  revision: number;
  meta: { page: number; pageSize: number; total: number };
  summary: ServerPhotoGroupSummary | null;
  stats?: ServerPhotoStats;
}
export interface PhotoListQuery {
  groupId?: string;
  childCode?: string;
  assigned?: boolean;
  status?: ServerPhotoStatus;
  page?: number;
  pageSize?: number;
}
export interface UploadPhotoResult {
  id: string;
  status: ServerPhotoStatus;
  revision: number;
  existingPhotoId: string | null;
  /** Codes the photo is labelled with after the upload; empty without codes or for a photo of another group. */
  childCodes?: string[];
}
export interface AssignmentResult {
  photoIds: string[];
  childCode: string;
  childId: string;
  revision: number;
}
export interface CoverResult {
  photoId: string;
  revision: number;
}
export interface ChildTransfer {
  fromGroupId: string;
  toGroupId: string;
  childCode: string;
  targetCode: string;
  expectedPhotoIds: string[];
  revision: number;
}
export interface PhotoDeletionResult {
  deleted: number;
  revision: number;
}
export interface ChildTransferResult {
  photoIds: string[];
  fromGroupId: string;
  toGroupId: string;
  childCode: string;
  revision: number;
}
// Matches PHP max_execution_time: the shared 15 s timeout cuts large originals on slow uplinks.
const uploadTimeout = 300_000;
export function idempotencyKey(): string {
  return crypto.randomUUID().replace(/-/g, '');
}

export const photosApi = {
  async list(shootId: string, query: PhotoListQuery): Promise<ServerPhotoPage> {
    const response = await api.get<ServerPhotoPage | null>('/api/v1/shoots/' + encodeURIComponent(shootId) + '/photos', {
      params: query
    });
    if (null === response.data || !Array.isArray(response.data.items) || !response.data.meta) {
      throw new Error('Не удалось загрузить список кадров. Повторите попытку.');
    }

    return response.data;
  },
  async detail(photoId: string): Promise<ServerPhoto> {
    return (await api.get('/api/v1/photos/' + encodeURIComponent(photoId))).data;
  },
  async assign(groupId: string, shootId: string, revision: number, photoIds: string[], childCode: string): Promise<AssignmentResult> {
    return (
      await api.post<AssignmentResult>(
        '/api/v1/groups/' + encodeURIComponent(groupId) + '/photo-assignments',
        { shootId, revision, photoIds, childCode },
        { headers: { 'Idempotency-Key': idempotencyKey() } }
      )
    ).data;
  },
  async cover(groupId: string, revision: number, photoId: string): Promise<CoverResult> {
    return (
      await api.put<CoverResult>(
        '/api/v1/groups/' + encodeURIComponent(groupId) + '/cover',
        { revision, photoId },
        { headers: { 'Idempotency-Key': idempotencyKey() } }
      )
    ).data;
  },
  /** The key comes with the attempt: a repeat after a lost answer must send the same one. */
  async remove(attempt: PhotoDeletionAttempt): Promise<PhotoDeletionResult> {
    return (
      await api.post<PhotoDeletionResult>(
        '/api/v1/groups/' + encodeURIComponent(attempt.groupId) + '/photo-deletions',
        { revision: attempt.revision, photoIds: attempt.photoIds },
        { headers: { 'Idempotency-Key': attempt.key } }
      )
    ).data;
  },
  async transferChild(shootId: string, transfer: ChildTransfer): Promise<ChildTransferResult> {
    return (
      await api.post<ChildTransferResult>('/api/v1/shoots/' + encodeURIComponent(shootId) + '/child-transfers', transfer, {
        headers: { 'Idempotency-Key': idempotencyKey() }
      })
    ).data;
  },
  async upload(
    shootId: string,
    groupId: string,
    file: File,
    signal: AbortSignal,
    onProgress: (progress: number) => void,
    childCodes: string[] = []
  ): Promise<UploadPhotoResult> {
    const body = new FormData();
    body.append('groupId', groupId);
    if (childCodes.length) body.append('childCodes', childCodes.join(','));
    body.append('file', file, file.name);
    return (
      await api.post('/api/v1/shoots/' + encodeURIComponent(shootId) + '/photos', body, {
        signal,
        timeout: uploadTimeout,
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (event) => onProgress(event.total ? Math.round((event.loaded / event.total) * 100) : 0)
      })
    ).data;
  }
};

export function photoApiErrorCode(cause: unknown): string | undefined {
  if (!isAxiosError(cause)) return undefined;
  return (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code;
}

export function childTransferError(cause: unknown): string {
  const details = isAxiosError(cause)
    ? (cause.response?.data as { error?: { details?: { photoCodes?: string[] } } } | undefined)?.error?.details
    : undefined;
  return childTransferErrorText(photoApiErrorCode(cause), details?.photoCodes) ?? photoApiError(cause);
}

export function photoApiError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось обработать фотографию.';
  const code = photoApiErrorCode(cause);
  if (code === 'FINGERPRINT_MISMATCH') return 'Контрольная сумма файла не совпала. Выберите исходник заново.';
  if (code === 'REVISION_CONFLICT') return 'Разметка уже изменилась. Обновите список и повторите действие.';
  if (code === 'PHOTO_NOT_ASSIGNABLE') return 'Один из кадров ещё не готов или уже относится к другой группе.';
  if (code === 'PHOTO_NOT_COVER_ELIGIBLE') return 'Сначала назначьте кадр ребёнку в этой группе.';
  if (code === 'PHOTO_NOT_DELETABLE') return 'Один из кадров уже удалён или относится к другой группе. Обновите список.';
  if (code === 'PHOTO_PROCESSING') return 'Один из кадров ещё обрабатывается. Дождитесь окончания и повторите удаление.';
  if (code === 'GROUP_LOCKED' || code === 'GROUP_MEDIA_LOCKED') return 'Подборка уже опубликована и недоступна для изменений.';
  if (code === 'INVALID_CHILD_CODES') return 'Сервер не принял коды детей. Проверьте имена папок в архиве.';
  if (cause.response?.status === 403) return 'Недостаточно прав для работы с фотографиями.';
  if (cause.response?.status === 404) return 'Съёмка, группа или фотография больше недоступна.';
  if (cause.response?.status === 413 || cause.response?.status === 422)
    return 'Сервер отклонил файл. Проверьте формат, размер и разрешение.';
  return 'Сервер не завершил операцию. Повторите попытку.';
}
