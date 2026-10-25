import type { AssetType } from "./api-types";

export function money(value: string | number, currency = "USD") {
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(exactIntlValue(value));
}

export function quantity(value: string | number) {
  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 8 }).format(
    exactIntlValue(value),
  );
}

function exactIntlValue(value: string | number): number {
  if (typeof value === "number") return Number.isFinite(value) ? value : 0;

  const normalized = value.trim();
  if (!/^-?(0|[1-9]\d*)(\.\d+)?$/.test(normalized)) return 0;

  // ECMA-402 accepts decimal strings without first rounding through a binary float.
  return normalized as unknown as number;
}

export function dateTime(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

export function shortDate(value: string) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(
    new Date(value),
  );
}

export const assetTypeLabel: Record<AssetType, string> = {
  stock: "Stock",
  crypto: "Crypto",
  real_estate: "Real estate",
  cash: "Cash",
};

export function localDateTimeInput() {
  return toLocalDateTimeInput(new Date());
}

export function toLocalDateTimeInput(value: string | Date) {
  const date = new Date(value);
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 16);
}
