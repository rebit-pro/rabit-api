import { isAxiosError } from 'axios';
import { onScopeDispose, shallowRef } from 'vue';
import { archiveErrorText, archiveHref } from './archive-rules';
import { institutionArchiveApi } from './institution-archive-api';
import { institutionArchiveError } from './institution-archive-rules';
import type { InstitutionArchiveVersion, InstitutionPrintArchive } from './institution-archive-types';

function problem(cause: unknown): string {
  if (!isAxiosError(cause)) return 'Не удалось выполнить действие. Обновите состояние архива.';
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code;
  return (
    institutionArchiveError(code) ??
    archiveErrorText({
      network: !cause.response,
      status: cause.response?.status,
      code
    })
  );
}

/** State belongs to one dialog session; stale loads and commands never replace another institution's answer. */
export function useInstitutionArchive() {
  const archive = shallowRef<InstitutionPrintArchive | null>(null);
  const loading = shallowRef(false);
  const busy = shallowRef('');
  const error = shallowRef('');
  const notice = shallowRef('');
  const keys = new Map<string, string>();
  let institutionId = '';
  let session = 0;
  let request = 0;
  let timer: ReturnType<typeof setTimeout> | undefined;
  function stop(): void {
    if (timer) clearTimeout(timer);
    timer = undefined;
  }
  async function load(): Promise<void> {
    if (!institutionId) return;
    const epoch = session;
    const sequence = ++request;
    const id = institutionId;
    stop();
    loading.value = true;
    try {
      const output = await institutionArchiveApi.get(id);
      if (epoch !== session || sequence !== request) return;
      archive.value = output;
      error.value = '';
      // Poll the next automatic release as well as an active build; a new shoot must not hide an earlier ready ZIP.
      if (output.state === 'open' || output.state === 'scheduled' || output.versions.some((version) => version.status === 'pending')) {
        timer = setTimeout(() => void load(), 4000);
      }
    } catch (cause) {
      if (epoch === session && sequence === request) error.value = problem(cause);
    } finally {
      if (epoch === session && sequence === request) loading.value = false;
    }
  }
  function close(): void {
    ++session;
    institutionId = '';
    stop();
    busy.value = '';
  }
  function open(id: string): void {
    close();
    institutionId = id;
    archive.value = null;
    error.value = '';
    notice.value = '';
    void load();
  }
  async function run(action: string, operation: () => Promise<unknown>, message: string): Promise<void> {
    if (busy.value) return;
    const epoch = session;
    busy.value = action;
    notice.value = '';
    error.value = '';
    try {
      await operation();
      if (epoch === session) notice.value = message;
    } catch (cause) {
      if (epoch === session) error.value = problem(cause);
    } finally {
      if (epoch === session) {
        busy.value = '';
        // Preserve command failures when the following state read succeeds.
        const commandError = error.value;
        await load();
        if (epoch === session && commandError) error.value = commandError;
      }
    }
  }
  async function command(id: string, send: (key: string) => Promise<unknown>): Promise<void> {
    const key = keys.get(id) ?? crypto.randomUUID().replace(/-/g, '');
    keys.set(id, key);
    await send(key);
    keys.delete(id);
  }
  function supplement(): Promise<void> {
    const id = institutionId;
    return run(
      'supplement',
      () => command('supplement:' + id, (key) => institutionArchiveApi.supplement(id, key)),
      'Дополнение поставлено на сборку. Оно содержит только новые позиции.'
    );
  }
  function renew(version: InstitutionArchiveVersion): Promise<void> {
    return run(
      'renew:' + version.id,
      () => command('renew:' + version.id, (key) => institutionArchiveApi.renew(version.id, key)),
      version.status === 'ready' ? 'Прежние ссылки скачивания отозваны.' : 'Сохранённый состав поставлен на повторную сборку.'
    );
  }
  function download(version: InstitutionArchiveVersion): Promise<void> {
    return run(
      'download:' + version.id,
      async () => {
        const link = await institutionArchiveApi.download(version.id);
        const anchor = document.createElement('a');
        anchor.href = archiveHref(link.url, import.meta.env.VITE_API_URL, window.location.origin);
        anchor.download = '';
        anchor.click();
      },
      'Файл передан браузеру для скачивания.'
    );
  }
  function manifest(version: InstitutionArchiveVersion): Promise<void> {
    return run(
      'csv:' + version.id,
      async () => {
        const url = URL.createObjectURL(await institutionArchiveApi.manifest(version.id));
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'institution-print-v' + version.number + '.csv';
        anchor.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      },
      'Состав CSV сохранён.'
    );
  }
  onScopeDispose(close);
  return {
    archive,
    loading,
    busy,
    error,
    notice,
    open,
    close,
    load,
    supplement,
    renew,
    download,
    manifest
  };
}
