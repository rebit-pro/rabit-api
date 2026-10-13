/**
 * #192: «Сохранить в телефон» opens the phone's own share sheet with the photo — «Сохранить изображение» puts it into
 * the gallery (an iPhone keeps a plain download in Files). The site may only offer the sheet, the phone owns it.
 */
export type ShareOutcome = 'shared' | 'cancelled' | 'retry' | 'failed';
type ShareTarget = Partial<Pick<Navigator, 'canShare' | 'share'>>;

/** Only touch devices whose browser accepts an image file in the share sheet get the button. */
export function sharesPhotos(target: ShareTarget | undefined, coarsePointer: boolean): boolean {
  if (!coarsePointer || typeof target?.share !== 'function' || typeof target.canShare !== 'function') return false;
  try {
    return target.canShare({ files: [new File([''], 'photo.jpg', { type: 'image/jpeg' })] });
  } catch {
    return false;
  }
}

/** A closed sheet is the buyer's choice; a sheet refused for an expired tap opens on the next tap with the file ready. */
export function shareOutcome(cause: unknown): Exclude<ShareOutcome, 'shared'> {
  const name = cause instanceof Error ? cause.name : '';
  return 'AbortError' === name ? 'cancelled' : 'NotAllowedError' === name ? 'retry' : 'failed';
}
