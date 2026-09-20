import { computed, onScopeDispose, shallowRef } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { fileProblem, photoLimits } from '../rules';
import { preparePhoto } from '../prepare';
import { acceptPhoto, editableGroup } from '../service';
import { photoApiError, photosApi } from '../api';
import { photosChangedEvent } from '../repository';
import type { UploadJob } from '../types';

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
      (isMockApiEnabled && ['queued', 'processing'].includes(job.status)) ||
      (!isMockApiEnabled && job.status === 'queued') ||
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
  const error = shallowRef('');
  const controller = new AbortController();
  let alive = true;
  const queued = computed(() => jobs.value.filter((job) => job.status === 'queued').length);
  const accepted = computed(() => jobs.value.filter((job) => job.status === 'done').length);
  const failed = computed(() => jobs.value.filter((job) => ['error', 'interrupted'].includes(job.status)).length);
  const persist = () => sessionStorage.setItem(key, JSON.stringify(jobs.value));
  function update(id: string, value: Partial<UploadJob>) {
    if (alive) {
      jobs.value = jobs.value.map((job) => (job.id === id ? { ...job, ...value } : job));
      persist();
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
      error.value = 'Выберите не больше 50 файлов за раз.';
      return;
    }
    for (const file of list) {
      const interrupted = jobs.value.find(
        (job) =>
          job.groupId === groupId &&
          job.filename === file.name &&
          job.bytes === file.size &&
          job.modified === file.lastModified &&
          job.status === 'interrupted'
      );
      const problem = fileProblem(file);
      const job: UploadJob = {
        id: interrupted?.id ?? crypto.randomUUID(),
        shootId,
        groupId,
        filename: file.name,
        bytes: file.size,
        modified: file.lastModified,
        progress: 0,
        status: problem ? 'error' : 'queued',
        message: problem || 'Готов к подготовке'
      };
      files.set(job.id, file);
      jobs.value = interrupted ? jobs.value.map((item) => (item.id === job.id ? job : item)) : [...jobs.value, job];
    }
    persist();
  }
  async function poll(jobId: string, photoId: string) {
    for (let attempt = 0; attempt < 120 && !controller.signal.aborted; attempt++) {
      const photo = await photosApi.detail(photoId);
      if (photo.status === 'ready') {
        update(jobId, { status: 'done', progress: 100, message: 'Защищённые превью готовы' });
        window.dispatchEvent(new Event(photosChangedEvent));
        return;
      }
      if (photo.status === 'failed') {
        update(jobId, {
          status: 'error',
          progress: 100,
          message: 'Сервер не смог подготовить превью. Повторно выберите исходный файл.'
        });
        return;
      }
      if (photo.status === 'duplicate') {
        update(jobId, {
          status: 'duplicate',
          progress: 100,
          message: 'Такой файл уже есть в этой съёмке. Второй кадр не создан.'
        });
        return;
      }
      update(jobId, { progress: Math.min(95, 78 + attempt), message: 'Сервер готовит защищённые превью' });
      await new Promise((resolve) => window.setTimeout(resolve, 500));
    }
    if (!controller.signal.aborted)
      update(jobId, { status: 'error', message: 'Обработка продолжается дольше минуты. Обновите страницу для проверки статуса.' });
  }
  async function processLive(job: UploadJob, file: File) {
    update(job.id, { status: 'processing', progress: 5, message: 'Отправляем приватный оригинал' });
    const result = await photosApi.upload(shootId, job.groupId, file, controller.signal, (progress) =>
      update(job.id, { progress, message: 'Отправляем приватный оригинал' })
    );
    update(job.id, { serverId: result.id, progress: 78, message: 'Файл принят. Ожидаем защищённые превью' });
    files.delete(job.id);
    if (result.status === 'duplicate') {
      update(job.id, {
        status: 'duplicate',
        progress: 100,
        message: 'Такой файл уже есть в этой съёмке. Второй кадр не создан.'
      });
      return;
    }
    await poll(job.id, result.id);
  }
  async function start() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
      for (const job of jobs.value.filter((item) => item.status === 'queued')) {
        if (controller.signal.aborted) break;
        const file = files.get(job.id);
        if (!file) {
          update(job.id, { status: 'interrupted', message: 'Выберите исходный файл снова.' });
          continue;
        }
        try {
          if (isMockApiEnabled) {
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
              message: result === 'done' ? 'Файл подготовлен' : 'Такой файл уже есть в этой съёмке. Второй кадр не создан.'
            });
            files.delete(job.id);
          } else await processLive(job, file);
        } catch (cause) {
          if (!controller.signal.aborted) update(job.id, { status: 'error', message: photoApiError(cause) });
        }
      }
    } finally {
      if (alive) busy.value = false;
    }
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
      persist();
    }
  }
  function remove(id: string) {
    if (!busy.value) {
      jobs.value = jobs.value.filter((job) => job.id !== id);
      files.delete(id);
      persist();
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
    window.removeEventListener('beforeunload', beforeUnload);
  });
  if (!isMockApiEnabled && jobs.value.some((job) => job.status === 'processing' && job.serverId)) {
    queueMicrotask(async () => {
      if (busy.value || !alive) return;
      busy.value = true;
      try {
        for (const job of jobs.value.filter((item) => item.status === 'processing' && item.serverId)) {
          try {
            await poll(job.id, job.serverId!);
          } catch (cause) {
            if (alive && !controller.signal.aborted) update(job.id, { status: 'error', message: photoApiError(cause) });
          }
        }
      } finally {
        if (alive) busy.value = false;
      }
    });
  }
  return { jobs, busy, error, queued, accepted, failed, add, start, retry, clear, remove };
}
