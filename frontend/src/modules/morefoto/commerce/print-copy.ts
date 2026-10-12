import type { CartQuote } from './types';

/** J4: a paid print brings the digital copy of its own frame; several prints or formats of one frame give one file. */
export function printIncludesCopy(quote: Pick<CartQuote, 'lines'>, photoId: string): boolean {
  return quote.lines.some((line) => line.product.kind === 'physical' && (line.photo?.id ?? line.photoId) === photoId);
}
