// Protected previews are authenticated XHR requests: native lazy loading does not delay them, and hundreds of
// simultaneous requests wait in the server's worker queue until the client timeout fires. The loader keeps a
// bounded queue, retries a transient failure once and caches the object URLs it created.
export interface PreviewLoaderOptions {
  fetch(url: string, signal: AbortSignal): Promise<Blob>;
  retryable(error: unknown): boolean;
  createUrl(blob: Blob): string;
  revokeUrl(url: string): void;
  concurrency: number;
  retryDelayMs: number;
  maxBytes: number;
  wait?(ms: number): Promise<void>;
}
export interface PreviewLoader {
  load(url: string, signal?: AbortSignal, urgent?: boolean): Promise<string>;
  clear(): void;
}
interface Task {
  url: string;
  controller: AbortController;
  waiters: number;
  attempts: number;
  running: boolean;
  settled: Promise<string>;
  resolve(value: string): void;
  reject(reason: unknown): void;
}
export function isPreviewAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}
function aborted(): DOMException {
  return new DOMException('Preview request cancelled.', 'AbortError');
}
export function createPreviewLoader(options: PreviewLoaderOptions): PreviewLoader {
  const wait = options.wait ?? ((ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms)));
  const cache = new Map<string, { objectUrl: string; bytes: number }>();
  const tasks = new Map<string, Task>();
  const queue: Task[] = [];
  let active = 0;
  let bytes = 0;
  function remember(url: string, blob: Blob): string {
    const objectUrl = options.createUrl(blob);
    cache.set(url, { objectUrl, bytes: blob.size });
    bytes += blob.size;
    // Map order is the recency order: the oldest entries go first, the frame just received always stays.
    for (const [key, entry] of cache) {
      if (bytes <= options.maxBytes || key === url) break;
      cache.delete(key);
      bytes -= entry.bytes;
      options.revokeUrl(entry.objectUrl);
    }
    return objectUrl;
  }
  function drop(task: Task, reason: unknown): void {
    if (tasks.get(task.url) === task) tasks.delete(task.url);
    const index = queue.indexOf(task);
    if (index >= 0) queue.splice(index, 1);
    task.controller.abort();
    task.reject(reason);
  }
  function pump(): void {
    while (active < options.concurrency && queue.length > 0) void run(queue.shift()!);
  }
  async function run(task: Task): Promise<void> {
    active++;
    task.running = true;
    task.attempts++;
    try {
      const blob = await options.fetch(task.url, task.controller.signal);
      if (tasks.get(task.url) === task) {
        tasks.delete(task.url);
        task.resolve(remember(task.url, blob));
      }
    } catch (error) {
      if (tasks.get(task.url) !== task) return;
      if (task.attempts < 2 && !task.controller.signal.aborted && options.retryable(error)) {
        task.running = false;
        void wait(options.retryDelayMs).then(() => {
          if (tasks.get(task.url) !== task) return;
          if (task.waiters === 0) {
            drop(task, aborted());
            return;
          }
          queue.unshift(task);
          pump();
        });
      } else {
        tasks.delete(task.url);
        task.reject(error);
      }
    } finally {
      active--;
      pump();
    }
  }
  function enqueue(url: string, urgent: boolean): Task {
    const existing = tasks.get(url);
    if (existing) {
      const index = queue.indexOf(existing);
      if (urgent && index > 0) queue.unshift(...queue.splice(index, 1));
      return existing;
    }
    let resolve!: (value: string) => void;
    let reject!: (reason: unknown) => void;
    const settled = new Promise<string>((done, fail) => {
      resolve = done;
      reject = fail;
    });
    const task: Task = { url, controller: new AbortController(), waiters: 0, attempts: 0, running: false, settled, resolve, reject };
    tasks.set(url, task);
    if (urgent) queue.unshift(task);
    else queue.push(task);
    return task;
  }
  return {
    load(url, signal, urgent = false) {
      const cached = cache.get(url);
      if (cached) {
        cache.delete(url);
        cache.set(url, cached);
        return Promise.resolve(cached.objectUrl);
      }
      if (signal?.aborted) return Promise.reject(aborted());
      const task = enqueue(url, urgent);
      task.waiters++;
      const result = new Promise<string>((resolve, reject) => {
        // A waiter that leaves before the request starts frees its place in the queue; a started request
        // still completes and fills the cache for the next visit.
        const leave = () => {
          task.waiters--;
          if (task.waiters === 0 && !task.running) drop(task, aborted());
          reject(aborted());
        };
        signal?.addEventListener('abort', leave, { once: true });
        task.settled.then(
          (value) => {
            signal?.removeEventListener('abort', leave);
            resolve(value);
          },
          (error: unknown) => {
            signal?.removeEventListener('abort', leave);
            reject(error);
          }
        );
      });
      pump();
      return result;
    },
    clear() {
      for (const task of [...tasks.values()]) drop(task, aborted());
      for (const entry of cache.values()) options.revokeUrl(entry.objectUrl);
      cache.clear();
      bytes = 0;
    }
  };
}
