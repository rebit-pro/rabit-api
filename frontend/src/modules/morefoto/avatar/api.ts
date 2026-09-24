import { isAxiosError } from 'axios';
import api from '@/api/http';
import type { AvatarRef } from '@/api/auth';
import { apiErrorCode } from '@/api/authErrors';

interface SavedAvatar {
  userId: number;
  avatar: AvatarRef;
}

export const AVATAR_TYPES: readonly string[] = ['image/jpeg', 'image/png', 'image/webp'];
const AVATAR_MAX_BYTES = 5 * 1024 * 1024;
const multipart = { headers: { 'Content-Type': 'multipart/form-data' } };

function form(file: File): FormData {
  const body = new FormData();
  body.append('file', file);
  return body;
}

export const avatarApi = {
  async saveMine(file: File): Promise<AvatarRef> {
    return (await api.put<SavedAvatar>('/api/v1/me/avatar', form(file), multipart)).data.avatar;
  },
  async removeMine(): Promise<void> {
    await api.delete('/api/v1/me/avatar');
  },
  async save(userId: number, file: File): Promise<AvatarRef> {
    return (await api.put<SavedAvatar>('/api/v1/users/' + userId + '/avatar', form(file), multipart)).data.avatar;
  },
  async remove(userId: number): Promise<void> {
    await api.delete('/api/v1/users/' + userId + '/avatar');
  }
};

/** A quick check before sending: a wrong file fails at once, the server still checks the content itself. */
export function avatarFileProblem(file: File): string | null {
  if (!AVATAR_TYPES.includes(file.type)) return 'Подойдёт фото в формате JPEG, PNG или WebP.';
  if (file.size > AVATAR_MAX_BYTES) return 'Фото больше 5 МБ — выберите файл поменьше.';
  return null;
}

export function avatarError(cause: unknown): string {
  switch (apiErrorCode(cause)) {
    case 'AVATAR_TOO_LARGE':
      return 'Фото слишком большое: до 5 МБ и 25 мегапикселей.';
    case 'AVATAR_TOO_SMALL':
      return 'Фото слишком маленькое: нужно не меньше 64 × 64 пикселей.';
    case 'UNSUPPORTED_AVATAR_FORMAT':
      return 'Подойдёт фото в формате JPEG, PNG или WebP.';
    case 'CORRUPTED_AVATAR':
      return 'Файл повреждён — выберите другое фото.';
    case 'STAFF_NOT_FOUND':
      return 'Сотрудник не найден. Обновите список.';
  }
  if (isAxiosError(cause) && cause.response?.status === 403) return 'Менять фото коллег может только организатор.';
  return 'Не удалось сохранить фото. Попробуйте ещё раз.';
}
