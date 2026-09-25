import { computed, onMounted, ref, watch, type Ref } from 'vue';
import { questionProblem, questionsApi } from '../api';
import { hasUnreadReply, isQuestionKey, lastCuratorReplyId, normalizeName, questionStorageKey, seenStorageKey } from '../rules';
import type { Question } from '../types';
import { useQuestionThread } from './useQuestionThread';

function read(key: string): string | null {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

function write(key: string, value: string | null): void {
  try {
    if (value === null) localStorage.removeItem(key);
    else localStorage.setItem(key, value);
  } catch {
    // Private mode: the conversation still works until the page is closed.
  }
}

/** The parent's conversation for one gallery link: the private key stays in this browser, the name is asked once. */
export function useGalleryQuestion(token: Ref<string>, open: Ref<boolean>) {
  const stored = read(questionStorageKey(token.value));
  const questionKey = ref<string | null>(isQuestionKey(stored) ? stored : null);
  const name = ref('');
  const seen = ref(Number(read(seenStorageKey(token.value)) ?? 0) || 0);

  const thread = useQuestionThread(
    {
      async load(): Promise<Question | null> {
        if (!questionKey.value) return null;
        try {
          return await questionsApi.current(questionKey.value);
        } catch (cause) {
          if (questionProblem(cause).status === 404) {
            questionKey.value = null;
            write(questionStorageKey(token.value), null);
            return null;
          }
          throw cause;
        }
      },
      async send(text: string, requestId: string): Promise<Question> {
        if (questionKey.value) return questionsApi.add(questionKey.value, text, requestId);
        const created = await questionsApi.ask(token.value, normalizeName(name.value), text, requestId);
        questionKey.value = created.questionKey;
        write(questionStorageKey(token.value), created.questionKey);
        return created;
      }
    },
    open
  );

  const needsName = computed(() => questionKey.value === null);
  const unread = computed(() => !open.value && hasUnreadReply(thread.question.value?.messages ?? [], seen.value));

  watch([open, thread.question], ([isOpen, question]) => {
    if (!isOpen || !question) return;
    const last = lastCuratorReplyId(question.messages);
    if (last > seen.value) {
      seen.value = last;
      write(seenStorageKey(token.value), String(last));
    }
  });
  onMounted(() => {
    // One quiet check on opening the gallery shows «Новый ответ» without starting the polling.
    if (questionKey.value && !open.value) void thread.reload(true);
  });

  return { ...thread, name, needsName, unread };
}
