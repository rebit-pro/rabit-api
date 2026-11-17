import type { InstitutionArchiveVersion, InstitutionPrintArchive } from './institution-archive-types';

export function latestInstitutionArchive(archive: InstitutionPrintArchive): InstitutionArchiveVersion | null {
  return [...archive.versions].reverse().find((version) => version.kind === 'full' && version.status === 'ready') ?? null;
}

export function canSupplementInstitution(archive: InstitutionPrintArchive): boolean {
  return (
    archive.canManage &&
    archive.outsideLines > 0 &&
    !archive.blockers.length &&
    archive.state !== 'scheduled' &&
    archive.versions.some((version) => version.kind === 'full')
  );
}

export function institutionVersionTitle(version: InstitutionArchiveVersion): string {
  return (version.kind === 'full' ? 'Полный архив' : 'Дополнение') + ' · версия ' + version.number;
}

const errors: Record<string, string> = {
  PRINT_SALES_OPEN: 'Для нового выпуска нужно закрытие печати всех групп учреждения. Прежний готовый архив остаётся доступен.',
  BUILD_DELAY: 'Новый архив появится через час после самого позднего срока печати.',
  COMPOSITION_CHANGED: 'Группы или сроки изменились во время сборки. Новый состав проверит автоматический планировщик.',
  ARCHIVE_NOT_READY: 'Готового архива пока нет. Обновите состояние.',
  ARCHIVE_NOT_FOUND: 'Файл недоступен или срок доступа закончился. Обновите состояние архива.',
  ARCHIVE_MISSING: 'Файл архива отсутствует. Организатор может собрать заново сохранённый состав.',
  INSTITUTION_NOT_FOUND: 'Учреждение недоступно в вашей области.',
  OPERATION_IN_PROGRESS: 'С архивом уже выполняется действие. Обновите состояние через несколько секунд.'
};

export function institutionArchiveError(code: string | null | undefined): string | null {
  return code ? (errors[code] ?? null) : null;
}
