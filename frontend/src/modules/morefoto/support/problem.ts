import { isAxiosError } from 'axios';
import type { QuestionProblem } from './types.ts';

/** Reduces a failed call to what the conversation rules need: status, error code and whether the network failed. */
export function questionProblem(cause: unknown): QuestionProblem {
  if (!isAxiosError(cause)) return { status: null, code: '', network: false };
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code ?? '';
  return { status: cause.response?.status ?? null, code, network: cause.response === undefined };
}
