import type { ManagedPhoto } from './types.js';
export const photoLimits = { batch: 50, bytes: 25 * 1024 * 1024, pixels: 40_000_000, formats: ['image/jpeg', 'image/png', 'image/webp'] };
export function fileProblem(file: { name: string; size: number; type: string }): string {
  if (!photoLimits.formats.includes(file.type)) return 'Допустимы JPEG, PNG и WebP.';
  if (!file.size) return 'Файл пуст.';
  if (file.size > photoLimits.bytes) return 'Файл больше 25 МБ.';
  if (file.name.length > 240) return 'Сократите имя файла до 240 символов.';
  return '';
}
export function validChildCode(code: string): boolean {
  return /^[A-Z]{1,3}$/.test(code);
}
export function childCodeAt(index: number): string {
  let result = '';
  let value = index + 1;
  while (value > 0) {
    value--;
    result = String.fromCharCode(65 + (value % 26)) + result;
    value = Math.floor(value / 26);
  }
  return result;
}
export function nextChildCode(photos: ManagedPhoto[], groupId: string): string {
  const codes = new Set(photos.filter((item) => item.groupId === groupId).map((item) => item.childCode));
  for (let i = 0; i < 18278; i++) {
    const code = childCodeAt(i);
    if (!codes.has(code)) return code;
  }
  throw new Error('Достигнут предел кодов детей.');
}
export function photoCode(child: string, sequence: number): string {
  return child + String(sequence).padStart(3, '0');
}
export function nextSequence(photos: ManagedPhoto[], groupId: string, child: string): number {
  return (
    Math.max(0, ...photos.filter((item) => item.groupId === groupId && item.childCode === child).map((item) => item.sequence ?? 0)) + 1
  );
}
export function duplicatePhoto(photos: ManagedPhoto[], shootId: string, fingerprint: string) {
  return photos.find((item) => item.shootId === shootId && item.fingerprint === fingerprint);
}
export function completeChildSelection(photos: ManagedPhoto[], ids: string[]): boolean {
  const chosen = photos.filter((item) => ids.includes(item.id));
  const first = chosen[0];
  return (
    !!first?.childCode &&
    chosen.length === new Set(ids).size &&
    chosen.every((item) => item.groupId === first.groupId && item.childCode === first.childCode) &&
    photos.filter((item) => item.groupId === first.groupId && item.childCode === first.childCode).every((item) => ids.includes(item.id))
  );
}
