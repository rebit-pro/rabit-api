import type { ArchiveFile, ArchiveFolder, ArchivePlan, ArchiveSkip } from './archive.ts';
import { childCodeAt, fileProblem, photoLimits, validChildCode } from './rules.ts';
import { compareCodes, mimeType } from './archive.ts';
import type { ZipEntry } from './zip-reader.ts';

export interface ArchiveClass {
  name: string;
  skipped: number;
  series: Map<string, ArchiveFile[]>;
}
export interface InstitutionArchive {
  groups: ArchiveClass[];
  skipped: ArchiveSkip[];
}
const service = /(^|\/)(__MACOSX|[.][^/]*|Thumbs[.]db|desktop[.]ini)(\/|$)/i;
const photo = /\.(jpe?g|png|webp)$/i;
const numericOrder = new Intl.Collator('ru', { numeric: true });
export function archiveRoots(entries: ZipEntry[]) {
  const paths = entries.filter((e) => !e.directory && !service.test(e.path) && photo.test(e.path)).map((e) => e.path.split('/'));
  const candidates = new Set(paths.filter((parts) => parts.length === 4).map((parts) => parts[0]!));
  const wrapper = candidates.size === 1 ? [...candidates][0]! : '';
  return {
    wrapper,
    initial: wrapper && !paths.some((parts) => parts.length === 3) ? wrapper : ''
  };
}

export function inspectInstitutionArchive(entries: ZipEntry[], archive: string, root: string): InstitutionArchive {
  const groups = new Map<string, ArchiveClass>();
  const skipped: ArchiveSkip[] = [];
  const seen = new Set<string>();
  const repeated = new Set<string>();
  for (const entry of entries) {
    if (!entry.directory && seen.has(entry.path)) repeated.add(entry.path);
    seen.add(entry.path);
  }
  for (const entry of entries) {
    const skip = (reason: string) => skipped.push({ archive, path: entry.path, reason });
    if (service.test(entry.path)) {
      if (!entry.directory) skip('Служебный файл.');
      continue;
    }
    if (root && !entry.path.startsWith(root + '/')) {
      if (!entry.directory) skip('Вне выбранной папки.');
      continue;
    }
    if (entry.directory && root && entry.path === root + '/') continue;
    const path = (root ? entry.path.slice(root.length + 1) : entry.path).replace(/\/$/, '');
    const parts = path.split('/');
    if (parts.some((part) => !part.trim() || part === '..' || part === '.' || /\p{Cc}/u.test(part)) || /^[a-z]:/i.test(path)) {
      skip('Недопустимый путь.');
      continue;
    }
    const [name, series, filename] = parts;
    if (!name || [...name.trim()].length > 255) {
      skip('Недопустимое название группы.');
      continue;
    }
    if (!entry.directory && (parts.length !== 3 || !series || !filename)) {
      skip('Нужен путь «группа / серия / фотография».');
      continue;
    }
    let group = groups.get(name);
    if (!group) {
      group = { name, series: new Map(), skipped: 0 };
      groups.set(name, group);
    }
    if (entry.directory) continue;
    const problem = repeated.has(entry.path)
      ? 'Путь повторяется в ZIP.'
      : entry.encrypted
        ? 'Файл защищён паролем.'
        : ![0, 8].includes(entry.method)
          ? 'Неподдерживаемое сжатие.'
          : !photo.test(filename!)
            ? 'Допустимы JPEG, PNG и WebP.'
            : fileProblem({
                name: filename!,
                size: entry.bytes,
                type: mimeType(filename!)
              });
    if (problem) {
      skip(problem);
      continue;
    }
    const files = group.series.get(series!) ?? [];
    files.push({
      archive,
      path: entry.path,
      name: filename!,
      bytes: entry.bytes
    });
    group.series.set(series!, files);
  }
  for (const group of groups.values()) {
    if (!group.series.size)
      skipped.push({
        archive,
        path: group.name,
        reason: 'В группе нет подходящих фотографий.'
      });
    for (const files of group.series.values()) files.sort((a, b) => numericOrder.compare(a.path, b.path));
  }
  for (const item of skipped) {
    if (root && !item.path.startsWith(root + '/')) continue;
    const relative = root ? item.path.slice(root.length + 1) : item.path;
    const group = groups.get(relative.split('/', 1)[0]!);
    if (group) group.skipped++;
  }
  return {
    groups: [...groups.values()].filter((group) => group.series.size),
    skipped
  };
}

