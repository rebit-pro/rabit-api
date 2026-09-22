import { computed, onScopeDispose, shallowRef } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { isAxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { fileProblem, photoLimits } from '../rules';
import { preparePhoto } from '../prepare';
import { acceptPhoto, editableGroup } from '../service';
import { photoApiError, photosApi } from '../api';
import { photosChangedEvent } from '../repository';
import type { UploadJob } from '../types';

const duplicateMessage = 'Такой файл уже есть в этой съёмке. Второй кадр не создан.';
const acceptedMessage = 'Файл принят. Сервер готовит защищённые превью';
// Accepted photos share one status budget per queue instead of polling each of a thousand files.
const checksPerTick = 2;
const checkDelay = (checks: number): number => [2000, 5000][checks - 1] ?? 10000;
const slowAfter = 10 * 60 * 1000;
const refreshEvery = 5000;

export function usePhotoQueue(shootId: string) {
  const auth = useAuthStore();
  const key = 'morefoto:' + (isMockApiEnabled ? 'demo' : 'live') + ':uploads:' + auth.user?.id + ':' + shootId;
  let initial: UploadJob[] = [];
  try {
    initial = JSON.parse(sessionStorage.getItem(key) ?? '[]') as UploadJob[];
    if (!Array.isArray(initial)) initial = [];
  } catch {
    /* A new queue replaces an unreadable draft. */
  }
  const jobs = shallowRef<UploadJob[]>(
    initial.map((job) =>
      (isMockApiEnabled && ['queued', 'uploading', 'processing'].includes(job.status)) ||
      (!isMockApiEnabled && ['queued', 'uploading'].includes(job.status)) ||
      (!isMockApiEnabled && job.status === 'processing' && !job.serverId)
        ? {
            ...job,
            status: 'interrupted',
            progress: 0,
            message: 'Отправка прервана. Выберите исходный файл снова.'
          }
        : job
    )
  );
  const files = new Map<string, File>();
  const busy = shallowRef(false);
  const paused = shallowRef(false);
  const error = shallowRef('');
  const controller = new AbortController();
  let alive = true;
  let persistTimer = 0;
  let trackTimer = 0;
  let refreshTimer = 0;
  let refreshedAt = 0;
  const queued = computed(() => jobs.value.filter((job) => job.status === 'queued').length);
  const accepted = computed(() => jobs.value.filter((job) => job.status === 'done').length);
  const waiting = computed(() => jobs.value.filter((job) => job.status === 'uploading' || job.status === 'processing').length);
  const failed = computed(() => jobs.value.filter((job) => ['error', 'interrupted'].includes(job.status)).length);
  function write() {
    try {
      sessionStorage.setItem(key, JSON.stringify(jobs.value));
    } catch {
      /* The queue keeps working in memory when the session storage is full. */
    }
  }
  function persist(now: boolean) {
    if (now) {
      window.clearTimeout(persistTimer);
      persistTimer = 0;
      write();
    } else if (!persistTimer) {
      persistTimer = window.setTimeout(() => {
        persistTimer = 0;
        write();
      }, 1000);
    }
  }
  function update(id: string, value: Partial<UploadJob>) {
    if (alive) {
      jobs.value = jobs.value.map((job) => (job.id === id ? { ...job, ...value } : job));
      persist('status' in value);
    }
  }
  function refreshWorkspace() {
    const wait = refreshedAt + refreshEvery - Date.now();
    if (wait <= 0) {
      refreshedAt = Date.now();
      window.dispatchEvent(new Event(photosChangedEvent));
    } else if (!refreshTimer) {
      refreshTimer = window.setTimeout(() => {
        refreshTimer = 0;
        refreshWorkspace();
      }, wait);
    }
  }
  function add(list: File[], groupId: string) {
    error.value = '';
    if (isMockApiEnabled) {
      try {
        editableGroup(auth.getAccessToken() ?? '', shootId, groupId);
      } catch (cause) {
        error.value = (cause as Error).message;
        return;
      }
    }
    if (list.length > photoLimits.batch) {
      error.value = 'Выберите не больше ' + photoLimits.batch + ' файлов за раз.';
      return;
    }
    const identity = (name: string, bytes: number, modified: number) => JSON.stringify([name, bytes, modified]);
    const interrupted = new Map(
      jobs.value
        .filter((job) => job.groupId === groupId && job.status === 'interrupted')
        .map((job) => [identity(job.filename, job.bytes, job.modified), job])
    );
    const replaced = new Map<string, UploadJob>();
    const added: UploadJob[] = [];
    for (const file of list) {
      const previous = interrupted.get(identity(file.name, file.size, file.lastModified));
      const problem = fileProblem(file);
      const job: UploadJob = {
        id: previous?.id ?? crypto.randomUUID(),
        shootId,
        groupId,
        filename: file.name,
        bytes: file.size,
        modified: file.lastModified,
        progress: 0,
        status: problem ? 'error' : 'queued',
        message: problem || (isMockApiEnabled ? 'Готов к подготовке' : 'Готов к отправке')
      };
      files.set(job.id, file);
      if (previous) replaced.set(job.id, job);
      else added.push(job);
    }
    jobs.value = [...jobs.value.map((job) => replaced.get(job.id) ?? job), ...added];
    persist(true);
  }
  async function check(job: UploadJob) {
    const checks = (job.checks ?? 0) + 1;
    const later = { checks, checkAt: Date.now() + checkDelay(checks) };
    try {
      const photo = await photosApi.detail(job.serverId!);
      if (photo.status === 'ready') {
        update(job.id, { status: 'done', progress: 100, message: 'Защищённые превью готовы' });
        refreshWorkspace();
      } else if (photo.status === 'failed') {
        update(job.id, {
          status: 'error',
          progress: 100,
          message: 'Сервер не смог подготовить превью. Повторно выберите исходный файл.'
        });
      } else if (photo.status === 'duplicate') {
        update(job.id, { status: 'duplicate', progress: 100, message: duplicateMessage });
      } else {
        const slow = Date.now() - (job.acceptedAt ?? Date.now()) > slowAfter;
        update(job.id, { ...later, message: slow ? 'Обработка задерживается. Статус проверяется автоматически' : acceptedMessage });
      }
    } catch (cause) {
      if (!alive || controller.signal.aborted) return;
      if (isAxiosError(cause) && [403, 404].includes(cause.response?.status ?? 0))
        update(job.id, { status: 'error', message: photoApiError(cause) });
      else update(job.id, { ...later, message: 'Статус пока не получен. Проверка повторится автоматически' });
    }
  }
  function track() {
    if (!isMockApiEnabled && alive && !trackTimer) trackTimer = window.setTimeout(tick, 1000);
  }
  async function tick() {
    trackTimer = 0;
    if (!alive || controller.signal.aborted) return;
    const now = Date.now();
    const due = jobs.value
      .filter((job) => job.status === 'processing' && job.serverId && (job.checkAt ?? 0) <= now)
      .sort((left, right) => (left.checkAt ?? 0) - (right.checkAt ?? 0))
      .slice(0, checksPerTick);
    await Promise.all(due.map(check));
    if (jobs.value.some((job) => job.status === 'processing' && job.serverId)) track();
  }
  function uploadProgress(id: string, progress: number) {
    const job = jobs.value.find((item) => item.id === id);
    if (job && (100 <= progress || 5 <= progress - job.progress)) update(id, { progress });
  }
  async function uploadLive(job: UploadJob, file: File) {
    update(job.id, { status: 'uploading', progress: 0, message: 'Отправляем приватный оригинал' });
    const result = await photosApi.upload(shootId, job.groupId, file, controller.signal, (progress) => uploadProgress(job.id, progress));
    files.delete(job.id);
    if (result.status === 'duplicate') {
      update(job.id, { serverId: result.id, status: 'duplicate', progress: 100, message: duplicateMessage });
      return;
    }
    const now = Date.now();
    update(job.id, {
      serverId: result.id,
      status: 'processing',
      progress: 100,
      message: acceptedMessage,
      acceptedAt: now,
      checkAt: now + checkDelay(1),
      checks: 0
    });
    track();
  }
  async function prepareMock(job: UploadJob, file: File) {
    update(job.id, { status: 'processing', progress: 5, message: 'Читаем и проверяем файл' });
    const token = auth.getAccessToken() ?? '';
    editableGroup(token, shootId, job.groupId);
    const prepared = await preparePhoto(file, controller.signal, (progress) =>
      update(job.id, { progress, message: progress < 50 ? 'Проверяем изображение' : 'Готовим защищённые превью' })
    );
    update(job.id, { progress: 90, message: 'Сохраняем в браузере' });
    const result = await acceptPhoto(token, job, prepared, controller.signal);
    update(job.id, {
      status: result,
      progress: 100,
      message: result === 'done' ? 'Файл подготовлен' : duplicateMessage
    });
    files.delete(job.id);
  }
  async function worker() {
    while (alive && !controller.signal.aborted && !paused.value) {
      const job = jobs.value.find((item) => item.status === 'queued');
      if (!job) return;
      const file = files.get(job.id);
      if (!file) {
        update(job.id, { status: 'interrupted', message: 'Выберите исходный файл снова.' });
        continue;
      }
      try {
        if (isMockApiEnabled) await prepareMock(job, file);
        else await uploadLive(job, file);
      } catch (cause) {
        if (!controller.signal.aborted) update(job.id, { status: 'error', progress: 0, message: photoApiError(cause) });
      }
    }
  }
  async function start() {
    if (busy.value) return;
    busy.value = true;
    paused.value = false;
    error.value = '';
    try {
      // Browser-side demo preparation is CPU bound, so it stays sequential.
      await Promise.all(Array.from({ length: isMockApiEnabled ? 1 : photoLimits.parallel }, worker));
    } finally {
      if (alive) {
        busy.value = false;
        persist(true);
      }
    }
  }
  function pause() {
    if (busy.value) paused.value = true;
  }
  function retry(id: string) {
    if (busy.value) return;
    if (!files.has(id)) {
      error.value = 'Выберите исходный файл снова: он не сохраняется после обновления страницы.';
      return;
    }
    update(id, { status: 'queued', progress: 0, message: 'Готов к повтору', serverId: undefined });
    void start();
  }
  function clear() {
    if (!busy.value) {
      jobs.value = jobs.value.filter((job) => !['done', 'duplicate'].includes(job.status));
      persist(true);
    }
  }
  function remove(id: string) {
    if (!busy.value) {
      jobs.value = jobs.value.filter((job) => job.id !== id);
      files.delete(id);
      persist(true);
    }
  }
  function beforeUnload(event: BeforeUnloadEvent) {
    if (busy.value) {
      event.preventDefault();
      event.returnValue = '';
    }
  }
  window.addEventListener('beforeunload', beforeUnload);
  onBeforeRouteLeave(
    () =>
      !busy.value ||
      window.confirm('Подготовка файлов ещё идёт. При уходе она остановится; оставшиеся файлы потребуется выбрать снова. Уйти?')
  );
  onScopeDispose(() => {
    alive = false;
    controller.abort();
    files.clear();
    window.clearTimeout(trackTimer);
    window.clearTimeout(refreshTimer);
    window.clearTimeout(persistTimer);
    write();
    window.removeEventListener('beforeunload', beforeUnload);
  });
  if (jobs.value.some((job) => job.status === 'processing' && job.serverId)) track();
  return { jobs, busy, paused, error, queued, accepted, waiting, failed, add, start, pause, retry, clear, remove };
}
