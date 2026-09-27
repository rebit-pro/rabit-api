export interface BulkFailure {
  name: string;
  reason: string;
}
export interface BulkResult {
  total: number;
  done: number;
  failed: BulkFailure[];
}
/** What the screen says after a bulk action: «Удалено: N из M» and the refusal of each record that stayed. */
export interface BulkNotice {
  tone: 'success' | 'warning';
  text: string;
  failures: string[];
}
/**
 * One request per record keeps each refusal attached to its name (#91, #92 DEC-07): a refused record does not stop the
 * rest, and the requests go one after another so a shared revision can move from answer to answer.
 */
export async function runEach<T>(
  items: readonly T[],
  name: (item: T) => string,
  action: (item: T) => Promise<void>,
  reason: (cause: unknown) => string
): Promise<BulkResult> {
  const result: BulkResult = { total: items.length, done: 0, failed: [] };
  for (const item of items) {
    try {
      await action(item);
      result.done++;
    } catch (cause) {
      result.failed.push({ name: name(item), reason: reason(cause) });
    }
  }
  return result;
}
export function bulkNotice(label: string, result: BulkResult, success = ''): BulkNotice {
  const text = label + ': ' + result.done + ' из ' + result.total + '.';
  return {
    tone: result.failed.length ? 'warning' : 'success',
    text: result.failed.length || !success ? text : text + ' ' + success,
    failures: result.failed.map((item) => item.name + ': ' + item.reason)
  };
}
