/**
 * #192: the parents' purchase guide — a static page the organizer shares in the group chat (KK-DEC-01).
 * Every step of the purchase links to it; a step anchor opens the guide on the matching card.
 */
export const buyerGuidePath = '/kak-kupit/';
export type BuyerGuideStep = 'rebenok' | 'foto' | 'zakaz' | 'oplata' | 'karta' | 'gotovo';

export function buyerGuideHref(step?: BuyerGuideStep): string {
  return undefined === step ? buyerGuidePath : buyerGuidePath + '#' + step;
}
