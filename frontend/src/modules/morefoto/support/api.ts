import { isAxiosError } from 'axios';
import api from '@/api/http';
import type { FeedbackDraft } from './feedback';
import type { CreatedQuestion, Question, QuestionProblem } from './types';

export const questionsApi = {
  async ask(galleryToken: string, name: string, message: string, requestId: string): Promise<CreatedQuestion> {
    return (
      await api.post<CreatedQuestion>(
        '/api/v1/public/galleries/' + encodeURIComponent(galleryToken) + '/questions',
        { name, message },
        { headers: { 'Idempotency-Key': requestId } }
      )
    ).data;
  },
  async current(questionKey: string): Promise<Question> {
    return (await api.get<Question>('/api/v1/public/questions/current', { headers: { 'X-Question-Key': questionKey } })).data;
  },
  async add(questionKey: string, message: string, requestId: string): Promise<Question> {
    return (
      await api.post<Question>(
        '/api/v1/public/questions/current/messages',
        { message },
        { headers: { 'X-Question-Key': questionKey, 'Idempotency-Key': requestId } }
      )
    ).data;
  },
  /** Login page feedback: goes to the curators' MAX group, the answer comes to the given contact. */
  async feedback(draft: FeedbackDraft, requestId: string): Promise<{ number: number }> {
    return (await api.post<{ number: number }>('/api/v1/public/feedback', draft, { headers: { 'Idempotency-Key': requestId } })).data;
  },
  async mine(): Promise<Question> {
    return (await api.get<Question>('/api/v1/questions/mine')).data;
  },
  async addMine(message: string, requestId: string): Promise<Question> {
    return (await api.post<Question>('/api/v1/questions/mine/messages', { message }, { headers: { 'Idempotency-Key': requestId } })).data;
  }
};

export function questionProblem(cause: unknown): QuestionProblem {
  if (!isAxiosError(cause)) return { status: null, code: '', network: false };
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code ?? '';
  return { status: cause.response?.status ?? null, code, network: cause.response === undefined };
}
