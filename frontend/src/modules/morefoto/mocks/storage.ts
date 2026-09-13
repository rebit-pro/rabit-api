export const demoChangedEvent = 'morefoto:demo:changed';
export function readDemo<T>(key: string, fallback: T): T {
  try {
    const value = localStorage.getItem('morefoto:demo:' + key);
    return value === null ? structuredClone(fallback) : (JSON.parse(value) as T);
  } catch {
    return structuredClone(fallback);
  }
}
export function writeDemo<T>(key: string, value: T): void {
  localStorage.setItem('morefoto:demo:' + key, JSON.stringify(value));
  window.dispatchEvent(new Event(demoChangedEvent));
}