export function planClass(group: ArchiveClass, overrides: ReadonlyMap<string, string>): ArchivePlan {
  const names = [...group.series.keys()].sort(numericOrder.compare);
  const reserved = new Set<string>();
  for (const name of names) {
    const candidate = overrides.get(name) ?? name.trim().toUpperCase();
    if (validChildCode(candidate)) reserved.add(candidate);
  }
  const folders: ArchiveFolder[] = [];
  const assigned = new Set<string>();
  const problems: string[] = [];
  let next = 0;
  function nextCode(): string {
    let code = childCodeAt(next++);
    while (reserved.has(code)) code = childCodeAt(next++);
    return validChildCode(code) ? code : '';
  }
  let count = 0;
  let bytes = 0;
  for (const name of names) {
    const shared = /^group/i.test(name);
    const upper = name.trim().toUpperCase();
    const code = shared ? null : (overrides.get(name) ?? (validChildCode(upper) ? upper : nextCode()));
    let problem = '';
    if (code !== null) {
      if (!validChildCode(code) || assigned.has(code)) problem = 'У каждой серии должен быть свой код из 1–3 латинских букв.';
      reserved.add(code);
      assigned.add(code);
    }
    if (problem) problems.push(problem);
    const files = group.series.get(name)!;
    count += files.length;
    for (const file of files) bytes += file.bytes;
    folders.push({
      folder: name,
      kind: shared ? 'group' : 'child',
      code,
      files,
      existing: false,
      problem
    });
  }
  folders.sort((a, b) => Number(a.kind === 'group') - Number(b.kind === 'group') || compareCodes(a.code ?? a.folder, b.code ?? b.folder));
  const groupCodes = [...assigned].sort(compareCodes);
  if (!groupCodes.length) problems.push('Групповому кадру нужна хотя бы одна серия ребёнка.');
  return { folders, groupCodes, files: count, bytes, skipped: [], problems };
}

/** Лимит очереди применяется к порции, порядок файлов и полный состав получателей сохраняются между порциями. */
export function* archivePortions(plan: ArchivePlan, limit = photoLimits.batch): Generator<ArchivePlan> {
  let folders: ArchiveFolder[] = [];
  let files = 0;
  let bytes = 0;
  for (const folder of plan.folders) {
    for (let offset = 0; offset < folder.files.length; ) {
      const part = folder.files.slice(offset, offset + limit - files);
      folders.push({ ...folder, files: part });
      files += part.length;
      for (const file of part) bytes += file.bytes;
      offset += part.length;
      if (files === limit) {
        yield { ...plan, folders, files, bytes };
        folders = [];
        files = 0;
        bytes = 0;
      }
    }
  }
  if (files) yield { ...plan, folders, files, bytes };
}

/** Контрольная сумма всего ZIP без чтения гигабайт в один ArrayBuffer. */
export async function archiveIdentity(file: Blob, signal: AbortSignal): Promise<string> {
  const chunks: ArrayBuffer[] = [];
  const size = 4 * 1024 * 1024;
  for (let start = 0; start < file.size; start += size) {
    signal.throwIfAborted();
    chunks.push(await crypto.subtle.digest('SHA-256', await file.slice(start, start + size).arrayBuffer()));
  }
  const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', await new Blob(chunks).arrayBuffer()));
  return Array.from(digest, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

export interface ArchiveClassChoice {
  name: string;
  codes: [string, string][];
}

/** Один и тот же выбор восстанавливает созданные этим импортом группы даже после потери ответа. */
export async function archiveImportKey(shootId: string, archive: string, root: string, choices: ArchiveClassChoice[]): Promise<string> {
  const manifest = new TextEncoder().encode(JSON.stringify(['institution-zip-v1', shootId, archive, root, choices]));
  const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', manifest));
  return Array.from(digest.subarray(0, 16), (byte) => byte.toString(16).padStart(2, '0')).join('');
}
