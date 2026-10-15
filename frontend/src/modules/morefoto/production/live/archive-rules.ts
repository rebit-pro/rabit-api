import type { StatusTone } from '../../../../components/status/tones';
import type { ArchiveState, ArchiveVersion, GroupArchive } from './archive-types';

/** Short state of the print archive next to the print deadline of a row; nothing while print sales run. */
export function archiveBadge(state: ArchiveState): { text: string; tone: StatusTone } | null {
  switch (state) {
    case 'scheduled':
      return { text: 'архив соберётся сам', tone: 'neutral' };
    case 'manual':
      return { text: 'архив не собран', tone: 'warning' };
    case 'building':
      return { text: 'архив собирается', tone: 'pending' };
    case 'ready':
      return { text: 'архив печати готов', tone: 'success' };
    case 'failed':
      return { text: 'архив: ошибка сборки', tone: 'danger' };
    case 'empty':
      return { text: 'оплаченной печати нет', tone: 'neutral' };
    case 'expired':
      return { text: 'архив удалён по сроку', tone: 'neutral' };
    default:
      return null;
  }
}

/** What the organizer may build now: the first archive, a supplement with late-confirmed payments or nothing. */
export function buildAction(archive: GroupArchive): 'archive' | 'supplement' | null {
  if (!archive.canManage || archive.state === 'open') return null;
  if (!archive.versions.length) return archive.state === 'manual' || archive.state === 'scheduled' ? 'archive' : null;
  return archive.outsideLines > 0 ? 'supplement' : null;
}

export function versionTitle(version: ArchiveVersion): string {
  return version.number === 1 ? 'Архив печати' : 'Дополнение ' + (version.number - 1);
}

/** The version is still being built: the dialog keeps reloading the block while it is open. */
export function isBuilding(archive: GroupArchive): boolean {
  return archive.versions.some((version) => version.status === 'pending');
}

const errors: Record<string, string> = {
  ORIGINAL_MISSING: 'Нет оригинала одного из оплаченных кадров. Проверьте фотографии группы и соберите архив заново.',
  ARCHIVE_FAILED: 'Архив не записался после трёх попыток. Соберите его заново; если ошибка повторится — напишите разработчику.'
};
export function versionErrorText(code: string | null): string {
  return (code && errors[code]) || 'Архив не собран. Соберите его заново.';
}

const commandErrors: Record<string, string> = {
  NOTHING_TO_BUILD: 'Новых оплаченных позиций нет — дополнение не нужно.',
  PRINT_SALES_OPEN: 'Приём печати ещё идёт: архив соберётся через час после его окончания.',
  VERSION_CONFLICT: 'Архив только что изменили. Обновите блок «Печать».',
  VERSION_NOT_RENEWABLE: 'Этот архив сейчас нельзя перевыпустить. Обновите блок «Печать».',
  IDEMPOTENCY_CONFLICT: 'Действие уже выполнено с другими данными. Обновите блок «Печать».',
  GROUP_NOT_FOUND: 'Группа больше не доступна в вашей области.',
  VERSION_NOT_FOUND: 'Архив больше не доступен в вашей области.',
  FORBIDDEN: 'Собирать архив и перевыпускать ссылку может только организатор.'
};
/** Text of a failed archive request; no answer at all is an unknown outcome — reloading the block shows it. */
export function archiveErrorText(problem: { network: boolean; status?: number; code?: string | null }): string {
  if (problem.network) return 'Ответ сервера не получен. Обновите блок «Печать», чтобы увидеть, выполнено ли действие.';
  const known = problem.code ? commandErrors[problem.code] : undefined;
  if (known) return known;
  if (problem.status === 403) return commandErrors.FORBIDDEN!;
  if (problem.status === 404) return commandErrors.GROUP_NOT_FOUND!;
  return 'Сервер не выполнил действие. Повторите позже.';
}

/** Absolute address of the archive link for the lab: the API may live on another origin than the cabinet. */
export function archiveHref(archiveUrl: string, apiBase: string | undefined, origin: string): string {
  return new URL((apiBase ?? '').replace(/\/api\/?$/, '').replace(/\/$/, '') + archiveUrl, origin).href;
}
