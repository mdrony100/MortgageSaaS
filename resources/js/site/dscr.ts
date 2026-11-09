// Illustrative DSCR example for the public website. Runs entirely in the browser.
// Mirrors the backend convention (App\Appraisal\Calculator): amounts are held as
// integer cents and the ratio is rounded HALF_UP to two decimal places.

export type MoneyError = 'empty' | 'invalid' | 'negative' | 'decimals' | 'too_large' | 'zero';
export type MoneyResult = { ok: true; cents: bigint } | { ok: false; error: MoneyError };

const MAX_CENTS = 1_000_000_000n; // $10,000,000.00 — keeps the example sensible.

export const messages: Record<'rent' | 'payment', Record<MoneyError, string>> = {
  rent: {
    empty: 'Enter a monthly rent amount.',
    invalid: 'Use numbers only, for example 3300 or 3,300.50.',
    negative: 'Rent can’t be a negative amount.',
    decimals: 'Use no more than two decimal places.',
    too_large: 'Enter an amount below $10,000,000.',
    zero: 'Enter an amount greater than $0.',
  },
  payment: {
    empty: 'Enter a monthly payment amount.',
    invalid: 'Use numbers only, for example 3000 or 3,000.50.',
    negative: 'The payment can’t be a negative amount.',
    decimals: 'Use no more than two decimal places.',
    too_large: 'Enter an amount below $10,000,000.',
    zero: 'The monthly payment must be greater than $0.',
  },
};

/** Parse a user-entered dollar amount into integer cents without floating point. */
export function parseMoney(raw: string, allowZero = true): MoneyResult {
  let s = raw.trim();
  if (s === '') return { ok: false, error: 'empty' };
  let negative = false;
  if (s.startsWith('-')) { negative = true; s = s.slice(1).trim(); }
  s = s.replace(/^\$\s*/, '');
  if (s.startsWith('-')) { negative = true; s = s.slice(1).trim(); }
  const m = /^(\d{1,3}(?:,\d{3})+|\d+)?(?:\.(\d*))?$/.exec(s);
  if (!m || (m[1] === undefined && !m[2])) return { ok: false, error: 'invalid' };
  if (negative) return { ok: false, error: 'negative' };
  const frac = m[2] ?? '';
  if (frac.length > 2) return { ok: false, error: 'decimals' };
  const whole = (m[1] ?? '0').replace(/,/g, '');
  const cents = BigInt(whole) * 100n + BigInt((frac + '00').slice(0, 2));
  if (cents >= MAX_CENTS) return { ok: false, error: 'too_large' };
  if (!allowZero && cents === 0n) return { ok: false, error: 'zero' };
  return { ok: true, cents };
}

/** numerator / denominator rounded HALF_UP to 2 decimals, returned as a fixed string (e.g. "1.10"). */
export function ratioHalfUp2(numeratorCents: bigint, denominatorCents: bigint): string {
  if (denominatorCents <= 0n) throw new RangeError('Denominator must be positive');
  if (numeratorCents < 0n) throw new RangeError('Numerator must not be negative');
  // floor((n * 100 * 2 + d) / (2 * d)) == round_half_up(n * 100 / d)
  const hundredths = (numeratorCents * 200n + denominatorCents) / (2n * denominatorCents);
  const whole = hundredths / 100n;
  const frac = (hundredths % 100n).toString().padStart(2, '0');
  return `${whole}.${frac}`;
}

export function formatDollars(cents: bigint): string {
  const whole = (cents / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const frac = cents % 100n;
  return '$' + whole + (frac === 0n ? '' : '.' + frac.toString().padStart(2, '0'));
}

export type DscrOutcome =
  | { ok: true; dscr: string; equation: string; explanation: string }
  | { ok: false; errors: Partial<Record<'rent' | 'payment', string>> };

export function sampleDscr(rentInput: string, paymentInput: string): DscrOutcome {
  const rent = parseMoney(rentInput, true);
  const payment = parseMoney(paymentInput, false);
  const errors: Partial<Record<'rent' | 'payment', string>> = {};
  if (!rent.ok) errors.rent = messages.rent[rent.error];
  if (!payment.ok) errors.payment = messages.payment[payment.error];
  if (!rent.ok || !payment.ok) return { ok: false, errors };
  const dscr = ratioHalfUp2(rent.cents, payment.cents);
  return {
    ok: true,
    dscr,
    equation: `${formatDollars(rent.cents)} ÷ ${formatDollars(payment.cents)} = ${dscr}`,
    explanation: `In this simplified example, rent is ${dscr} times the monthly property payment.`,
  };
}
