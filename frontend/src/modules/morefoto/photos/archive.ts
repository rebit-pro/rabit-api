import { isVolumePart, type ZipEntry } from './zip-reader.ts';

/** One chosen archive: `key` identifies the same file when it is chosen again after a reload. */
export interface ArchiveSource {
  key: string;
  name: string;
  entries: ZipEntry[];
}
export interface ArchiveFile {
  archive: string;
  path: string;
  name: string;
  bytes: number;
}
export interface ArchiveFolder {
  /** Folder name as written in the archive; the key of the manual group mark. */
  folder: string;
  kind: 'child' | 'group';
  /** Child code; null for a group folder and for a folder whose name is not a code. */
  code: string | null;
  files: ArchiveFile[];
  problem: string;
  /** The group already has frames of this child: new ones are added to them. */
  existing: boolean;
}
export interface ArchiveSkip {
  archive: string;
  path: string;
  reason: string;
}
export interface ArchivePlan {
  folders: ArchiveFolder[];
  skipped: ArchiveSkip[];
  /** Codes every group frame is assigned to: children of the archives and children already in the group. */
  groupCodes: string[];
  problems: string[];
  files: number;
  bytes: number;
}
export interface ArchiveLimits {
  batch: number;
  bytes: number;
}

const photoExtension = /\.(jpe?g|png|webp)$/i;
const servicePath = /(^|\/)(__MACOSX\/|\.[^/]*$|Thumbs\.db$|desktop\.ini$)/i;
const groupPrefix = /^group/i;
const childCode = /^[A-Z]{1,3}$/;
// Cyrillic letters that look like Latin ones: a folder «А» is almost always a mistyped «A».
const lookalikes: Record<string, string> = {
  А: 'A',
  В: 'B',
  Е: 'E',
  К: 'K',
  М: 'M',
  Н: 'H',
  О: 'O',
  Р: 'P',
  С: 'C',
  Т: 'T',
  Х: 'X',
  У: 'Y'
};

export function archiveKey(file: { name: string; size: number; lastModified: number }): string {
  return JSON.stringify([file.name, file.size, file.lastModified]);
}

export function mimeType(name: string): string {
  const extension = name.split('.').pop()?.toLowerCase();
  return extension === 'png' ? 'image/png' : extension === 'webp' ? 'image/webp' : 'image/jpeg';
}

export function compareCodes(left: string, right: string): number {
  return left.length - right.length || (left < right ? -1 : left > right ? 1 : 0);
}

function folderProblem(name: string): string {
  const latin = Array.from(name.toUpperCase(), (letter) => lookalikes[letter] ?? '').join('');
  if (latin.length === name.length && childCode.test(latin))
    return 'Имя папки «' + name + '» набрано кириллицей. Нужна латинская буква: ' + latin + '.';

  return 'Имя папки «' + name + '» — не код ребёнка. Нужны латинские буквы (A, B, AA) или префикс GROUP для групповых кадров.';
}

/** A folder holding every file of one archive is a packing wrapper, unless the wrapper is itself a child or a group. */
function wrapper(paths: string[]): string {
  const name = paths[0]?.split('/')[0] ?? '';
  const root = name + '/';
  if (!paths.every((path) => path.startsWith(root)) || !paths.some((path) => path.split('/').length >= 3)) return '';
  const folder = name.trim();

  return childCode.test(folder.toUpperCase()) || groupPrefix.test(folder) ? '' : root;
}

/**
 * Turns the chosen archives into one upload plan. A top-level folder is a child with its code, a folder with the
 * GROUP prefix or a manually marked one is a set of group frames shared by every child of the group.
 */
export function planArchives(
  sources: ArchiveSource[],
  existingCodes: readonly string[],
  groupMarks: ReadonlySet<string>,
  limits: ArchiveLimits
): ArchivePlan {
  const folders = new Map<string, ArchiveFolder>();
  const skipped: ArchiveSkip[] = [];
  const problems: string[] = [];
  const existing = new Set(existingCodes);
  for (const source of sources) {
    if (isVolumePart(source.name)) {
      problems.push('«' + source.name + '» — том многотомного архива. Нужны самостоятельные ZIP-архивы.');
      continue;
    }
    const files = source.entries.filter((entry) => !entry.directory && !servicePath.test(entry.path));
    const root = wrapper(files.map((entry) => entry.path));
    for (const entry of files) {
      const path = entry.path.slice(root.length);
      const parts = path.split('/').filter(Boolean);
      const name = parts[parts.length - 1] ?? path;
      const skip = (reason: string) => skipped.push({ archive: source.key, path: entry.path, reason });
      if (parts.length < 2) {
        skip('Файл лежит вне папки ребёнка.');
        continue;
      }
      if (!photoExtension.test(name)) {
        skip('Не фотография: допустимы JPEG, PNG и WebP.');
        continue;
      }
      if (entry.encrypted) {
        skip('Файл защищён паролем.');
        continue;
      }
      if (entry.bytes === 0) {
        skip('Файл пуст.');
        continue;
      }
      if (entry.bytes > limits.bytes) {
        skip('Файл больше 25 МБ.');
        continue;
      }
      const folderName = (parts[0] ?? '').trim();
      const code = folderName.toUpperCase();
      const group = groupPrefix.test(folderName) || groupMarks.has(folderName);
      const valid = group || childCode.test(code);
      const key = group ? 'group:' + folderName.toUpperCase() : valid ? 'child:' + code : 'invalid:' + folderName;
      let folder = folders.get(key);
      if (!folder) {
        folder = {
          folder: folderName,
          kind: group ? 'group' : 'child',
          code: group || !valid ? null : code,
          files: [],
          problem: valid ? '' : folderProblem(folderName),
          existing: !group && valid && existing.has(code)
        };
        folders.set(key, folder);
      }
      folder.files.push({ archive: source.key, path: entry.path, name, bytes: entry.bytes });
    }
  }
  const ordered = [...folders.values()].sort(
    (left, right) =>
      Number(left.kind === 'group') - Number(right.kind === 'group') ||
      compareCodes(left.code ?? left.folder.toUpperCase(), right.code ?? right.folder.toUpperCase())
  );
  for (const folder of ordered) folder.files.sort((left, right) => left.path.localeCompare(right.path, 'en', { numeric: true }));
  const groupCodes = [...new Set([...ordered.flatMap((folder) => (folder.code ? [folder.code] : [])), ...existingCodes])].sort(
    compareCodes
  );
  const files = ordered.reduce((sum, folder) => sum + folder.files.length, 0);
  if (ordered.some((folder) => folder.problem)) problems.push('Исправьте имена папок, отмеченных ошибкой, или отметьте их как групповые.');
  if (ordered.some((folder) => folder.kind === 'group') && !groupCodes.length)
    problems.push('Групповым кадрам некому достаться: в архивах и в группе нет ни одного ребёнка.');
  if (files > limits.batch) problems.push('В архивах ' + files + ' фотографий. За одну загрузку — не больше ' + limits.batch + '.');
  if (!files && !problems.length) problems.push('В архивах нет фотографий в папках детей.');

  return {
    folders: ordered,
    skipped,
    groupCodes,
    problems,
    files,
    bytes: ordered.reduce((sum, folder) => sum + folder.files.reduce((total, file) => total + file.bytes, 0), 0)
  };
}

/** Codes one file of the folder is uploaded with. */
export function folderCodes(folder: ArchiveFolder, plan: ArchivePlan): string[] {
  return folder.kind === 'group' ? plan.groupCodes : folder.code ? [folder.code] : [];
}
