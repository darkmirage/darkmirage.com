// Runtime checks for data from outside the code: page globals and JSON responses.

export function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v);
}

/** A string field of an object, or `fallback` when it is missing or not a string. */
export function str(obj: unknown, key: string, fallback: string): string {
  if (!isRecord(obj)) return fallback;
  const v = obj[key];
  return typeof v === 'string' ? v : fallback;
}

/** A finite number field, or `fallback`. Numeric strings count (wp_localize_script sends strings). */
export function num(obj: unknown, key: string, fallback: number): number {
  if (!isRecord(obj)) return fallback;
  const v = obj[key];
  const n = typeof v === 'number' ? v : typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN;
  return Number.isFinite(n) ? n : fallback;
}

/** Parse JSON without letting `any` escape. */
export function parseJson(text: string): unknown {
  const v: unknown = JSON.parse(text);
  return v;
}

/** A response body as checked JSON. */
export async function fetchJson(url: string, init?: RequestInit): Promise<unknown> {
  const r = await fetch(url, init);
  if (!r.ok) throw new Error(`HTTP ${String(r.status)}`);
  return parseJson(await r.text());
}

/** Any function can take unknown arguments and give back an unknown result. */
export type Fn = (...args: unknown[]) => unknown;
export function isFn(v: unknown): v is Fn {
  return typeof v === 'function';
}

/** The value at a dotted path of nested objects, e.g. at(window.wp, 'blocks.registerBlockType'). */
export function at(obj: unknown, path: string): unknown {
  let v = obj;
  for (const key of path.split('.')) {
    if (!(isRecord(v) || isFn(v))) return undefined;
    v = Reflect.get(v, key);
  }
  return v;
}

/** Array.isArray narrows to any[]; this narrows to unknown[], so each element still needs checking. */
export function isArray(v: unknown): v is unknown[] {
  return Array.isArray(v);
}

/** `n` when it is a finite number other than zero, else `fallback`. */
export function orIfZero(n: number, fallback: number): number {
  return Number.isFinite(n) && n !== 0 ? n : fallback;
}
