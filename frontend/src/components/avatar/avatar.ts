/** Letters and color of a staff avatar without a photo (design plan 8.2). */

export const AVATAR_TONES = 8;
/** Index of the neutral «pebble» tone: blocked accounts never get a bright color. */
export const AVATAR_NEUTRAL_TONE = 7;

/**
 * Up to two letters in the order the name is written: first letters of the first two words.
 * Surnames cannot be detected reliably, so words are never reordered. Parenthesised notes are ignored;
 * without a name the email local part is used, without both — a bullet.
 */
export function avatarInitials(name?: string | null, email?: string | null): string {
  const words = (name ?? '')
    .replace(/\([^)]*\)/g, ' ')
    .split(/\s+/)
    .map((word) => word.replace(/^[^\p{L}\p{N}]+/u, ''))
    .filter((word) => '' !== word);
  if (words.length > 0) {
    return words
      .slice(0, 2)
      .map((word) => Array.from(word)[0])
      .join('')
      .toLocaleUpperCase('ru-RU');
  }
  const local = Array.from((email ?? '').split('@')[0]!.replace(/[^\p{L}\p{N}]/gu, ''));
  return local.length > 0 ? local.slice(0, 2).join('').toLocaleUpperCase('ru-RU') : '•';
}

/** Stable seed: the staff ID; invited people without an ID are keyed by email. The name never changes the color. */
export function avatarSeed(id?: number | string | null, email?: string | null): string {
  return null !== id && undefined !== id && '' !== String(id) ? 'staff:' + id : 'email:' + (email ?? '').trim().toLowerCase();
}

/** FNV-1a 32-bit hash of the seed mapped onto the eight data pastels. */
export function avatarTone(seed: string): number {
  let hash = 0x811c9dc5;
  for (let index = 0; index < seed.length; index++) {
    hash ^= seed.charCodeAt(index);
    hash = Math.imul(hash, 0x01000193);
  }
  return (hash >>> 0) % AVATAR_TONES;
}
