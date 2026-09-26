export function quantityValue(value: string | number | null | undefined, min = 1, max = 99): number | null {
  const text = String(value ?? '').trim();
  if (!/^\d+$/.test(text)) return null;
  const number = Number(text);
  return Number.isSafeInteger(number) && number >= min && number <= max ? number : null;
}

/**
 * Phone digits as stored, same rule as backend BuyerPolicy: national 8XXXXXXXXXX without "+" becomes Russian 7XXXXXXXXXX,
 * an explicit international "+" before the first digit (+852…, +8…) keeps the number as is.
 */
export function phoneDigits(value: string): string {
  const digits = value.replace(/\D/g, '');
  return digits.length === 11 && digits.startsWith('8') && !/^\D*\+/.test(value) ? '7' + digits.slice(1) : digits;
}

/** Digits of a Russian number (+7/7 or national 8 with 10 more digits); null keeps anything else as entered. */
function russianPhoneDigits(value: string): string | null {
  if (!/^[+\d\s().-]+$/.test(value)) return null;
  const digits = phoneDigits(value);
  return digits.length === 11 && digits.startsWith('7') ? digits : null;
}

/** Input mask of the phone field: +7 (900) 123-45-67. */
export function displayPhone(value: string): string {
  const digits = russianPhoneDigits(value);
  if (digits === null) return value;
  return '+7 (' + digits.slice(1, 4) + ') ' + digits.slice(4, 7) + '-' + digits.slice(7, 9) + '-' + digits.slice(9);
}

/** Read-only display of a stored phone for buyers and staff: +7 900 123-45-67. */
export function formatPhone(value: string): string {
  const digits = russianPhoneDigits(value);
  if (digits === null) return value;
  return '+7 ' + digits.slice(1, 4) + ' ' + digits.slice(4, 7) + '-' + digits.slice(7, 9) + '-' + digits.slice(9);
}

export function moneyInputValue(value: string): number | null {
  const text = value.trim().replace(/[ \u00a0\u202f]/g, '');
  if (!/^\d+(?:[.,]\d{1,2})?$/.test(text)) return null;
  const [whole = '', fraction = ''] = text.split(/[.,]/);
  const amount = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
  return Number.isSafeInteger(amount) ? amount : null;
}

export function dateInputValid(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const date = new Date(value + 'T00:00:00Z');
  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
}
