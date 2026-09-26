import { mayHaveBeenStored, requestProblem } from '../../../api/outcome.ts';

/** The exact request a repeat must send again: the server compares the revision and IDs with the stored ones. */
export interface PhotoDeletionAttempt {
  groupId: string;
  revision: number;
  photoIds: string[];
  key: string;
}

export interface PhotoDeletionPorts<T> {
  send(attempt: PhotoDeletionAttempt): Promise<T>;
  newKey(): string;
}

export const unknownDeletionMessage =
  'Не удалось подтвердить удаление: связь прервалась. Нажмите «Повторить удаление» — если кадры уже удалены, повтор это подтвердит.';

/**
 * Deletes a set of frames so that a lost answer can be recovered instead of turning into conflicts.
 *
 * The attempt stays unchanged until the server answers definitely: after a network failure or a 5xx the same set is
 * sent again with the original revision, IDs and Idempotency-Key, and the server returns its stored result.
 */
export function createPhotoDeletion<T>(ports: PhotoDeletionPorts<T>) {
  let attempt: PhotoDeletionAttempt | null = null;

  function continues(groupId: string, photoIds: readonly string[]): boolean {
    return (
      attempt !== null &&
      attempt.groupId === groupId &&
      attempt.photoIds.length === photoIds.length &&
      photoIds.every((id) => attempt!.photoIds.includes(id))
    );
  }

  return {
    /** An attempt whose outcome is unknown waits for a repeat. */
    pending(): boolean {
      return attempt !== null;
    },
    async run(groupId: string, revision: number, photoIds: readonly string[]): Promise<T> {
      if (!continues(groupId, photoIds)) attempt = { groupId, revision, photoIds: [...photoIds], key: ports.newKey() };
      const current = attempt!;
      try {
        const result = await ports.send(current);
        if (attempt === current) attempt = null;
        return result;
      } catch (cause) {
        // Only a definite answer ends the attempt; after it a new request gets a new key and the current revision.
        if (attempt === current && !mayHaveBeenStored(requestProblem(cause))) attempt = null;
        throw cause;
      }
    },
    forget(): void {
      attempt = null;
    }
  };
}
