import { isAxiosError } from 'axios';

/** What retry rules need from a failed call: HTTP status, API error code and whether the network failed. */
export interface RequestProblem {
  status: number | null;
  code: string;
  network: boolean;
}

export function requestProblem(cause: unknown): RequestProblem {
  if (!isAxiosError(cause)) return { status: null, code: '', network: false };
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code ?? '';
  return { status: cause.response?.status ?? null, code, network: cause.response === undefined };
}

/** A repeat may keep the same Idempotency-Key only while the server could have stored the change. */
export function mayHaveBeenStored(problem: RequestProblem): boolean {
  return problem.network || problem.status === null || problem.status >= 500;
}
