import { isAxiosError } from 'axios';
import { onScopeDispose, shallowRef } from 'vue';
import { archiveApi } from './archive-api';
import { archiveErrorText, archiveHref, isBuilding } from './archive-rules';
import type { ArchiveVersion, GroupArchive } from './archive-types';

const POLL_MS = 4000;

function problem(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : archiveErrorText({ network: true });
  return archiveErrorText({
    network: !cause.response,
    status: cause.response?.status,
    code: (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code
  });
}

/**
 * L1: the «Печать» block of one group. A version being built is reloaded every few seconds while the dialog is open;
 * every command reloads the block, so a lost answer never leaves a stale state on screen.
 */
export function useGroupArchive() {
  const archive = shallowRef<GroupArchive | null>(null);
  const loading = shallowRef(false);
  const busy = shallowRef('');
  const error = shallowRef('');
  const notice = shallowRef('');
  /** The link the clipboard refused: shown in a field to copy by hand. */
  const manualLink = shallowRef('');
  let groupId = '';
  let timer: ReturnType<typeof setTimeout> | undefined;

  function stop(): void {
    if (timer) clearTimeout(timer);
    timer = undefined;
  }
  async function load(): Promise<void> {
    if (!groupId) return;
    const requested = groupId;
    stop();
    loading.value = true;
    try {
      const next = await archiveApi.group(requested);
      if (requested !== groupId) return;
      archive.value = next;
      error.value = '';
      if (isBuilding(next)) timer = setTimeout(() => void load(), POLL_MS);
    } catch (cause) {
      if (requested === groupId) error.value = problem(cause);
    } finally {
      if (requested === groupId) loading.value = false;
    }
  }
  function open(id: string): void {
    groupId = id;
    archive.value = null;
    error.value = '';
    notice.value = '';
    manualLink.value = '';
    void load();
  }
  function close(): void {
    groupId = '';
    stop();
  }
  async function run(action: string, command: () => Promise<unknown>, done: string): Promise<void> {
    if (busy.value) return;
    busy.value = action;
    error.value = '';
    notice.value = '';
    manualLink.value = '';
    try {
      await command();
      notice.value = done;
    } catch (cause) {
      error.value = problem(cause);
    } finally {
      busy.value = '';
      await load();
    }
  }
  const build = (kind: 'archive' | 'supplement') =>
    run(kind, () => archiveApi.build(groupId), kind === 'archive' ? 'Архив собирается — это займёт до минуты.' : 'Дополнение собирается.');
  const renew = (version: ArchiveVersion) =>
    run(
      'renew:' + version.id,
      () => archiveApi.renew(version.id),
      version.status === 'ready' ? 'Новая ссылка готова. Прежняя ссылка больше не открывается.' : 'Архив собирается заново.'
    );
  function href(version: ArchiveVersion): string {
    return version.archiveUrl ? archiveHref(version.archiveUrl, import.meta.env.VITE_API_URL, window.location.origin) : '';
  }
  async function copy(version: ArchiveVersion): Promise<void> {
    const link = href(version);
    notice.value = '';
    manualLink.value = '';
    try {
      await navigator.clipboard.writeText(link);
      notice.value = 'Ссылка на архив скопирована. Она открывается без входа до конца срока хранения.';
    } catch {
      manualLink.value = link;
    }
  }
  async function manifest(version: ArchiveVersion): Promise<void> {
    await run(
      'csv:' + version.id,
      async () => {
        const url = URL.createObjectURL(await archiveApi.manifest(version.exportUrl));
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'print-' + version.groupId.slice(0, 8) + '-v' + version.number + '.csv';
        anchor.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      },
      'Состав CSV сохранён.'
    );
  }
  onScopeDispose(close);
  return { archive, loading, busy, error, notice, manualLink, open, close, load, build, renew, href, copy, manifest };
}
