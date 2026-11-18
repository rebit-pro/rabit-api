import type { ProgressStorage } from './archive-progress.ts';

/** Сохраняет явно начатую новую попытку, чтобы потеря ответа и обновление страницы повторяли тот же импорт. */
export class ImportAttempt {
  private readonly keys = new Map<string, string>();
  private readonly storage: ProgressStorage | null;
  private readonly namespace: string;

  constructor(storage: ProgressStorage | null, namespace: string) {
    this.storage = storage;
    this.namespace = namespace;
  }

  current(base: string): string {
    const cached = this.keys.get(base);
    if (cached) return cached;
    let key = base;
    try {
      const saved = this.storage?.getItem(this.namespace + ':' + base);
      if (saved && /^[a-f0-9]{32}$/.test(saved)) key = saved;
    } catch {
      /* Без хранилища исходный ключ всё ещё воспроизводим по архиву. */
    }
    this.keys.set(base, key);
    return key;
  }

  async restart(base: string): Promise<string> {
    const previous = this.current(base);
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode('restart:' + previous));
    const key = Array.from(new Uint8Array(digest).slice(0, 16), (byte) => byte.toString(16).padStart(2, '0')).join('');
    this.keys.set(base, key);
    try {
      this.storage?.setItem(this.namespace + ':' + base, key);
    } catch {
      /* В текущем мастере повтор сохраняет ключ даже при недоступном localStorage. */
    }
    return key;
  }
}
