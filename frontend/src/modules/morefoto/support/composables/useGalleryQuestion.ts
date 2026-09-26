import { computed, onMounted, ref, watch, type Ref } from 'vue';
import type { questionsApi } from '../api.ts';
import { sendBeforeKey, type FirstQuestionPorts } from '../firstQuestion.ts';
import { questionProblem } from '../problem.ts';
import {
  hasUnreadReply,
  isQuestionKey,
  lastCuratorReplyId,
  normalizeName,
  parsePendingAsk,
  pendingStorageKey,
  questionStorageKey,
  seenStorageKey
} from '../rules.ts';
import type { Question } from '../types.ts';
import { useQuestionThread } from './useQuestionThread.ts';

/** Server calls of the parent's conversation; injected so that tests run the composable without the HTTP client. */
export type GalleryQuestionApi = Pick<typeof questionsApi, 'ask' | 'current' | 'add'>;

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

function storedKey(galleryToken: string): string | null {
  const stored = read(questionStorageKey(galleryToken));
  return isQuestionKey(stored) ? stored : null;
}

function storedSeen(galleryToken: string): number {
  return Number(read(seenStorageKey(galleryToken)) ?? 0) || 0;
}

function hasPending(galleryToken: string): boolean {
  return parsePendingAsk(read(pendingStorageKey(galleryToken))) !== null;
}

/**
 * The parent's conversation for one gallery link: the private key stays in this browser, the name is asked once.
 *
 * The screen is reused when the parent follows a link to another gallery: the conversation, its read replies and an
 * unfinished first question are then switched to the new link, and answers to requests of the previous gallery are
 * kept only under that gallery.
 */
export function useGalleryQuestion(token: Ref<string>, open: Ref<boolean>, api: GalleryQuestionApi) {
  const questionKey = ref<string | null>(storedKey(token.value));
  const name = ref('');
  const seen = ref(storedSeen(token.value));
  // An unfinished first question fixes the name: the field is hidden until the conversation key is recovered.
  const pending = ref(hasPending(token.value));

  /** Storage of the gallery the request was made for; the screen's state changes only while it still shows that gallery. */
  function portsFor(galleryToken: string): FirstQuestionPorts {
    const shown = (): boolean => galleryToken === token.value;
    return {
      ask: (author, text, requestId) => api.ask(galleryToken, author, text, requestId),
      add: (key, text, requestId) => api.add(key, text, requestId),
      problem: questionProblem,
      readPending: () => read(pendingStorageKey(galleryToken)),
      writePending(value) {
        write(pendingStorageKey(galleryToken), value);
        if (shown()) pending.value = value !== null;
      },
      keep(key) {
        write(questionStorageKey(galleryToken), key);
        if (shown()) questionKey.value = key;
      }
    };
  }

  const thread = useQuestionThread(
    {
      async load(): Promise<Question | null> {
        const galleryToken = token.value;
        const key = questionKey.value;
        if (!key) return null;
        try {
          return await api.current(key);
        } catch (cause) {
          if (questionProblem(cause).status === 404) {
            // A late answer must not erase a key that replaced this one meanwhile (a new question, another tab).
            if (read(questionStorageKey(galleryToken)) === key) write(questionStorageKey(galleryToken), null);
            if (galleryToken === token.value && key === questionKey.value) questionKey.value = null;
            return null;
          }
          throw cause;
        }
      },
      async send(text: string, requestId: string): Promise<Question> {
        if (questionKey.value) return api.add(questionKey.value, text, requestId);
        return sendBeforeKey(portsFor(token.value), normalizeName(name.value), text, requestId);
      }
    },
    open
  );

  const needsName = computed(() => questionKey.value === null && !pending.value);
  const unread = computed(() => !open.value && hasUnreadReply(thread.question.value?.messages ?? [], seen.value));

  watch([open, thread.question], ([isOpen, question]) => {
    if (!isOpen || !question) return;
    const last = lastCuratorReplyId(question.messages);
    if (last > seen.value) {
      seen.value = last;
      write(seenStorageKey(token.value), String(last));
    }
  });

  function start(): void {
    const unfinished = parsePendingAsk(read(pendingStorageKey(token.value)));
    if (questionKey.value || !unfinished) portsFor(token.value).writePending(null);
    // The previous page lost the answer to the first question: repeat it with the same key to get the same conversation.
    if (!questionKey.value && unfinished) void thread.send(unfinished.text, unfinished.requestId);
    // One quiet check on opening the gallery shows «Новый ответ» without starting the polling.
    if (questionKey.value && !open.value) void thread.reload(true);
  }

  // Synchronous: no send between the route change and the next render may pair the new link with the old key.
  watch(
    token,
    (galleryToken) => {
      thread.reset();
      questionKey.value = storedKey(galleryToken);
      seen.value = storedSeen(galleryToken);
      pending.value = hasPending(galleryToken);
      name.value = '';
      start();
      if (open.value) void thread.reload();
    },
    { flush: 'sync' }
  );
  onMounted(start);

  return { ...thread, name, needsName, unread, pending };
}
