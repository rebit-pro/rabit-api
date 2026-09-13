import { computed, onScopeDispose, shallowRef } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { fileProblem, photoLimits } from '../rules';
import { preparePhoto } from '../prepare';
import { acceptPhoto, editableGroup } from '../service';
import type { UploadJob } from '../types';

export function usePhotoQueue(shootId: string) {
  const auth = useAuthStore();
  const key = 'morefoto:demo:uploads:' + auth.user?.id + ':' + shootId;
  let initial: UploadJob[] = [];
  try {
    initial = JSON.parse(sessionStorage.getItem(key) ?? '[]') as UploadJob[];
    if (!Array.isArray(initial)) initial = [];
  } catch {
    /* A new queue replaces an unreadable draft. */
  }
  const jobs = shallowRef<UploadJob[]>(
    initial.map((job) =>
      ['queued', 'processing'].includes(job.status)
        ? { ...job, status: 'interrupted', progress: 0, message: 'Подготовка прервана. Выберите исходный файл снова.' }
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
    try {
      editableGroup(auth.getAccessToken() ?? '', shootId, groupId);
    } catch (cause) {
      error.value = (cause as Error).message;
      return;
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
        update(job.id, { status: 'processing', progress: 5, message: 'Читаем и проверяем файл' });
        try {
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
        } catch (cause) {
          update(job.id, { status: 'error', message: cause instanceof Error ? cause.message : 'Не удалось подготовить файл.' });
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
    update(id, { status: 'queued', progress: 0, message: 'Готов к повтору' });
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
  return { jobs, busy, error, queued, accepted, failed, add, start, retry, clear, remove };
}
