export const tokenPrice = 35;
const includedPages = 5;
const baseTokens = 10;
export const tokensFor = (pages: number) => baseTokens + Math.max(0, pages - includedPages);

export function priceFor(pages: number) {
  const feninga = tokensFor(pages) * tokenPrice;
  const marks = String(Math.trunc(feninga / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const fraction = String(feninga % 100).padStart(2, '0');
  return `${marks},${fraction} KM`;
}
