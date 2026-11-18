/** Display money for the review pane.
 * JSON numbers are minor units (cents), which is how transactions are stored.
 * JSON strings are dollar amounts, which is how statement totals are stored.
 */
export function formatMoney(value: unknown, currency = 'USD'): string {
  if (value === null || value === undefined || value === '') return '—'
  const code = /^[A-Z]{3}$/.test(currency) ? currency : 'USD'
  let dollars: number
  if (typeof value === 'number') {
    if (!Number.isFinite(value)) return '—'
    dollars = Number.isInteger(value) ? value / 100 : value
  } else if (typeof value === 'string') {
    const trimmed = value.trim().replace(/[$,]/g, '')
    if (!/^-?\d+(\.\d+)?$/.test(trimmed)) return '—'
    dollars = Number(trimmed)
  } else {
    return '—'
  }
  if (!Number.isFinite(dollars)) return '—'
  try {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: code }).format(dollars)
  } catch {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(dollars)
  }
}

export function formatDate(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value))
  if (!match) return String(value)
  const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])))
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(date)
}

export function humanize(value: unknown): string {
  const text = String(value ?? '').trim()
  if (!text) return ''
  return text.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase())
}
