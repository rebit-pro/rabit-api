import type { InstitutionDigitalArchive } from './institution-digital-types';

export function digitalArchiveError(code?: string | null): string {
  switch (code) {
    case 'ORIGINAL_MISSING':
      return 'Не все нужные оригиналы доступны. Архив не выдан частично. Проверьте исходники и создайте новую выгрузку.';
    case 'COMPOSITION_CHANGED':
      return 'Исходные данные изменились после снимка. Проверьте фотографии и создайте новую выгрузку.';
    case 'INVALID_ORIGINAL_FILENAME':
      return 'Исходное имя файла небезопасно для ZIP. Исправьте имя в источнике перед новой выгрузкой.';
    case 'DIGITAL_ARCHIVE_TOO_LARGE':
      return 'Выгрузка превышает предел: 10 000 файлов, 20 ГиБ исходников или 50 000 связей с покупками. Обратитесь к организатору.';
    case 'DIGITAL_ARCHIVE_IN_PROGRESS':
      return 'У учреждения уже собирается архив. Обновите состояние и дождитесь готовности.';
    case 'OPERATION_IN_PROGRESS':
      return 'Другая операция ещё выполняется. Обновите состояние и повторите действие.';
    case 'ARCHIVE_ACCESS_REVOKED':
      return 'Права инициатора изменились. Организатор с действующим доступом может создать новую выгрузку.';
    case 'ARCHIVE_NOT_FOUND':
      return 'Архив недоступен или срок скачивания истёк. Обновите состояние.';
    case 'FORBIDDEN':
    case 'INSTITUTION_NOT_FOUND':
      return 'Нет доступа к электронному архиву этого учреждения.';
    default:
      return 'Не удалось выполнить действие. Обновите состояние архива и повторите попытку.';
  }
}

export function digitalArchiveStatus(archive: InstitutionDigitalArchive): string {
  if (archive.status === 'pending') return archive.attempts ? 'Собирается или ожидает повтора' : 'Ожидает сборки';
  return {
    ready: 'Готов к скачиванию',
    failed: 'Ошибка сборки',
    empty: 'Нет купленных электронных кадров',
    expired: 'Срок ZIP истёк'
  }[archive.status];
}

/** Poll pending builds and refresh a ready row at the exact expiry even if the dialog stays open. */
export function digitalArchiveRefreshDelay(items: InstitutionDigitalArchive[], now: number): number | null {
  if (items.some((item) => item.status === 'pending')) return 4000;
  const deadlines = items
    .filter((item) => item.status === 'ready' && item.expiresAt)
    .map((item) => new Date(item.expiresAt!).getTime() - now)
    .filter((delay) => Number.isFinite(delay));
  return deadlines.length ? Math.max(1000, Math.min(...deadlines)) : null;
}
