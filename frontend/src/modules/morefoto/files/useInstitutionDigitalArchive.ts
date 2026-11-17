import { isAxiosError } from 'axios';
import { onScopeDispose, shallowRef } from 'vue';
import { archiveHref } from '../production/live/archive-rules';
import { institutionDigitalApi } from './institution-digital-api';
import { digitalArchiveError, digitalArchiveRefreshDelay } from './institution-digital-rules';
import type { InstitutionDigitalArchive, InstitutionDigitalArchives } from './institution-digital-types';

function problem(cause: unknown): string {
  if (isAxiosError(cause) && !cause.response) return 'Не удалось связаться с сервером. Проверьте соединение и повторите действие.';
  return digitalArchiveError(
    isAxiosError(cause) ? (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code : undefined
  );
}

/** One dialog session owns requests, polling and browser downloads; closing invalidates every pending effect. */
export function useInstitutionDigitalArchive() {
  const archive = shallowRef<InstitutionDigitalArchives | null>(null);
  const loading = shallowRef(false);
  const busy = shallowRef('');
  const error = shallowRef('');
  const notice = shallowRef('');
  const keys = new Map<string, string>();
  let institutionId = '';
  let session = 0;
  let sequence = 0;
  let timer: ReturnType<typeof setTimeout> | undefined;
  let read: AbortController | undefined;
  let command: AbortController | undefined;

  function stop(): void {
    if (timer) clearTimeout(timer);
    timer = undefined;
  }
  function close(): void {
    ++session;
    institutionId = '';
    stop();
    read?.abort();
    command?.abort();
    loading.value = false;
    busy.value = '';
  }
  async function load(): Promise<void> {
    if (!institutionId) return;
    const epoch = session;
    const request = ++sequence;
    stop();
    read?.abort();
    const controller = new AbortController();
    read = controller;
    loading.value = true;
    try {
      const output = await institutionDigitalApi.list(institutionId, controller.signal);
      if (epoch !== session || request !== sequence) return;
      archive.value = output;
      error.value = '';
      const delay = digitalArchiveRefreshDelay(output.items, Date.now());
      if (delay !== null) timer = setTimeout(() => void load(), delay);
    } catch (cause) {
      if (epoch === session && request === sequence && !controller.signal.aborted) error.value = problem(cause);
    } finally {
      if (epoch === session && request === sequence) loading.value = false;
    }
  }
  function open(id: string): void {
    close();
    institutionId = id;
    archive.value = null;
    error.value = '';
    notice.value = '';
    void load();
  }
  async function run(action: string, operation: (current: () => boolean, signal: AbortSignal) => Promise<void>, message: string) {
    if (busy.value || !institutionId) return;
    const epoch = session;
    const controller = new AbortController();
    command = controller;
    stop();
    busy.value = action;
    error.value = '';
    notice.value = '';
    const current = () => epoch === session && !controller.signal.aborted;
    try {
      await operation(current, controller.signal);
      if (current()) notice.value = message;
    } catch (cause) {
      if (current()) error.value = problem(cause);
    } finally {
      if (current()) {
        busy.value = '';
        const commandError = error.value;
        await load();
        if (epoch === session && commandError) error.value = commandError;
      }
    }
  }
  function create(): Promise<void> {
    const id = institutionId;
    return run(
      'create',
      async (current, signal) => {
        const key = keys.get(id) ?? crypto.randomUUID().replace(/-/g, '');
        keys.set(id, key);
        await institutionDigitalApi.create(id, key, signal);
        if (current()) keys.delete(id);
      },
      'Состав выгрузки сохранён. Если в нём есть файлы, начнётся фоновая сборка ZIP.'
    );
  }
  function download(version: InstitutionDigitalArchive): Promise<void> {
    return run(
      'download:' + version.id,
      async (current, signal) => {
        const link = await institutionDigitalApi.download(version.id, signal);
        if (!current()) return;
        const anchor = document.createElement('a');
        anchor.href = archiveHref(link.url, import.meta.env.VITE_API_URL, window.location.origin);
        anchor.download = '';
        anchor.click();
      },
      'Архив передан браузеру для скачивания.'
    );
  }
  function manifest(version: InstitutionDigitalArchive): Promise<void> {
    return run(
      'manifest:' + version.id,
      async (current, signal) => {
        const blob = await institutionDigitalApi.manifest(version.id, signal);
        if (!current()) return;
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'institution-digital-' + version.id + '.csv';
        anchor.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      },
      'Манифест сохранён.'
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
    create,
    download,
    manifest
  };
}
