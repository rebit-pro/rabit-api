import type { CartQuote, CartQuoteLine, Product } from './types';

/**
 * #192: the product list of a frame shows what the cart already holds row by row, so a parent sees that one frame
 * takes several formats. A bundle belongs to the child's series, every other product to this frame.
 */
export function frameLine(
  quote: Pick<CartQuote, 'lines'>,
  photoId: string,
  childCode: string | undefined,
  product: Pick<Product, 'id' | 'kind'>
): CartQuoteLine | undefined {
  return quote.lines.find(
    (line) =>
      line.productId === product.id &&
      (product.kind === 'bundle' ? line.childCode === childCode : (line.photo?.id ?? line.photoId) === photoId)
  );
}

/** Pieces and amount of this frame in the cart: prints count by quantity, a file once; the series bundle is not the frame's. */
export function frameSummary(quote: Pick<CartQuote, 'lines'>, photoId: string): { count: number; total: number } {
  return quote.lines
    .filter((line) => line.product.kind !== 'bundle' && (line.photo?.id ?? line.photoId) === photoId)
    .reduce((sum, line) => ({ count: sum.count + line.quantity, total: sum.total + line.total }), { count: 0, total: 0 });
}
