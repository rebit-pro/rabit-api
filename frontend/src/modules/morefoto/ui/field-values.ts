export function quantityValue(value: string | number | null | undefined, min = 1, max = 99): number | null {
  const text = String(value ?? '').trim();
  if (!/^\d+$/.test(text)) return null;
  const number = Number(text);
  return Number.isSafeInteger(number) && number >= min && number <= max ? number : null;
}

export function displayPhone(value: string): string {
  if (!/^[+\d\s().-]+$/.test(value)) return value;
  const digits = value.replace(/\D/g, '');
  if (digits.length !== 11 || !/^[78]/.test(digits)) return value;
  return '+7 (' + digits.slice(1, 4) + ') ' + digits.slice(4, 7) + '-' + digits.slice(7, 9) + '-' + digits.slice(9);
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
