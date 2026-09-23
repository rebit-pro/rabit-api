import type { ManagedPhoto } from './types.js';
// parallel: simultaneous original uploads; the server prepares previews independently of the queue.
export const photoLimits = {
  batch: 2000,
  parallel: 2,
  bytes: 25 * 1024 * 1024,
  pixels: 40_000_000,
  formats: ['image/jpeg', 'image/png', 'image/webp']
};
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
function assignments(photo: ManagedPhoto) {
  return (
    photo.assignments ??
    (photo.childCode
      ? [{ childId: photo.groupId + ':' + photo.childCode, childCode: photo.childCode, sequence: photo.sequence ?? 1, code: photo.code }]
      : [])
  );
}
export function freeChildCode(codes: ReadonlySet<string>): string {
  for (let i = 0; i < 18278; i++) {
    const code = childCodeAt(i);
    if (!codes.has(code)) return code;
  }
  throw new Error('Достигнут предел кодов детей.');
}
export function nextChildCode(photos: ManagedPhoto[], groupId: string): string {
  return freeChildCode(
    new Set(
      photos.filter((item) => item.groupId === groupId).flatMap((item) => assignments(item).map((assignment) => assignment.childCode))
    )
  );
}
export function photoCode(child: string, sequence: number): string {
  return child + String(sequence).padStart(3, '0');
}
export function nextSequence(photos: ManagedPhoto[], groupId: string, child: string): number {
  return (
    Math.max(
      0,
      ...photos
        .filter((item) => item.groupId === groupId)
        .flatMap(assignments)
        .filter((assignment) => assignment.childCode === child)
        .map((assignment) => assignment.sequence)
    ) + 1
  );
}
export function duplicatePhoto(photos: ManagedPhoto[], shootId: string, fingerprint: string) {
  return photos.find((item) => item.shootId === shootId && item.fingerprint === fingerprint);
}
export function completeChildSelection(photos: ManagedPhoto[], ids: string[]): boolean {
  const chosen = photos.filter((item) => ids.includes(item.id));
  const first = chosen[0];
  const child = first ? assignments(first)[0]?.childCode : undefined;
  if (!first || !child) return false;

  return (
    chosen.length === new Set(ids).size &&
    chosen.every((item) => item.groupId === first.groupId && assignments(item).some((assignment) => assignment.childCode === child)) &&
    photos
      .filter((item) => item.groupId === first.groupId && assignments(item).some((assignment) => assignment.childCode === child))
      .every((item) => ids.includes(item.id))
  );
}
const childTransferErrors: Record<string, string> = {
  SET_CHANGED: 'Набор ребёнка изменился. Список обновлён — проверьте кадры и повторите перенос.',
  TARGET_CODE_TAKEN: 'Этот код уже занят в целевой группе. Выберите другой код: существующие наборы не объединяются.',
  GROUP_KIND_MISMATCH: 'Перенос возможен только между группами одного типа.',
  GROUP_LOCKED: 'Одна из групп уже передана. Перенос набора возможен только до передачи ссылки.',
  CHILD_HAS_ORDERS: 'По этому набору уже есть заказы. Перенос недоступен.',
  INVALID_PHOTO_IDS: 'Набор ребёнка не удалось передать на сервер. Обновите страницу и повторите перенос.'
};
/** Текст отказа MED-07; null — код не относится к переносу. */
export function childTransferErrorText(code: string | undefined, photoCodes: string[] = []): string | null {
  if (code === 'SHARED_PHOTO')
    return (
      'Кадры ' +
      (photoCodes.length ? photoCodes.join(', ') : 'набора') +
      ' назначены ещё и другому ребёнку этой группы. Такой набор нельзя перенести.'
    );
  return code ? (childTransferErrors[code] ?? null) : null;
}
