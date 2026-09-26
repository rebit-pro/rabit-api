import { onBeforeUnmount, ref, shallowRef, watch, type Ref } from 'vue';
import { questionProblem } from '../problem.ts';
import { mayHaveBeenStored, newRequestId, normalizeMessage, POLL_MS, questionProblemMessage } from '../rules.ts';
import type { Question } from '../types.ts';

export interface QuestionThreadSource {
  load: () => Promise<Question | null>;
  send: (text: string, requestId: string) => Promise<Question>;
}

/**
 * One conversation with the curators: loads history, sends replies with a stable Idempotency-Key while the outcome
 * is unknown, and polls for answers only while the thread is shown and the tab is visible. A reset switches to another
 * conversation: answers still on their way from the previous one are never applied.
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
  let scope = 0;
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

  /** A known requestId continues an attempt whose outcome is unknown, for example after a reload. */
  async function send(text: string, requestId?: string): Promise<boolean> {
    const own = scope;
    const clean = normalizeMessage(text);
    if (requestId) attempt = { text: clean, id: requestId };
    else if (!attempt || attempt.text !== clean) attempt = { text: clean, id: newRequestId() };
    sending.value = true;
    sendError.value = '';
    try {
      const result = await source.send(clean, attempt.id);
      if (own !== scope) return false;
      ++generation;
      question.value = result;
      attempt = null;
      return true;
    } catch (cause) {
      if (own !== scope) return false;
      const problem = questionProblem(cause);
      if (!mayHaveBeenStored(problem)) attempt = null;
      sendError.value = questionProblemMessage(problem);
      return false;
    } finally {
      if (own === scope) sending.value = false;
    }
  }

  /** Forgets the current conversation, including an unfinished load or send, before another one takes its place. */
  function reset(): void {
    ++generation;
    ++scope;
    attempt = null;
    question.value = null;
    loading.value = false;
    loadError.value = '';
    sending.value = false;
    sendError.value = '';
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

  return { question, loading, loadError, sending, sendError, reload, send, reset };
}
