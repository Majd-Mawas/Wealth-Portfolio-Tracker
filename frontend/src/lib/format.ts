import type { AssetType } from './api-types'

export function money(value: string | number, currency = 'USD') {
  const number = typeof value === 'string' ? Number(value) : value
  return new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 2 }).format(Number.isFinite(number) ? number : 0)
}

export function quantity(value: string | number) {
  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 8 }).format(Number(value))
}

export function dateTime(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

export function shortDate(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

export const assetTypeLabel: Record<AssetType, string> = {
  stock: 'Stock', crypto: 'Crypto', real_estate: 'Real estate', cash: 'Cash',
}

export function localDateTimeInput() {
  return toLocalDateTimeInput(new Date())
}

export function toLocalDateTimeInput(value: string | Date) {
  const date = new Date(value)
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
  return date.toISOString().slice(0, 16)
}
