import { isAxiosError } from 'axios';
import api from '@/api/http';

export type ServerPhotoStatus = 'processing' | 'ready' | 'failed' | 'duplicate';
export interface ServerPhoto {
  id: string;
  status: ServerPhotoStatus;
  shootId: string;
  groupId: string;
  originalGroupId: string;
  childCode: string | null;
  code: string | null;
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

export const photosApi = {
  async list(shootId: string, page = 1, pageSize = 100): Promise<ServerPhotoPage> {
    return (await api.get<ServerPhotoPage>('/api/v1/shoots/' + encodeURIComponent(shootId) + '/photos', { params: { page, pageSize } }))
      .data;
  },
  async detail(photoId: string): Promise<ServerPhoto> {
    return (await api.get('/api/v1/photos/' + encodeURIComponent(photoId))).data;
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

export function photoApiError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось обработать фотографию.';
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code;
  if (code === 'FINGERPRINT_MISMATCH') return 'Контрольная сумма файла не совпала. Выберите исходник заново.';
  if (cause.response?.status === 403) return 'Недостаточно прав для загрузки фотографий.';
  if (cause.response?.status === 404) return 'Съёмка или группа больше недоступна.';
  if (cause.response?.status === 413 || cause.response?.status === 422)
    return 'Сервер отклонил файл. Проверьте формат, размер и разрешение.';
  return 'Сервер не завершил обработку. Повторите попытку.';
}
