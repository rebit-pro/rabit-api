import { computed, onScopeDispose, shallowRef } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { isAxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { fileProblem, photoLimits } from '../rules';
import { preparePhoto } from '../prepare';
import { acceptPhoto, editableGroup } from '../service';
import { photoApiError, photoApiErrorCode, photosApi } from '../api';
import { photosChangedEvent } from '../repository';
import { folderCodes, mimeType, type ArchivePlan, type ArchiveSource } from '../archive';
import { ArchiveProgress, browserStorage } from '../archive-progress';
import { maxRetries, retryDelay, uploadFailure } from '../upload-retry';
import { zipEntryBlob, type ZipEntry } from '../zip-reader';
import type { UploadJob } from '../types';

const duplicateMessage = 'Такой файл уже есть в этой съёмке. Второй кадр не создан.';
const archiveInterrupted = 'Отправка прервана. Выберите те же архивы снова — отправленные файлы повторно не уйдут.';
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
            message: job.archive ? archiveInterrupted : 'Отправка прервана. Выберите исходный файл снова.'
          }
        : job
    )
  );
  const files = new Map<string, File>();
  const archives = new Map<string, { file: File; entries: Map<string, ZipEntry> }>();
  const progress = new ArchiveProgress(browserStorage(), 'morefoto:archive-progress:' + auth.user?.id + ':' + shootId);
  /** Archive files accepted before a reload: they are skipped, not sent again. */
  const previous = shallowRef(0);
  const busy = shallowRef(false);
  const paused = shallowRef(false);
  const error = shallowRef('');
  const controller = new AbortController();
  let alive = true;
  let persistTimer = 0;
  let trackTimer = 0;
  let refreshTimer = 0;
  let refreshedAt = 0;
  let offline = false;
  let wakeUps: (() => void)[] = [];
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
  /** Queues the files of the checked plan: children first, group frames last, so they close every child's set. */
  function addArchive(plan: ArchivePlan, chosen: { file: File; source: ArchiveSource }[], groupId: string) {
    error.value = '';
    for (const item of chosen)
      archives.set(item.source.key, { file: item.file, entries: new Map(item.source.entries.map((entry) => [entry.path, entry])) });
    const keys = new Set(chosen.map((item) => item.source.key));
    const kept = jobs.value.filter(
      (job) =>
        !(job.groupId === groupId && job.archive && keys.has(job.archive) && !['processing', 'done', 'duplicate'].includes(job.status))
    );
    const listed = new Set(kept.filter((job) => job.groupId === groupId && job.archive).map((job) => job.archive + '\n' + job.entry));
    const added: UploadJob[] = [];
    let skipped = 0;
    for (const folder of plan.folders) {
      const childCodes = folderCodes(folder, plan);
      for (const file of folder.files) {
        if (listed.has(file.archive + '\n' + file.path)) continue;
        if (progress.has(groupId + ' ' + file.archive, file.path)) {
          skipped++;
          continue;
        }
        added.push({
          id: crypto.randomUUID(),
          shootId,
          groupId,
          filename: file.name,
          bytes: file.bytes,
          modified: 0,
          progress: 0,
          status: 'queued',
          message: 'Готов к отправке',
          childCodes,
          archive: file.archive,
          entry: file.path,
          folder: folder.code ?? folder.folder,
          shared: folder.kind === 'group'
        });
      }
    }
    previous.value += skipped;
    jobs.value = [...kept, ...added];
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
    const result = await photosApi.upload(
      shootId,
      job.groupId,
      file,
      controller.signal,
      (value) => uploadProgress(job.id, value),
      job.childCodes
    );
    files.delete(job.id);
    if (job.archive && job.entry) progress.mark(job.groupId + ' ' + job.archive, job.entry);
    if (result.status === 'duplicate') {
      const message = !job.childCodes?.length
        ? duplicateMessage
        : result.childCodes?.length
          ? 'Файл уже был загружен. Разметка проверена, второй кадр не создан.'
          : 'Такой файл уже есть в другой группе съёмки. Буква не присвоена.';
      update(job.id, { serverId: result.id, status: 'duplicate', progress: 100, message });
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
  function sleep(milliseconds: number): Promise<void> {
    return new Promise((resolve) => {
      const timer = window.setTimeout(resolve, milliseconds);
      wakeUps.push(() => {
        window.clearTimeout(timer);
        resolve();
      });
    });
  }
  function wake() {
    const pending = wakeUps;
    wakeUps = [];
    pending.forEach((resolve) => resolve());
  }
  /** An archive entry is cut out of the chosen archive only now, so memory holds no more files than workers. */
  async function source(job: UploadJob, key: string): Promise<File | undefined> {
    const archive = archives.get(key);
    const entry = archive?.entries.get(job.entry ?? '');
    if (!archive || !entry) return undefined;
    update(job.id, { status: 'uploading', progress: 0, message: 'Достаём файл из архива' });
    return new File([await zipEntryBlob(archive.file, entry)], job.filename, { type: mimeType(job.filename) });
  }
  /** Transient failures repeat on their own; the queue stops only when every next file would fail the same way. */
  function fail(job: UploadJob, cause: unknown) {
    const kind = isAxiosError(cause) ? uploadFailure(cause.response?.status, photoApiErrorCode(cause), navigator.onLine) : 'fail';
    const attempts = (job.attempts ?? 0) + 1;
    const delay = kind === 'retry' ? retryDelay(attempts) : null;
    if (delay !== null) {
      update(job.id, {
        status: 'queued',
        progress: 0,
        attempts,
        retryAt: Date.now() + delay,
        message:
          'Сервер не ответил. Повторим через ' +
          Math.round(delay / 1000) +
          ' с, попытка ' +
          (attempts + 1) +
          ' из ' +
          (maxRetries + 1) +
          '.'
      });
    } else if (kind === 'offline') {
      offline = true;
      paused.value = true;
      update(job.id, { status: 'queued', progress: 0, message: 'Нет подключения к интернету. Продолжим сами, когда связь появится.' });
    } else if (kind === 'session') {
      paused.value = true;
      error.value = 'Сессия завершилась. Войдите снова и выберите те же файлы: отправленные повторно не уйдут.';
      update(job.id, { status: 'queued', progress: 0, message: 'Ждёт входа в кабинет.' });
    } else {
      if (kind === 'locked') {
        paused.value = true;
        error.value = 'Группа уже передана. Загрузка в неё остановлена.';
      }
      update(job.id, { status: 'error', progress: 0, message: photoApiError(cause) });
    }
  }
  async function worker() {
    while (alive && !controller.signal.aborted && !paused.value) {
      const waitingJobs = jobs.value.filter((item) => item.status === 'queued');
      if (!waitingJobs.length) return;
      const now = Date.now();
      const job = waitingJobs.find((item) => (item.retryAt ?? 0) <= now);
      if (!job) {
        await sleep(Math.min(...waitingJobs.map((item) => item.retryAt ?? now)) - now);
        continue;
      }
      try {
        // The status changes before the first await, so a parallel worker never takes the same job.
        const file = job.archive ? await source(job, job.archive) : files.get(job.id);
        if (!file) {
          update(job.id, { status: 'interrupted', message: job.archive ? archiveInterrupted : 'Выберите исходный файл снова.' });
          continue;
        }
        if (isMockApiEnabled) await prepareMock(job, file);
        else await uploadLive(job, file);
      } catch (cause) {
        if (!controller.signal.aborted) fail(job, cause);
      }
    }
  }
  async function keepAwake(): Promise<WakeLockSentinel | null> {
    try {
      return 'wakeLock' in navigator ? await navigator.wakeLock.request('screen') : null;
    } catch {
      return null;
    }
  }
  async function start() {
    if (busy.value) return;
    busy.value = true;
    paused.value = false;
    offline = false;
    error.value = '';
    // A sleeping laptop drops the connection: an upload of a whole shoot keeps the screen on while it runs.
    const awake = await keepAwake();
    try {
      // Browser-side demo preparation is CPU bound, so it stays sequential.
      await Promise.all(Array.from({ length: isMockApiEnabled ? 1 : photoLimits.parallel }, worker));
    } finally {
      void awake?.release().catch(() => undefined);
      if (alive) {
        busy.value = false;
        persist(true);
      }
    }
  }
  function pause() {
    if (busy.value) {
      paused.value = true;
      wake();
    }
  }
  function online() {
    if (offline && !busy.value) void start();
  }
  function retry(id: string) {
    if (busy.value) return;
    const job = jobs.value.find((item) => item.id === id);
    if (!job || (job.archive ? !archives.has(job.archive) : !files.has(id))) {
      error.value = job?.archive ? archiveInterrupted : 'Выберите исходный файл снова: он не сохраняется после обновления страницы.';
      return;
    }
    update(id, { status: 'queued', progress: 0, message: 'Готов к повтору', serverId: undefined, attempts: 0, retryAt: undefined });
    void start();
  }
  function clear() {
    if (!busy.value) {
      jobs.value = jobs.value.filter((job) => !['done', 'duplicate'].includes(job.status));
      previous.value = 0;
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
  window.addEventListener('online', online);
  onBeforeRouteLeave(
    () =>
      !busy.value ||
      window.confirm('Подготовка файлов ещё идёт. При уходе она остановится; оставшиеся файлы потребуется выбрать снова. Уйти?')
  );
  onScopeDispose(() => {
    alive = false;
    controller.abort();
    wake();
    files.clear();
    archives.clear();
    window.clearTimeout(trackTimer);
    window.clearTimeout(refreshTimer);
    window.clearTimeout(persistTimer);
    write();
    window.removeEventListener('beforeunload', beforeUnload);
    window.removeEventListener('online', online);
  });
  if (jobs.value.some((job) => job.status === 'processing' && job.serverId)) track();
  return { jobs, busy, paused, error, queued, accepted, waiting, failed, previous, add, addArchive, start, pause, retry, clear, remove };
}
