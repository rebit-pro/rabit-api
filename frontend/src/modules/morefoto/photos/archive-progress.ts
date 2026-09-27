/**
 * Accepted files of an archive upload, kept in the browser between reloads and closed tabs: choosing the same
 * archives again skips them instead of sending gigabytes twice. Storage failures only cost that convenience.
 */
export interface ProgressStorage {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
}

type Accepted = Record<string, string[]>;

export class ArchiveProgress {
  private readonly storage: ProgressStorage | null;
  private readonly key: string;
  private readonly accepted: Accepted;

  constructor(storage: ProgressStorage | null, key: string) {
    this.storage = storage;
    this.key = key;
    this.accepted = this.read();
  }

  has(archive: string, path: string): boolean {
    return this.accepted[archive]?.includes(path) ?? false;
  }

  mark(archive: string, path: string): void {
    if (this.has(archive, path)) return;
    (this.accepted[archive] ??= []).push(path);
    try {
      this.storage?.setItem(this.key, JSON.stringify(this.accepted));
    } catch {
      /* A full or blocked storage keeps the progress for this page only. */
    }
  }

  private read(): Accepted {
    try {
      const value: unknown = JSON.parse(this.storage?.getItem(this.key) ?? '{}');
      if (typeof value !== 'object' || value === null || Array.isArray(value)) return {};
      return Object.fromEntries(
        Object.entries(value).filter(
          (item): item is [string, string[]] => Array.isArray(item[1]) && item[1].every((path) => typeof path === 'string')
        )
      );
    } catch {
      return {};
    }
  }
}

export function browserStorage(): ProgressStorage | null {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}
