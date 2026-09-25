import { mayHaveBeenStored, normalizeMessage, parsePendingAsk } from './rules.ts';
import type { CreatedQuestion, PendingAsk, Question, QuestionProblem } from './types.ts';

/** Server calls and browser storage used before the conversation key is known; injected for tests. */
export interface FirstQuestionPorts {
  ask(name: string, text: string, requestId: string): Promise<CreatedQuestion>;
  add(questionKey: string, text: string, requestId: string): Promise<Question>;
  problem(cause: unknown): QuestionProblem;
  readPending(): string | null;
  writePending(value: string | null): void;
  keep(questionKey: string): void;
}

/**
 * Sends a parent's message while the conversation key is still unknown.
 *
 * An unfinished first question stays immutable until the server returns its key: it is replayed with the original
 * name, text and Idempotency-Key first, so a lost answer never becomes a second conversation. A different text typed
 * meanwhile is then added to that same conversation as the next reply.
 */
export async function sendBeforeKey(ports: FirstQuestionPorts, name: string, text: string, requestId: string): Promise<Question> {
  const pending = parsePendingAsk(ports.readPending());
  if (pending) {
    const created = await replay(ports, pending);
    return normalizeMessage(text) === pending.text ? created : ports.add(created.questionKey, text, requestId);
  }
  const first: PendingAsk = { name, text, requestId };
  // Until the server returns the key, only this record can repeat the question without a second conversation.
  ports.writePending(JSON.stringify(first));
  return replay(ports, first);
}

async function replay(ports: FirstQuestionPorts, pending: PendingAsk): Promise<CreatedQuestion> {
  let created: CreatedQuestion;
  try {
    created = await ports.ask(pending.name, pending.text, pending.requestId);
  } catch (cause) {
    const problem = ports.problem(cause);
    // Only a proven refusal of this exact payload ends the attempt; 409 or an unknown outcome keeps it for a replay.
    if (!mayHaveBeenStored(problem) && problem.status !== 409) ports.writePending(null);
    throw cause;
  }
  ports.keep(created.questionKey);
  ports.writePending(null);
  return created;
}
