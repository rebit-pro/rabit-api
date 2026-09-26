import type { PendingAsk, QuestionDelivery, QuestionMessage, QuestionProblem } from './types';

export const NAME_MAX = 60;
export const MESSAGE_MAX = 2000;
/** Replies are polled only while the thread is visible on screen. */
export const POLL_MS = 15_000;

const QUESTION_KEY = /^[a-f0-9]{64}$/;

/** The parent's private conversation key lives next to the gallery link in this browser only. */
export function questionStorageKey(galleryToken: string): string {
  return 'morefoto:live:question:v1:' + galleryToken;
}

export function pendingStorageKey(galleryToken: string): string {
  return 'morefoto:live:question-pending:v1:' + galleryToken;
}

/** A stored unfinished first question is replayed only when it is intact; anything else is discarded. */
export function parsePendingAsk(raw: string | null): PendingAsk | null {
  if (!raw) return null;
  try {
    const value = JSON.parse(raw) as Partial<PendingAsk>;
    if (typeof value.name !== 'string' || typeof value.text !== 'string' || typeof value.requestId !== 'string') return null;
    if (!/^[a-f0-9]{32}$/.test(value.requestId) || textProblem(value.name, value.text) !== null) return null;
    return { name: value.name, text: value.text, requestId: value.requestId };
  } catch {
    return null;
  }
}

export function seenStorageKey(galleryToken: string): string {
  return 'morefoto:live:question-seen:v1:' + galleryToken;
}

export function isQuestionKey(value: unknown): value is string {
  return typeof value === 'string' && QUESTION_KEY.test(value);
}

export function normalizeMessage(value: string): string {
  return value.replace(/\r\n?/g, '\n').trim();
}

export function normalizeName(value: string): string {
  return value.replace(/\s+/g, ' ').trim();
}

/** The same limits as the server, so the parent sees the reason before sending. */
export function textProblem(name: string | null, message: string): string | null {
  if (name !== null) {
    const clean = normalizeName(name);
    if (!clean) return 'Укажите, как к вам обращаться.';
    if (clean.length > NAME_MAX) return `Имя — не длиннее ${NAME_MAX} символов.`;
  }
  const text = normalizeMessage(message);
  if (!text) return 'Напишите вопрос.';
  if (text.length > MESSAGE_MAX) return `Сообщение — не длиннее ${MESSAGE_MAX} символов.`;
  return null;
}

/**
 * Sends the typed text; success clears the field only while it still holds that text, so a next question typed during
 * the wait survives. A failed send keeps the text for a retry.
 */
export async function submitDraft(draft: { value: string }, submit: (text: string) => Promise<boolean>): Promise<void> {
  const text = draft.value;
  if ((await submit(text)) && draft.value === text) draft.value = '';
}

export function lastCuratorReplyId(messages: readonly QuestionMessage[]): number {
  return messages.reduce((last, message) => (message.author === 'curator' && message.id > last ? message.id : last), 0);
}

export function hasUnreadReply(messages: readonly QuestionMessage[], seenId: number): boolean {
  return lastCuratorReplyId(messages) > seenId;
}

export function deliveryLabel(delivery: QuestionDelivery | null): string {
  if (delivery === 'delivered') return 'Доставлено куратору';
  if (delivery === 'failed') return 'Не доставлено. Напишите ещё раз чуть позже';
  if (delivery === 'sending') return 'Отправляется куратору';
  if (delivery === 'unknown') return 'Не удалось подтвердить доставку. Если куратор не ответит, напишите ещё раз';
  return '';
}

/** A repeat may keep the same Idempotency-Key only while the server could have stored the message. */
export function mayHaveBeenStored(problem: QuestionProblem): boolean {
  return problem.network || problem.status === null || problem.status >= 500;
}

export function questionProblemMessage(problem: QuestionProblem): string {
  if (problem.network) return 'Нет связи с сервером. Текст сохранён — отправьте ещё раз.';
  if (problem.code === 'RATE_LIMITED') return 'Слишком много сообщений подряд. Попробуйте через час.';
  if (problem.code === 'INVALID_QUESTION_NAME') return `Имя — от 1 до ${NAME_MAX} символов, без служебных знаков.`;
  if (problem.code === 'INVALID_QUESTION_MESSAGE') return `Сообщение — от 1 до ${MESSAGE_MAX} символов, без служебных знаков.`;
  if (problem.code === 'QUESTION_NOT_FOUND') return 'Переписка не найдена. Задайте вопрос заново.';
  if (problem.code === 'GALLERY_NOT_FOUND') return 'Ссылка на галерею недействительна.';
  if (problem.code === 'FORBIDDEN' || problem.status === 403) return 'Вопросы куратору доступны заведующей и воспитателям.';
  if (problem.code === 'IDEMPOTENCY_CONFLICT') return 'Сообщение уже отправляется. Обновите переписку.';
  return 'Не удалось отправить сообщение. Повторите попытку.';
}

export function formatMoment(iso: string, now: Date = new Date()): string {
  const moment = new Date(iso);
  if (Number.isNaN(moment.getTime())) return '';
  const sameDay = moment.toDateString() === now.toDateString();
  return new Intl.DateTimeFormat(
    'ru-RU',
    sameDay ? { hour: '2-digit', minute: '2-digit' } : { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }
  ).format(moment);
}

export function newRequestId(random: () => string = () => crypto.randomUUID()): string {
  return random().replace(/-/g, '');
}
