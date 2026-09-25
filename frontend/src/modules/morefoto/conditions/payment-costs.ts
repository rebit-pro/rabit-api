export interface PaymentCostPolicy {
  enabled: boolean;
  rateBps: number;
}

/** Шаг округления цены для покупателя в копейках (50 ₽), как на сервере. */
export const PAYMENT_COST_ROUNDING_STEP = 5000;

/**
 * Цена для покупателя по правилу E6: цена каталога / (1 − ставка) с округлением вверх до 50 ₽.
 * Выключенная политика возвращает цену каталога без округления. Используется для предпросмотра до сохранения,
 * итоговую цену считает сервер.
 */
export function salePrice(base: number, policy: PaymentCostPolicy): number {
  if (!policy.enabled) return base;
  const divisor = (10000 - policy.rateBps) * PAYMENT_COST_ROUNDING_STEP;
  const dividend = base * 10000 + divisor - 1;
  let quotient = Math.floor(dividend / divisor);
  if (quotient * divisor > dividend) quotient -= 1;
  if ((quotient + 1) * divisor <= dividend) quotient += 1;
  return quotient * PAYMENT_COST_ROUNDING_STEP;
}

/** Ставка из поля ввода в процентах («3,8», «3.80») в базисных пунктах; null — неверный ввод или выше предела. */
export function rateInputValue(value: string, maxRateBps: number): number | null {
  const text = value.trim().replace(/[ \u00a0\u202f]/g, '');
  if (!/^\d{1,3}(?:[.,]\d{1,2})?$/.test(text)) return null;
  const [whole = '', fraction = ''] = text.split(/[.,]/);
  const bps = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
  return bps <= maxRateBps ? bps : null;
}

/** Ставка для показа: 380 → «3,80», 1000 → «10,00». */
export function rateText(rateBps: number): string {
  return (rateBps / 100).toFixed(2).replace('.', ',');
}
