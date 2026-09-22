import { isAxiosError } from 'axios';
import api from '@/api/http';
import { childTransferErrorText } from './rules';

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
export interface ServerPhotoPage {
  items: ServerPhoto[];
  groups: ServerMediaGroup[];
  covers: Record<string, string>;
  revision: number;
  meta: { page: number; pageSize: number; total: number };
}
export interface UploadPhotoResult {
  id: string;
  status: ServerPhotoStatus;
  revision: number;
  existingPhotoId: string | null;
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
export interface ChildTransferResult {
  photoIds: string[];
  fromGroupId: string;
  toGroupId: string;
  childCode: string;
  revision: number;
}
function idempotencyKey(): string {
  return crypto.randomUUID().replace(/-/g, '');
}

export const photosApi = {
  async list(shootId: string, page = 1, pageSize = 100): Promise<ServerPhotoPage> {
    const response = await api.get<ServerPhotoPage | null>('/api/v1/shoots/' + encodeURIComponent(shootId) + '/photos', {
      params: { page, pageSize }
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
    onProgress: (progress: number) => void
  ): Promise<UploadPhotoResult> {
    const body = new FormData();
    body.append('groupId', groupId);
    body.append('file', file, file.name);
    return (
      await api.post('/api/v1/shoots/' + encodeURIComponent(shootId) + '/photos', body, {
        signal,
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (event) => onProgress(event.total ? Math.min(75, Math.round((event.loaded / event.total) * 75)) : 20)
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
  if (code === 'GROUP_LOCKED') return 'Подборка уже опубликована и недоступна для изменений.';
  if (cause.response?.status === 403) return 'Недостаточно прав для работы с фотографиями.';
  if (cause.response?.status === 404) return 'Съёмка, группа или фотография больше недоступна.';
  if (cause.response?.status === 413 || cause.response?.status === 422)
    return 'Сервер отклонил файл. Проверьте формат, размер и разрешение.';
  return 'Сервер не завершил операцию. Повторите попытку.';
}
