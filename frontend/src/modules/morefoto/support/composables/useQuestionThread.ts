import { onBeforeUnmount, ref, shallowRef, watch, type Ref } from 'vue';
import { questionProblem } from '../api';
import { mayHaveBeenStored, newRequestId, normalizeMessage, POLL_MS, questionProblemMessage } from '../rules';
import type { Question } from '../types';

export interface QuestionThreadSource {
  load: () => Promise<Question | null>;
  send: (text: string, requestId: string) => Promise<Question>;
}

/**
 * One conversation with the curators: loads history, sends replies with a stable Idempotency-Key while the outcome
 * is unknown, and polls for answers only while the thread is shown and the tab is visible.
 */
export function useQuestionThread(source: QuestionThreadSource, active: Ref<boolean>) {
  const question = shallowRef<Question | null>(null);
  const loading = ref(false);
  const loadError = ref('');
  const sending = ref(false);
  const sendError = ref('');
  let attempt: { text: string; id: string } | null = null;
  let timer: number | undefined;
  let generation = 0;
  let alive = true;

  async function reload(silent = false): Promise<void> {
    const current = ++generation;
    if (!silent) loading.value = true;
    try {
      const result = await source.load();
      if (current === generation) {
        question.value = result;
        loadError.value = '';
      }
    } catch {
      if (current === generation && !silent) loadError.value = 'Не удалось загрузить переписку. Повторите попытку.';
    } finally {
      if (current === generation) loading.value = false;
    }
  }

  async function send(text: string): Promise<boolean> {
    const clean = normalizeMessage(text);
    if (!attempt || attempt.text !== clean) attempt = { text: clean, id: newRequestId() };
    sending.value = true;
    sendError.value = '';
    try {
      const result = await source.send(clean, attempt.id);
      ++generation;
      question.value = result;
      attempt = null;
      return true;
    } catch (cause) {
      const problem = questionProblem(cause);
      if (!mayHaveBeenStored(problem)) attempt = null;
      sendError.value = questionProblemMessage(problem);
      return false;
    } finally {
      sending.value = false;
    }
  }

  function stop(): void {
    if (timer !== undefined) window.clearTimeout(timer);
    timer = undefined;
  }

  function schedule(): void {
    stop();
    if (!alive || !active.value) return;
    timer = window.setTimeout(async () => {
      if (document.visibilityState === 'visible') await reload(true);
      schedule();
    }, POLL_MS);
  }

  function onVisibility(): void {
    if (document.visibilityState === 'visible' && active.value) void reload(true);
  }

  watch(
    active,
    (on) => {
      if (on) {
        void reload(question.value !== null);
        schedule();
      } else {
        stop();
      }
    },
    { immediate: true }
  );
  document.addEventListener('visibilitychange', onVisibility);
  onBeforeUnmount(() => {
    alive = false;
    stop();
    document.removeEventListener('visibilitychange', onVisibility);
  });

  return { question, loading, loadError, sending, sendError, reload, send };
}
