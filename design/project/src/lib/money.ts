export function formatMoney(amount: number, currency = 'USD', locale = 'en-US') {
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency', currency: currency.toUpperCase(),
      minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return `${currency.toUpperCase()} ${amount.toFixed(2)}`;
  }
}
