import { shallowRef, onMounted, onScopeDispose, nextTick } from 'vue';
import { reviewStatus, prepareReview, configureReview, failReviewRequest } from './service';
import type { ReviewStatus } from './service';
import type { ReviewSettings } from './rules';
export function useReview() {
  const data = shallowRef<ReviewStatus | null>(null),
    busy = shallowRef(false),
    error = shallowRef(''),
    notice = shallowRef('');
  let alive = true,
    run = 0;
  async function reload() {
    const id = ++run;
    try {
      const v = await reviewStatus();
      if (alive && id === run) {
        data.value = v;
        error.value = '';
      }
    } catch (e) {
      if (alive && id === run) error.value = e instanceof Error ? e.message : 'Не удалось открыть проверку.';
    }
  }
  async function action(operation: () => void | Promise<void>, message: string) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
      await operation();
      await reload();
      notice.value = message;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Не удалось выполнить действие.';
      await nextTick();
      document.querySelector<HTMLElement>('[data-testid="review-error"]')?.focus();
    } finally {
      busy.value = false;
    }
  }
  const prepare = () => action(() => prepareReview(), 'Набор подготовлен. Можно начинать проверку.');
  const reset = () => action(() => prepareReview(data.value?.session?.generation), 'Набор восстановлен. Войдите в нужную роль заново.');
  const configure = (settings: ReviewSettings) =>
    action(() => configureReview(data.value?.session?.generation ?? '', settings), 'Условия проверки применены.');
  const fail = () =>
    action(
      () => failReviewRequest(data.value?.session?.generation ?? ''),
      'Следующий запрос завершится ошибкой. Перейдите к проверяемому действию.'
    );
  onMounted(() => {
    void reload();
    window.addEventListener('storage', reload);
    window.addEventListener('morefoto:demo:changed', reload);
  });
  onScopeDispose(() => {
    alive = false;
    window.removeEventListener('storage', reload);
    window.removeEventListener('morefoto:demo:changed', reload);
  });
  return { data, busy, error, notice, reload, prepare, reset, configure, fail };
}
