const plurals = new Intl.PluralRules('ru');
function count(value: number, forms: [string, string, string]) {
  const rule = plurals.select(value);
  return `${value} ${forms[rule === 'one' ? 0 : rule === 'few' ? 1 : 2]}`;
}
export const packCount = (value: number) => count(value, ['пакет', 'пакета', 'пакетов']);
export const printCountLabel = (value: number) => count(value, ['отпечаток', 'отпечатка', 'отпечатков']);
