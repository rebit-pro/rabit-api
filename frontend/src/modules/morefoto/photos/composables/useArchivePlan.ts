import { computed, shallowRef } from 'vue';
import { archiveKey, planArchives, type ArchiveSource } from '../archive';
import { photoLimits } from '../rules';
import { isVolumePart, readZipEntries } from '../zip-reader';

export interface ChosenArchive {
  file: File;
  source: ArchiveSource;
}

/**
 * Reads only the directories of the chosen archives and keeps the plan the photographer checks before the start.
 * Archives chosen in several steps are merged; the same archive chosen twice is read once.
 */
export function useArchivePlan(existingCodes: () => readonly string[]) {
  const chosen = shallowRef<ChosenArchive[]>([]);
  const marks = shallowRef<ReadonlySet<string>>(new Set());
  const reading = shallowRef(false);
  const error = shallowRef('');
  const plan = computed(() =>
    chosen.value.length
      ? planArchives(
          chosen.value.map((item) => item.source),
          existingCodes(),
          marks.value,
          photoLimits
        )
      : null
  );

  async function read(files: File[]) {
    reading.value = true;
    error.value = '';
    const known = new Set(chosen.value.map((item) => item.source.key));
    const next = [...chosen.value];
    const problems: string[] = [];
    try {
      for (const file of files) {
        const key = archiveKey(file);
        if (known.has(key)) continue;
        known.add(key);
        if (isVolumePart(file.name)) {
          next.push({ file, source: { key, name: file.name, entries: [] } });
          continue;
        }
        if (!/\.zip$/i.test(file.name)) {
          problems.push('«' + file.name + '» — не ZIP-архив.');
          continue;
        }
        try {
          next.push({ file, source: { key, name: file.name, entries: await readZipEntries(file) } });
        } catch (cause) {
          problems.push('«' + file.name + '»: ' + (cause instanceof Error ? cause.message : 'архив не прочитан.'));
        }
      }
    } finally {
      chosen.value = next;
      error.value = problems.join(' ');
      reading.value = false;
    }
  }

  function toggleGroup(folder: string) {
    const next = new Set(marks.value);
    if (!next.delete(folder)) next.add(folder);
    marks.value = next;
  }

  function reset() {
    chosen.value = [];
    marks.value = new Set();
    error.value = '';
  }

  return { chosen, marks, reading, error, plan, read, toggleGroup, reset };
}
