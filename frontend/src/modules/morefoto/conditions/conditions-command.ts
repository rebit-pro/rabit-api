import type { ConditionsCommand, ManagementErrors } from '../management/types';
import { moneyInputValue } from '../ui/field-values.ts';
import type { ConditionsAttempt, ConditionsSnapshot } from './api';
import { rateInputValue, rateText } from './payment-costs.ts';

export interface ConditionsEditorSource {
  snapshot: ConditionsSnapshot;
  groupId: string | null;
  institutionId?: string | null;
  shootId?: string | null;
}
export function createConditionsCommand(source: ConditionsEditorSource): ConditionsCommand {
  const value = source.snapshot;
  return {
    kind: 'conditions',
    requestId: crypto.randomUUID().replace(/-/g, ''),
    revision: value.revision,
    catalogRevision: value.catalogRevision,
    conditionsRevision: value.conditionsRevision ?? value.revision,
    groupId: source.groupId,
    institutionId: source.institutionId ?? null,
    shootId: source.shootId ?? null,
    inherit: value.inherit ?? false,
    products: value.products.map((product) => ({
      id: product.id,
      name: product.name,
      kind: product.kind,
      price: String(product.price / 100),
      active: product.active,
      staffDiscount: product.staffDiscount
    })),
    giftEnabled: value.giftThreshold > 0,
    giftThreshold: String(value.giftThreshold / 100),
    giftForStaff: value.giftForStaff,
    paymentCosts: source.groupId
      ? undefined
      : {
          enabled: value.paymentCosts.enabled,
          rate: rateText(value.paymentCosts.rateBps),
          maxRateBps: value.paymentCosts.maxRateBps,
          savedRateBps: value.paymentCosts.rateBps
        }
  };
}
/** Предел цены товара в копейках (1 000 000 ₽), как на сервере. */
export const MAX_PRICE = 100000000;
export function conditionsCommandErrors(command: ConditionsCommand): ManagementErrors {
  const errors: ManagementErrors = {};
  if (command.groupId && command.inherit) return errors;
  for (const product of command.products) {
    const price = moneyInputValue(product.price);
    if (price === null || price > MAX_PRICE) errors['price:' + product.id] = 'Цена: от 0 до 1 000 000 ₽, до двух знаков после запятой.';
  }
  const costs = command.paymentCosts;
  // #68: the rate matters only while the policy is on; switching it off must stay possible with any draft.
  if (!command.groupId && costs?.enabled && rateInputValue(costs.rate, costs.maxRateBps) === null)
    errors.paymentCostRate = 'Ставка: от 0 до ' + String(costs.maxRateBps / 100).replace('.', ',') + ' %, до двух знаков после запятой.';
  const threshold = moneyInputValue(command.giftThreshold);
  if (command.giftEnabled && (threshold === null || threshold < 1 || threshold > 2147483647))
    errors.giftThreshold = 'Порог: от 0,01 до 21 474 836,47 ₽.';
  if (command.giftEnabled && command.products.filter((product) => product.kind === 'bundle' && product.active).length !== 1)
    errors.products = 'Для подарка должен быть включён ровно один электронный комплект.';
  return errors;
}
export function conditionsAttempt(command: ConditionsCommand): ConditionsAttempt {
  const inherit = !!command.groupId && command.inherit;
  const body: ConditionsAttempt['body'] = {
    revision: command.revision,
    catalogRevision: command.catalogRevision,
    products: inherit
      ? []
      : command.products.map((product) => ({
          id: product.id,
          price: moneyInputValue(product.price)!,
          active: product.active,
          staffDiscount: product.staffDiscount
        })),
    giftEnabled: !inherit && command.giftEnabled,
    giftThreshold: !inherit && command.giftEnabled ? moneyInputValue(command.giftThreshold)! : 0,
    giftForStaff: !inherit && command.giftEnabled && command.giftForStaff
  };
  if (command.groupId) {
    body.conditionsRevision = command.conditionsRevision;
    body.inherit = command.inherit;
  } else if (command.paymentCosts) {
    const costs = command.paymentCosts;
    // A switched-off policy keeps the saved rate when the draft rate is not valid.
    body.paymentCosts = { enabled: costs.enabled, rateBps: rateInputValue(costs.rate, costs.maxRateBps) ?? costs.savedRateBps };
  }
  return { groupId: command.groupId, key: command.requestId, body };
}
